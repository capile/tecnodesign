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
class Tecnodesign_Query_File
{
    const TYPE='file';
    public static 
        $options=array(
            'recursive'=>false,
            'index'=>false,
        ),
        $microseconds=6,
        $enableOffset=true,
        $connectionCallback,
        $errorCallback,
        $timeout=-1;
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
    protected static $schemas=array(), $conn=array();

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

    public static function connect($n='', $exception=true)
    {
        if(!isset(static::$conn[$n]) || !static::$conn[$n]) {
            try {
                $level = 'find';
                $db = Tecnodesign_Query::database($n);
                if(!$n && is_array($db)) $db = array_shift($db); 
                $db += array('options'=>static::$options);
                if(isset($db['dsn']) && preg_match('/^([^\:]+)\:(.+)$/', $db['dsn'], $m) && is_dir($d=TDZ_VAR.'/'.dirname($m[2]))) {
                    $db['dsn'] = $d.'/'.basename($m[2]);
                    $db['format'] = $m[1];
                } else {
                    throw new Tecnodesign_Exception('This database does not exist or is not accesible!');
                }
                $level = 'connect';
                if(!is_writable($d)) {
                    throw new Tecnodesign_Exception('This database is not writable!');
                }
                static::$conn[$n] = $db;
            } catch(Exception $e) {
                tdz::log('Could not '.$level.' to '.$n.":\n  {$e->getMessage()}", $db);
                tdz::log("[INFO] {$e}");
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
        if(isset(static::$conn[$n])) {
            unset(static::$conn[$n]);
        }
    }

    public function reset()
    {
        $this->_schema = null;
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
        $this->_select = $this->_where = $this->_limit = $this->_offset = $this->_last = null;
        $this->_from = $this->getFrom();
        $this->filter($options);
        return $this;
    }

    public function filter($options=array())
    {
        if(!$this->_schema) return $this;
        if(!is_array($options)) {
            $options = ($options)?(array('where'=>$options)):(array());
        }

        $this->_where = array();
        if(isset($options['where'])) {
            $this->where($options['where']);
        }
        return $this;
    }

    public function where($w=null)
    {
        if($w) {
            $this->_from = null;
            $this->_where = array();
            foreach($w as $k=>$f) {
                if(is_array($f) && count($f)>1) {
                    $f = '{'.implode(',',static::escape($f)).'}';
                } else if(is_array($f)) {
                    $f = static::escape(implode('', $f));
                } else {
                    $f = static::escape($f);
                }
                if($p=$this->schema($k.'Pattern')) {
                    $this->_from = str_replace(array('*.*', '*'), sprintf($p, $f), $this->getFrom());
                }
                $this->_where[$k]=$f;
            }
        }
        return $this->_where;
    }

    public function getFrom()
    {
        if($this->_from) return $this->_from;
        else return $this->schema('tableName').'*';
    }

    public function buildQuery($count=false)
    {
        $src = $this->connect($this->schema('database'));
        $pattern = $src['dsn'];
        if(strpos($pattern, '*')===false) {
            if(substr($pattern, -1)!='/') $pattern = '/*';
            else $pattern .= '*';
        }

        if($tn=$this->getFrom()) {
            $pattern = str_replace(array('*.*','*'), $tn, $pattern);
        }
        //any more filters?
        $this->_last = glob($pattern, GLOB_BRACE);

        // where applied?
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
        $r = array();
        if(!$this->_last) {
            return $r;
        }
        $prop = array('_new'=>false);
        if($this->_scope) $prop['_scope'] = $this->_scope;
        $this->_offset = $o;
        $this->_limit = $l;

        $i0 = (int) $this->_offset;
        $i1 = ($this->_limit)?($i0 + (int)$this->_limit):(0);

        if($i0 || $i1) {
            $res = array_slice($this->_last, $i0, $i1);
        } else {
            $res = $this->_last;
        }

        if($res) {
            $db=self::connect($this->schema('database'));
            $cn=$this->schema('className');
            $parser = 'yaml';
            if(isset($db['format']) && $db['format']!=$parser && $db['format']!='file') $parser = $db['format'];
            foreach($res as $i=>$f) {
                if($parser = 'yaml') {
                    $prop['uid'] = basename($f);
                    $prop['src'] = $f;
                    $d = Tecnodesign_Yaml::load($f);
                } else {
                    $d = tdz::unserialize(file_get_contents($f), $parser);
                }
                if($d) {
                    if(isset($db['root']) && $db['root']) {
                        if(isset($d[$db['root']])) $d = $d[$db['root']];
                        else continue;
                    }
                    $r[] = ($cn)?(new $cn($prop+$d)):($d);
                }
            }
        }

        return $r;
    }

    public function fetchArray($o=null, $l=null)
    {
        return $this->fetch($o, $l, false);
    }

    public function count()
    {
        if(!$this->_schema) return false;
        if(!$this->_last) {
            $this->buildQuery();
        }
        return count($this->_last);
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


    public static function escape($str)
    {
        if(is_array($str)) {
            foreach($str as $k=>$v){
                $str[$k]=self::escape($v);
                unset($k, $v);
            }
            return $str;
        }
        return preg_replace('/[^a-zA-Z0-9 \-_]+/', '', $str);
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