<?php
/**
 * Database abstraction
 *
 * PHP version 5.3
 *
 * @category  Database
 * @package   Model
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2016 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Query_Sql
{
    const TYPE='sql';
    public static 
        $microseconds=6,
        $enableOffset=true,
        $textToVarchar,
        $connectionCallback,
        $errorCallback;
    protected static 
        $options, 
        $conn=array(), 
        $tableDefault;
    protected 
        $_schema, 
        $_scope, 
        $_select, 
        $_distinct, 
        $_selectDistinct, 
        $_from, 
        $_where, 
        $_groupBy, 
        $_orderBy, 
        $_limit, 
        $_offset, 
        $_alias, 
        $_transaction, 
        $_last;

    public function __construct($s=null)
    {
        if($s) {
            if(is_object($s)) {
                $this->_schema = get_class($s);
            } else {
                $this->_schema = $s;
            }
        }
        // should throw an exception if no schema is found?
    }

    public function __toString()
    {
        return (string) $this->buildQuery();
    }

    public static function connect($n='', $exception=true, $tries=3)
    {
        if(!isset(static::$conn[$n]) || !static::$conn[$n]) {
            try {
                $level = 'find';
                $db = Tecnodesign_Query::database($n);
                if(!$n && is_array($db)) $db = array_shift($db); 
                $db += array('username'=>null, 'password'=>null, 'options'=>static::$options);
                $level = 'connect';
                static::$conn[$n] = new \PDO($db['dsn'], $db['username'], $db['password'], $db['options']);
                if(!static::$conn[$n]) {
                    tdz::log('[INFO] Connection to '.$n.' failed, retrying... '.$tries);
                    $tries--;
                    if(!$tries) return false;
                    return static::connect($n, $exception, $tries);
                }
                if(isset($db['options'][\PDO::MYSQL_ATTR_INIT_COMMAND])) {
                    $level = 'initialize';
                    static::$conn[$n]->exec($db['options'][\PDO::MYSQL_ATTR_INIT_COMMAND]);
                }
            } catch(Exception $e) {
                tdz::log('[INFO] Could not '.$level.' to '.$n.":\n  {$e->getMessage()}\n".$e);
                if($tries) {
                    $tries--;
                    if(isset(static::$conn[$n])) static::$conn[$n]=null;
                    return static::connect($n, $exception, $tries);
                }
                if($exception) {
                    throw new Exception('Could not connect to '.$n);
                }
            }
        }
        if(static::$connectionCallback) {
            static::$conn[$n] = call_user_func(static::$connectionCallback, static::$conn[$n], $n);
        }
        return static::$conn[$n];
    }

    public static function disconnect($n='')
    {
        if(isset(static::$conn[$n]) && static::$conn[$n]) {
            static::$conn[$n] = null;
            unset(static::$conn[$n]);
        }
    }

    public function reset()
    {
        $this->_select = null;
        $this->_distinct = null;
        $this->_selectDistinct = null;
        $this->_scope = null;
        $this->_from = null;
        $this->_where = null;
        $this->_orderBy = null;
        $this->_limit = null;
        $this->_offset = null;
        $this->_alias = null;
        $this->_transaction = null;
        $this->_last = null;
    }

    public function schema($prop=null)
    {
        $cn = $this->_schema;
        if($prop) {
            if(isset($cn::$schema[$prop])) return $cn::$schema[$prop];
            return null;
        }
        return $cn::$schema;
    }

    public function scope($o=null, $sc=null)
    {
        $cn = ($sc && isset($sc['className']))?($sc['className']):($this->_schema);
        if($o==='uid') return $cn::pk(null, true);
        return $cn::scope($o);
    }

    public function getDatabaseName($n=null)
    {
        if(!$n) $n = $this->schema('database');
        $db = Tecnodesign_Query::database($n);
        if($db && isset($db['dsn']) && preg_match('/(^|;)dbname=([^\;]+)/', $db['dsn'], $m)) {
            $n = $m[2];
        }
        return $n;
    }

    public static function getTables($n='')
    {
        if(is_string($n)) $n = self::connect($n);
        return $n->query('show tables')->fetchAll(\PDO::FETCH_COLUMN);
    }


    public function find($options=array(), $asArray=false)
    {
        $this->_select = $this->_where = $this->_groupBy = $this->_orderBy = $this->_limit = $this->_offset = null;
        $sc = $this->schema();
        $this->_alias = array($sc['className']=>'a');
        if(isset($sc['view'])) {
            $this->_from = ((strpos($sc['view'], ' ')!==false))?('('.$sc['view'].') as a'):($sc['view'].' as a');
        } else {
            $this->_from = $sc['tableName'].' as a';
        }
        if(isset($sc['defaults']['find'])) $this->filter($sc['defaults']['find']);
        unset($sc);
        $this->filter($options);
        return $this;
    }

    public function filter($options=array())
    {
        if(!$this->_schema) return $this;
        else if(!$this->_alias) return $this->find($options);
        if(!is_array($options)) {
            $options = ($options)?(array('where'=>$options)):(array());
        }
        foreach($options as $p=>$o) {
            if(method_exists($this, ($m='add'.ucfirst($p)))) {
                $this->$m($o);
            }
            unset($m, $p, $o);
        }
        return $this;
    }

    public static function concat($a, $p='a.', $sep='-')
    {
        if(is_array($a)) return 'concat('.$p.implode(','.tdz::sql($sep).','.$p, $a).')';
        else return $p.$a;
    }

    public function buildQuery($count=false)
    {
        if(is_null($this->_where)) {
            $this->_where = $this->getWhere(array());
        }
        if($count) {
            if(!is_string($count)) {
                $cc = '';
                if($this->_groupBy) {
                    if(strpos($this->_groupBy, ',')!==false) {
                        $cc = static::concat(preg_split('/\s*,\s*/', trim($this->_groupBy), null, PREG_SPLIT_NO_EMPTY),'');
                    } else {
                        $cc = trim($this->_groupBy);
                    }
                } else if($this->_from && strpos($this->_from, ' left outer join ')) {
                    $cc = static::concat($this->scope('uid'),'a.');
                } else {
                    $count = '1';
                }
                if($cc) {
                    $count = 'distinct '.$cc;
                }
            } else if(!is_numeric($count)) {
                $count = $this->getAlias($count);
            }
            $s = ' count('.$count.')';
        } else if($this->_select) {
            if($this->_distinct && $this->_selectDistinct) {
                $this->_select = $this->_selectDistinct + $this->_select;
            }
            $s = $this->_distinct.' '.implode(', ', $this->_select);
        } else {
            $s = ' a.*';
        }

        $q = 'select'
            . $s
            . ' from '.$this->_from
            . (($this->_where)?(' where '.$this->_where):(''))
            . ((!$count && $this->_groupBy)?(' group by'.$this->_groupBy):(''))
            . ((!$count && $this->_orderBy)?(' order by'.$this->_orderBy):(''))
            . ((!$count && $this->_limit)?(' limit '.$this->_limit):(''))
            . ((!$count && $this->_offset)?(' offset '.$this->_offset):(''))
        ;
        return $q;
    }

    public function lastQuery()
    {
        return $this->_last;
    }

    public function fetch($o=null, $l=null)
    {
        if(!$this->_schema) return false;
        $prop = array('_new'=>false);
        if($this->_scope) $prop['_scope'] = $this->_scope;
        if($o || $l) {
            if($o && !is_numeric($o)) $o=null;
            if($l && !is_numeric($l)) $l=null;
            $this->_offset = $o;
            $this->_limit = $l;
        }
        return $this->query($this->buildQuery(), \PDO::FETCH_CLASS, $this->schema('className'), array($prop));
    }

    public function fetchArray($o=null, $l=null)
    {
        if($o || $l) {
            if($o && !is_numeric($o)) $o=null;
            if($l && !is_numeric($l)) $l=null;
            $this->_offset = $o;
            $this->_limit = $l;
        }
        return $this->query($this->buildQuery(), \PDO::FETCH_ASSOC);
    }

    public function count($column=true)
    {
        if(!$this->_schema) return false;
        if(!$column) $column=true;
        $r = $this->queryColumn($C=$this->buildQuery($column));
        $i = (is_array($r))?((int) array_shift($r)):(0);
        if($this->_limit && $i > $this->_limit) $i = $this->_limit;
        return $i;
    }

    public function select($o=false)
    {
        if($o!==false) {
            $this->_select = null;
            $this->addSelect($o);
        }
        return ' '.implode(', ', $this->_select);
    }

    public function addSelect($o)
    {
        if(is_null($this->_select)) $this->_select = array();
        if(is_array($o)) {
            foreach($o as $s) {
                $this->addSelect($s);
                unset($s);
            }
        } else {
            $fn = $this->getAlias($o);
            if($fn) {
                $this->_select[$fn]=$fn;
                //$this->_select .= ($this->_select)?(", {$fn}"):(" {$fn}");
            }
            unset($fn);
        }
        return $this;
    }

    public function addScope($o)
    {
        if(is_array($o)) {
            foreach($o as $s) {
                $this->addScope($s);
                unset($s);
            }
        } else if($s=$this->scope($o)) {
            $this->addSelect($s);
            $this->_scope = $o;
        }
        return $this;
    }

    public function where($w)
    {
        $this->_where = $this->getWhere($w);
        return $this;
    }

    public function addWhere($w)
    {
        if(is_null($this->_where)) $this->_where = $this->getWhere($w);
        else $this->_where .= " and ({$this->getWhere($w)})";
        return $this;
    }

    public function addOrderBy($o, $sort='asc')
    {
        if(is_array($o)) {
            foreach($o as $i=>$s) {
                if(!is_int($i) || $s=='asc' || $s=='desc') {
                    $this->addOrderBy($i, $s);
                } else {
                    $this->addOrderBy($s);
                }
                unset($s);
            }
        } else if($o) {
            if(preg_match('/\s+[\_\-a-z0-9]+\s*$/i', $o, $m)) {
                $o = substr($o, 0, strlen($o)-strlen($m[0]));
            }
            $fn = (!is_int($o))?($this->getAlias($o, null, true)):($o);
            if($fn && strpos($fn, $this->_orderBy)===false) {
                if($sort!='asc' && $sort!='desc') $sort='';
                $this->_orderBy .= ($this->_orderBy)?(", {$fn} {$sort}"):(" {$fn} {$sort}");
            }
            unset($fn);
        }
        return $this;
    }

    public function addGroupBy($o)
    {
        if(is_array($o)) {
            foreach($o as $s) {
                $this->addGroupBy($s);
                unset($s);
            }
        } else {
            $fn = $this->getAlias($o, null, true);
            if($fn && strpos($fn, $this->_groupBy)===false) {
                $this->_groupBy .= ($this->_groupBy)?(", {$fn}"):(" {$fn}");
            }
            unset($fn);
        }
        return $this;
    }

    public function limit($o)
    {
        $this->_limit = (int) $o;
        return $this;
    }

    public function addLimit($o)
    {
        $this->_limit = (int) $o;
        return $this;
    }

    public function offset($o)
    {
        $this->_offset = (int) $o;
        return $this;
    }

    public function addOffset($o)
    {
        $this->_offset = (int) $o;
        return $this;
    }

    protected function getFunctionNext($fn)
    {
        return 'ifnull(max('.$this->getAlias($fn).'),0)+1';
    }

    protected function getAlias($f, $sc=null, $noalias=null)
    {
        $ofn = $fn = $f;
        if(substr($f, 0, 1)=='-' && substr($f,-1)=='-') {
            return false;
        } else if(preg_match_all('#`([^`]+)`#', $fn, $m)) {
            $r = $s = array();
            foreach($m[1] as $i=>$nfn) {
                $s[]=$m[0][$i];
                $r[]=$this->getAlias($nfn, $sc, true);
                unset($i, $nfn);
            }
            return str_replace($s, $r, $fn);
        } else if(preg_match('#^([a-z\.0-9A-Z_]+)\s+(as\s+)?([a-z\.0-9A-Z_]+)$#', trim($fn), $m) && ($r = $this->getAlias($m[1], $sc, true))) {
            return $r.' '.tdz::sql($m[3]);
        } else if(preg_match('/@([a-z]+)\(([^\)]*)\)/', $fn, $m) && method_exists($this, $a='getFunction'.ucfirst($m[1]))) {
            return str_replace($m[0], $this->$a(trim($m[2])), $fn);
        } else if(substr($f, 0, 1)=='_' && !isset($this->schema('columns')[$f])) {
            // not to be queried
            return false;
        }

        if(strpos($fn, '[')!==false && preg_match('/\[([^\]]+)\]/', $fn, $m)) {
            $fn = $m[1];
            $fnt = $m[0];
            $ofn = $fn;
            unset($m);
        }
        $ta='a';
        if(!$sc) $sc = $this->schema();
        else {
            if(isset($this->_alias[$sc['className']])) {
                $ta = $this->_alias[$sc['className']];
            }
        }
        if($ta=='a' && !in_array($ta, $this->_alias)) {
            $this->_alias[$sc['className']] = $ta;
        }
        $found=false;
        if($fn=='*') {
            return '*';
        }

        if (isset($sc['columns'][$fn])) {
            $found = true;
            if(isset($sc['columns'][$fn]['alias']) && $sc['columns'][$fn]['alias']) {
                if(strpos($sc['columns'][$fn]['alias'], '`')!==false) {
                    $fn = $this->getAlias($sc['columns'][$fn]['alias'], $sc, true).((!$noalias)?(' '.tdz::sql($fn)):(''));
                } else {
                    $fn = $ta.'.'.$sc['columns'][$fn]['alias'].((!$noalias)?(' '.tdz::sql($fn)):(''));
                }
            } else {
                if(isset($sc['columns'][$fn]['type']) && $sc['columns'][$fn]['type']=='string' && isset($sc['columns'][$fn]['size']) && $this::$textToVarchar && $this::$textToVarchar<=$sc['columns'][$fn]['size']) {
                    if(!$this->_selectDistinct) $this->_selectDistinct=array();
                    $this->_selectDistinct[$ta.'.'.$fn] = 'cast('.$ta.'.'.$fn.' as varchar(max)) '.$fn;
                }
                $fn = $ta.'.'.$fn;
            }
        } else if(!$found) {
            $rnf = '';
            $cn = get_called_class();
            while(strpos($ofn, '.')) {
                @list($rn, $fn) = explode('.', $ofn,2);
                $ofn=$fn;
                $rnf .= ($rnf)?('.'.$rn):($rn);
                if($rn==$rnf && isset($sc['columns'][$rn]['serialize'])) {
                    $found = true;
                    if(isset($sc['columns'][$rn]['alias']) && $sc['columns'][$rn]['alias']) {
                        $fn = $ta.'.'.$sc['columns'][$rn]['alias'].((!$noalias)?(' '.tdz::sql($fn)):(''));
                    } else {
                        $fn = $ta.'.'.$rn;
                    }
                    break;
                } else if(isset($sc['relations'][$rn])) {
                    $rcn = (isset($sc['relations'][$rn]['className']))?($sc['relations'][$rn]['className']):($rn);
                    $rsc = $rcn::$schema;
                    if(!isset($this->_alias[$rnf])) {
                        $chpos=($this->_alias)?(ceil(count($this->_alias)/2)):(0);
                        while(in_array($an=tdz::letter($chpos), $this->_alias)) $chpos++;

                        $this->_alias[$rnf]=$an;
                        if($rcn != $rnf) {
                            $this->_alias[$rcn]=$an;
                        }
                        if($sc['relations'][$rn]['type']!='one') {
                            $this->_distinct = ' distinct';
                        }

                        if(isset($rsc['view'])) {
                            $jtn = (strpos($rsc['view'], ' '))?('('.$rsc['view'].')'):($rsc['view']);
                        } else if(isset($rsc['database']) && $rsc['database']!=$this->schema('database')) {
                            $jtn = $this->getDatabaseName($rsc['database']).'.'.$rsc['tableName'];
                        } else {
                            $jtn = $rsc['tableName'];
                        }
                        if(!is_array($sc['relations'][$rn]['foreign'])) {
                            $rfn = $this->getAlias($sc['relations'][$rn]['foreign'], $rsc, true);
                            $lfn = $this->getAlias($sc['relations'][$rn]['local'], $sc, true);
                            $this->_from .= " left outer join {$jtn} as `{$an}` on {$lfn}={$rfn}";
                        } else {
                            $this->_from .= " left outer join {$jtn} as `{$an}` on";
                            foreach($sc['relations'][$rn]['foreign'] as $rk=>$rv) {
                                $rfn = $this->getAlias($rv, $rsc, true);
                                $lfn = $this->getAlias($sc['relations'][$rn]['local'][$rk], $sc, true);
                                $this->_from .= (($rk>0)?(' and'):(''))." {$lfn}={$rfn}";
                            }
                        }
                        if(isset($sc['relations'][$rn]['on'])) {
                            if(!is_array($sc['relations'][$rn]['on'])) $sc['relations'][$rn]['on']=array($sc['relations'][$rn]['on']); 
                            foreach($sc['relations'][$rn]['on'] as $rfn) {
                                @list($rfn,$fnc)=explode(' ', $rfn, 2);
                                if(substr($rfn,0,strlen($rn))==$rn) {
                                    $this->_from .= " and {$an}".substr($rfn,strlen($rn))." {$fnc} ";
                                } else {
                                    $this->_from .= ' and '.$this->getAlias($rfn, $rsc, true).' '.$fnc;
                                }
                                unset($rfn, $fnc);
                            }
                        }

                        if(isset($rsc['events']['active-records']) && $rsc['events']['active-records']) {
                            $ar = $rsc['events']['active-records'];
                            unset($rsc['events']['active-records']);
                            if(is_array($ar)) {
                                foreach($ar as $r=>$v) {
                                    if(is_int($r)) {
                                        $this->_from .= ' and '.$this->getAlias($v, $rsc, $noalias);
                                    } else {
                                        $this->_from .= ' and '.$this->getWhere(array($r=>$v), 'and', $rsc);
                                    }
                                    unset($ar[$r], $r, $v);
                                }
                            } else {
                                if(strpos($ar, '`')!==false || strpos($ar, '[')!==false) {
                                    $this->_from .= ' and '.$this->getAlias($ar, $rsc, $noalias);
                                } else if(preg_match('/^([a-z0-9\_]+)[\s\<\>\!\=]/', $ar, $m)) {
                                    $this->_from .= ' and '.$this->getAlias($m[1], $rsc, $noalias).substr($ar, strlen($m[1]));
                                } else {
                                    $this->_from .= ' and '.$ar;
                                }
                            }
                            unset($ar);
                        }
                    } else {
                        $an = $this->_alias[$rnf];
                    }
                    unset($sc, $rn);
                    $sc = $rsc;
                    unset($rsc);
                    $ta=$an;
                    $fn = $this->getAlias($fn, $sc, $noalias);
                    //$fn = $an.'.'.$fn;
                    $found = true;
                } else {
                    $found = false;
                    break;
                }
            }
        }
        if(!$found) {
            if (isset($sc['relations'][$fn])) {
                // ignore relations, this should be set on preview
                /*
                $found = true;
                if(is_array($sc['relations'][$fn]['local'])) {
                    $fn = $ta.'.'.array_pop($sc['relations'][$fn]['local']).' \''.$fn.'\'';
                } else {
                    $fn = $ta.'.'.$sc['relations'][$fn]['local'].' \''.$fn.'\'';
                }
                */
                return;
            } else if ((isset($sc['className']) && $sc['className']::$allowNewProperties) || isset($sc['columns'][$fn]) || property_exists($cn, $fn)) {
                $found = true;
                if(isset($sc['columns'][$fn]['alias']) && $sc['columns'][$fn]['alias']) {
                    $fn = $ta.'.'.$sc['columns'][$fn]['alias'].((!$noalias)?(' '.tdz::sql($fn)):(''));
                } else {
                    $fn = $ta.'.'.$fn;
                }
            } else if(strtolower($fn)!=='null') {
                tdz::log("[INFO] Cannot find by [{$ofn}] at [{$sc['tableName']}]");
                throw new Exception("Cannot find by [{$ofn}] at [{$sc['tableName']}]");
            }
        }
        unset($found, $sc, $ta);
        if(isset($fnt) && $fnt) {
            $fn = str_replace($fnt, $fn, $f);
            unset($fnt, $f);
        }
        return $fn;
    }

    protected function getWhere($w, $xor='and', $sc=null)
    {
        $r='';
        if(!$sc) $sc = $this->schema();
        $e = (isset($sc['events']))?($sc['events']):(null);
        $add=array();
        $ar = null;
        if(is_null($this->_where) && $e && isset($e['active-records']) && $e['active-records']) {
            if(is_array($e['active-records'])) {
                $add=$e['active-records'];
            } else {
                if(strpos($e['active-records'], '`')!==false || strpos($e['active-records'], '[')!==false) {
                    $ar = $this->getAlias($e['active-records'], $sc, true);
                } else {
                    $ar = $e['active-records'];
                }
            }
        }

        if(!is_array($w)) {
            // must get from primary key or first column
            $pk = $this->scope('uid', $sc);
            if(!$pk) return '';
            else if(count($pk)==1) $w = array( $pk[0] => $w );
            else {
                $v = preg_split('/\s*[\,\;]\s*/', $w, count($pk));
                unset($w);
                $w=array();
                foreach($v as $i=>$k) {
                    $w[$pk[$i]] = $k;
                    unset($i, $k);
                }
                unset($v);
            }
        }
        if($add) $w += $add;

        $op = '=';
        $not = false;
        static $cops = array('>=', '<=', '<>', '!', '!=', '>', '<');
        static $like = array('%', '$', '^', '*', '~');
        static $xors = array('and'=>'and', '&'=>'and', 'or'=>'or', '|'=>'or');
        foreach($w as $k=>$v) {
            if(is_int($k)) {
                if(is_array($v)) {
                    if($v = $this->getWhere($v, 'and', $sc)) {
                        $r .= ($r)?(" {$xor} ({$v})"):("({$v})");
                    }
                } else {
                    $c1=substr($v, 0, 1);
                    if($c1=='!') {
                        $not = true;
                        $v = substr($v, 1);
                    } else if(isset($xors[$c1])) {
                        $xor = $xors[$c1];
                        $v = substr($v, 1);
                    }
                    unset($c1);
                    if(in_array($v, $cops) || in_array(substr($v,0,1), $like)) {
                        $op = $v;
                    } else if(isset($xors[$v])) {
                        $xor = $xors[$v];
                    }
                }
            } else {
                $k=trim($k);
                $cop = $op;
                $pxor = (isset($cxor))?($cxor):(null);
                $cxor = $xor;
                $cnot = $not;
                $c1=substr($k, 0, 1);
                if(isset($xors[$c1])) {
                    $cxor = $xors[$c1];
                    $k = substr($k, 1);
                }
                if($pxor && $pxor=='or' && $pxor!=$cxor) {
                    $r = ' ('.trim($r).')';
                }
                unset($c1);
                if(preg_match('/(\~|\!\~|\<\>|[\<\>\^\$\*\!\%]?\=?|[\>\<])$/', $k, $m) && $m[1]) {
                    // operators: <=  >= < > ^= $=
                    $cop = (!in_array($m[1], $cops))?(substr($m[1], 0, 1)):($m[1]);
                    $k = trim(substr($k, 0, strlen($k) - strlen($m[0])));
                    unset($m);
                    if($cop=='!') {
                        $cnot = true;
                        $cop = '=';
                    }
                }
                $fn = $this->getAlias($k, $sc, true);
                if($fn) {
                    $cn = (isset($sc['className']))?($sc['className']):($this->_schema);
                    if($cn && $cn::$prepareWhere && method_exists($cn, $m='prepareWhere'.tdz::camelize(substr($fn, 2),true))) {
                        $v = $cn::$m($v);
                    }
                    $r .= ($r)?(" {$cxor}"):('');
                    if(is_array($v) && count($v)==1) {
                        $v = array_shift($v);
                    }
                    if($cop=='~' || $cop=='!~') {
                        // between
                        if(is_array($v) && count($v)==2) {
                            $r .= " {$fn} ".(($cop=='!~')?('not '):(''))."between ".self::escape($v[0])." and ".self::escape($v[1]);
                        }
                    } else if (is_array($v) && ($cop=='=' || $cop=='!=' || $cop=='!' || $cop=='<>')) {
                        foreach ($v as $vk=>$vs) {
                            $v[$vk] = self::escape($vs);
                            if($vs==''){
                                $v['']='null';
                            }
                            unset($vk, $vs);
                        }
                        $r .= " {$fn}".(($cnot || $cop=='<>' || substr($cop, 0, 1)=='!')?(' not'):('')).' in('.implode(',',$v).')';
                    } else if(is_array($v) && !($cop=='^' || $cop=='$' || $cop=='*' || $cop=='%')) {
                        $nv = array();
                        if($cnot) $nv[] = '!';
                        if($cxor!='and') $nv[] = $cxor;
                        foreach($v as $vk=>$vs) {
                            $nv[] = ($vs && $cop!='=')?(array($k.$cop=>$vs)):(array($k=>$vs));
                        }
                        $v = $this->getWhere($nv, 'or');
                        if($v) $r .= "({$v})";

                    } else if(!$v && $cop=='=') {
                        $r .= (($cnot)?(' not'):(' '))."({$fn}=".self::escape($v)." or {$fn} is null)";

                    } else if(!$v && $cop=='<>') {
                        $r .= " ({$fn}<>".self::escape($v)." and {$fn} is not null)";

                    } else if($cop=='^' || $cop=='$' || $cop=='*') {
                        $b = " {$fn}".(($cnot)?(' not'):(''))." like '";
                        if($cop!='^') $b .= '%';
                        $a = ($cop!='$')?("%'"):("'");
                        if(is_array($v)) {
                            $r .= $b.implode($a." {$cxor}".$b, self::escape($v, false)).$a;
                        } else {
                            $r .= $b.self::escape($v, false).$a;
                        }
                    } else if($cop=='%') {
                        $r .= " {$fn}".(($cnot)?(' not'):(''))." like '%".str_replace('-', '%', tdz::slug($v, $cn::$queryAllowedChars, true))."%'";
                    } else {
                        $r .= ($not)?(" not({$fn}{$cop}".self::escape($v).')'):(" {$fn}{$cop}".self::escape($v));
                    }
                }
                unset($cop, $cnot);
            }
            unset($k, $fn, $v);
        }

        if($ar) {
            $r = ($r)?('('.trim($r).") and ({$ar})"):($ar);
        }

        return trim($r);
    }

    public function exec($q, $conn=null)
    {
        if(!$conn) {
            $conn = self::connect($this->schema('database'));
        }
        return $conn->exec($q);
    }

    public function run($q)
    {
        $this->_last = $q;
        return self::runStatic($q, $this->schema('database'));
    }

    public static function runStatic($q, $n='')
    {
        static $stmt;
        if($stmt) {
            $stmt->closeCursor();
            $stmt = null;
        }
        $stmt = self::connect($n)->query($q);
        if(!$stmt) {
            throw new \Exception('Statement failed! '.$q);
        }
        return $stmt;
    }

    public function query($q, $p=null)
    {
        try {
            if (is_null($p)) {
                return $this->run($q)->fetchAll(\PDO::FETCH_ASSOC);
            } else {
                $arg = func_get_args();
                array_shift($arg);
                return call_user_func_array(array($this->run($q), 'fetchAll'), $arg);
            }
        } catch(Exception $e) {
            if(isset($this::$errorCallback) && $this::$errorCallback) {
                return call_user_func($this::$errorCallback, $e, func_get_args(), $this);
            }
            tdz::log('[INFO] Error in '.__METHOD__." {$e->getCode()}:\n  ".$e->getMessage()."\n ".$this);
            return false;
        }
    }

    public function queryColumn($q, $i=0)
    {
        return $this->query($q, \PDO::FETCH_COLUMN, $i);
    }


    public static function escape($str, $enclose=true)
    {
        if(is_array($str)) {
            foreach($str as $k=>$v){
                $str[$k]=self::escape($v, $enclose);
                unset($k, $v);
            }
            return $str;
        }
        $str = str_replace(array('\\', "'"), array('\\\\', "''"), $str);
        $str = ($enclose) ? ("'{$str}'") : ($str);
        return $str;
    }

    public static function sql($v, $d) {
        if(is_null($v) || $v===false) {
            return 'null';
        } else if(isset($d['type']) && $d['type']=='int') {
            return (int) $v;
        } else if(isset($d['type']) && $d['type']=='bool') {
            return ($v && $v>0)?(1):(0);
        } else if(isset($d['type']) && $d['type']=='datetime') {
            $ms = (int) static::$microseconds;
            if(preg_match('/^(([0-9]{4}\-[0-9]{2}\-[0-9]{2}) ?(([0-9]{2}:[0-9]{2})(:[0-9]{2}(\.[0-9]{1,'.$ms.'})?)?)?)[0-9]*$/', $v, $m)) {
                if(!isset($m[3]) || !$m[3]) {
                    return "'{$m[2]}T00:00:00'";
                } else if(!isset($m[5]) || !$m[5]) {
                    return "'{$m[2]}T{$m[4]}:00'";
                } else {
                    return "'{$m[2]}T{$m[3]}'";
                }
            }
        } else if(is_array($v)) {
            if(isset($d['serialize'])) {
                $v = tdz::serialize($v, $d['serialize']);
            } else {
                $v = implode(',',$v);
            }
        }

        return self::escape($v);
    }

    /**
     * Enables transactions for this connector
     * returns the transaction $id
     */
    public function transaction($id=null, $conn=null)
    {
        if(is_null($this->_transaction)) $this->_transaction = array();
        if(!$id) {
            $id = uniqid('tdzt');
        }
        if(!isset($this->_transaction[$id])) {
            if(!$conn) {
                $conn = self::connect($this->schema('database'));
            }
            $conn->setAttribute(\PDO::ATTR_AUTOCOMMIT, 0);
            $conn->beginTransaction();
            $this->_transaction[$id] = $conn;
        }
        return $id;
    }
    
    /**
     * C0mmits transactions opened by ::transaction
     * returns true if successful
     */
    public function commit($id=null, $conn=null)
    {
        if(!$this->_transaction) {
            if($id && $conn) {
                $this->_transaction = array( $id => $conn );
            } else {
                return;
            }
        }
        if(!$id) {
            $id = array_shift(array_keys($this->_transaction));
        }
        if(isset($this->_transaction[$id])) {
            if(!$conn) $conn = $this->_transaction[$id];
            unset($this->_transaction[$id]);
            if($conn && $conn->inTransaction()) {
                $r = $conn->commit();
                $conn->setAttribute(\PDO::ATTR_AUTOCOMMIT, 1);
                return $r;
            } else {
                return false;
            }
        }
    }

    /**
     * Rollback transactions opened by ::transaction
     * returns true if successful
     */
    public function rollback($id=null, $conn=null)
    {
        if(!$this->_transaction) {
            if($id && $conn) {
                $this->_transaction = array( $id => $conn );
            } else {
                return;
            }
        }
        if(!$id) {
            $id = array_shift(array_keys($this->_transaction));
        }
        if(isset($this->_transaction[$id])) {
            if(!$conn) $conn = $this->_transaction[$id];
            unset($this->_transaction[$id]);
            if($conn && $conn->inTransaction()) {
                $r = $conn->rollback();
                $conn->setAttribute(\PDO::ATTR_AUTOCOMMIT, 1);
                return $r;
            } else {
                return false;
            }
        }
    }

    /**
     * Returns the last inserted ID from a insert call
     * returns true if successful
     */
    public function lastInsertId($M=null, $conn=null)
    {
        if(!$conn) {
            $conn = self::connect($this->schema('database'));
        }
        $pk = $M::pk();
        $fn = (is_array($pk))?(null):($pk);
        return $conn->lastInsertId($fn);
    }

    public function insert($M, $conn=null)
    {
        $odata = $M->asArray('save', null, null, true);
        $data = array();

        $fs = $M::$schema['columns'];
        if(!$fs) $fs = array_flip(array_keys($odata));
        foreach($fs as $fn=>$fv) {
            if(!is_array($fv)) $fv=array('null'=>true);
            if(isset($fv['increment']) && $fv['increment']=='auto' && !isset($odata[$fn])) {
                continue;
            }
            if(isset($fv['alias'])) continue;
            if(!isset($odata[$fn]) && isset($fv['default']) &&  $M->getOriginal($fn, false, true)===false) {
                $odata[$fn] = $fv['default'];
            }
            if (!isset($odata[$fn]) && $fv['null']===false) {
                throw new Tecnodesign_Exception(array(tdz::t('%s should not be null.', 'exception'), $M::fieldLabel($fn)));
            } else if(array_key_exists($fn, $odata)) {
                $data[$fn] = self::sql($odata[$fn], $fv);
            } else if($M->getOriginal($fn, false)!==false && is_null($M->$fn)) {
                $data[$fn] = 'null';
            }
            unset($fs[$fn], $fn, $fv);
        }

        $tn = $M::$schema['tableName'];
        if($data) {
            if(!$conn) {
                $conn = self::connect($this->schema('database'));
            }
            $this->_last = "insert into {$tn} (".implode(', ', array_keys($data)).') values ('.implode(', ', $data).')';
            $r = $this->exec($this->_last, $conn);
            if($r===false && $conn->errorCode()!=='00000') {
                throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $M::label()));
            }

            if($id = $this->lastInsertId($M, $conn)) {
                $pk = $M::pk();
                if(is_array($id)) {
                    if(!is_array($pk)) $pk = array($pk);
                    foreach($pk as $f) {
                        if(isset($id[$f])) {
                            $M->$f = $id[$f];
                        }
                        unset($f);
                    }
                } else {
                    if(is_array($pk)) $pk = array_shift($pk);
                    $M[$pk] = $id;
                }
                $M->isNew(false);
                $r = $id;
            }
            return $r;
        }
    }

    public function update($M, $conn=null)
    {
        $odata = $M->asArray('save', null, null, true);
        $data = array();

        $fs = $M::$schema['columns'];
        if(!$fs) $fs = array_flip(array_keys($odata));
        $sql = '';
        foreach($fs as $fn=>$fv) {
            $original=$M->getOriginal($fn, false);
            if(isset($fv['primary']) && $fv['primary']) {
                $pks[$fn] = tdz::sql(($original)?($original):($odata[$fn]));
                continue;
            }
            if(isset($fv['alias'])) continue;
            if(!is_array($fv)) $fv=array('null'=>true);

            if (!isset($odata[$fn]) && $original===false) {
                continue;
            } else if(array_key_exists($fn, $odata)) {
                $v  = $odata[$fn];
                $fv = self::sql($v, $fv);
            } else if($original!==false && $M->$fn===false) {
                $v  = null;
                $fv = 'null';
            } else {
                continue;
            }

            if($original===false) $original=null;

            if((string)$original!==(string)$v) {
                $sql .= (($sql!='')?(', '):(''))
                      . "{$fn}={$fv}";
                //$M->setOriginal($fn, $v);
            }
            unset($fs[$fn], $fn, $fv, $v);
        }
        if($sql) {
            $tn = $M::$schema['tableName'];
            $wsql = '';
            foreach($pks as $fn=>$fv) {
                $wsql .= (($wsql!='')?(' and '):(''))
                       . "{$fn}={$fv}";
            }
            if(!$conn) {
                $conn = self::connect($this->schema('database'));
            }
            $this->_last = "update {$tn} set {$sql} where {$wsql}";
            $r = $this->exec($this->_last, $conn);
            if($r===false && $conn->errorCode()!=='00000') {
                throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $M::label()));
            }
            return $r;
        }
    }

    public function delete($M, $conn=null)
    {
        $pk = $M->getPk(true);
        if($pk) {
            $tn = $M::$schema['tableName'];
            $wsql = '';
            foreach($pk as $fn=>$v) {
                $fv = self::sql($v, (isset($M::$schema['columns'][$fn]))?($M::$schema['columns'][$fn]):(null));
                $wsql .= (($wsql!='')?(' and '):(''))
                       . "{$fn}={$fv}";
            }
            if(!$conn) {
                $conn = self::connect($this->schema('database'));
            }
            $this->_last = "delete from {$tn} where {$wsql}";
            $r = $this->exec($this->_last, $conn);
            if($r===false && $conn->errorCode()!=='00000') {
                throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $M::label()));
            }
            return $r;
        }
    }

    public function create($tn=null, $conn=null)
    {
        $schema = $this->schema();
        if(!$tn) $tn = $schema['tableName'];
        $q = '';
        $pk = array();
        foreach($schema['columns'] as $fn=>$fd) {
            $q .= (($q)?(",\n "):("\n "))
                . '`'.$fn.'` ';
            if($fd['type']=='string') {
                $q .= 'varchar('
                    . ((isset($fd['size']))?($fd['size']):(255))
                    . ')';
            } else if(isset($fd['data-type'])) {
                $q .= $fd['data-type'];
            } else {
                $q .= $fd['type'];
            }
            if(isset($fd['null']) && !$fd['null']) $q .= ' not';
            $q .= ' null';
            if(isset($fd['primary']) && $fd['primary']) $pk[]=$fn;
        }
        if($pk) {
            $q .= (($q)?(",\n "):("\n "))
                . 'primary key(`'.implode('`,`', $pk).'`)';
        }
        $q = 'create table `'.$tn.'` ('.$q."\n)".static::$tableDefault;
        if(!$conn) {
            $conn = self::connect($this->schema('database'));
        }
        $this->_last = $q;
        $r = $this->exec($this->_last, $conn);
        if($r===false && $conn->errorCode()!=='00000') {
            throw new Tecnodesign_Exception(array(tdz::t('Could not create %s.', 'exception'), $tn));
        }
        return $r;
    }

    /**
     * Gets the timestampable last update
     */
    public function timestamp($tns=null)
    {
        $cn = $this->schema('className');
        if(!isset(tdz::$variables['timestamp']))tdz::$variables['timestamp']=array();
        if(isset(tdz::$variables['timestamp'][$cn])) {
            return tdz::$variables['timestamp'][$cn];
        }
        tdz::$variables['timestamp'][$cn] = false;
        $tn=array();
        $fn=array();
        $sc = $this->schema();
        if(is_null($tns)) {
            if(!is_null(tdz::$variables['timestamp'][$cn])) {
                return tdz::$variables['timestamp'][$cn];
            }
            tdz::$variables['timestamp'][$cn] = false;
            if(isset($sc['actAs']['before-insert']['timestampable'])) {
                foreach($sc['actAs']['before-insert']['timestampable'] as $c) {
                    $fn[$c]='max(c.'.$c.')';
                }
                $tn[$sc['tableName']]=$sc['tableName'].' as c';
            }
            if(isset($sc['actAs']['before-update']['timestampable'])) {
                foreach($sc['actAs']['before-update']['timestampable'] as $c) {
                    $fn[$c]='max(c.'.$c.')';
                }
                $tn[$sc['tableName']]=$sc['tableName'].' as c';
            }
            if(isset($sc['relations'])) {
                $i=0;
                foreach($sc['relations'] as $rn=>$rel) {
                    $rcn = (isset($rel['className']))?($rel['className']):($rn);
                    if(!isset($rcn::$schema['tableName']) || isset($tn[$rcn::$schema['tableName']])) {
                        continue;
                    }
                    if(isset($rcn::$schema['actAs']['before-insert']['timestampable'])) {
                        foreach($rcn::$schema['actAs']['before-insert']['timestampable'] as $c) {
                            $fn[$rn.'.'.$c]='max(r'.$i.'.'.$c.')';
                        }
                        $tn[$rcn::$schema['tableName']]=$rcn::$schema['tableName'].' as r'.$i;
                    }
                    if(isset($rcn::$schema['actAs']['before-update']['timestampable'])) {
                        foreach($rcn::$schema['actAs']['before-update']['timestampable'] as $c) {
                            $fn[$rn.'.'.$c]='max(r'.$i.'.'.$c.')';
                        }
                        $tn[$rcn::$schema['tableName']]=$rcn::$schema['tableName'].' as r'.$i;
                    }
                    $i++;
                }
            }
        } else {
            foreach($tns as $i=>$cn) {
                if(isset($sc['actAs']['before-insert']['timestampable'])) {
                    foreach($sc['actAs']['before-insert']['timestampable'] as $c) {
                        $fn[$i.'.'.$c]='max(t'.$i.'.'.$c.')';
                    }
                    $tn[$sc['tableName']]=$sc['tableName'].' as t'.$i;
                }
                if(isset($sc['actAs']['before-update']['timestampable'])) {
                    foreach($sc['actAs']['before-update']['timestampable'] as $c) {
                        $fn[$i.'.'.$c]='max(t'.$i.'.'.$c.')';
                    }
                    $tn[$sc['tableName']]=$sc['tableName'].' as t'.$i;
                }
            }
        }
        if(count($fn)==0) {
            return time();
        }
        $sql = 'select greatest('.implode(',', $fn).') as date from '.implode(',', $tn);
        $conn = tdz::connect($sc['database'], null, true);
        if(tdz::$perfmon) tdz::$perfmon = microtime(true);
        try
        {
            $query = $conn->query($sql);
            $result=array();
            if ($query) {
                $result = $query->fetchAll(PDO::FETCH_COLUMN, 0);
                //$query->closeCursor();
                tdz::$variables['timestamp'][$cn] = strtotime($result[0]);
            }
            
        }
        catch(PDOException $e)
        {
            tdz::log('[WARNING] timestamp exception: in '.__METHOD__.': '.$e->getMessage());
            return false;
        }
        return tdz::$variables['timestamp'][$cn];
    }
}