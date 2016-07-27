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
    public static $microseconds=6;
    protected static $options, $conn=array();
    protected $_schema, $_scope, $_select, $_distinct, $_from, $_where, $_groupBy, $_orderBy, $_limit, $_offset, $_alias, $_transaction;

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
                $db = Tecnodesign_Query::database($n);
                if(!$n && is_array($db)) $db = array_shift($db); 
                $db += array('username'=>null, 'password'=>null, 'options'=>static::$options);
                static::$conn[$n] = new \PDO($db['dsn'], $db['username'], $db['password'], $db['options']);
                if(!static::$conn[$n]) {
                    $tries--;
                    if(!$tries) return false;
                    return static::connect($n, $exception, $tries);
                }
                if(isset($db['options'][\PDO::MYSQL_ATTR_INIT_COMMAND])) {
                    static::$conn[$n]->exec($db['options'][\PDO::MYSQL_ATTR_INIT_COMMAND]);
                }
            } catch(Exception $e) {
                tdz::log('Could not connect to '.$n.":\n  {$e->getMessage()}");
                if($exception) {
                    throw new Exception('Could not connect to '.$n);
                }
            }
        }
        return static::$conn[$n];
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

    public function scope($o=null)
    {
        $cn = $this->_schema;
        if($o==='uid') return $cn::pk();
        return $cn::scope($o);
    }

    /*
    public function getSchema($tn, $schema=array())
    {
        $cn = 'Birds\\Data\\'.ucfirst($this->engine).'Schema';
        return $cn::load($this, $tn, $schema);
    }
    */

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
            $this->_from = '('.$sc['view'].') as a';
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

    public function buildQuery($count=false)
    {
        if(is_null($this->_where)) {
            $this->_where = $this->getWhere(array());
        }
        $q = 'select'
            . (($count)
                ?(' count(1)')
                :(($this->_select)?($this->_distinct.$this->_select):(' a.*'))
              )
            . ' from '.$this->_from
            . (($this->_where)?(' where '.$this->_where):(''))
            . (($this->_groupBy)?(' group by'.$this->_groupBy):(''))
            . ((!$count && $this->_orderBy)?(' order by'.$this->_orderBy):(''))
            . ((!$count && $this->_limit)?(' limit '.$this->_limit):(''))
            . ((!$count && $this->_offset)?(' offset '.$this->_offset):(''))
        ;
        if($this->_schema=='certificates')tdz::log(__METHOD__, $q);
        return $q;

    }

    public function fetch($o=null, $l=null)
    {
        if(!$this->_schema) return false;
        $prop = array('_new'=>false);
        if($this->_scope) $prop['_scope'] = $this->_scope;
        if($o || $l) {
            $this->_offset = $o;
            $this->_limit = $l;
        }
        return $this->query($this->buildQuery(), \PDO::FETCH_CLASS, $this->schema('className'), array($prop));
    }

    public function fetchArray($o=null, $l=null)
    {
        if($o || $l) {
            $this->_offset = $o;
            $this->_limit = $l;
        }
        return $this->query($this->buildQuery(), \PDO::FETCH_ASSOC);
    }

    public function count($column='1')
    {
        if(!$this->_schema) return false;
        $r = $this->queryColumn($this->buildQuery(true));
        $i = (is_array($r))?((int) array_shift($r)):(0);
        if($this->_limit && $i > $this->_limit) $i = $this->_limit;
        return $i;
    }

    public function select($o)
    {
        $this->_select = null;
        return $this->addSelect($o);
    }

    public function addSelect($o)
    {
        if(is_array($o)) {
            foreach($o as $s) {
                $this->addSelect($s);
                unset($s);
            }
        } else {
            $fn = $this->getAlias($o);
            if($fn && strpos($fn, $this->_select)===false) {
                $this->_select .= ($this->_select)?(", {$fn}"):(" {$fn}");
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
        } else {
            $this->addSelect($this->scope($o));
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
                if(!is_int($i)) {
                    $this->addOrderBy($i, $s);
                } else {
                    $this->addOrderBy($s);
                }
                unset($s);
            }
        } else if($o) {
            $fn = $this->getAlias($o);
            if($fn && strpos($fn, $this->_orderBy)===false) {
                if($sort!='asc' && $sort!='desc')$sort='';
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
            $fn = $this->getAlias($o);
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

    private function getFunctionNext($fn)
    {
        return 'ifnull(max('.$this->getAlias($fn).'),0)+1';
    }

    private function getAlias($f, $sc=null)
    {
        $ofn = $fn = $f;
        if(substr($f, 0, 1)=='-' && substr($f,-1)=='-') return false;
        else if(preg_match_all('#`([^`]+)`#', $fn, $m)) {
            $r = $s = array();
            foreach($m[1] as $i=>$nfn) {
                $s[]=$m[0][$i];
                $r[]=$this->getAlias($nfn, $sc);
                unset($i, $nfn);
            }
            return str_replace($s, $r, $fn);
        } else if(preg_match('/@([a-z]+)\(([^\)]*)\)/', $fn, $m) && method_exists($this, $a='getFunction'.ucfirst($m[1]))) {
            return str_replace($m[0], $this->$a(trim($m[2])), $fn);
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
        $found=false;
        if($fn=='*') {
            return '*';
        }

        if (isset($sc['columns'][$fn])) {
            $found = true;
            if(isset($sc['columns'][$fn]['alias']) && $sc['columns'][$fn]['alias']) {
                $fn = $ta.'.'.$sc['columns'][$fn]['alias'].' '.tdz::sqlEscape($fn);
            } else {
                $fn = $ta.'.'.$fn;
            }
        } else if(!$found) {
            $rnf = '';
            $cn = get_called_class();
            while(strpos($ofn, '.')) {
                @list($rn, $fn) = explode('.', $ofn,2);
                $ofn=$fn;
                $rnf .= ($rnf)?('.'.$rn):($rn);
                if(isset($sc['relations'][$rn])) {
                    $rcn = (isset($sc['relations'][$rn]['className']))?($sc['relations'][$rn]['className']):($rn);
                    $rsc = $rcn::$schema;
                    if(!isset($this->_alias[$rnf])) {
                        $an = chr(97+count($this->_alias));
                        $this->_alias[$rnf]=$an;
                        if($rcn != $rnf) {
                            $this->_alias[$rcn]=$an;
                        }
                        if($sc['relations'][$rn]['type']!='one') {
                            $this->_distinct = ' distinct';
                        }
                        $jtn = (isset($rsc['view']))?('('.$rsc['view'].')'):($rsc['tableName']);
                        if(!is_array($sc['relations'][$rn]['foreign'])) {
                            $this->_from .= " left outer join {$jtn} as {$an} on {$an}.{$sc['relations'][$rn]['foreign']}={$ta}.{$sc['relations'][$rn]['local']}";
                        } else {
                            $this->_from .= " left outer join {$jtn} as {$an} on";
                            foreach($sc['relations'][$rn]['foreign'] as $rk=>$rv) {
                                $this->_from .= (($rk>0)?(' and'):(''))." {$an}.{$rv}={$ta}.{$sc['relations'][$rn]['local'][$rk]}";
                            }
                        }
                        if(isset($sc['relations'][$rn]['on'])) {
                            if(!is_array($sc['relations'][$rn]['on'])) $sc['relations'][$rn]['on']=array($sc['relations'][$rn]['on']); 
                            foreach($sc['relations'][$rn]['on'] as $rfn) {
                                list($rfn,$fnc)=explode(' ', $rfn, 2);
                                if(substr($rfn,0,strlen($rn))==$rn) $join .=  "and {$an}".substr($rfn,strlen($rn))." {$fnc} ";
                                else $this->_from .= ' and '.$this->getAlias($rfn, $rsc).' '.$fnc;
                                unset($rfn, $fnc);
                            }
                        }

                        if(isset($rsc['events']['active-records']) && $rsc['events']['active-records']) {
                            if(is_array($rsc['events']['active-records'])) {
                                foreach($rsc['events']['active-records'] as $r=>$v) {
                                    $this->_from .= ' and '.$this->getAlias($r, $rsc).'='.tdz::sqlEscape($v);
                                }
                            } else {
                                if(strpos($rsc['events']['active-records'], '`')!==false || strpos($rsc['events']['active-records'], '[')!==false) {
                                    $this->_from .= ' and '.$this->getAlias($rsc['events']['active-records'], $rsc);
                                } else {
                                    $this->_from .= ' and '.$rsc['events']['active-records'];
                                }
                            }
                        }

                    } else {
                        $an = $this->_alias[$rnf];
                    }
                    unset($sc, $rn);
                    $sc = $rsc;
                    unset($rsc);
                    $ta=$an;
                    $fn = $an.'.'.$fn;
                    $found = true;
                } else {
                    $found = false;
                    break;
                }
            }
        }
        if(!$found) {
            if (isset($sc['relations'][$fn])) {
                $found = true;
                $fn = $ta.'.'.$sc['relations'][$fn]['local'];
            } else if (isset($sc['columns'][$fn]) || property_exists($cn, $fn)) {
                $found = true;
                if(isset($sc['columns'][$fn]['alias']) && $sc['columns'][$fn]['alias']) {
                    $fn = $ta.'.'.$sc['columns'][$fn]['alias'].' '.tdz::sqlEscape($fn);
                } else {
                    $fn = $ta.'.'.$fn;
                }
            } else {
                tdz::debug(__METHOD__, func_get_args(), "Cannot find by [{$fn}] at [{$sc['tableName']}]");
                tdz::log("Cannot find by [{$fn}] at [{$sc['tableName']}]");
                throw new Exception("Cannot find by [{$fn}] at [{$sc['tableName']}]");
            }
        }
        unset($found, $sc, $ta);
        if(isset($fnt) && $fnt) {
            $fn = str_replace($fnt, $fn, $f);
            unset($fnt, $f);
        }
        return $fn;
    }

    private function getWhere($w, $xor='and')
    {
        $r='';
        $e = $this->schema('events');
        $add=array();
        if(is_null($this->_where) && isset($e['active-records']) && $e['active-records']) {
            if(is_array($e['active-records'])) {
                $add=$e['active-records'];
            } else {
                if(strpos($e['active-records'], '`')!==false || strpos($e['active-records'], '[')!==false) {
                    $r = $this->getAlias($e['active-records']);
                } else {
                    $r = $e['active-records'];
                }
                if($r) {
                    $r = "($r)";
                }
            }
        }

        if(!is_array($w)) {
            // must get from primary key or first column
            $pk = $this->scope('uid');
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
        static $cops = array('>=', '<=', '<>', '>', '<');
        static $like = array('%', '$', '^', '*', '~');
        static $xors = array('and'=>'and', '&'=>'and', 'or'=>'or', '|'=>'or');
        foreach($w as $k=>$v) {
            if(is_int($k)) {
                if(is_array($v)) {
                    if($v = $this->getWhere($v)) {
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
                    if(in_array($v, $cops)) {
                        $op = $v;
                    } else if(in_array($v[0], $like)) {
                        $op = $v[0];
                    } else if(isset($xors[$v])) {
                        $xor = $xors[$v];
                    }
                }
            } else {
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
                if(preg_match('/(\~|\<\>|[\<\>\^\$\*\!\%]?\=?|[\>\<])$/', $k, $m) && $m[1]) {
                    // operators: <=  >= < > ^= $=
                    $cop = (!in_array($m[1], $cops))?(substr($m[1], 0, 1)):($m[1]);
                    $k = trim(substr($k, 0, strlen($k) - strlen($m[0])));
                    unset($m);
                    if($cop=='!') {
                        $cnot = true;
                        $cop = '=';
                    }
                }
                $fn = $this->getAlias($k);
                if($fn) {
                    $r .= ($r)?(" {$cxor}"):('');
                    if(is_array($v) && count($v)==1) {
                        $v = array_shift($v);
                    }
                    if($cop=='~') {
                        // between
                    } else if (is_array($v) && ($cop=='=' || $cop=='!=' || $cop=='!' || $cop=='<>')) {
                        foreach ($v as $vk=>$vs) {
                            $v[$vk] = self::escape($vs);
                            if($vs==''){
                                $v['']='null';
                            }
                            unset($vk, $vs);
                        }
                        $r .= " {$fn}".(($cnot || $cop=='<>' || substr($cop, 0, 1)=='!')?(' not'):('')).' in('.implode(',',$v).')';
                    } else if(is_array($v)) {
                        $nv = array();
                        if($cop!='=') $nv[] = $cop;
                        if($cnot) $nv[] = '!';
                        if($cxor!='and') $nv[] = $cxor;
                        foreach($v as $vk=>$vs) {
                            $nv[] = array($k=>$vs);
                        }
                        $v = $this->getWhere($v);
                        if($v) $r .= "({$v})";
                    }
                    else if(!$v && $cop=='=') $r .= (($cnot)?(' not'):(' '))."({$fn}=".self::escape($v)." or {$fn} is null)";
                    else if(!$v && $cop=='<>') $r .= " ({$fn}<>".self::escape($v)." and {$fn} is not null)";
                    else if($cop=='^') $r .= " {$fn}".(($cnot)?(' not'):(''))." like '".self::escape($v, false)."%'";
                    else if($cop=='$') $r .= " {$fn}".(($cnot)?(' not'):(''))." like '%".self::escape($v, false)."'";
                    else if($cop=='*') $r .= " {$fn}".(($cnot)?(' not'):(''))." like '%".self::escape($v, false)."%'";
                    else if($cop=='%') $r .= " {$fn}".(($cnot)?(' not'):(''))." like '%".str_replace('-', '%', tdz::slug($v))."%'";
                    else $r .= ($not)?(" not({$fn}{$cop}".self::escape($v).')'):(" {$fn}{$cop}".self::escape($v));
                }
                unset($cop, $cnot);
            }
            unset($k, $fn, $v);
        }
        return trim($r);
    }

    public function run($q)
    {
        return self::runStatic($this->schema('database'), $q);
    }

    public static function runStatic($n, $q)
    {
        static $stmt;
        if($stmt) {
            $stmt->closeCursor();
            $stmt = null;
        }
        $stmt = self::connect($n)->query($q);
        if(!$stmt) {
            //\tdz::debug($n, var_Export(self::connect($n), true), self::connect($n)->errorInfo());
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
            tdz::log('Error in '.__METHOD__.":\n  ".$e->getMessage()."\n ".$this);
            return false;
        }
    }

    public function queryColumn($q, $i=0)
    {
        return $this->query($q, \PDO::FETCH_COLUMN, $i);
    }

    /*
    public function lastInsertId($fn=null)
    {
        $id = self::connect($this->schema('database'))->lastInsertId($fn);
        return $id;
    }
    */


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
            $ms = (int) self::$microseconds;
            if(preg_match('/^(([0-9]{4}\-[0-9]{2}\-[0-9]{2}) ?(([0-9]{2}:[0-9]{2})(:[0-9]{2}(\.[0-9]{1,'.$ms.'})?)?)?)[0-9]*$/', $v, $m)) {
                if(!isset($m[3]) || !$m[3]) {
                    return "'{$m[2]}T00:00:00'";
                } else if(!isset($m[5]) || !$m[5]) {
                    return "'{$m[2]}T{$m[4]}:00'";
                } else {
                    return "'{$m[2]}T{$m[3]}'";
                }
            }
        }

        return self::escape($v);
    }


    public function transaction($id=null)
    {
        // check if there's a current transaction
        // replace current transaction?
        // multiple transactions?
        $conn = self::connect($this->schema('database'));
        $conn->setAttribute(\PDO::ATTR_AUTOCOMMIT, 0);
        $this->_transaction = $conn->beginTransaction();
        unset($conn);
        return $this->_transaction;
    }
    
    public function commit($id=null)
    {
        if(!$this->_transaction) return false;
        $conn = self::connect($this->schema('database'));
        if($conn->inTransaction() && $conn->commit()===false){//  && !$conn->getAttribute(PDO::ATTR_AUTOCOMMIT)
            return false;
        } else {
            $conn->setAttribute(\PDO::ATTR_AUTOCOMMIT, 1);
        }
        $this->_transaction = null;
        unset($conn);
        return true;
    }

    public function rollback($id=null)
    {
        if(!$trans) return false;
        $conn = self::connect($this->schema('database'));
        $conn->rollBack();
        $conn->setAttribute(\PDO::ATTR_AUTOCOMMIT, 1);
        unset($conn);
        $this->_transaction = null;
        return true;
    }

    public function insert($o)
    {
        $schema = $o::$schema;
        $fs = $schema->getScope($o->getScope());
        $vs = array();
        foreach($fs as $f) {
            if(!isset($schema->columns[$f])) continue;
            $vs[$f] = self::sql($o->$f, $schema->columns[$f]);
            unset($f);
        }
        $tn = $schema->table;
        if($vs) {
            $this->run("insert into {$tn} (".implode(', ', array_keys($vs)).') values ('.implode(', ', $vs).')');
            $pks = $schema->getScope('primary');
            if($pks) {
                $insertId = self::connect($this->schema('database'))->lastInsertId();
                foreach($pks as $fn) {
                    if(is_null($o->$fn)) {
                        $o->$fn = $insertId;
                    }
                    unset($fn);
                }
            }
            unset($pks);
            $o->isNew(false);
        }
        unset($fs, $schema);
        return !$o->isNew();
    }

    public function update($o)
    {
        $schema = \Birds\Schema::load(get_class($o));
        $fs = $schema->getScope($o->getScope());
        $q = $pk = '';
        foreach($fs as $f) {
            if(!isset($schema->columns[$f])) continue;
            if(isset($schema->columns[$f]['primary'])) {
                $pk .= (($pk)?(' and '):(''))
                    . $f . '=' . self::sql($o->$f, $schema->columns[$f]);
            } else {
                $q .= (($q)?(', '):(''))
                    . $f . '=' . self::sql($o->$f, $schema->columns[$f]);
            }
            unset($f);
        }
        $tn = $schema->table;
        unset($fs, $schema);
        if($q && $pk) {
            return $this->run("update {$tn} set {$q} where {$pk}");
        }
        return false;
    }
    public function delete()
    {
        $schema = \Birds\Schema::load(get_class($o));
        $fs = $schema->getScope('primary');
        $pk = '';
        foreach($fs as $f) {
            if(!isset($schema->columns[$f])) continue;
            $pk .= (($pk)?(' and '):(''))
                . $f . '=' . self::sql($o->$f, $schema->columns[$f]);
            unset($f);
        }
        $tn = $schema->table;
        unset($fs, $schema);
        if($pk) {
            return $this->run("delete from {$tn} where {$pk}");
        }
        return false;
    }

}