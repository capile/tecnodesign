<?php
/**
 * Database abstraction
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Query_Sql
{
    const TYPE='sql', DRIVER='sql', QUOTE='``', PDO_AUTOCOMMIT=1, PDO_TRANSACTION=1;
    public static 
        $microseconds=6,
        $datetimeSize=6,
        $enableOffset=true,
        $typeMap=['float'=>'decimal', 'number'=>'decimal'],
        $textToVarchar,
        $logSlowQuery,
        $queryCallback,
        $connectionCallback,
        $errorCallback;
    protected static 
        $options, 
        $conn=array(), 
        $tableDefault,
        $tableAutoIncrement;
    protected 
        $_schema,
        $_database,
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
        $_classAlias, 
        $_transaction, 
        $_last,
        $_query;

    public static $schemaBehaviors=array(
        'uid'=>array('before-insert', 'before-update', 'before-delete'),
        'timestampable'=>array('before-insert', 'before-update', 'before-delete'),
        'insertable'=>array('before-update', 'before-delete'),
        'sortable'=>array('before-insert', 'before-update', 'before-delete'),
        'versionable'=>array('after-insert', 'after-update', 'after-delete'),
        'soft-delete'=>array('active-records', 'before-delete'),
        'auto-increment'=>array('before-insert'),
    );
    public static $schemaProperties=array('serialize','alias');

    public function __construct($s=null)
    {
        if($s) {
            if(is_object($s)) {
                $this->_schema = get_class($s);
            } else if(is_string($s) && class_exists($s)) {
                $this->_schema = $s;
            } else if(is_string($s) && Tecnodesign_Query::databaseHandler($s)===get_called_class()) {
                // connection name
                $this->_schema = new Tecnodesign_Schema_Model(array('database'=>$s));
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

    public static function setConnection($n, $conn)
    {
        $r = null;
        if(isset(static::$conn[$n])) $r = static::$conn[$n];
        static::$conn[$n] = $conn;
        return $r;
    }

    public static function connect($n='', $exception=true, $tries=3)
    {
        if(!isset(static::$conn[$n]) || !static::$conn[$n]) {
            try {
                $level = 'find';
                $db = Tecnodesign_Query::database($n);
                if(!$db) {
                    if($exception) throw new Exception('Could not connect to '.$n);
                    return false;
                }
                if(!$n && is_array($db)) $db = array_shift($db);
                $db += array('username'=>null, 'password'=>null, 'options'=>static::$options);
                $db['options'][PDO::ATTR_ERRMODE]=PDO::ERRMODE_EXCEPTION;

                if(isset($db['options']['command'])) {
                    $cmd = $db['options']['command'];
                    unset($db['options']['command']);
                } else if(isset($db['options']['initialize'])) {
                    $cmd = $db['options']['initialize'];
                } else {
                    $cmd = null;
                }
                $level = 'connect';
                static::$conn[$n] = new \PDO($db['dsn'], $db['username'], $db['password'], $db['options']);
                if(!static::$conn[$n]) {
                    tdz::log('[INFO] Connection to '.$n.' failed, retrying... '.$tries);
                    $tries--;
                    if(!$tries) return false;
                    return static::connect($n, $exception, $tries);
                }
                if($cmd) {
                    $level = 'initialize';
                    static::$conn[$n]->exec($cmd);
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
        $this->_classAlias = null;
        $this->_transaction = null;
        $this->_last = null;
        return $this;
    }

    public function schema($prop=null)
    {
        $cn = $this->_schema;
        if(is_string($this->_schema)) {
            if(property_exists($cn, 'schema') || ($cn instanceof Tecnodesign_Model)) {
                $schema = $cn::$schema;
            } else {
                $schema = ['database'=>$cn];
            }
        } else {
            $schema = $this->_schema;
        }
        if($prop) {
            $ret = null;
            if(isset($schema[$prop])) $ret = $schema[$prop];
            unset($schema);

            return $ret;
        }

        return $schema;
    }

    public function find($options=array(), $asArray=false)
    {
        $this->_select = $this->_where = $this->_groupBy = $this->_orderBy = $this->_limit = $this->_offset = null;
        $sc = $this->schema();
        $this->getFrom();
        if(isset($sc['defaults']['find'])) $this->filter($sc['defaults']['find']);
        unset($sc);
        $this->filter($options);
        return $this;
    }

    public function getFrom($sc=null)
    {
        if(!$this->_from || !is_array($this->_alias)) {
            if(!$sc) $sc = $this->schema();
            $this->_classAlias = [''=>$sc['className']];
            $this->_alias = [''=>'a'];
            $quote = static::QUOTE;
            if(isset($this->_query)) {
                $q = $this->_query;
                if(is_object($q)) $q = $q->buildQuery();
                if(preg_match('/ order by [^\']+$/', $q, $m)) {
                    $r = '';
                    if(preg_match('/( group by [^\']+| limit [0-9]\,]+)$/', $m[0], $n)) {
                        $r = $n[1];
                    }
                    $q = substr($q, 0, strlen($q) - strlen($m[0])).$r;
                }
                $this->_from = '('.$q.')';
            } else if(isset($sc['view']) && $sc['view']) {
                $this->_from = ( (strpos($sc['view'], ' ')!==false) ?'('.$sc['view'].')' :$sc['view'] );
            } else {
                $this->_from = $sc['tableName'];
            }
            $this->_from .= " as {$quote[0]}a{$quote[1]}";
            $sc = null;
        }
        return $this->_from;
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

    public function concat($a, $sep='-')
    {
        if(is_array($a) && count($a)>1) {
            $r = '';
            foreach($a as $fn) {
                $r .= (($r) ?','.tdz::sql($sep).',' :'')
                    . 'coalesce('.$this->getAlias($fn, null, true).',\'\')';
            }
            return ($r) ?'concat('.$r.')' :'null';
        } else {
            if(is_array($a)) $a = array_shift($a);
            $r = $this->getAlias($a, null, true);
        }

        return $r;
    }

    public function setQuery($q)
    {
        $this->_select = $this->_where = $this->_groupBy = $this->_orderBy = $this->_limit = $this->_offset = null;
        $this->_query = $q;
    }

    public function buildQuery($count=false)
    {
        if(isset($this->_query) && !$count) {
            return $this->_query;
        }

        if(is_null($this->_where)) {
            $this->_where = $this->getWhere(array());
        }
        if($count) {
            if(!is_string($count)) {
                $cc = '';
                if($this->_groupBy) {
                    if(strpos($this->_groupBy, ',')!==false && strpos($this->_groupBy, '(')===false) {
                        $cc = $this->concat(preg_split('/\s*,\s*/', trim($this->_groupBy), null, PREG_SPLIT_NO_EMPTY),'');
                    } else {
                        $cc = trim($this->_groupBy);
                    }
                } else if($this->_from && strpos($this->_from, ' left outer join ')) {
                    $cc = $this->concat($this->scope('uid'));
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
            . ' from '.$this->getFrom()
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
        if(is_array($o) && isset($o['bind'])) {
            $o = $o['bind'];
        } else if(!is_string($o) || preg_match('/[-:]{2}/', $o)) {
            $o = $this->scope($o);
        }

        if(!is_array($o)) $o=[$o];
        foreach($o as $k=>$n) {
            if(is_array($n)) {
                if(isset($n['bind']) && $n['bind']) {
                    $n = $n['bind'];
                } else {
                    continue;
                }
            }
            if($fn = $this->getAlias($n)) {
                $this->_select[$fn] = $fn;
            }
        }

        return $this;
    }


    public function scope($o=null, $sc=null)
    {
        $cn = ($sc && isset($sc['className']))?($sc['className']):($this->_schema);
        if($o==='uid') return $cn::pk(null, true);
        return $cn::columns($o, null, 3, true);
    }

    public function addScope($o)
    {
        if(is_array($o)) {
            $this->addSelect($o);
        } else if($s=$this->scope($o)) {
            $this->addSelect($s);
            $this->_scope = $o;
        }
        return $this;
    }

    public function where($w)
    {
        $this->_where = null;
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

            if($fn && is_string($fn) && (!$this->_orderBy || strpos($fn, $this->_orderBy)===false)) {
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

    protected function getAlias($f, $ref=null, $noalias=null)
    {
        $sc = null;
        $cn = null;
        if(is_string($ref) && isset($this->_classAlias[$ref])) {
            $cn = $this->_classAlias[$ref];
            $sc = $cn::$schema;
        } else if($ref && ($ref instanceof Tecnodesign_Model)) {
            $sc = $ref::$schema;
            $ref = $sc->className;
        }
        if(!$sc) $sc = $this->schema();

        $ofn = $fn = $f;
        $r = null;
        if($f==='null' || $f===false || (substr($f, 0, 1)=='-' && substr($f,-1)=='-')) {
            return false;
        } else if(preg_match_all('#`([^`]+)`#', $fn, $m)) {
            $r = $s = array();
            foreach($m[1] as $i=>$nfn) {
                $s[]=$m[0][$i];
                $r[]=$this->getAlias($nfn, $ref, true);
                unset($i, $nfn);
            }
            return str_replace($s, $r, $fn);
        } else if(preg_match('#^([a-z\.0-9A-Z_]+)\s+(as\s+)?([a-z\.0-9A-Z_]+)$#', trim($fn), $m) && ($r = $this->getAlias($m[1], $ref, true))) {
            return $r.' '.tdz::sql($m[3]);
        } else if($r===false) {
            return null;
        } else if(preg_match('/@([a-z]+)\(([^\)]*)\)/', $fn, $m) && method_exists($this, $a='getFunction'.ucfirst($m[1]))) {
            return str_replace($m[0], $this->$a(trim($m[2])), $fn);
        } else if(substr($f, 0, 1)=='_' && !isset($sc->properties[$f])) {
            // not to be queried
            return false;
        } else if($this->_alias && preg_match('/^('.implode('|', $this->_alias).')\.[a-zA-Z0-9\-\_]+$/', $f)) {
            return $f;
        }

        if(strpos($fn, '[')!==false && preg_match('/\[([^\]]+)\]/', $fn, $m)) {
            $fn = $m[1];
            $fnt = $m[0];
            $ofn = $fn;
            unset($m);
        }
        $ta='a';
        if(!is_array($this->_alias)) $this->getFrom();

        if(isset($this->_alias[$ref])) {
            $ta = $this->_alias[$ref];
        }
        /*
        if($ta==='a' && !in_array($ta, $this->_alias)) {
            $this->_alias[''] = $ta;
        }
        */
        if($fn=='*') {
            return ($ref) ?$ta.'.*' :'*';
        }

        $found=false;
        if(!$cn) $cn = $sc->className;
        $quote = static::QUOTE;

        if (isset($sc->properties[$fn])) {
            $found = $sc->properties[$fn];
            if(isset($found['alias'])) {
                if($found['alias']===false) {
                    return null;
                } else if(strpos($found['alias'], '`')!==false) {
                    $fn = $this->getAlias($found['alias'], $ref, true).((!$noalias)?(' '.tdz::sql($fn)):(''));
                } else {
                    $qfn = ($quote && strpos($found['alias'], ' ')!==false) ?"{$quote[0]}{$found['alias']}{$quote[1]}" :$found['alias'];
                    $fn = $ta.'.'.$qfn.((!$noalias)?(' '.tdz::sql($fn)):(''));
                }
            } else {
                $qfn = ($quote) ?"{$quote[0]}{$fn}{$quote[1]}" :$fn;
                if(!$noalias && isset($found['type']) && $found['type']=='string' && isset($found['size']) && $this::$textToVarchar && $this::$textToVarchar<=$found['size']) {
                    if(!$this->_selectDistinct) $this->_selectDistinct=array();
                    $scn = $sc['className'];
                    $this->_selectDistinct[$ta.'.'.$qfn] = 'cast('.$ta.'.'.$qfn.' as varchar(max)) '.((!property_exists($scn, $fn) && !$scn::$allowNewProperties) ?'_' :'' ).$fn;
                }
                $fn = $ta.'.'.$qfn;
            }
        } else if(isset($sc->overlay[$fn]['bind']) && $sc->overlay[$fn]['bind']!=$fn) {
            $fn = $this->getAlias($sc->overlay[$fn]['bind'], $ref, $noalias);
            if(!$fn) return;
        } else {
            $rnf = (string)$ref;
            if(strpos($fn, '.')) {
                @list($rn, $ofn) = explode('.', $fn,2);
                $rnf .= ($rnf) ?('.'.$rn) :($rn);
                if(isset($sc->properties[$rn]['serialize'])) {
                    $found = $sc->properties[$rn];
                    if(isset($found['alias']) && $found['alias']) {
                        $qfn = ($quote && strpos($found['alias'], ' ')!==false) ?"{$quote[0]}{$found['alias']}{$quote[1]}" :$found['alias'];
                        $fn = $ta.'.'.$qfn.((!$noalias)?(' '.tdz::sql($ofn)):(''));
                    } else {
                        $qfn = ($quote) ?"{$quote[0]}{$rn}{$quote[1]}" :$rn;
                        $fn = $ta.'.'.$qfn;
                    }
                } else if(isset($sc->relations[$rn])) {
                    $rel = $sc->relations[$rn];
                    $rcn = (isset($rel['className']))?($rel['className']):($rn);
                    $rsc = $rcn::$schema;
                    if(!isset($this->_alias[$rnf])) {
                        $chpos=($this->_alias)?(ceil(count($this->_alias)/2)):(0);
                        while(in_array($an=tdz::letter($chpos), $this->_alias)) $chpos++;

                        $this->_alias[$rnf]=$an;
                        $this->_classAlias[$rnf]=$rcn;
                        if($rel['type']!='one') {
                            $this->_distinct = ' distinct';
                        }

                        if(isset($rsc->view) && $rsc->view) {
                            $jtn = (strpos($rsc->view, ' '))?('('.$rsc->view.')'):((string)$rsc->view);
                        } else if(isset($rsc->database) && $rsc->database!=$this->schema('database')) {
                            $jtn = $this->getDatabaseName($rsc->database).'.'.$rsc->tableName;
                        } else {
                            $jtn = $rsc->tableName;
                        }
                        if(!is_array($rel['foreign'])) {
                            $rfn = $this->getAlias($rel['foreign'], $rnf, true);
                            $lfn = $this->getAlias($rel['local'], $ref, true);
                            $this->_from .= " left outer join {$jtn} as {$quote[0]}{$an}{$quote[1]} on {$lfn}={$rfn}";
                        } else {
                            $this->_from .= " left outer join {$jtn} as {$quote[0]}{$an}{$quote[1]} on";
                            foreach($rel['foreign'] as $rk=>$rv) {
                                $rfn = $this->getAlias($rv, $rnf, true);
                                $lfn = $this->getAlias($rel['local'][$rk], $ref, true);
                                $this->_from .= (($rk>0)?(' and'):(''))." {$lfn}={$rfn}";
                            }
                        }
                        if(isset($rel['on'])) {
                            if(!is_array($rel['on'])) $rel['on']=array($rel['on']); 
                            foreach($rel['on'] as $rfn) {
                                @list($rfn,$fnc)=explode(' ', $rfn, 2);
                                if(substr($rfn,0,strlen($rn))==$rn) {
                                    $this->_from .= " and {$an}".substr($rfn,strlen($rn))." {$fnc} ";
                                } else {
                                    $this->_from .= ' and '.$this->getAlias($rfn, $rnf, true).' '.$fnc;
                                }
                                unset($rfn, $fnc);
                            }
                        }
                        if(isset($rel['params']) && is_array($rel['params'])) {
                            $this->_from .= ' and '.$this->getWhere($rel['params'], 'and', $rnf);
                        }

                        if(isset($rsc->events['active-records']) && $rsc->events['active-records']) {
                            $ar = $rsc->events['active-records'];
                            unset($rsc->events['active-records']);
                            if(is_array($ar)) {
                                foreach($ar as $r=>$v) {
                                    if(is_int($r)) {
                                        $this->_from .= ' and '.$this->getAlias($v, $rnf, $noalias);
                                    } else {
                                        $this->_from .= ' and '.$this->getWhere(array($r=>$v), 'and', $rnf);
                                    }
                                    unset($r, $v);
                                }
                            } else {
                                if(strpos($ar, '`')!==false || strpos($ar, '[')!==false) {
                                    $this->_from .= ' and '.$this->getAlias($ar, $rnf, $noalias);
                                } else if(preg_match('/^([a-z0-9\_]+)[\s\<\>\!\=]/', $ar, $m)) {
                                    $this->_from .= ' and '.$this->getAlias($m[1], $rnf, $noalias).substr($ar, strlen($m[1]));
                                } else {
                                    $this->_from .= ' and '.$ar;
                                }
                            }
                            $rsc->events['active-records'] = $ar;
                            unset($ar);
                        }
                    }
                    unset($rn, $rsc, $rel);
                    $fn = $this->getAlias($ofn, $rnf, $noalias);
                    $found = true;
                }
            }
        }
        if(!$found) {
            if(isset($sc->className) && $cn!=$sc->className) $cn = $sc->className;
            if (isset($sc->relations[$fn]) || strtolower($fn)==='null') {
                // ignore
                return;
            } else if(isset($sc->properties[$fn])) {
                if($sc->properties[$fn]->alias) {
                    $qfn = ($quote) ?"{$quote[0]}{$sc->properties[$fn]->alias}{$quote[1]}" :$sc->properties[$fn]->alias;
                    $fn = $ta.'.'.$qfn.((!$noalias)?(' '.tdz::sql($fn)):(''));
                } else {
                    $qfn = ($quote) ?"{$quote[0]}{$fn}{$quote[1]}" :$fn;
                    $fn = $ta.'.'.$qfn;
                }
            } else if (strpos($fn, '.')===false && ($cn::$allowNewProperties || property_exists($cn, $fn))) {
                $qfn = ($quote) ?"{$quote[0]}{$fn}{$quote[1]}" :$fn;
                $fn = $ta.'.'.$qfn;
            } else {
                tdz::log("[WARNING] Cannot find by [{$fn}] at [{$this->schema('className')}.$ref]");
                throw new Exception("Cannot find by [{$fn}] at [{$this->schema('className')}.$ref]");
            }
        }
        unset($found, $sc, $ta);
        if(isset($fnt) && $fnt) {
            $fn = str_replace($fnt, $fn, $f);
            unset($fnt, $f);
        }
        return $fn;
    }

    protected function getWhere($w, $xor='and', $ref=null)
    {
        $r='';
        $sc = null;
        $cn = null;
        if(is_string($ref) && isset($this->_classAlias[$ref])) {
            $cn = $this->_classAlias[$ref];
            $sc = $cn::$schema;
        } else if($ref && ($ref instanceof Tecnodesign_Model)) {
            $sc = $ref::$schema;
            $ref = $sc->className;
        }
        if(!$sc) $sc = $this->schema();

        $e = (isset($sc->events))?($sc->events):(null);
        $add=array();
        $ar = null;
        if(is_null($this->_where) && $e && isset($e['active-records']) && $e['active-records']) {
            if(is_array($e['active-records'])) {
                $add=$e['active-records'];
            } else {
                if(strpos($e['active-records'], '`')!==false || strpos($e['active-records'], '[')!==false) {
                    $ar = $this->getAlias($e['active-records'], $ref, true);
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
        if($add) {
            $w += $add;
        }
        $op = '=';
        $not = false;
        static $cops = array('>=', '<=', '<>', '!', '!=', '>', '<');
        static $like = array('%', '$', '^', '*', '~');
        static $xors = array('and'=>'and', '&'=>'and', 'or'=>'or', '|'=>'or');
        foreach($w as $k=>$v) {
            if(is_int($k)) {
                if(is_array($v)) {
                    if($v = $this->getWhere($v, 'and', $ref)) {
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
                    } else if(strpos($v, '`')!==false || strpos($v, '[')!==false) {
                        $pxor = (isset($cxor))?($cxor):(null);
                        if($pxor && $pxor=='or' && $pxor!=$xor) {
                            $r = ' ('.trim($r).')';
                        }
                        $r .= ($r)?(" {$xor}"):('');
                        $r .= ' ('.$this->getAlias($v, $ref, true).')';
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
                $fn = $this->getAlias($k, $ref, true);
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

                    } else if(tdz::isempty($v) && $cop=='=') {
                        $r .= (($cnot)?(' not'):(' '))."({$fn}=".self::escape($v)." or {$fn} is null)";

                    } else if(tdz::isempty($v) && $cop=='<>') {
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
        if(self::$queryCallback) {
            return call_user_func(self::$queryCallback, $q, $this->schema('database'));
        } else {
            return self::runStatic($q, $this->schema('database'));
        }
    }

    public static function runMetrics($q, $n='')
    {
        static $stmt;
        if($stmt) {
            $stmt->closeCursor();
            $stmt = null;
        }
        $t0 = microtime(true);
        $stmt = self::connect($n)->query($q);
        if(!$stmt) {
            throw new \Exception('Statement failed! '.$q);
        }
        $t = microtime(true) - $t0;
        if(!isset(tdz::$variables['metrics']['query'])) {
            if(!isset(tdz::$variables['metrics'])) tdz::$variables['metrics'] = array();
            tdz::$variables['metrics']['query'] = array('time'=>(float) 0,'count'=>0);
        }
        tdz::$variables['metrics']['query']['time']+=$t;
        tdz::$variables['metrics']['query']['count']++;
        if(self::$logSlowQuery && $t>(float)self::$logSlowQuery) {
            if(!isset(tdz::$variables['metrics']['query']['slow'])) tdz::$variables['metrics']['query']['slow']=[];
            tdz::$variables['metrics']['query']['slow'][]=['time'=>$t, 'query'=>$q];
        }
        return $stmt;
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
            tdz::log('[WARNING] Error in '.get_called_class()."::query: {$e->getCode()}:\n  ".$e->getMessage()."\n {$this}", var_Export($this, true));
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
        } else if((isset($d['format']) && $d['format']=='datetime') || (isset($d['type']) && $d['type']=='datetime')) {
            $ms = (int) static::$microseconds;
            if(preg_match('/^(([0-9]{4}\-[0-9]{2}\-[0-9]{2}) ?(([0-9]{2}:[0-9]{2})(:[0-9]{2}(\.[0-9]{1,'.$ms.'})?)?)?)[0-9]*$/', $v, $m)) {
                if(!isset($m[3]) || !$m[3]) {
                    return "'{$m[2]}T00:00:00'";
                } else if(!isset($m[5]) || !$m[5]) {
                    return "'{$m[2]}T{$m[4]}:00'";
                } else {
                    return "'{$m[2]}T{$m[3]}'";
                }
            } else if($t=strtotime($v)) {
                return '\''.date('Y-m-d\TH:i:s', $t).'\'';
            }
        } else if((isset($d['format']) && $d['format']=='date') || (isset($d['type']) && $d['type']=='date')) {
            $ms = (int) static::$microseconds;
            if(preg_match('/^(([0-9]{4}\-[0-9]{2}\-[0-9]{2}) ?(([0-9]{2}:[0-9]{2})(:[0-9]{2}(\.[0-9]{1,'.$ms.'})?)?)?)[0-9]*$/', $v, $m)) {
                return "'{$m[2]}'";
            } else if($t=strtotime($v)) {
                return '\''.date('Y-m-d\TH:i:s', $t).'\'';
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
            if(static::PDO_AUTOCOMMIT) $conn->setAttribute(\PDO::ATTR_AUTOCOMMIT, 0);
            if(static::PDO_TRANSACTION) {
                $conn->beginTransaction();
            } else {
                $this->exec('begin transaction '.$id, $conn);
            }
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
            if($conn) {
                if(static::PDO_TRANSACTION) {
                    $r = ($conn->inTransaction() && $conn->commit());
                } else {
                    $r = $this->exec('commit transaction '.$id, $conn);
                }
                if(static::PDO_AUTOCOMMIT) $conn->setAttribute(\PDO::ATTR_AUTOCOMMIT, 1);
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
            if($conn) {
                if(static::PDO_TRANSACTION) {
                    $r = ($conn->inTransaction() && $conn->rollback());
                } else {
                    $r = $this->exec('rollback transaction '.$id, $conn);
                }
                if(static::PDO_AUTOCOMMIT) $conn->setAttribute(\PDO::ATTR_AUTOCOMMIT, 1);
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
        $data = [];
        $a = [];

        $fs = $M::$schema['columns'];
        if(!$fs) $fs = array_flip(array_keys($odata));
        foreach($fs as $fn=>$fv) {
            if(!is_array($fv) && !is_object($fv)) $fv=array('null'=>true);
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
                $a[$fn] = $odata[$fn];
            } else if($M->getOriginal($fn, false)!==false && is_null($M->$fn)) {
                $data[$fn] = 'null';
                $a[$fn] = null;
            }
            unset($fs[$fn], $fn, $fv);
        }

        $tn = $M::$schema['tableName'];
        if($data) {
            if(!$conn) {
                $conn = self::connect($this->schema('database'));
            }
            if($quote = static::QUOTE) {
                $this->_last = "insert into {$tn} ({$quote[0]}".implode("{$quote[1]}, {$quote[0]}", array_keys($data))."{$quote[1]}) values (".implode(', ', $data).')';
            } else {
                $this->_last = "insert into {$tn} (".implode(", ", array_keys($data)).") values (".implode(', ', $data).')';
            }
            $r = $this->exec($this->_last, $conn);
            if($r===false && $conn->errorCode()!=='00000') {
                throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $M::label()));
            }

            if($id = $this->lastInsertId($M, $conn)) {
                $pk = $M::pk(null, true);
                if($pk) {
                    foreach($pk as $i=>$fn) {
                        if(isset($M::$schema->properties[$fn]) && (!$M::$schema->properties[$fn]->increment || isset($data[$fn]))) {
                            unset($pk[$i]);
                        }
                    }
                }

                if($pk) {
                    if(is_array($id)) {
                        foreach($pk as $f) {
                            if(isset($id[$f])) {
                                $M->$f = $id[$f];
                            }
                            unset($f);
                        }
                    } else {
                        $pk = array_shift($pk);
                        $M[$pk] = $id;
                    }
                }

                $M->isNew(false);
                $r = $id;
            }
            if($M::$schema->audit) {
                $M->auditLog('insert', $id, $data);
            }
            return $r;
        }
    }

    public function update($M, $conn=null)
    {
        $odata = $M->asArray('save', null, null, true);
        $data = [];

        $fs = $M::$schema->properties;
        if(!$fs) {
            $fs = array_flip(array_keys($odata));
        }
        $sql = '';
        $quote = static::QUOTE;
        foreach($fs as $fn=>$fv) {
            $original=$M->getOriginal($fn, false);
            if(is_object($fv) && ($fv->primary || $fv->alias)) continue;
            if(!is_object($fv)) $fv=new stdClass();

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

            if(@(string)$original!==@(string)$v) {
                $qfn = ($quote) ?"{$quote[0]}{$fn}{$quote[1]}" :$fn;
                $sql .= (($sql!='')?(', '):(''))
                      . "{$qfn}={$fv}";
                $data[$fn]=$v;
                //$M->setOriginal($fn, $v);
            }
            unset($fs[$fn], $fn, $fv, $v);
        }
        if($sql) {
            $tn = $M::$schema->tableName;
            $wsql = '';
            $pks = tdz::sql($M->getPk(true));
            foreach($pks as $fn=>$fv) {
                if($quote) $fn = "{$quote[0]}{$fn}{$quote[1]}";
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
            if($M::$schema->audit) {
                $M->auditLog('update', $M->getPk(), $data);
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
            if($M::$schema->audit) {
                $M->auditLog('delete', $pk, $M->asArray('save', null, null, true));
            }
            return $r;
        }
    }

    public function create($schema=null, $conn=null)
    {
        if(!$schema) {
            $schema = $this->schema();
        } else if(is_string($schema)) {
            $schema = $schema::$schema;
        }
        $tn = $schema->tableName;
        $q = '';
        $q0 = static::QUOTE[0];
        $q1 = static::QUOTE[1];
        $pk = [];
        $idx = [];
        $formats = ['date', 'datetime', 'int', 'decimal' ];
        foreach($schema->properties as $fn=>$fd) {
            if($fd->alias) continue;
            $q .= (($q)?(",\n "):("\n "))
                . $q0.$fn.$q1.' ';

            $type = ($fd->format) ?$fd->format :$fd->type;
            if(isset(static::$typeMap[$type])) {
                $type = static::$typeMap[$type];
            }
            if(in_array($type, $formats)) {
                $q .= $type;
                if($type=='datetime') {
                    if(static::$datetimeSize) $q .= '('.static::$datetimeSize.')';
                } else if($type=='decimal') {
                    $d = [10,2];
                    if($fd->decimal) $d[1] = $fd->decimal;
                    if($fd->size) $d[0] = $fd->size;
                    $q .='('.implode(',', $d).')';
                }
            } else if($type=='string') {
                $q .= 'varchar('
                    . ((isset($fd['size']))?((int)$fd['size']):(255))
                    . ')';
            } else if($fd->increment) {
                $q .= 'int';
            } else {
                $q .= $type;
            }
            if($fd->required) $q .= ' not';
            $q .= ' null';
            if($fd->increment==="auto") {
                $q .= ' '.static::$tableAutoIncrement;
            }
            if($fd->primary) $pk[]=$fn;
            if($fd->index) {
                if(is_array($fd->index)) {
                    foreach($fd->index as $fidx) $idx[$fidx][] = $fn;
                } else {
                    $idx[$fd->index][] = $fn;
                }
            }
        }
        if($pk) {
            $q .= (($q)?(",\n "):("\n "))
                . 'primary key('.$q0.implode($q1.','.$q0, $pk).$q1.')';
        }
        $fks = [];
        $actions = ['cascade', 'no action', 'set null'];
        if($schema->relations) {
            foreach($schema->relations as $reln=>$rel) {
                if(isset($rel['constraint']) && $rel['constraint']) {
                    if(!is_array($rel)) $rel = ['fk_'.$schema->tableName.'__'.$rel => $rel ];
                    foreach($rel['constraint'] as $fk=>$action) {
                        $rn = (isset($rel['className'])) ?$rel['className'] :$rel;
                        $action = (in_array(strtolower($action), $actions) ?$action :'no action');
                        $q .= (($q)?(",\n "):("\n "))
                            . 'constraint '.$q0.$fk.$q1.' foreign key('.$q0.((is_array($rel['local'])) ?implode($q1.','.$q0, $rel['local']) :$rel['local']).$q1.')'
                            . ' references '.$q0.$rn::$schema->tableName.$q1.' ('.$q0.((is_array($rel['foreign'])) ?implode($q1.','.$q0, $rel['foreign']) :$rel['foreign']).$q1.')'
                            . ' on delete '.$action
                            . ' on update '.$action
                            ;
                    }
                }
            }
        }
        $q = 'create table '.$q0.$tn.$q1.' ('.$q."\n)".static::$tableDefault;
        if(!$conn) {
            $conn = self::connect($schema->database);
        }
        $this->_last = $q;
        $r = $this->exec($this->_last, $conn);
        if($r===false && $conn->errorCode()!=='00000') {
            throw new Tecnodesign_Exception(array(tdz::t('Could not create %s.', 'exception'), $tn));
        }
        if($idx) {
            foreach($idx as $in=>$fn) {
                $q = "create index {$q0}{$in}{$q1} on {$q0}{$tn}{$q1}({$q0}".implode($q1.','.$q0, $fn).$q1.');';
                if(!$this->exec($q, $conn) && $conn->errorCode()!=='00000') {
                    tdz::log('[WARNING] Could not create index '.$in.' on '.$tn.': '.$q);
                }
            }
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
        } else if($tns && is_array($tns)) {
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


    public function getDatabaseName($n=null)
    {
        if(!$n) $n = $this->schema('database');
        $db = Tecnodesign_Query::database($n);
        if($db && isset($db['dsn']) && preg_match('/(^|;)(dbname|Database)=([^\;]+)/i', $db['dsn'], $m)) {
            $n = $m[3];
        }
        return $n;
    }

    public function getTables($database=null)
    {
        if(is_null($database)) $database = $this->schema('database');
        $query = $this->getTablesQuery($database);
        $conn = static::connect($database);
        return $conn->query($query)->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function getTablesQuery($database=null, $enableViews=null)
    {
        return 'show tables';
    }

    public function getTableSchemaQuery($table, $database=null, $enableViews=null)
    {
        return 'show full columns from '.\tdz::sql($table, false);
    }

    protected static $columnSchemaMap=[
        // mysql/mariadb return values
        'Field'=>'bind','Type'=>'type', 'Key'=>'keys', 'Null'=>'null', 'Default'=>'default', 'Extra'=>'options',
        // sqlite return values
        'name'=>'bind', 'pk'=>'keys', 'dflt_value'=>'required'
    ];

    public function getColumnSchema($fd, $base=array())
    {
        $protected = array('type', 'min', 'max', 'length', 'null');// , 'increment'
        foreach(static::$columnSchemaMap as $src=>$dst) {
            if(isset($fd[$src])) {
                $fd[$dst] = $fd[$src];
                unset($fd[$src]);
            } else if(!isset($fd[$dst])) {
                $fd[$dst] = null;
            }
        }
        if(is_array($base) && count($base)>0) {
            foreach ($protected as $remove) {
                if(isset($base[$remove])) {
                    unset($base[$remove]);
                }
            }
        } else {
            $base = array();
        }
        $f=array(
            'bind'=>trim($fd['bind']),
        );
        // find type and limits
        // date and datetime
        $type = trim(strtolower($fd['type']));
        $desc = '';
        $unsigned = false;
        if(preg_match('/\s*\(([0-9\,]+)\)\s*(signed|unsigned)?.*/', $type, $m)) {
            $desc = trim($m[1]);
            $type = substr($type, 0, strlen($type) - strlen($m[0]));
            if(isset($m[2]) && $m[2]=='unsigned') {
                $unsigned = true;
            }
        }
        if ($type=='datetime' || $type=='date') {
            $f['format'] = $type;
            $f['type'] = 'string';
        //} else if($type=='tinyint' && $desc=='1') {
        //    $f['type'] = 'bool';
        } else if($type=='bit') {
            $f['type'] = 'bool';
        } else if(substr($type, -3)=='int') {
            $f['type'] = 'int';
            if($unsigned) {
                $f['min'] = 0;
            }
            if ($type=='tinyint') {
                $f['max'] = ($unsigned)?(255):(128);
            }
            if($fd['options']=='auto_increment') {
                $f['increment']='auto';
            }
        } else if($type=='float') {
            $f['type'] = 'number';
            $f['size'] = (int) 10;
            $f['decimal'] = (int) 2;
        } else if($type=='decimal') {
            $f['type'] = 'number';
            $desc = explode(',',$desc);
            $f['size'] = (int)$desc[0];
            $f['decimal'] = (int)$desc[1];
        } else if($type=='double') {
            $f['type'] = 'number';
            $f['size'] = (int) 10;
            $f['decimal'] = (int) 2;
        } else if(substr($type, -4)=='text') {
            $f['type'] = 'string';
            $f['size'] = (int)$desc;
        } else if($type=='varchar') {
            $f['type'] = 'string';
            $f['size'] = $desc;
        } else if($type=='char') {
            $f['type'] = 'string';
            $f['size'] = (int)$desc;
            $f['min_size'] = $desc;
        } else if(substr($type, 0, 4)=='enum') {
            $f['type'] = 'string';
            $choices = array();
            preg_match_all('/\'([^\']+)\'/', $type, $m);
            foreach($m[1] as $v) {
                $choices[$v]=$v;
            }
            $f['choices']=$choices;
        } else {
            $f['type'] = 'string';
        }
        if($fd['null']=='YES') $f['required'] = false;
        else if(isset($fd['required'])) $f['required'] = ($fd['required']!='1');
        else $f['required'] = false;

        if(isset($fd['size']) && is_numeric($fd['size'])) $f['size'] = (int)$fd['size'];

        if($fd['keys']=='PRI' || $fd['keys']=='1') {
            $f['primary']=true;
        }
        if(isset($fd['default'])) $f['default'] = $fd['default'];
        $f += $base;
        
        return $f;
    }

    public function getTableSchema($table, $schema=null, $Model=null)
    {
        if(is_null($schema)) {
            $schema = $this->schema();
            if(!$schema->className) {
                if(class_exists($cn=Tecnodesign_Database::className($table))) {
                    $schema = $cn::$schema;
                }
            }
        }
        $schema->tableName = $table;

        $raw = $this->query($this->getTableSchemaQuery($table));
        if(!$raw) {
            return false;
        }

        $properties = [];
        $overlay = $schema->overlay ?$schema->overlay :[];
        $events = $schema->actAs ?$schema->actAs :[];
        $se = $schema->events ?$schema->events :[];
        $reprop = '/('.implode('|',static::$schemaProperties).'):\s*([^,\s\;]+)([,\s\;]+)?/';
        foreach($raw as $i=>$o) {
            $fd = $this->getColumnSchema($o);
            if(isset($fd['bind'])) {
                $fn = $fd['bind'];
            } else {
                $fn = array_values($o)[0];
            }
            if($Model && isset($fd['default']) && $fd['default']!='') {
                $Model[$fn]=$fd['default'];
            }
            $properties[$fn] = $fd;
            if(isset($o['Comment']) && $o['Comment']!='') {
                if(preg_match_all($reprop, $o['Comment'], $m)) {
                    foreach($m[1] as $k=>$v) {
                        $properties[$fn][$v] = $m[2][$k];
                        unset($k, $v);
                    }
                    str_replace($m[0], '', $o['Comment']);
                    unset($m);
                    if(trim($o['Comment'])=='') continue;
                }

                foreach(static::$schemaBehaviors as $bn=>$e) {
                    if(strpos($o['Comment'], $bn)!==false) {
                        if(isset($overlay[$fn])) unset($overlay[$fn]);

                        $found=array();
                        foreach($e as $en) {
                            if(strpos($o['Comment'], $en)!==false) {
                                $found[]=$en;
                            }
                        }
                        if(count($found)>0) {
                            $e = $found;
                        }
                        foreach($e as $en) {
                            if($en=='active-records') {
                                $events[$en][]=$fn;
                            } else {
                                if(!isset($events[$en][$bn]) || !in_array($fn, $events[$en][$bn])) $events[$en][$bn][]=$fn;
                                $se[$en][0]='actAs';
                            }
                        }
                    }
                }
            }
        }

        $schema->properties = $properties;
        if(!$schema->scope) $schema->scope=array();
        if(!$overlay) {
            $cn = ($schema->className) ?$schema->className :tdz::camelize($schema->tableName, true);
            if(method_exists($cn, 'formFields')) {
                $cn::$schema =& $schema;
                $cn::formFields();
            }
        }
        if(count($se)>0) {
            if(isset($se['active-records'])) {
                if(isset($events['active-records']['soft-delete'])) {
                    $sd = $events['active-records']['soft-delete'];
                    unset($events['active-records']);
                } else if(isset($events['before-delete']['soft-delete'])) {
                    $sd = $events['before-delete']['soft-delete'];
                } else {
                    $sd = null;
                }
                if($sd) $se['active-records'] = '`'.implode('` is null and `', $sd).'` is null';
            }
        }

        $schema->events = $se;
        $schema->actAs = $events;
        $schema->overlay = $overlay;

        $this->getRelationSchema($schema);        

        return $schema;
    }

    public function getRelationSchemaQuery($table, $database=null, $enableViews=null)
    {
        $dbname = tdz::sql($database);
        $tn = tdz::sql($table);
        return "select constraint_name as fk, ordinal_position as pos, table_name as tn, column_name as f, referenced_table_name as ref, referenced_column_name as ref_f from information_schema.key_column_usage where table_schema={$dbname} and referenced_table_name is not null and (table_name={$tn} or referenced_table_name={$tn}) order by 2, 1";
    }


    public function getRelationSchema(&$schema)
    {
        $conn = static::connect($schema->database);
        $dbname = $this->getDatabaseName($schema->database);
        $tn = $schema->tableName;
        if(!($q=$this->getRelationSchemaQuery($tn, $dbname, true)) || !($rels = $conn->query($q)->fetchAll(\PDO::FETCH_ASSOC))) {
            return false;
        }

        $r = array();
        foreach ($rels as $rel) {
            if ($rel['tn']==$tn) {
                $ref = $rel['ref'];
                $local = $rel['f'];
                $type = 'one';
                $foreign = $rel['ref_f'];
            } else {
                $ref = $rel['tn'];
                $local = $rel['ref_f'];
                $type = 'many';
                $foreign = $rel['f'];
            }
            $class = Tecnodesign_Database::className($ref, $schema->database);
            if(class_exists($class)) {
                if(($cn=$class::$schema->className) && $cn!=$class) {
                    $class = $cn;
                }
            } else {
                if(tdz::$log) tdz::log("[DEBUG] Class $class does not exist and cannot return a schema");
                continue;
            }
            $alias = $class;
            if(preg_match('/^__([a-z0-9]+)__$/i', $rel['fk'], $m)) {
                $alias = $m[1];
            } else if(strpos($alias, '_')!==false || strpos($alias, '\\')!==false) {
                $alias = preg_replace('/.*[_\\\\]([^_\\\\]+)$/', '$1', $alias);
            }
            if (isset($r[$alias])) {
                if(!is_array($r[$alias]['local'])) {
                    $r[$alias]['local']=array($r[$alias]['local']);
                    $r[$alias]['foreign']=array($r[$alias]['foreign']);
                }
                $r[$alias]['local'][]=$local;
                $r[$alias]['foreign'][]=$foreign;
            } else {
                $r[$alias]['local']=$local;
                $r[$alias]['foreign']=$foreign;
            }
            $r[$alias]['type']=$type;
            if($alias!=$class){
                //$r[$class]=$r[$alias];
                $r[$alias]['className']=$class;
            }
        }

        if($schema->relations) {
            $schema->relations += $r;
        } else {
            $schema->relations = $r;
        }
        return $schema;
    }
    
}