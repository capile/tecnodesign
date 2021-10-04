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
    const TYPE='file', DRIVER='file';
    public static 
        $options=array(
            'create'=>true,
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
        $_conn,
        $_scope,
        $_from, 
        $_where, 
        $_limit, 
        $_offset, 
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
                if(isset($db['options'])) $db['options'] += static::$options;
                else $db['options'] = static::$options;
                if(isset($db['dsn']) && preg_match('/^([^\:]+)\:(.+)$/', $db['dsn'], $m) && is_dir($d=S_VAR.'/'.dirname($m[2]))) {
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
        $this->_conn = null;
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
            $this->_where = (is_array($w)) ?array_values($w) :[$w];
        }
        return $this->_where;
    }

    public function getFrom()
    {
        if($this->_from) return $this->_from;
        else return $this->schema('tableName');
    }

    public function buildQuery($count=false)
    {
        $src = ($this->_conn) ?$this->_conn :$this->connect($this->schema('database'));
        $pattern = $src['dsn'];
        if(strpos($pattern, '*')===false) {
            if(substr($pattern, -1)!='/') $pattern .= '/*';
            else $pattern .= '*';
        }
        $ext = (isset($src['options']['extension'])) ?'.'.$src['options']['extension'] :null;
        if($ext) $pattern .= $ext;

        $r = [];
        if($tn=$this->getFrom()) {
            $pattern = str_replace(array('*.*','*'), $tn, $pattern);
            if(strpos($pattern, '*')===false) {
                $r[] = $pattern;
            }
        }

        if(!$r) {
            $r = glob($pattern, GLOB_BRACE);
        }

        $recursive = (isset($src['options']['recursive']) && $src['options']['recursive']);
        $create = (isset($src['options']['create']) && $src['options']['create']);
        $this->_last = null;
        if($r) {
            $this->_last = [];
            while($f=array_shift($r)) {
                if($this->_where && !in_array(basename($f, $ext), $this->_where)) {
                    continue;
                }
                if(is_dir($f)) {
                    if($recursive && ($d = glob($f.'/*'))) {
                        $r = array_merge($d, $r);
                    }
                } else if($create || file_exists($f)) {
                    $this->_last[] = $f;
                }
            }
        }

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
        $prop0 = [];
        if($this->_scope) $prop0['_scope'] = $this->_scope;
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
            $db = ($this->_conn) ?$this->_conn :$this->connect($this->schema('database'));
            $cn=$this->schema('className');
            $parser0 = (isset($db['format']) && $db['format'] && $db['format']!='file') ?$db['format'] :'yaml';
            $create = (isset($db['options']['create']) && $db['options']['create']);
            $ext = (isset($db['options']['extension'])) ?'.'.$db['options']['extension'] :null;

            foreach($res as $i=>$f) {

                $prop = $prop0;
                $prop['__uid'] = basename($f, $ext);
                $prop['__src'] = $f;
                if(preg_match('/\.(ya?ml|js(on)?)$/', $f, $m)) {
                    $parser = (substr($m[1], 0, 1)=='y') ?'yaml' :'json';
                } else {
                    $parser = $parser0;
                }
                $prop['__serialize'] = $parser;
                if(!file_exists($f) && $create) {
                    $prop['_new'] = true;
                    $d = [];
                } else {
                    $prop['_new'] = false;

                    if($parser == 'yaml') {
                        $d = Tecnodesign_Yaml::load($f);
                    } else {
                        $d = tdz::unserialize(file_get_contents($f), $parser);
                    }
                    if($d) {

                        if(isset($db['options']['root']) && $db['options']['root']) {
                            if(isset($d[$db['options']['root']])) $d = $d[$db['options']['root']];
                            else continue;
                        }
                    }
                }
                $r[] = ($cn)?(new $cn($prop+$d)):($d);
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
        if(is_string($o)) $this->_scope = $o;
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
        if($conn) {
            if(is_array($conn) && isset($conn['dsn'])) $this->_conn = $conn;
            else $this->_conn = $this->connect($conn);
        }
        $this->buildQuery();
        return $this->_last;
    }

    public function run($q)
    {
        return $this->exec($q);
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

    public function update($M, $conn=null)
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
            if(!isset($odata[$fn]) && isset($fv['default']) &&  $M->getOriginal($fn, false, true)===false) {
                $odata[$fn] = $fv['default'];
            }
            if (!isset($odata[$fn]) && $fv['null']===false) {
                throw new Tecnodesign_Exception(array(tdz::t('%s should not be null.', 'exception'), $M::fieldLabel($fn)));
            } else if(array_key_exists($fn, $odata)) {
                $data[$fn] = $odata[$fn];
            } else if($M->getOriginal($fn, false, true)!==false && is_null($M->$fn)) {
                $data[$fn] = null;
            }
            unset($fs[$fn], $fn, $fv);
        }

        if($conn) {
            if(is_array($conn) && isset($conn['dsn'])) $this->_conn = $conn;
            else $this->_conn = $this->connect($conn);
        }
        $db = ($this->_conn) ?$this->_conn :$this->connect($this->schema('database'));
        if(isset($db['options']['root']) && $db['options']['root']) {
            $data = [$db['options']['root']=>$data];
        }

        // serialize and save
        $r = null;
        if(isset($M->__src) && $M->__src) {
            if(!($r=tdz::save($M->__src, tdz::serialize($data, $M->__serialize)))) {
                throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $M::label()));
            }
        }
        return $r;
    }

    public function insert($M, $conn=null)
    {
        return $this->update($M, $conn);
    }

    public function delete($M, $conn=null)
    {
        $r = null;
        if(isset($M->__src) && $M->__src) {
            if(!($r=@unlink($M->__src))) {
                throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $M::label()));
            }
        }
        return $r;
    }

    //  public function create($tn=null, $conn=null) {}

    /**
     * Gets the timestampable last update
     */
    // public function timestamp($tns=null) {}
}
