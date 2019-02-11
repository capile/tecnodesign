<?php
/**
 * Database abstraction
 *
 * PHP version 5.4
 *
 * @category  Database
 * @package   Model
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2019 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Query_Ldap
{
    const TYPE='ldap';
    public static 
        $microseconds=6,
        $enableOffset=true,
        $connectionCallback,
        $errorCallback,
        $fetchOperationalAttributes=true,
        $operationalAttributes=array(),
        $timeout=-1;
    protected static 
        $options=[
            LDAP_OPT_PROTOCOL_VERSION => 3,
        ],
        $conn=[], 
        $schemas=[],
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
                static::$conn[$n] = $ldapconn = ldap_connect($db['dsn']);
                if(!static::$conn[$n]) {
                    throw new Tecnodesign_Exception('ldap_connect failed!');
                }
                if($db['options']) {
                    foreach($db['options'] as $k=>$v) {
                        ldap_set_option(static::$conn[$n], $k, $v);
                    }
                }
                if(isset($db['username'])) {
                    $level = 'bind';
                    if(!ldap_bind(static::$conn[$n], $db['username'], $db['password'])) {
                        throw new Tecnodesign_Exception(ldap_error(static::$conn[$n]));
                    }
                }
            } catch(Exception $e) {
                tdz::log('Could not '.$level.' to '.$n.":\n  {$e->getMessage()}", $db);
                if($tries) {
                    $tries--;
                    if(isset(static::$conn[$n])) static::$conn[$n]=null;
                    return static::connect($n, $exception, $tries);
                } else {
                    tdz::log("[INFO] {$e}");
                }
                if($exception) {
                    throw new Exception('Could not connect to '.$n);
                }
            }
            if(static::$connectionCallback) {
                static::$conn[$n] = call_user_func(static::$connectionCallback, static::$conn[$n], $n);
            }
        }
        return static::$conn[$n];
    }

    public static function disconnect($n='')
    {
        if(isset(static::$conn[$n]) && static::$conn[$n]) {
            ldap_unbind(static::$conn[$n]);
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
        $this->_transaction = null;
        $this->_last = null;
    }

    public function schema($prop=null, $object=true)
    {
        $cn = $this->_schema;
        if($prop) {
            if(isset($cn::$schema[$prop])) return $cn::$schema[$prop];
            return null;
        }
        if($object) {
            if(!isset(static::$schemas[$cn])) static::$schemas[$cn] = new Tecnodesign_Schema($cn::$schema);
            return static::$schemas[$cn];
        }
        return $cn::$schema;
    }

    public function scope($o=null, $sc=null)
    {
        $this->_scope = $o;
        if($sc && is_array($sc)) $sc = new Tecnodesign_Schema($sc);
        else if(!$sc) $sc = $this->schema();
        if($o==='uid') return $sc->uid();
        return $sc->properties($o, false, null, false);
    }

    public function getDatabaseName($n=null)
    {
        if(!$n) $n = $this->schema('database');
        return $n;
    }

    /*
    public static function getTables($n=''){}
    */

    public function find($options=array(), $asArray=false)
    {
        $this->_select = $this->_where = $this->_groupBy = $this->_orderBy = $this->_limit = $this->_offset = $this->_last = null;
        $sc = $this->schema();
        $this->_from = $sc['tableName'];
        if(isset($sc['defaults']['find'])) $this->filter($sc['defaults']['find']);
        unset($sc);
        $this->filter($options);
        return $this;
    }

    public function filter($options=array())
    {
        if(!$this->_schema) return $this;
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
        $select = $this->select();
        if(!is_array($select)) $select = array();
        if(static::$fetchOperationalAttributes) $select[]='+';
        $select = array_values(array_unique($select));
        $where = $this->getWhere($this->_where);
        if(!$where) $where = 'ou=*';
        $limit = -1;//($count || !$this->_limit)?(-1):((int)$this->_limit);
        $this->_last = ldap_list(self::connect($this->schema('database')), $this->schema('tableName'), $where, $select, 0, $limit, static::$timeout);
        return $this->_last;
    }

    public function lastQuery()
    {
        return $this->_last;
    }

    public function fetch($o=null, $l=null, $cn=true)
    {
        if(!$this->_schema) return false;
        if(!$this->_last) {
            $this->buildQuery();
        }
        $prop = array('_new'=>false);
        if($this->_scope) $prop['_scope'] = $this->_scope;
        $this->_offset = $o;
        $this->_limit = $l;

        $i0 = (int) $this->_offset;
        $i1 = ($this->_limit)?($i0 + (int)$this->_limit):(0);
        $e = ldap_first_entry($conn=self::connect($this->schema('database')), $this->_last);
        if(!is_string($cn) && $cn) $cn = $this->schema('className');
        $r = array();
        $i = 0;
        while($e && (!$i1 || $i<$i1)) {
            if($i>=$i0) {
                if($a = ldap_get_attributes($conn, $e)) {
                    $r[] = ($cn)?(new $cn($prop+static::entry($a, ldap_get_dn($conn, $e)), false, false)):(static::entry($a, ldap_get_dn($conn, $e)));
                } else {
                    break;
                }
            }
            $i++;
            $e = ldap_next_entry($conn, $e);
        }

        return $r;
    }

    public function fetchArray($o=null, $l=null)
    {
        return $this->fetch($o, $l, false);
    }

    public static function entry($a, $dn=null)
    {
        $r = array();
        if($dn) $r['dn'] = $dn;
        foreach($a as $i=>$o) {
            if(!is_int($i)) {
                if($o['count']=='1') {
                    $r[$i] = $o[0];
                } else {
                    unset($o['count']);
                    $r[$i] = $o;
                }
            }
            unset($a[$i], $i, $o);
        }
        return $r;
    }

    public function count()
    {
        if(!$this->_schema) return false;
        if(!$this->_last) {
            $this->buildQuery();
        }

        $i = ldap_count_entries(self::connect($this->schema('database')), $this->_last);
        return $i;
    }

    public function select($o=false)
    {
        if($o!==false) {
            $this->_select = null;
            $this->addSelect($o);
        }
        if(!$this->_select && $this->_scope) {
            $this->_select = $this->schema()->properties($this->_scope, null, null, false);
        }
        return $this->_select;
    }

    public function addSelect($o)
    {
        $r = $this->schema()->properties($o, null, null, false);
        if(is_null($this->_select)) $this->_select = $r;
        else $this->_select = array_merge($this->_select, $r);
        unset($r);
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
        $this->addWhere($w);
        return $this;
    }

    public function addWhere($w)
    {
        if(is_null($this->_where)) $this->_where = (is_array($w))?($w):(array($w));
        else if(is_array($w)) {
            foreach($w as $k=>$v) {
                if(is_int($k)) $this->_where[] = $v;
                else if(!isset($this->_where[$k])) $this->_where[$k] = $v;
                else {
                    if(!is_array($this->_where[$k])) $this->_where[$k] = array($this->_where[$k]);
                    if(is_array($v)) $this->_where[$k] = array_merge($this->_where[$k], $v);
                    else $this->_where[$k][] = $v;
                }
            }
        } else $this->_where[] = $w; 
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
            $fn = $o;
            if($fn && strpos($fn, $this->_orderBy)===false) {
                if($sort!='asc' && $sort!='desc') $sort='';
                $this->_orderBy .= ($this->_orderBy)?(", {$fn} {$sort}"):(" {$fn} {$sort}");
            }
            unset($fn);
        }
        return $this;
    }

    public function addGroupBy($o) // not yet supported
    {
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

    protected function getAlias($f, $sc=null)
    {
        return $f;
    }

    protected function getWhere($w, $xor='&', $sc=null)
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
                $ar = $e['active-records'];
            }
        }

        if(!$w) $w = array();
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
        else if($ar) array_unshift($w, $ar);
        unset($add, $ar);

        $op = '=';
        $not = false;
        static $cops = array('>=', '<=', '<>', '!', '!=', '>', '<');
        static $like = array('%', '$', '^', '*');
        static $xors = array('and'=>'&', '&'=>'&', 'or'=>'|', '|'=>'|');
        foreach($w as $k=>$v) {
            if(is_int($k)) {
                if(is_array($v)) {
                    if($v = $this->getWhere($v, '&', $sc)) {
                        $r .= $v;
                    }
                    continue;
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
                    $r = '('.trim($r).')';
                }
                unset($c1);
                if(preg_match('/(\<\>|[\<\>\^\$\*\!\%]?\=?|[\>\<])$/', $k, $m) && $m[1]) {
                    // operators: <=  >= < > ^= $=
                    $cop = (!in_array($m[1], $cops))?(substr($m[1], 0, 1)):($m[1]);
                    $k = trim(substr($k, 0, strlen($k) - strlen($m[0])));
                    unset($m);
                    if($cop=='!') {
                        $cnot = true;
                        $cop = '=';
                    }
                }
                $fn = $this->getAlias($k, $sc);
                if($fn) {
                    $cn = (isset($sc['className']))?($sc['className']):($this->_schema);
                    if($cn && $cn::$prepareWhere && method_exists($cn, $m='prepareWhere'.tdz::camelize(substr($fn, 2),true))) {
                        $v = $cn::$m($v);
                    }
                    if($r) {
                        if(substr($r, 0, 1)=='(') $r = $cxor.$r;
                    }
                    if(is_array($v) && count($v)==1) {
                        $v = array_shift($v);
                    }
                    if (is_array($v) && ($cop=='=' || $cop=='!=' || $cop=='!' || $cop=='<>')) {
                        foreach ($v as $vk=>$vs) {
                            $v[$vk] = self::escape($vs, LDAP_ESCAPE_FILTER);
                            unset($vk, $vs);
                        }
                        if($cnot || $cop=='<>' || substr($cop, 0, 1)=='!') {
                            $r .= "(&({$fn}=*)(!({$fn}=" . implode("))(!({$fn}=", $v). '))';
                        } else {
                            $r .= "(&({$fn}=" . implode("))(&({$fn}=", $v) . '))';
                        }
                    } else if(is_array($v) && !($cop=='^' || $cop=='$' || $cop=='*' || $cop=='%')) {
                        $nv = array();
                        if($cnot) $nv[] = '!';
                        if($cxor!='and') $nv[] = $cxor;
                        foreach($v as $vk=>$vs) {
                            $nv[] = ($vs && $cop!='=')?(array($k.$cop=>$vs)):(array($k=>$vs));
                        }
                        $v = $this->getWhere($nv, '|');
                        if($v) $r .= "({$v})";
                    } else if($cop=='<>' || $cop=='!=' || $cop=='!') {
                        $r .= "(!({$fn}=".self::escape($v, LDAP_ESCAPE_FILTER)."))";

                    } else if($cop=='^' || $cop=='$' || $cop=='*') {
                        if($cnot){
                            $b='(!(';
                            $a='))';
                        } else {
                            $b = '(';
                            $a = ')';
                        }
                        $b .= "{$fn}=";
                        if($cop!='^') $b .= '*';
                        $a = (($cop!='$')?('*'):('')).$a;
                        if(is_array($v)) {
                            $r .= '(&'.$b.implode($a.$b, self::escape($v, LDAP_ESCAPE_FILTER)).$a.')';
                        } else {
                            $r .= $b.self::escape($v, LDAP_ESCAPE_FILTER).$a;
                        }
                    } else if($cop=='%') {
                        $r .= (($cnot)?('(!('):('('))."{$fn}=*".str_replace('-', '*', tdz::slug($v, $cn::$queryAllowedChars, true))."*".(($cnot)?('))'):(')'));
                    } else {
                        $r .= (($not)?('(!('):('('))."{$fn}{$cop}".self::escape($v, LDAP_ESCAPE_FILTER).(($not)?('))'):(')'));
                    }
                }
                unset($cop, $cnot);
            }
            unset($k, $fn, $v);
        }

        if(!$r) $r='(ou=*)';
        else if(substr($r, 0, 1)!='(') $r = "($r)";

        return trim($r);
    }

    public function exec($q, $conn=null)
    {
        if(!$conn) {
            $conn = self::connect($this->schema('database'));
        }
        $select = array('*');
        $limit = -1;//($count || !$this->_limit)?(-1):((int)$this->_limit);
        $this->_last = ldap_list($conn, $this->schema('tableName'), $q, $select, 0, $limit, static::$timeout);
        return $this->_last;
    }

    public function run($q)
    {
        return $this->exec($q);
    }

    public static function runStatic($q, $n='')
    {
        $select = array('*');
        $limit = -1;
        return ldap_list(self::connect($n), $this->schema('tableName'), $q, $select, 0, $limit, static::$timeout);
    }

    public function query($q, $p=null)
    {
        try {
            $this->exec($q);
            if (is_null($p)) {
                return $this->fetchArray();
            } else {
                return $this->fetch(null, null, array_shift($arg));
            }
        } catch(Exception $e) {
            if(isset($this::$errorCallback) && $this::$errorCallback) {
                return call_user_func($this::$errorCallback, $e, func_get_args(), $this);
            }
            tdz::log('Error in '.__METHOD__." {$e->getCode()}:\n  ".$e->getMessage()."\n ".$this);
            return false;
        }
    }

    public function queryColumn($q, $i=0)
    {
        return $this->query($q, 1, $i);
    }


    public static function escape($str, $flags=null, $ignore='')
    {
        if(is_array($str)) {
            foreach($str as $k=>$v){
                $str[$k]=self::escape($v, $flags, $ignore);
                unset($k, $v);
            }
            return $str;
        }
        return ldap_escape($str, $ignore, $flags);
    }

    /**
     * Enables transactions for this connector
     * returns the transaction $id
     */
    // public function transaction($id=null, $conn=null) {}
    
    /**
     * Commits transactions opened by ::transaction
     * returns true if successful
     */
    // public function commit($id=null, $conn=null) {}

    /**
     * Rollback transactions opened by ::transaction
     * returns true if successful
     */
    // public function rollback($id=null, $conn=null) {}

    /**
     * Returns the last inserted ID from a insert call
     * returns true if successful
     */
    // public function lastInsertId($M=null, $conn=null) {}

    public function insert($M, $conn=null)
    {
        return null;
        $odata = $M->asArray('save', null, null, true);
        $data = array();

        $fs = $M::$schema['columns'];
        if(!$fs) $fs = array_flip(array_keys($odata));
        foreach($fs as $fn=>$fv) {
            if(!is_array($fv)) $fv=array('null'=>true);
            if(isset($fv['increment']) && $fv['increment']=='auto' && !isset($odata[$fn])) {
                continue;
            }
            if(!isset($odata[$fn]) && isset($fv['default']) &&  $M->getOriginal($fn, false, true)===false) {
                $odata[$fn] = $fv['default'];
            }
            if (!isset($odata[$fn]) && $fv['null']===false) {
                throw new Tecnodesign_Exception(array(tdz::t('%s should not be null.', 'exception'), $M::fieldLabel($fn)));
            } else if(array_key_exists($fn, $odata)) {
                $data[$fn] = self::sql($odata[$fn], $fv);
            } else if($M->getOriginal($fn, false, true)!==false && is_null($M->$fn)) {
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
        return null;
        $odata = $M->asArray('save', null, null, true);
        $data = array();

        $fs = $M::$schema['columns'];
        if(!$fs) $fs = array_flip(array_keys($odata));
        $sql = '';
        foreach($fs as $fn=>$fv) {
            $original=$M->getOriginal($fn, false, true);
            if(isset($fv['primary']) && $fv['primary']) {
                $pks[$fn] = tdz::sql(($original)?($original):($odata[$fn]));
                continue;
            }
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
        return null;
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

    //  public function create($tn=null, $conn=null) {}

    /**
     * Gets the timestampable last update
     */
    // public function timestamp($tns=null) {}
}


if (!function_exists('ldap_escape')) {
    define('LDAP_ESCAPE_FILTER', 0x01);
    define('LDAP_ESCAPE_DN',     0x02);

    /**
     * @param string $subject The subject string
     * @param string $ignore Set of characters to leave untouched
     * @param int $flags Any combination of LDAP_ESCAPE_* flags to indicate the
     *                   set(s) of characters to escape.
     * @return string
     */
    function ldap_escape($subject, $ignore = '', $flags = 0)
    {
        static $charMaps = array(
            LDAP_ESCAPE_FILTER => array('\\', '*', '(', ')', "\x00"),
            LDAP_ESCAPE_DN     => array('\\', ',', '=', '+', '<', '>', ';', '"', '#'),
        );

        // Pre-process the char maps on first call
        if (!isset($charMaps[0])) {
            $charMaps[0] = array();
            for ($i = 0; $i < 256; $i++) {
                $charMaps[0][chr($i)] = sprintf('\\%02x', $i);;
            }

            for ($i = 0, $l = count($charMaps[LDAP_ESCAPE_FILTER]); $i < $l; $i++) {
                $chr = $charMaps[LDAP_ESCAPE_FILTER][$i];
                unset($charMaps[LDAP_ESCAPE_FILTER][$i]);
                $charMaps[LDAP_ESCAPE_FILTER][$chr] = $charMaps[0][$chr];
            }

            for ($i = 0, $l = count($charMaps[LDAP_ESCAPE_DN]); $i < $l; $i++) {
                $chr = $charMaps[LDAP_ESCAPE_DN][$i];
                unset($charMaps[LDAP_ESCAPE_DN][$i]);
                $charMaps[LDAP_ESCAPE_DN][$chr] = $charMaps[0][$chr];
            }
        }

        // Create the base char map to escape
        $flags = (int)$flags;
        $charMap = array();
        if ($flags & LDAP_ESCAPE_FILTER) {
            $charMap += $charMaps[LDAP_ESCAPE_FILTER];
        }
        if ($flags & LDAP_ESCAPE_DN) {
            $charMap += $charMaps[LDAP_ESCAPE_DN];
        }
        if (!$charMap) {
            $charMap = $charMaps[0];
        }

        // Remove any chars to ignore from the list
        $ignore = (string)$ignore;
        for ($i = 0, $l = strlen($ignore); $i < $l; $i++) {
            unset($charMap[$ignore[$i]]);
        }

        // Do the main replacement
        $result = strtr($subject, $charMap);

        // Encode leading/trailing spaces if LDAP_ESCAPE_DN is passed
        if ($flags & LDAP_ESCAPE_DN) {
            if ($result[0] === ' ') {
                $result = '\\20' . substr($result, 1);
            }
            if ($result[strlen($result) - 1] === ' ') {
                $result = substr($result, 0, -1) . '\\20';
            }
        }

        return $result;
    }
}