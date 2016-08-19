<?php
/**
 * Tecnodesign Model
 *
 * Basic and simple ORM based on PDO methods only
 *
 * PHP version 5.3
 *
 * @category  Model
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: Model.php 1305 2014-02-12 12:51:36Z capile $
 * @link      http://tecnodz.com/
 */

/**
 * Tecnodesign Model
 *
 * Basic and simple ORM based on PDO methods only
 *
 * @category  Model
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */
class Tecnodesign_Model implements ArrayAccess, Iterator, Countable
{
    public static $schema = array(
        'database'=>null,
        'tableName'=>null,
        'columns'=>array(
        ),
        'relations'=>array(
        ),
        'scope'=>array(
        ),
        'events'=>array(
        ),
    );
    public static $allowNewProperties = false;
    public static $keepCollection = false, $microsecondsLength=3, $transaction=true;
    protected static $found=array();
    protected static $relations=null, $relationDepth=1;
    protected static $_conn=null;
    protected $_new = false;
    protected $_delete = null;
    protected $_connected = false;
    protected $_original = array();
    protected $_lastQuery=null;
    protected $_p = 0;
    protected $_forms=null;
    private $_collection=null;
    // for MSSQL-based objects
    protected $rowstat=null;
    protected $ROWSTAT=null;
    public static 
        $boxTemplate     = '<div$ATTR>$INPUT</div>',
        $headingTemplate = '<hr /><h3>$LABEL</h3>',
        $previewTemplate = '<dl><dt>$LABEL</dt><dd>$INPUT</dd></dl>';

    
    /**
     * Class constructor: you can create a new instance based on an associative array with the values
     */
    public function __construct($vars=array(), $insert=null, $save=null)
    {
        $checkNew = false;
        if (is_array($vars)) {
            foreach($vars as $k=>$v) {
                $checkNew = true;
                $this->__set($k, $v);
                unset($k, $v);
            }
        }
        if(!is_null($insert)) {
            $this->isNew($insert);
            if($insert && is_null($save)) {
                $save = true;
            }
        } else {
            $this->_new = null;
        }
        $this->initialize();
        if(!is_null($save) && $save) {
            $this->save();
        }
    }

    public function __destruct()
    {
        if(!is_null($this->relations) && $this->relations) {
            foreach($this->relations as $rid=>$r) {
                unset($this->relations[$rid], $r, $rid);
            }
        }
        unset($this->_collection, $this->_forms);
    }

    /**
     * Gets the timestampable last update
     */
    public static function timestamp($tns=null)
    {
        return Tecnodesign_Query::handler(get_called_class())->timestamp($tns);
    }
    
    public function __sleep()
    {
        if(!is_null($this->_collection)) {
            $this->_collection=null;
        }
        $ret = get_object_vars($this);
        $not = array('_forms', '_collection', '_delete', '_connected', '_original', '_lastQuery', '_p', 'rowstat', 'ROWSTAT');
        foreach($not as $r) unset($ret[$r]);
        return array_keys($ret);
        //$ret=array_merge(array('_new'), array_keys(static::$schema['columns']), array_keys(static::$schema['relations']));
        //if(isset(static::$schema['serialize'])) $ret=array_merge($ret, static::$schema['serialize']);
        return $ret;
    }

    public function __wakeup()
    {
        foreach($this->asArray() as $k=>$v) {
            if(!array_key_exists($k, $this->_original)) {
                $this->_original[$k]=$v;
            }
        }
    }
    
    
    public function initialize()
    {
        if($this->_connected) return false;
        $this->_connected = true;
        /*if(is_null($this->_new) || !$this->_new) {
            $this->_original += $this->asArray();
        }
        */
    }
        
    /**
     * Schema loader & builder
     *
     * loads the schema if it is a Tecnodesign_Model object, or builds the schema and stores it in cache, if it is from another class
     * Syntax: tdz::schema($cn=null, $base=array())
     *
     * return array $schema array
     */
    public static function schema($cn=null, $base=array())
    {
        if(is_null($cn) || !class_exists($cn)) {
            $cn = get_called_class();
        }
        $schema=$base;
        if(isset($cn::$schema)) {
            $schema = $cn::$schema;
        } else if(!($schema=Tecnodesign_Cache::get('schema/'.$cn, tdz::$timeout, false))) {
            $schema = $base;
            if(!isset($schema['tableName']) || $schema['tableName']=='') {
                $schema['tableName'] = tdz::uncamelize(lcfirst($cn));
            }
            $dbold = tdz::$database;
            @list($db) = array_keys($dbold);
            if(!isset($schema['database']) || $schema['database']=='') {
                $schema['database']=$db;
            } else if($db!=$schema['database']) {
                // set default database for tdz::query
                $db = $schema['database'];
                $databases = array($db=>$dbold[$db]);
                $databases += $dbold;
                tdz::$database = $databases;
            }
            $tn = $schema['tableName'];
            $dbtype = preg_replace('/\:.*/', '', $dbold[$db]['dsn']);
            $scn = 'Tecnodesign_Model_'.ucfirst($dbtype);
            if(!class_exists($scn)) {
                tdz::log('Don\'t know how to update this schema, sorry...');
            } else {
                $schema = $scn::updateSchema($schema);
                Tecnodesign_Cache::set('schema/'.$cn, $schema, 0, false);
            }
        }
        return $schema;
    }

    public static function pk($schema=null)
    {
        $update=false;
        if(!$schema) {
            $schema = static::$schema;
            $update = true;
        }
        if(isset($schema['scope']['uid'])) {
            return $schema['scope']['uid'];
        }
        $pks=array();
        foreach($schema['columns'] as $fn=>$fd) {
            if (isset($fd['increment']) && $fd['increment']=='auto') {
                $pks = $fn;
            } else if(isset($fd['primary']) && $fd['primary']) {
                if(!is_array($pks)) $pks=array($pks);
                $pks[]=$fn;
            }
        }
        if($update) static::$schema['scope']['uid']=$pks;
        return $pks;
    }
    
    public function getPk()
    {
        $cn = get_class($this);
        $scope = $cn::pk();
        if(!is_array($scope) || count($scope)==1) {
            if(is_array($scope)) {
                $scope = array_shift($scope);
            }
            if($p=strrpos($scope, ' ')) $scope = substr($scope, $p+1);
            unset($p);
            return $this->$scope;
        } else {
            $result = array();
            foreach($scope as $fn) {
                $result[]=$this->$fn;
            }
            return implode('-', $result);
        }
    }
    
    public static function formFields($scope=false, $allowText=false)
    {
        $cn = get_called_class();
        if(!isset(static::$schema['form'])) {
            $fk=array();
            if(isset(static::$schema['relations'])) {
                foreach(static::$schema['relations'] as $rn=>$rel) {
                    if($rel['type']=='one') {
                        $fk[$rel['local']]=$rn;
                    }
                }
            }
            foreach(static::$schema['columns'] as $fn=>$fd) {
                if (isset($fd['increment'])) {
                    continue;
                }
                static::$schema['form'][$fn]=array('bind'=>$fn);
                if (isset($fk[$fn])) {
                    static::$schema['form'][$fn]['type']='select';
                    static::$schema['form'][$fn]['choices']=$fk[$fn];
                }
            }
        }
        $fo = static::$schema['form'];
        if($scope) {
            if(!is_array($scope)) $scope = static::columns($scope);
            $sfo = array();
            foreach($scope as $label=>$fn) {
                $fd = array();
                if(is_array($fn)) {
                    $fd = $fn;
                    if(isset($fd['bind'])) $fn=$fd['bind'];
                    else $fn=$label;
                }
                if(preg_match('/^`?([a-z0-9\_\.]+)`?( +as)? ([a-z0-9\_]+)$/i', $fn, $m)) {
                    $fn = $m[3];
                    if(!isset(static::$schema['form'][$fn])) {
                        $col = static::column($m[1], true);
                        if($col) {
                            static::$schema['form'][$fn] = $col;
                            if(isset(static::$schema['form'][$fn]['bind'])) static::$schema['form'][$fn]['bind'] = $fn; // or $m[1]?
                        }
                        unset($col);
                    }
                }
                if(isset(static::$schema['form'][$fn])) {
                    $fd+=static::$schema['form'][$fn];
                    if (!is_int($label)) {
                        $fd['label']=$label;
                    }
                    $sfo[$fn]=$fd;
                } else if($fd && isset($fd['type'])) {
                    $id = (isset($fd['id']))?($fd['id']):($label);
                    $sfo[$id] = $fd;
                    unset($id);
                } else if($allowText) {
                    $sfo[] = $fn;
                }
                unset($label, $fn, $fd);
            }
            $fo = $sfo;
        }
        return $fo;
    }
    
    public static function columns($scope='default', $type=null, $expand=3, $clean=false)
    {
        if(!$scope) $scope = 'default';
        if(!is_array($scope)) {
            if($scope=='uid') {
                $scope = static::pk();
                if(!is_array($scope)) $scope = array($scope);
            } else {
                if (!isset(static::$schema['scope'][$scope])) {
                    $labels = array();
                    foreach(static::$schema['columns'] as $fn=>$fd) {
                        if (!isset($fd['increment'])) {
                            $labels[static::fieldLabel($fn)] = $fn;
                        }
                        unset($fn, $fd);
                    }
                    static::$schema['scope'][$scope] = $labels;
                    unset($labels);
                }
                $scope = static::$schema['scope'][$scope];
            }
        }
        if($type && $scope) {
            if(!is_array($type)) $type=array($type);
            foreach($scope as $k=>$fn) {
                $fd = array();
                if(is_array($fn)) {
                    $fd = $fn;
                    if(isset($fn['bind'])) {
                        $fn=$fn['bind'];
                    } else if(!isset($fd['type']) || !in_array($fd['type'],$type)) {
                        unset($scope[$k]);
                        continue;
                    }
                }
                if(strpos($fn, ' ')) $fn = preg_replace('/\s+(as\s+)?[a-z0-9\_]+$/i', '', $fn);
                if(!isset(static::$schema['columns'][$fn]) || !in_array(static::$schema['columns'][$fn]['type'], $type)) {
                    $rfd = static::column($fn);
                    if($rfd) $fd += $rfd;
                    unset($rfd);
                    if(!$fd || !in_array($fd['type'],$type)) {
                        unset($scope[$k]);
                    }
                }
                unset($k, $fn, $fd);
            }
        }
        if($expand && $scope) {
            $expand--;
            $r = array();
            if(!is_array($scope)) $scope = array($scope);
            foreach($scope as $k=>$fn) {
                if(is_array($fn)) {
                    if(isset($fn['bind'])) {
                        $fd = $fn;
                        $fn=$fn['bind'];
                    } else {
                        unset($scope[$k], $fn, $k, $fd);
                        continue;
                    }
                }
                if(preg_match('/^([a-z0-9\-\_]+)::([a-z0-9\-\_\,]+)$/i', $fn, $m)) {
                    if($m[1]=='scope') {
                        $r = array_merge($r, static::columns($m[2], $type, $expand));
                    }
                } else {
                    $r[$k] = (isset($fd))?($fd):($fn);
                }
                unset($scope[$k], $fn, $k, $fd);
            }
            $scope = $r;
            unset($r);
        }
        if($clean) {
            foreach($scope as $i=>$fn) {
                if(is_array($fn) || (substr($fn, 0, 2)=='--' && substr($fn, -2)=='--')) {
                    unset($scope[$i]);
                }
            }
        }
        return $scope;
    }

    public static function column($s, $applyForm=false, $relation=false)
    {
        $cn = get_called_class();
        while(strpos($s, '.')!==false) {
            list($rn,$s)=explode('.', $s, 2);
            if(isset($cn::$schema['relations'][$rn])) {
                $cn = (isset($cn::$schema['relations'][$rn]['className']))?($cn::$schema['relations'][$rn]['className']):($rn);
            } else {
                return false;
            }
        }
        if(strpos($s, ' ')) $s = substr($s, 0, strpos($s, ' '));
        $d=false;
        if(isset($cn::$schema['columns'][$s])) {
            $d = $cn::$schema['columns'][$s];
        } else if($relation && isset($cn::$schema['relations'][$s])) {
            $d = $cn::$schema['relations'][$s];
            if(!isset($d['className'])) $d['className']=$s;
        }
        if($d && $applyForm && isset($cn::$schema['form'][$s])) {
            $d = array_merge($d, $cn::$schema['form'][$s]);
        }
        return $d;
    }
    
    /**
     * Tecnodesign_Form renderer
     * 
     * @param string $scope the self::$schema scope to select which fields should be available.
     *
     * @return Tecnodesign_Form instance
     */
    public function getForm($scope=null, $pk=false)
    {
        if(is_null($scope)) {
            $scope = 0;
        }
        if (is_null($this->_forms)) {
            $this->_forms=array();
        }
        $hash = (string)(is_array($scope))?(md5(implode(',',array_keys($scope)))):($scope);
        if (!isset($this->_forms[$hash]) || !($F=Tecnodesign_Form::getInstance($this->_forms[$hash]))) {
            $cn = get_called_class();
            $fo = array('fields'=>static::formFields($scope, true), 'model'=>$this);
            if($pk) {
                $pk = static::pk();
                if(is_array($pk)) {
                    foreach($pk as $k) {
                        if(!isset($fo['fields'][$k])) $fo['fields'][$k] = array('type'=>'hidden','bind'=>$k,'disabled'=>true,'required'=>false);
                    }
                } else {
                    if(!isset($fo['fields'][$pk])) $fo['fields'][$pk] = array('type'=>'hidden','bind'=>$pk,'disabled'=>true,'required'=>false);
                }
            }
            $F = new Tecnodesign_Form($fo);
            unset($fo);
            $this->_forms[$hash] = $F->uid;
        }
        return $F;
        
    }
    

    public function lastQuery()
    {
        return $this->_lastQuery;
    }
    
    
    /**
     * Model schema auto-updates
     * 
     * This method updates the schema definitions ot the Models indicated by $f
     * 
     * @param string $f file name to update
     *
     * @return bool  whether the class was updated.
     */
    public function updateSchema($f=null)
    {
        if(is_null($f) || !file_exists($f)) {
            return false;
        }
        $schema = $this->schema();
        
        $classCode = file_get_contents($f);
        $start = strpos($classCode, '//--tdz-schema-start--');
        $end = strpos($classCode, '//--tdz-schema-end--');
        if ($start===false || $end===false) {
            return false;
        }
        if(!isset($schema['tableName']) || $schema['tableName']=='') {
            $schema['tableName'] = tdz::uncamelize(get_class($this));
        }
        $app = tdz::getApp();
        if($app) {
            $dbold = tdz::$database;
            if(!isset($schema['database']) || $schema['database']=='') {
                $db = array_keys($dbold);
                $db=$db[0];
                $schema['database']=$db;
            } else {
                $db = $schema['database'];
            }
            // set default database for tdz::query
            $databases = array($db=>$dbold[$db]);
            $databases += $dbold;
            tdz::$database = $databases;
            $dbo = $databases[$db];
            unset($databases);
        } else {
            $schema['database'] = Tecnodesign_Database::$database;
            $dbo = Tecnodesign_Database::$dbo;
        }
        $tn = $schema['tableName'];
        $dbtype = preg_replace('/\:.*/', '', $dbo['dsn']);
        $scn = 'Tecnodesign_Model_'.ucfirst($dbtype);
        if(!class_exists($scn)) {
            tdz::debug('Don\'t know how to update this schema, sorry...');
        }
        $code = $scn::updateSchema($schema, $this);
        $code = str_replace("\n", "\n    ", $code);
        $code = substr($classCode, 0, $start).$code.substr($classCode, $end);
        return tdz::save($f, $code);
    }
    
    /**
     * Events are triggers to be run once each action is done
     * 
     * @param string $e event name, can be (before|after)-(save|insert|update|select)
     * 
     * @return bool 
     */
    public function runEvent($e, $conn=null)
    {
        if(isset(static::$schema['events'][$e])) {
            $eo = (!is_array(static::$schema['events'][$e]))?(array(static::$schema['events'][$e])):(static::$schema['events'][$e]);
            try {
                foreach($eo as $i=>$fn) {
                    $result=true;
                    if(method_exists($this, $fn)) {
                        $result = $this->$fn($e, $conn);
                    } else if(is_callable($fn)) {
                        $result=false;
                        if(function_exists($fn)) {
                            $result=$fn($this, $e, $conn);
                        } else {
                            @list($fo, $m) = explode('::', $fn,2);
                            if(method_exists($fo, $m)) $result = $fo::$m($this,$e,$conn);
                            unset($fo, $m);
                        }
                        if($result!==false) $result=true;
                    } else {
                        $result = eval($fn);
                    }
                    if (!$result) {
                        throw new Exception("{$fn} ({$e}) failed on [".static::$schema['tableName'].']');
                    }
                    unset($eo[$i], $i, $fn);
                }
            } catch (Exception $e) {
                tdz::log(__METHOD__.', '.$e->getLine().': '.get_class($this)."::{$fn}\n".$e->getMessage());
                return false;
            }
        }
        return true;
    }
    
    /**
     * Auto-increment Behavior
     */
    protected static $increment=array();
    public function autoIncrementTrigger($fields, $conn=null)
    {
        $cn = get_class($this);
        $schema = $cn::$schema;
        $scope = $cn::pk();
        foreach($fields as $fn) {
            $sfn=$fn;
            $w=array();
            if (is_array($scope)) {
                foreach($scope as $sn) {
                    if ($sn!=$fn) {
                        $w[$sn.'-'.$this->$sn]="{$sn}=".tdz::sqlEscape($this->$sn);
                    } else break;
                }
            }
            if(count($w)>0) {
                $sfn .= ':'.implode(',',array_keys($w));
            }
            if(isset($cn::$increment[$sfn])) {
                $cn::$increment[$sfn]++;
            } else {
                $cn::$increment[$sfn]=1;
                if(!$conn) {
                    if(!is_null($cn::$_conn)) $conn=$cn::$_conn;
                    else $conn = tdz::connect($schema['database']);
                }
                $driver = @$conn->getAttribute(PDO::ATTR_DRIVER_NAME);
                if(count($w)==0 && $driver=='dblib') {
                    $sql = "select ident_current('{$schema['tableName']}') as next";
                } else {
                    $ifnull = ($driver=='dblib')?('isnull'):('ifnull');
                    $sql = "select {$ifnull}(max({$fn}),0)+1 as next from {$schema['tableName']}";
                    if(count($w)>0) {
                        $sql .= ' where '.implode(' and ', $w);
                    }
                }
                $next = tdz::query($sql);
                if($next) {
                    $cn::$increment[$sfn]=$next[0]['next'];
                }
            }
            $this[$fn]=$cn::$increment[$sfn];
        }
        return true;
    }
    
    /**
     * Versionable Behavior
     */
    public function versionableTrigger($fields, $conn=null)
    {
        $schema = $this->schema();
        $vtn = (isset($schema['versionTableName']))?($schema['versionTableName']):($schema['tableName'].'_version');
        $fns = implode(',', array_keys($schema['columns']));
        $cn = get_class($this);
        $scope = $cn::pk();
        $w=array();
        if(!is_array($scope)) {
            $scope = array($scope);
        }
        foreach($scope as $fn) {
            $w[]="{$fn}=".tdz::sqlEscape($this->$fn);
        }
        $w = implode(' and ', $w);
        if(is_array($fields)) {
            $fields = array_shift($fields);
        }
        $version = (int) $this->$fields;
        $version++;
        $sqls = array(
            "update {$schema['tableName']} set {$fields}={$version} where {$w}",
            "replace into {$vtn} ({$fns}) select {$fns} from {$schema['tableName']} where {$w}",
        );
        $this->$fields=$version;
        if(!$conn) {
            $conn = tdz::connect($schema['database']);
        }
        foreach($sqls as $sql) {
            $conn->exec($sql);
        }
        return true;
    }

    /**
     * Timestampable Behavior
     */
    public function timestampableTrigger($fields, $conn=null)
    {
        list($u, $t) = explode(' ', (string) microtime());
        $tstamp = date('Y-m-d H:i:s', $t).substr($u,1,6);
        unset($t, $u);
        foreach($fields as $fn) {
            if(!isset($this->_original[$fn])) $this->_original[$fn]=$this->$fn;
            $this->$fn = $tstamp;
        }
        return true;
    }

    /**
     * Make New Behavior
     * 
     * Makes all updates a new entry
     */
    public function insertableTrigger($fields, $conn=null)
    {
        $this->_new = true;
        return true;
    }

    /**
     * Sortable Behavior
     */
    public function sortableTrigger($fields, $conn=null)
    {
        $cn = get_class($this);
        $schema = $cn::$schema;
        $scope = (isset($schema['scope']['sortable']))?($schema['scope']['sortable']):(array());
        $w = $ik = array();
        $fn='';
        foreach($scope as $sn) {
            if ($sn!=$fn) {
                $ik[]=$this->$sn;
                $w[]="{$sn}=".tdz::sqlEscape($this->$sn);
                $fn=$sn;
            }
        }
        $ik = implode('-', $ik);
        foreach($fields as $fn) {
            if ($this->$fn) {
                continue;
            }
            if(isset($cn::$increment[$fn.'-'.$ik])) {
                $cn::$increment[$fn.'-'.$ik]++;
            } else {
                $sql = "select ifnull(max({$fn}),0)+1 as next from {$schema['tableName']}";
                if(count($w)>0) {
                    $sql .= ' where '.implode(' and ', $w);
                }
                $next = tdz::query($sql); 
                if($next) {
                    $cn::$increment[$fn.'-'.$ik]=$next[0]['next'];
                }
            }
            if(!isset($this->_original[$fn])) $this->_original[$fn]=$this->$fn;
            $this->$fn=$cn::$increment[$fn.'-'.$ik];
            //tdz::log('sortable trigger!!!!', $this->$fn, $sql);
        }
        return true;
    }
    
    /**
     * Soft Delete Behavior
     */
    public function softDeleteTrigger($fields, $conn=null)
    {
        if($this->_delete) {
            $tstamp = date('Y-m-d H:i:s');
            if($fields && !is_array($fields)) {
                $fields = array($fields);
            }
            foreach($fields as $fn) {
                if(!isset($this->_original[$fn])) $this->_original[$fn]=$this->$fn;
                $this->$fn = $tstamp;
                $this->_delete = false;
            }
        }
        return true;
    }

    /**
     * Implementing behaviors
     * 
     * @return type 
     */
    public function actAs($e, $conn=null)
    {
        if(!isset(static::$schema['actAs'][$e])) {
            return false;
        }
        $behavior = static::$schema['actAs'][$e];
        foreach($behavior as $bn=>$fields) {
            $m = tdz::camelize($bn).'Trigger';
            if(method_exists($this, $m)) {
                if($this->$m($fields, $conn)===false) {
                    return false;
                    break;
                }
            }
            unset($behavior[$bn], $bn, $fields, $m);
        }
        unset($behavior);
        return true;
    }
    
    public function isNew($new=null)
    {
        if (!is_null($new)) {
            $this->_new = $new;
            if($new) {
                $this->_original=array();
            }
        }
        if(is_null($this->_new)) {
            $schema = $this->schema();
            $pks=array();
            foreach($schema['columns'] as $fn=>$fv) {
                if(isset($fv['primary']) && $fv['primary']) {
                    $pks[$fn]=(!is_null($this->$fn));
                }
            }
            $hasPk=true;
            foreach ($pks as $pk) {
                if(!$pk) {
                    $hasPk = false;
                    break;
                }
            }
            $this->_new = !$hasPk;
        }
        return $this->_new;
    }

    public function isDeleted($delete=null)
    {
        if (!is_null($delete)) {
            $this->_delete = $delete;
        }
        return $this->_delete;
    }
    
    public function asArray($scope=null, $keyFormat=null, $valueFormat=null)
    {
        $schema = $this->schema();
        $result = array();
        if (!is_null($scope) && (is_array($scope) || isset($schema['scope'][$scope]))) {
            if(!is_array($scope)) $scope = $schema['scope'][$scope];
            foreach($scope as $fn=>$fv) {
                if(strpos($fv, ' ')) {
                    $fv = trim(substr($fv, strrpos($fv, ' ')+1));
                }
                if(!is_null($this->$fv)) {
                    $result[($keyFormat)?(sprintf($keyFormat, $fn)):($fn)] = ($valueFormat)?(sprintf($valueFormat, $this->$fv)):($this->$fv);
                }
                unset($fn, $fv);
            }
        } else if(isset($schema['columns'])) {
            foreach ($schema['columns'] as $fn=>$fv) {
                if (!is_null($this->$fn)) {
                    $result[($keyFormat)?(sprintf($keyFormat, $fn)):($fn)] = ($valueFormat)?(sprintf($valueFormat, $this->$fn)):($this->$fn);
                }
                unset($fn, $fv);
            }
        }
        return $result;
    }
    
    public static function relate($r, &$f=null, $addRef=false, $rp='')
    {
        $cn = get_called_class();
        if($p=strpos($r, '.')) {
            $rn = substr($r, 0, $p);
            $r = substr($r, $p+1);
        } else {
            $rn = $r;
            $r='';
        }
        if(!isset($cn::$schema['relations'][$rn])) return false;
        $ro = $cn::$schema['relations'][$rn];
        $rcn = (isset($ro['className']))?($ro['className']):($rn);
        if($f) {

            if(!is_array($f)) {
                $pk = $cn::pk();
                if(is_array($pk)) {
                    $fe = preg_split('/[\-\,\/]/', $f);
                    $f = array();
                    while(isset($pk[0])) {
                        $f[array_shift($pk)]=(isset($fe[0]))?(array_shift($fe)):('');
                    }
                } else {
                    $f = array($pk=>$f);
                }
            }

            foreach($rcn::$schema['relations'] as $rrn=>$rr) {
                if(isset($rr['className'])) {
                    if($rr['className']==$cn && $rr['foreign']==$ro['local'] && $rr['local']==$ro['foreign']) break;
                } else if($rrn==$cn && $rr['foreign']==$ro['local'] && $rr['local']==$ro['foreign']) break;

                unset($rrn, $rr);
            }
            if(!isset($rrn)) {
                $i=0;
                while(isset($rcn::$schema['relations']['r'.$i])) {
                    $i++;
                    if($i>20) return false;
                }
                $rrn = 'r'.$i;
                $rcn::$schema['relations'][$rrn] = array('className'=>$cn, 'foreign'=>$ro['local'], 'local'=>$ro['foreign'], 'type'=>($ro['type']=='one')?('many'):('one'));
            } else {
                unset($rr);
            }

            $fc = (array) $f;
            foreach($fc as $k=>$v) {
                unset($f[$k], $fc[$k]);
                $f[self::_rn($k, $rrn, $rp)]=$v;
                unset($k, $v);
            }

            $rp .= ($rp)?('.'.$rrn):($rrn);
            unset($rrn);
        }

        unset($ro);
        if($r) {
            return $rcn::relate($r, $f, false, $rp);
        }
        return $rcn;
    }

    private static function _rn($k, $rrn, $rp)
    {
        if(preg_match_all('#`([^`]+)`#', $k, $m)) {
            $r = $s = array();
            foreach($m[1] as $i=>$nfn) {
                $s[]=$m[0][$i];
                $r[]='`'.self::_rn($nfn, $rrn, $rp).'`';
            }
            return str_replace($s, $r, $k);
        }
        $s=$rp.'.'.$rrn.'.';
        if(strpos($k, '.')) {
            if(substr($k, 0, strlen($s))==$s) {
                $k = substr($k, strlen($s));
            }
        }
        return $rrn.'.'.$k;
    }
    
    public function getRelation($relation, $fn=null, $scope=null, $asCollection=true)
    {
        $cn = get_called_class();
        $schema = $cn::$schema;
        if(is_null($fn) && ($p=strpos($relation, '.'))) {
            $fn = substr($relation, $p+1);
            $relation = substr($relation, 0, $p);
            unset($p);
        }
        if (!isset($schema['relations'][$relation])) {
            throw new Tecnodesign_Exception(array(tdz::t('Relation "%s" is not available at %s.','exception'), $relation, $cn));
        }
        $rel = $schema['relations'][$relation];
        $rcn = (isset($rel['className']))?($rel['className']):($relation);
        if (!class_exists($rcn)) {
            throw new Tecnodesign_Exception(array(tdz::t('Class "%s" does not exist.','exception'), $rcn));
        }
        $limit = (int)($rel['type']=='one');
        $local = $rel['local'];
        if(is_array($local)) {
            $rk = $this->getPk();
            foreach($local as $l) {
                $rk .= '/'.$this->$l;
            }
        } else {
            $rk = $this->getPk().'/'.$this->$local;
        }
        if(is_null($cn::$relations)) {
            $cn::$relations = array();
        }
        if(!isset($cn::$relations[$relation][$rk])) {
            $search=array();
            if(isset($rel['params'])) {
                $search = $rel['params'];
            }
            if(is_array($local)) {
                foreach($local as $i=>$l) {
                    $search[$rel['foreign'][$i]]=$this->$l;
                }
            } else {
                $search[$rel['foreign']]=$this->$local;
            }
            $cn::$relations[$relation][$rk] = $rcn::find($search, $limit, $scope, $asCollection);
            if($cn::$relations[$relation][$rk]===false && $limit==0){
                $cn::$relations[$relation][$rk] = new Tecnodesign_Collection(null, $rcn);
            }
            
            $this->$relation =& $cn::$relations[$relation][$rk];
        }
        if(!is_null($fn) && $fn) {
            if($cn::$relations[$relation][$rk])
              return $cn::$relations[$relation][$rk][$fn];
        } else {
            return $cn::$relations[$relation][$rk];
        }
        return false;
    }
    
    public function delete($save=true)
    {
        $this->_delete = true;
        if($save) {
            $this->save();
        }
        return $this->_delete;
    }
    
    
    public function setRelation($relation, $value, $raw=false)
    {
        if($raw) {
            $this->$relation = $value;
            return $value;
        }
        $ro = $this->getRelation($relation, null, null, false);
        if($ro instanceof Tecnodesign_Collection) {
            $ro = $ro->getItems();
            if(!$ro) $ro = array();
        }
        if($value instanceof Tecnodesign_Collection) {
            if($value == $ro) {
                return $ro;
            } else {
                $value = $value->getItems();
            }
        } else if($value instanceof Tecnodesign_Model) {
            $value = array($value);
        } else {
            $value = (array) $value;
        }
        $rel = static::$schema['relations'][$relation];
        $local = (is_array($rel['local']))?($rel['local']):(array($rel['local']));
        $foreign = (is_array($rel['foreign']))?($rel['foreign']):(array($rel['foreign']));
        $cn = (isset($rel['className']))?($rel['className']):($relation);
        $rpk = $cn::pk();
        if($rel['type']=='many') {
            $map = array();
            if($ro instanceof Tecnodesign_Model) $ro = array($ro);
            foreach($ro as $i=>$R) {
                if(is_array($rpk)) {
                    foreach($rpk as $j=>$n) {
                        if(isset($pk)) $pk .= ','.$R[$n];
                        else $pk = $R[$n];
                        unset($j, $n);
                    }
                } else if(isset($R[$rpk])) {
                    $pk = $R[$rpk];
                }
                if(!isset($pk) || !$pk) $pk = implode(',',$R->asArray());
                $map[$pk] = $i;
                unset($i, $R, $pk);
            }
            foreach($value as $i=>$v) {
                foreach($local as $lk=>$lv) {
                    if(isset($this->$lv) && !isset($v[$foreign[$lk]])) $v[$foreign[$lk]]=$this->$lv;
                }
                if(is_array($rpk)) {
                    foreach($rpk as $j=>$n) {
                        $w = (isset($v[$n]))?($v[$n]):('');
                        if(isset($pk)) $pk .= ','.$w;
                        else $pk = $w;
                        unset($j, $n, $w);
                    }
                    if(preg_match('/^,+$/', $pk)) $pk='';
                } else if(isset($v[$rpk])) {
                    $pk = $v[$rpk];
                } else {
                    $pk = null;
                }
                if($pk && isset($map[$pk])) {
                    $value[$i] = $ro[$map[$pk]];
                    unset($ro[$map[$pk]], $map[$pk]);
                    foreach($v as $k=>$kv) {
                        $value[$i][$k]=$kv;
                    }
                } else if(is_array($v)) {
                    $value[$i] = new $cn($v, true, false);
                }
                unset($pk);
            }
            $value = array_values($value);
            foreach($ro as $i=>$R) {
                if($R->getPk()) {
                    $R->delete(false);
                    $value[] = $R;
                }
                unset($ro[$i], $i, $R);
            }
            unset($ro);
        } else {
            if(!$ro || !($ro instanceof Tecnodesign_Model)) {
                $ro = new $cn($ro);
            }
            foreach($value as $k=>$v){
                $ro[$k] = $v;
            }
            $value = $ro;
            unset($ro);
        }
        $this->$relation = $value;
        return $value;
    }

    public static function connect($conn=null)
    {
        if(!$conn) {
            $conn = static::$schema['database'];
        }
        static::$_conn = tdz::connect($conn);
        return static::$_conn;
    }

    public static function beginTransaction($conn=null)
    {
        if(!$conn) {
            $cn = get_called_class();
            $conn = $cn::connect();
        }
        $driver = $conn->getAttribute(PDO::ATTR_DRIVER_NAME);
        $trans=false;
        if($driver!='dblib' && $driver!='sqlite') {
            $conn->setAttribute(PDO::ATTR_AUTOCOMMIT, 0);
            $trans = $conn->beginTransaction();
        } else {
            $trans = uniqid('tdzt');
            $conn->exec('begin transaction '.$trans);
        }
        return $trans;
    }
    
    public static function commitTransaction($trans, $conn=null)
    {
        //if(!$trans) return false;
        if(!$conn) {
            $cn = get_called_class();
            $conn = $cn::connect();
        }
        if(is_string($trans) && substr($trans, 0, 4)=='tdzt') {
            $conn->exec('commit transaction '.$trans);
        } else {
            if($conn->inTransaction() && $conn->commit()===false){
                return false;
            } else {
                $conn->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
            }
        }
        return true;
    }

    public static function rollbackTransaction($trans, $conn=null)
    {
        if(!$trans) return false;
        if(!$conn) {
            $cn = get_called_class();
            $conn = $cn::connect();
        }
        if(is_string($trans) && substr($trans, 0, 4)=='tdzt') {
            $conn->exec('rollback transaction '.$trans);
        } else {
            $conn->rollBack();
            $conn->setAttribute(PDO::ATTR_AUTOCOMMIT, 1);
        }
        return true;
    }

    public static function sqlEscape($v, $d) {
        if(is_null($v) || $v===false) {
            return 'null';
        } else if(isset($d['type']) && $d['type']=='int') {
            return (int) $v;
        } else if(isset($d['type']) && $d['type']=='bool') {
            return ($v && $v>0)?(1):(0);
        } else if(isset($d['type']) && $d['type']=='datetime') {
            $ms = (int) self::$microsecondsLength;
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

        return tdz::sqlEscape($v);
    }
    
    public function save($beginTransaction=null, $relations=null, $conn=false)
    {
        $cn = get_class($this);
        $schema = $cn::$schema;
        if(is_null($beginTransaction)) {
            $beginTransaction = static::$transaction;
        }
        if(is_null($relations)) $relations = static::$relationDepth;
        if(tdz::$perfmon) $perfmon = microtime(true);
        try {
            $conn = $cn::connect($conn);
            $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            if(!$this->runEvent('before-save', $conn)) {
                throw new Exception("Could not save [{$schema['tableName']}]");
            }
            if(is_null($this->_delete)) {
                $this->_delete = false;
            }
            if(is_null($this->_new)) {
                $this->isNew();
            }
            $new = $this->_new;
            $trans = false;
            if ($beginTransaction) {
                $trans = $this->beginTransaction($conn);
                $defaultTransaction=self::$transaction;
                self::$transaction=false;
            }
            if ($this->_delete) {
                if(!$this->runEvent('before-delete', $conn)) {
                    throw new Tecnodesign_Exception(array(tdz::t('Could not delete record at %s.', 'exception'), $cn::label()));
                }
            } else if ($this->_new) {
                if(!$this->runEvent('before-insert', $conn)) {
                    throw new Tecnodesign_Exception(array(tdz::t('Could not insert new record at %s.', 'exception'), $cn::label()));
                }
            } else if (!$this->runEvent('before-update', $conn)) {
                throw new Tecnodesign_Exception(array(tdz::t('Could not update record at %s.', 'exception'), $cn::label()));
            }
            $odata = $this->asArray('save');
            $data = array();
            $pks = array();
            foreach ($schema['columns'] as $fn=>$fv) {
                if(!$new && isset($fv['primary']) && $fv['primary']) {
                    $pks[$fn] = tdz::sqlEscape($this->getOriginal($fn));
                }
                if (isset($fv['increment']) && $fv['increment']=='auto' && !isset($odata[$fn])) {
                    continue;
                } else if($new && !isset($odata[$fn]) && !array_key_exists($fn, $this->_original) && isset($fv['default'])) {
                    $odata[$fn] = $fv['default'];
                }
                if (!isset($odata[$fn]) && $fv['null']===false) {
                    if($this->$fn===null && !$new && !array_key_exists($fn, $this->_original)) {
                        continue;
                    }
                    throw new Tecnodesign_Exception(array(tdz::t('%s should not be null.', 'exception'), $cn::fieldLabel($fn)));
                } else if(array_key_exists($fn, $odata)) {
                    $data[$fn] = self::sqlEscape($odata[$fn], $fv);
                } else if(isset($this->_original[$fn]) && is_null($this->$fn)) {
                    $data[$fn] = 'null';
                }
            }
            if($this->_delete) {
                $wsql = '';
                foreach($pks as $fn=>$fv) {
                    $wsql .= ($wsql!='')?(' and '):('');
                    $wsql .= "{$fn}={$fv}";
                }
                $sql = "delete from {$schema['tableName']} where {$wsql}";
            } else if($this->_new) {
                $sql = "insert into {$schema['tableName']} (".implode(', ', array_keys($data)).') values ('.implode(',', $data).')';
            } else {
                $sql = '';
                foreach($data as $fn=>$fv) {
                    if(!array_key_exists($fn, $this->_original)) continue;
                    $original=$this->_original[$fn];
                    if(!array_key_exists($fn, $odata)) $odata[$fn]=null;
                    if((string)$original!==(string)$odata[$fn]) {
                        $sql .= ($sql!='')?(', '):('');
                        $sql .= "{$fn}={$fv}";
                        $this->_original[$fn]=$odata[$fn];
                    }
                }
                if($sql) {
                    $wsql = '';
                    foreach($pks as $fn=>$fv) {
                        $wsql .= ($wsql!='')?(' and '):('');
                        $wsql .= "{$fn}={$fv}";
                    }
                    $sql = "update {$schema['tableName']} set {$sql} where {$wsql}";
                }
            }
            $insertId=false;
            // @DEBUG
            //tdz::log(get_called_class()."\n  ".$sql);
            if($sql) {
                $this->_lastQuery=$sql;
                $result = $conn->exec($sql);
            } else {
                $result = true;
            }
            if($result===false && $conn->errorCode()!=='00000') {
                throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $cn::label()));
            }
            if ($this->_new) {
                $pks = $cn::pk();

                $driver = @$conn->getAttribute(PDO::ATTR_DRIVER_NAME);
                if($driver=='dblib') {
                    $q = $conn->query('SELECT CAST(COALESCE(SCOPE_IDENTITY(), @@IDENTITY) AS int) as id');
                    if($q) {
                        list($insertId) = $q->fetch(PDO::FETCH_NUM);
                    }
                } else {
                    $insertId = $conn->lastInsertId($fn);
                }
                if(is_array($pks)) {
                    foreach($pks as $fn) {
                        if(is_null($this->$fn)) {
                            $this->$fn = $insertId;
                        }
                    }
                } else if(is_null($this->$pks)) {
                    $this->$pks = $insertId;
                }
                $this->_new = false;
            }
            if ($this->_delete) {
                if(!$this->runEvent('after-delete', $conn)) {
                    throw new Tecnodesign_Exception(array(tdz::t('Could not insert new record at %s.', 'exception'), $cn::label()));
                }
            } else if ($new) {
                if(!$this->runEvent('after-insert', $conn)) {
                    throw new Tecnodesign_Exception(array(tdz::t('Could not insert new record at %s.', 'exception'), $cn::label()));
                }
            } else if (!$this->runEvent('after-update', $conn)) {
                throw new Tecnodesign_Exception(array(tdz::t('Could not update record at %s.', 'exception'), $cn::label()));
            }
           
            // Run dependencies
            if($relations) {
                $relations--;
                foreach ($schema['relations'] as $rcn=>$rd) {
                    $rc=(isset($rd['className']))?($rd['className']):($rcn);
                    if(isset($this->$rcn)) {
                        $rel = $this->$rcn;
                        $lfn=(is_array($rd['local']))?($rd['local']):(array($rd['local']));
                        $rfn=(is_array($rd['foreign']))?($rd['foreign']):(array($rd['foreign']));
                        $rv=array();
                        foreach($lfn as $ri=>$fn) {
                            $rv[$rfn[$ri]]=$this->$fn;
                        }
                        if($rel) {
                            if($rel instanceof self) {
                                $rel=array($rel);
                            }
                            foreach($rel as $relo) {
                                if(!is_object($relo) || !($relo instanceof self)) continue;
                                foreach($rv as $fn=>$fv) {
                                    $relo[$fn]=$fv;
                                }
                                $relo->save(false, $relations, $conn);
                            }
                        }
                    }
                }
            }
            if($trans) {
                if(!$cn::commitTransaction($trans, $conn)) {
                    throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $cn::label().'...'));
                }
                self::$transaction=$defaultTransaction;
            }
            if(!$this->runEvent('after-save', $conn)) {
                throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $cn::label()));
            }
        } catch(Exception $e) {
            $msg = $e->getMessage();
            tdz::log(__METHOD__."($cn): ".$e);
            if(isset($sql)) tdz::log($sql);
            if (isset($trans) && $trans) {
                $cn::rollbackTransaction($trans, $conn);
                self::$transaction=$defaultTransaction;
            }
            throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception')."\n".tdz::t('Issues are', 'exception').":\n".$msg, $cn::label()));
        }
        if(tdz::$perfmon>0) tdz::log(__METHOD__.': '.tdz::formatNumber(microtime(true)-$perfmon).'s '.tdz::formatBytes(memory_get_peak_usage())." mem\n  {$sql}");
        return true;
    }
    
    /**
     * Class Name labels
     * 
     * Uses translation to get proper table name
     * 
     * @param string $translate trasnlation table to use, or false to prevent translation
     * 
     * @return string Class label
     */
    public static function label($translate='ui-labels')
    {
        $cn = get_called_class();
        if(!isset($cn::$schema['label'])) {
            $label = ucwords(str_replace('_', ' ', $cn::$schema['tableName']));
        } else {
            $label = $cn::$schema['label'];
            if(substr($label, 0, 1)=='*') {
                $label = substr($label,1);
            } else {
                $translate = false;
            }
        }
        if ($translate) {
            $label = tdz::t($label, $translate);
        }
        $cn::$schema['label']=$label;
        return $cn::$schema['label'];
    }
    
    /**
     * Column Name labels
     * 
     * Uses translation to get proper table name
     * 
     * @param string|bool $translate trasnlation table to use, or false to prevent translation
     * 
     * @return string Column name label
     */
    public static function fieldLabel($fn, $translate=true)
    {
        $cn = get_called_class();
        if(!isset($cn::$schema['form'][$fn]['label'])) {
            $label = trim(ucwords(strtr(tdz::uncamelize($fn), '-_', '  ')));
            if ($translate) {
                if (!is_string($translate)) {
                    $translate = 'model-'.$cn::$schema['tableName'];
                }
                $label = tdz::t($label, $translate);
            }
            if(!isset($cn::$schema['form'][$fn])) {
                return $label;
            }
            $cn::$schema['form'][$fn]['label']=$label;
        }
        return $cn::$schema['form'][$fn]['label'];
    }
    
    public static function find($s=null, $limit=0, $scope=null, $collection=true, $orderBy=null, $groupBy=null)
    {
        $q=array();
        if(!$groupBy) {
            $c = static::pk();
            if(!is_array($c)) $c=array($c);
            $q['select'] = array_merge($c, static::columns($scope, null, 3, true));
            unset($c);
        } else  {
            $q['select'] = static::columns($scope, null, 3, true);
        }
        if(is_string($scope)) {
            $q['scope'] = $scope;
        }
        if($s) {
            if(!is_array($s)) {
                $c = static::pk();
                if(is_array($c)) $c=array_shift($c);
                $q['where'] = array($c=>$s);

            } else {
                $q['where'] = $s;
            }
        }
        if(!is_null($orderBy)) $q['orderBy'] = $orderBy;
        else if(isset(static::$schema['order'])) $q['orderBy'] = static::$schema['order'];
        if(!is_null($groupBy)) $q['group'] = $groupBy;
        else if(isset(static::$schema['group-by'])) $q['groupBy'] = static::$schema['group-by'];
        $q['limit'] = $limit;
        $Q = Tecnodesign_Query::handler(get_called_class())->find($q);
        if(!$Q) {
            return false;
        } else if($limit==1) {
            $r = $Q->fetch(0, $limit);
            return ($r)?(array_shift($r)):(false);
        } else if(!($c=$Q->count())) {
            return false;
        } else if(!$collection) {
            return $Q->fetch(0, $limit);
        } else {
            return new Tecnodesign_Collection(null, get_called_class(), $Q, null);
        }
    }

    public static function query($q=null, $cn=null)
    {
        if(!$cn) $cn = get_called_class();
        if($q) return Tecnodesign_Query::handler($cn)->find($q);
        else return Tecnodesign_Query::handler($cn);
    }

    public static function find2($s=null, $limit=1, $scope=null, $collection=true, $orderBy=null, $groupBy=null)
    {
        $usek=false;
        if(is_null($s)) {
            $s = array();
            if(func_num_args()<=1) $limit = 0;
        } else if(!is_array($s)) {
            $usek=($limit==1)?($s):(false);
            $s = explode(',', $s);
        }
        $className = $cn = get_called_class();
        if($cn=='Tecnodesign_Model' && class_exists($collection)) {
            if($usek) {
                $usek = $collection.':'.$cn;
            }
            $schema = self::schema($collection);
            $className = $collection;
        } else {
            $schema = $cn::$schema;
        }
        if($usek && is_string($usek) && isset($cn::$found[$usek])) {
            return $cn::$found[$usek];
        }
        $w = array();
        $fns = array_keys($schema['columns']);
        $alias = array();
        $distinct = '';
        $join = array();
        $innerJoin = true;
        try {
            foreach($s as $fn=>$fv) {
                $bb=$b='and';
                if(substr($fn, 0, 1)=='|'){
                    $b='or';
                    $fn = trim(substr($fn,1));
                }
                if(substr($fn, -1)=='|'){
                    $bb='or';
                    $fn = trim(substr($fn,0,strlen($fn)-1));
                }
                $w[]=$b;
                $cop = '=';
                $ofn = $fn;
                if(is_int($fn)) {
                    $fn = 't.'.array_shift($fns);
                } else if (isset($schema['columns'][$fn])) {
                    $found = true;
                    $fn = 't.'.$fn;
                } else {
                    $found = false;
                    if(preg_match('/(\~|\<\>|[\<\>\^\$\*\!\%]?\=|[\>\<])/', $fn, $m)) {
                        // operators: <=  >= < > ^= $=
                        $cop = $m[1];
                        $fn = trim(str_replace($cop, '', $fn));
                        if (isset($schema['columns'][$fn])) {
                            $found = true;
                        }
                    }
                    if($found) {
                        $fn = 't.'.$fn;
                    } else if(strpos($fn, '.') || strpos($fn, '`')!==false) {
                        $fn=$cn::resolveAlias($fn, $alias, $join, $distinct);
                    }
                    if($bb=='or') $innerJoin = false;
                }
                if($cop=='~') { // between
                    $cols = preg_split('/\s*\~\s*/', $ofn, null, PREG_SPLIT_NO_EMPTY);
                    // if is at the end, the column should be between two values
                    if(count($cols)==1) {
                        if(!is_array($fv)) {
                            $fv = array($fv,$fv);
                        }
                        $w[] = "{$fn} between ".tdz::sqlEscape(array_shift($fv)).' and '.tdz::sqlEscape(array_shift($fv));
                    } else {
                    // otherwise, two columns should be between the value
                        $fv = tdz::sqlEscape($fv);
                        $w[] = "{$fv} between t.{$cols[0]} and t.{$cols[1]}";
                    }
                } else if (is_array($fv) && ($cop=='=' || $cop=='<>' || $cop=='!=')) {
                    foreach ($fv as $fvk=>$fvs) {
                        $fv[$fvk] = tdz::sqlEscape($fvs);
                        if($fvs==''){
                            $fv['']='null';
                        }
                    }
                    $not = ($cop!='=')?(' not'):('');
                    $w[] = "{$fn}{$not} in (".implode(',',$fv).")";
                } else if(is_array($fv)) {
                    $ww=array();
                    $bbw='or';
                    if($cop=='^=') {
                        foreach($fv as $fvs)
                            $ww[] = "{$fn} like '".tdz::sqlEscape($fvs, false)."%'";
                    } else if($cop=='$=') {
                        foreach($fv as $fvs)
                            $ww[] = "{$fn} like '%".tdz::sqlEscape($fvs, false)."'";
                    } else if($cop=='*=') {
                        foreach($fv as $fvs)
                            $ww[] = "{$fn} like '%".tdz::sqlEscape($fvs, false)."%'";
                    } else if($cop=='%=') {
                        foreach($fv as $fvs)
                            $ww[] = "{$fn} like '%".str_replace('-', '%', tdz::slug($fvs))."%'";
                    } else {
                        foreach($fv as $fvs) {
                            if($fvs=='') $ww[]="({$fn}='' or {$fn} is null)";
                            else $ww[] = "{$fn}{$cop}".tdz::sqlEscape($fvs);
                        }
                    }
                    // this should be OR
                    $w[] = '('.implode(" {$bbw} ", $ww).')';
                    //$w[] = '('.implode(" {$bb} ", $ww).')';
                } else {
                    if($fv=='' && $cop=='='){
                        $w[] = "({$fn}=".tdz::sqlEscape($fv)." or {$fn} is null)";
                    } else if($fv=='' && ($cop=='<>' || $cop=='!=')) {
                        $w[] = "{$fn}>''";
                    } else if($fv=='' && ($cop=='<>' || $cop=='!=')) {
                        $w[] = "({$fn}<>".tdz::sqlEscape($fv)." and {$fn} is not null)";
                    } else if($cop=='^=') {
                        $w[] = "{$fn} like '".tdz::sqlEscape($fv, false)."%'";
                    } else if($cop=='$=') {
                        $w[] = "{$fn} like '%".tdz::sqlEscape($fv, false)."'";
                    } else if($cop=='*=') {
                        $w[] = "{$fn} like '%".tdz::sqlEscape($fv, false)."%'";
                    } else if($cop=='%=') {
                        $w[] = "{$fn} like '%".str_replace('-', '%', tdz::slug($fv))."%'";
                    } else {
                        $w[] = "{$fn}{$cop}".tdz::sqlEscape($fv);
                    }
                }
            }
            $fobj=true;
            $f = static::columns($scope);
            foreach($f as $k=>$fn) {
                if(is_array($fn)) {
                    if(isset($fn['bind'])) $f[$k]=$fn['bind'];
                    else unset($f[$k]);
                }
                unset($k, $fn);
            }
            /*
            if (is_array($scope)) {
                $f = $scope;
            } else if (!is_null($scope) && isset($schema['scope'][$scope])) {
                $f = $schema['scope'][$scope];
            } else {
                $f = array_keys($schema['columns']);
            }
            */
            if(count($f)==0 || !$groupBy) {
                $pks = self::pk($schema);
                if($pks) {
                    if(is_array($pks)) $f = array_merge($pks, $f);
                    else array_unshift($f, $pks);
                }
            }
            foreach($f as $k=>$v) {
                if(substr($v, 0, 2)=='--' && substr($v, -2)=='--') unset($f[$k]);
                unset($k, $v);
            }
            $order = '1';
            $o=false;
            if(!is_null($orderBy)) {
                $o=(!is_array($orderBy) && $orderBy)?(array($orderBy)):($orderBy);
            } else if(isset($schema['order'])) {
                $o=$schema['order'];
            }
            if($o) {
                $order = '';
                $oi=0;
                foreach($o as $fn=>$dir) {
                    if(strpos($fn, ' ')!==false) $fn=substr($fn,0,strpos($fn, ' '));
                    if(is_numeric($fn)) {
                        $i = (int)$fn;
                        if($i==$fn && $i>0 && $i<count($f)) $fn=$i;
                    }
                    if(!is_int($fn)) {
                        if(!in_array($fn, $f)) {
                            $found=false;
                            $ps = $fn.' ';
                            $pl = strlen($ps);
                            foreach($f as $v) {
                                if(substr($v, 0, $pl)==$ps) {
                                    $found = true;
                                    break;
                                }
                            }
                            if(!$found && !strpos($fn, '``')) {
                                if(strpos($fn, '.')) {
                                    $f[]=$fn.' __orderby'.($oi++);
                                } else {
                                    $f[]=$fn;
                                }
                            }
                            unset($found, $v, $ps, $pl);
                        }
                        $fn=$cn::resolveAlias($fn, $alias, $join, $distinct);
                    } else {
                        $fn++;
                    }
                    $order .= ($order!='')?(', '):('');
                    $order .= $fn.' '.$dir;
                    /* // mssql specific
                    if(!in_array($fn, $f)) $f[] = $fn;
                     */
                }
                unset($oi);
            } else if($orderBy===false) {
                $order='';
            }
            $group = '';
            $g=false;
            if($groupBy===true) { // just disable adding the foreign key
            } else if(!is_null($groupBy)) {
                $g=(!is_array($groupBy))?(array($groupBy)):($groupBy);
            } else if(isset($schema['group-by'])) {
                $g=$schema['group-by'];
            }
            $fields = '';
            if($g) {
                if(!is_array($g)) $g=array($g);
                foreach($g as $fn) {
                    if(!$fn)continue;
                    $group .= ($group!='')?(', '):('');
                    if(strpos($fn, ' ')) list($fn,$fnc)=explode(' ', $fn, 2);
                    $fn=$cn::resolveAlias($fn, $alias, $join, $distinct);
                    $group .= $fn;
                }
                if(isset($schema['aggregate'])) {

                    foreach($schema['aggregate'] as $func=>$fn) {
                        if(strpos($fn, ' ')) {
                            list($fn,$fnc)=explode(' ', $fn, 2);
                        } else {
                            $fnc=tdz::slug($func.'-'.$fn);
                        }
                        $fn=$cn::resolveAlias($fn, $alias, $join, $distinct);
                        if($fields) $fields .= ', ';
                        $fields .= "{$func}({$fn}) _{$fnc}";
                    }
                }
                if($group) $group = ' group by '.$group;
            }
            if(isset($schema['events']['active-records']) && $schema['events']['active-records']) {
                $ar = $schema['events']['active-records'];
                if(!is_array($ar)) $ar=array($ar);
                foreach($ar as $fi=>$fn) {
                    if(!is_int($fi)) {
                        $fnc = '= '.tdz::sqlEscape($fn);
                        $fn = $fi;
                    } else if(strpos($fn, '`')!==false) {
                        $fnc = '';
                    } else {
                        list($fn,$fnc)=explode(' ', $fn, 2);
                    }
                    $w[] = 'and';
                    $w[] = $cn::resolveAlias($fn, $alias, $join, $distinct).' '.$fnc;
                }
            }
            if($order!='') {
                $order = ' order by '.$order;
            }
            $where = '';
            $b=$bb='and';
            /*
            // replace left outer join + where for inner joins
            if($innerJoin && count($join)>0 && count($w)>0) {
                foreach($w as $i=>$ws) {
                    if(strlen($ws) > 5 && preg_match('/^(t[0-9]+)\..+/', $ws, $m) && isset($join[$m[1]])) {
                        if($w[$i-1]=='and') {
                            $ws = 'and '.$ws.' ';
                            unset($w[$i-1]);
                        } else continue;
                        unset($w[$i]);
                        if(substr($join[$m[1]], 0, 16)=='left outer join ') $join[$m[1]] = 'inner join '.substr($join[$m[1]],16);
                        $join[$m[1]] .= $ws;
                    }
                    unset($m, $i, $ws);
                }
                $w = array_values($w);
            }
            */

            array_shift($w);
            foreach($w as $i=>$ws) {
                if($i>0) {
                    if($ws=='or' || $ws=='and') {
                        $b=$ws;
                        if($bb!=$b) {
                            $where = "({$where})";
                            $bb=$b;
                        }
                    }
                    $where .= ' ';
                }
                $where .= $ws;
            }
            $where = ($where)?(' where '.$where):('');
            $tn = (isset($schema['view']))?('('.$schema['view'].')'):($schema['tableName']);
            $f = array_unique($f);
            $cn::$_conn = tdz::connect($schema['database'], null, true);
            $driver = @$cn::$_conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            if($driver=='dblib' && $distinct) {
                $unique=array();
                foreach($f as $fn) {
                    if(isset($cn::$schema['columns'][$fn]['size']) && $cn::$schema['columns'][$fn]['size']>65000) {
                        $nfn = 'cast(t.'.$fn.' as varchar(max)) as '.$fn;
                        $as=true;
                    } else {
                        $nfn = $cn::resolveAlias($fn, $alias, $join, $distinct);
                        $fn = substr($nfn, strpos($nfn, '.')+1);
                        $as=false;
                    }
                    if(isset($unique[$nfn])) continue;
                    if($fields) $fields .= ', ';
                    if(in_array($fn, $unique)) {
                        $i=1;$fni='_'.$fn;
                        while(in_array($fni, $unique)) {
                            $fni = '_'.$fn.$i;
                            $i++;
                        }
                        $nfn .= ($as)?($i):(' as '.$fni);
                    }
                    $unique[$nfn]=$fn;
                    $fields .= $nfn;
                }
            } else {
                $unique=array();
                foreach($f as $fn) {
                    $nfn = $cn::resolveAlias($fn, $alias, $join, $distinct);
                    $fn = substr($nfn, strpos($nfn, '.')+1);
                    if(isset($unique[$nfn])) continue;
                    if($fields) $fields .= ', ';
                    if(in_array($fn, $unique)) {
                        $i=1;$fni='_'.$fn;
                        while(in_array($fni, $unique)) {
                            $i++;
                            $fni = '_'.$fn.$i;
                        }
                        $nfn .= ' as '.$fni;
                    }
                    $unique[$nfn]=$fn;
                    $fields .= $nfn;
                }
                //$fields = 't.'.implode(', t.', $f);
            }
            $sql = "select {$distinct}{$fields} from {$tn} as t ".implode(' ', $join)."{$where}{$group}{$order}";
            if($collection && $limit!=1) {  // ($collection && $limit!=1) || $limit>1
                $result = new Tecnodesign_Collection(null, $className, $sql, null, $cn::$_conn);
                if (!$result || $result->count()==0) {
                    return false;
                } else if ($limit==1) {
                    return $result->getItem(0);
                } else if($limit>0 || !$collection) {
                    return $result->getItem(0, $limit, $collection);
                } else {
                    return $result;
                }
            } else {
                if(tdz::$perfmon) tdz::$perfmon = microtime(true);
                $result=array();
                $query = $cn::$_conn->query($sql);
                if($query) {
                    if($limit>0) {
                        $query->setFetchMode(PDO::FETCH_CLASS, $className);
                        if($limit==1) {
                            $ret = $query->fetch();
                        } else {
                            $i = $limit;
                            $ret = array();
                            while($i>0) {
                                $r = $query->fetch();
                                if($r) {
                                    $ret[] = $r;
                                    $i--;
                                    unset($r);
                                } else {
                                    break;
                                }
                            }
                        }
                    } else {
                        $ret = $query->fetchAll(PDO::FETCH_CLASS, $className);
                    }
                    $query->closeCursor();
                    $query = null;
                    unset($query);
                    if(tdz::$perfmon>0) tdz::log(__METHOD__.': '.tdz::formatNumber(microtime(true)-tdz::$perfmon).'s '.tdz::formatBytes(memory_get_peak_usage()).' mem: '.count($ret)."\n  {$sql}");
                } else {
                    $err = $cn::$_conn->errorInfo();
                    tdz::log($err);
                    if($err[1]>0)
                        tdz::log('Error at '.$cn."::find():\n  ".implode("\n  ", $err));
                    if(tdz::$perfmon>0) tdz::log(__METHOD__.': '.tdz::formatNumber(microtime(true)-tdz::$perfmon).'s '.tdz::formatBytes(memory_get_peak_usage())." mem: 0\n  {$sql}");
                    return false;
                }
                return $ret;
            }
        } catch(Exception $e) {
            tdz::log(__METHOD__.': '.$e);
            return false;
        }
    }

    protected static function resolveAlias($fn, &$alias, &$join, &$distinct, $unique=false)
    {
        $ofn=$fn;
        if(preg_match_all('#`([^`]*)`#', $fn, $m)) {
            $r = $s = array();
            foreach($m[1] as $i=>$nfn) {
                $s[]=$m[0][$i];
                $r[]=($nfn)?(self::resolveAlias($nfn, $alias, $join, $distinct, $unique)):('');
            }
            return str_replace($s, $r, $fn);
        }

        if(strpos($fn, '[')!==false && preg_match('/\[([^\]]+)\]/', $fn, $fnt)) {
            $fn = $fnt[1];
            $fnt = $fnt[0];
            $fn0 = $ofn;
            $ofn = $fn;
        }
        $cn = get_called_class();
        $sc = $cn::$schema;
        $ta='t';
        $found=false;
        if ($fn=='*') {
            $found = true;
        } else if (isset($sc['columns'][$fn])) {
            $found = true;
            $fn = $ta.'.'.$fn;
        } else if(!$found) {
            $rnf = '';
            while(strpos($ofn, '.')) {
                @list($rn, $fn) = explode('.', $ofn,2);
                $ofn=$fn;
                $rn = ucfirst(tdz::camelize($rn));
                $rnf .= ($rnf)?('.'.$rn):($rn);
                if(isset($sc['relations'][$rn])) {
                    $rcn = (isset($sc['relations'][$rn]['className']))?($sc['relations'][$rn]['className']):($rn);
                    if(!isset($alias[$rnf])) {
                        $an = 't'.count($alias);
                        $alias[$rnf]=$an;
                        if($sc['relations'][$rn]['type']!='one') {
                            $distinct = 'distinct ';
                        }
                        $jtn = (isset($rcn::$schema['view']))?('('.$rcn::$schema['view'].')'):($rcn::$schema['tableName']);
                        if(!is_array($sc['relations'][$rn]['foreign'])) {
                            $join[$an] = "left outer join {$jtn} as {$an} on {$an}.{$sc['relations'][$rn]['foreign']}={$ta}.{$sc['relations'][$rn]['local']} ";
                        } else {
                            $join[$an] = "left outer join {$jtn} as {$an} on ";
                            foreach($sc['relations'][$rn]['foreign'] as $rk=>$rv) {
                                $join[$an] .= (($rk>0)?('and '):(''))."{$an}.{$rv}={$ta}.{$sc['relations'][$rn]['local'][$rk]} ";
                            }
                        }
                        if(isset($sc['relations'][$rn]['on'])) {
                            if(!is_array($sc['relations'][$rn]['on'])) $sc['relations'][$rn]['on']=array($sc['relations'][$rn]['on']); 
                            foreach($sc['relations'][$rn]['on'] as $rfn) {
                                list($rfn,$fnc)=explode(' ', $rfn, 2);
                                if(substr($rfn,0,strlen($rn))==$rn) $join[$an] .=  "and {$an}".substr($rfn,strlen($rn))." {$fnc} ";
                                else $join[$an] .= 'and '.$cn::resolveAlias($rfn, $alias, $join, $distinct).' '.$fnc.' ';
                                unset($rfn, $fnc);
                            }
                        }
                    } else {
                        $an = $alias[$rnf];
                    }
                    $sc = $rcn::$schema;
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
                $fn = $ta.'.'.$fn;
            } else if (($p=strrpos($fn, ' ')) && (substr($fn, $p+1, 1)=='_' || property_exists($cn, substr($fn, $p+1)))) {
                $found = true;
            } else {
                throw new Exception("Cannot find by [{$fn}] at [{$sc['tableName']}]");
            }
        }
        if(isset($fnt) && $fnt) {
            $fn = str_replace($fnt, $fn, $fn0);
        }
        return $fn;
    }
    
    public function setCollection($c)
    {
        if($c instanceof Tecnodesign_Collection) {
            $this->_collection=$c;
        }
    }

    public function getCollection()
    {
        return $this->_collection;
    }

    public function renderScope($scope=null, $xmlEscape=true, $box=null, $tpl=null, $sep=null)
    {
        $id = $scope;

        if(!is_array($scope)) $scope = static::columns($scope, null, false);
        else $id = static::$schema['tableName'];

        $nobox = !$box;
        if(!$box) $box = static::$boxTemplate;
        if(!$sep) $sep = static::$headingTemplate;
        if(!$tpl) $tpl = static::$previewTemplate;
        $s = '';
        $a = '';
        foreach($scope as $label=>$fn) {
            if(is_array($fn)) {
                $fd = $fn;
                if(isset($fd['bind'])) $fn=$fd['bind'];
                else $fn=''; 
            }
            if(substr($fn, 0, 2)=='--' && substr($fn, -2)=='--') {
                $class = $label;
                $label = substr($fn, 2, strlen($fn)-4);
                $s .= str_replace(array('$LABEL', '$ID', '$INPUT', '$CLASS', '$ERROR'), array($label, $fn, $label, $class, ''), $sep);
                unset($class);
            } else {
                $class='';
                if(is_integer($label)) $label = static::fieldLabel($fn, false);
                if(preg_match('/^([a-z0-9\-\_]+)::([a-z0-9\-\_\,]+)$/i', $fn, $m)) {
                    if($m[1]=='scope') {
                        $s .= $this->renderScope($m[2], $xmlEscape, $box, $tpl, $sep);
                    } else if($m[1]=='sub') {
                        $class = $fn = $m[2];
                        $class .= ($class)?(' sub'):('sub');
                        $input = str_replace(
                            array('$LABEL', '$ID', '$INPUT', '$CLASS', '$ERROR'), 
                            array($label, $fn, $label, $class, ''),
                            $sep);

                        $s .= str_replace(
                            array('$LABEL', '$ID', '$INPUT', '$CLASS', '$ERROR', '$ATTR'), 
                            array($label, $fn, $input, $class, '', ''),
                            $box);

                        unset($class, $input);
                    }
                    $v = false;
                } else {
                    if(isset($fd)) {
                        $fd1 = static::column($fn,true,true);
                        if($fd1) $fd += $fd1;
                        unset($fd1);
                    } else {
                        $fd=static::column($fn,true,true);
                    }
                    $v = $this->renderField($fn, $fd, $xmlEscape);
                    if(isset($fd['class'])) $class = $fd['class'];
                }
                if(substr($label, 0, 2)=='a:') {
                    if($v && !$xmlEscape) $v = tdz::xmlEscape($v);
                    $a .= ' '.substr($label, 2).'="'.$v.'"';
                } else if($v!==false) {
                    if(strpos($fn, ' ')) $fn = substr($fn, strrpos($fn, ' ')+1);
                    $s .= str_replace(array('$LABEL', '$ID', '$INPUT', '$CLASS', '$ERROR'), array($label, $fn, $v, $class, ''), $tpl);
                }
                unset($scope[$label], $label, $fn, $v, $m, $class, $fd);
            }
        }
        if($nobox) return $s;
        $s = str_replace(array('$LABEL', '$ID', '$INPUT', '$CLASS', '$ERROR', '$ATTR'), array((strpos($box, '$LABEL')!==false)?(static::label()):(''), $id, $s, '', '', $a), $box);
        return $s;
    }
    
    public function renderUi($o=array())
    {
        $s = '';
        $cn = get_called_class();
        $schema = $cn::$schema;
        if(isset($o['labels'])) $labels = $o['labels'];
        else if(isset($o['scope']) && is_array($o['scope'])) $labels = $o['scope'];
        else $labels = $cn::columns((isset($o['scope']))?($o['scope']):('review'));
        if(isset($o['checkbox']) && $o['checkbox']) {
            $checkbox = 'checkbox';
        } else if(isset($o['radio']) && $o['radio']) {
            $checkbox = 'radio';
        } else {
            $checkbox = false;
        }
        $link = (isset($o['link']))?($o['link']):(tdz::scriptName());
        $i = (isset($o['position']))?($o['position']):(0);
        $start = (isset($o['start']))?($o['start']):($i);
        $max = (isset($o['hits']))?($o['hits']):(20);
        $qs = Tecnodesign_Ui::$qs;
        $qsb = ($qs)?(substr($qs,1)):('');
        if($qsb) {
            $qsb = preg_replace('#&?(o|d|p)=[^&]*#', '', $qsb);
            if(substr($qsb, 0, 1)=='&') $qsb = substr($qsb,1);
        }
        $qsb=($qsb)?('?'.$qsb.'&'):('?');
        if(!(($sf=Tecnodesign_App::request('get', 'o')) && is_numeric($sf) && $sf<count($labels))) {
            $sf = (isset($schema['order']))?(array_keys($schema['order'])):(array(''));
            $sf=$sf[0];
        }
        if(!(($sd=Tecnodesign_App::request('get', 'd')) && ($sd=='asc' || $sd=='desc'))) {
            $sd=(isset($schema['order'][$sf]))?($schema['order'][$sf]):('asc');
        }

        if(!is_null($this->_collection)) {
            $i=$this->_collection->getPosition();
            $start=$this->_collection->getPageStart();
            if($this->_collection->count() < $max) $max = $this->_collection->count();
        }
        $max += $start;
        $model = $cn::$schema['tableName'];
        $tattr = '';
        if($checkbox) {
            $tid = 't-'.tdz::slug($model);
            $tattr .= ' id="'.$tid.'"';
        }
        $ba = array();
        foreach($labels as $label=>$fn) {
            if(substr($label, 0, 2)=='a:') {
                $fn = ($p=strrpos($fn, ' '))?(substr($fn, $p+1)):($fn);
                $ba[substr($label,2)] = $this->$fn;
                unset($labels[$label], $p);
            }
            unset($label, $fn);
        }

        $ext = (isset($o['extension']))?($o['extension']):('');


        if($i==$start) {
            $s .= '<table class="app-list"'.$tattr.'><thead><tr>';
            $first = true;
            $so = 1;
            foreach($labels as $label=>$fn) {
                $sort = !preg_match('/\.[A-Z]/', $fn);
                $fid = (preg_match('/\s+_?([\_a-z0-9]+)$/i', $fn, $m))?($m[1]):(str_replace(array('`', '[', ']'), '', $fn));
                if(is_numeric($label)) $label = tdz::t(ucwords(str_replace('_', ' ', $fid)), 'model-'.$model);
                else if(substr($label,0,1)=='*') $label = tdz::t(substr($label,1), 'model-'.$model);
                $s .= '<th class="c-'.$so.' f-'.$fid.(($so==$sf)?(' ui-order ui-order-'.$sd):('')).'">'
                    . ((isset($first) && $checkbox==='checkbox')?('<input type="checkbox" onclick="tdz.toggleInput(\'#'.$tid.' input[type='.$checkbox.']\', this);" label="'.tdz::t('Select all', 'ui').'" data-label-alternative="'.tdz::t('Clear selection', 'ui').'" />'):(''))
                    . $label
                    . (($sort)?('<a href="'.tdz::scriptName().$ext.tdz::xmlEscape($qsb.'o='.$so.'&d=asc').'" class="icon asc"></a>'):(''))
                    . (($sort)?('<a href="'.tdz::scriptName().$ext.tdz::xmlEscape($qsb.'o='.$so.'&d=desc').'" class="icon desc"></a>'):(''))
                    . '</th>';
                $so++;
                unset($label, $fn, $sort, $first);
            }
            $s .= '</tr></thead><tbody>';
        }
        if(!is_array($link)) {
            if($link!==false || $checkbox) {
                $uid = $this->getPk();//str_replace('-', ',', $this->getPk());
            } else {
                $uid=false;
            }
            $url = $link.'/';
        } else {
            $url=false;
        }
        if($qs=='?')$qs='';
        if(!isset($ba['class'])) $ba['class'] = (($i%2)?('even'):('odd'));
        $s .= '<tr';
        foreach($ba as $p=>$v) $s .= ' '.$p.'="'.$v.'"';
        $s .= '>';
        $first = true;
        foreach($labels as $label=>$fn) {
            if($p=strrpos($fn, ' ')) {
                $fn = substr($fn, $p+1);
            }
            $dm = 'preview'.tdz::camelize(ucfirst($fn));
            $m = 'get'.tdz::camelize(ucfirst($fn));
            $display=false;
            if(method_exists($this, $dm)) {
                $value = $this->$dm();
                $display=true;
            } else if(method_exists($this, $m)) {
                $value = $this->$m();
            } else if(strpos($fn, '.')!==false || isset($cn::$schema['relations'][$fn])) {
                $value = (string) $this->getRelation($fn);
            } else {
                $value = $this->$fn;
            }
            $fd=false;
            if(!$display && isset($schema['columns'][$fn])) {
                $fd=$schema['columns'][$fn];
                if($fd['type']=='datetime' || $fd['type']=='date') {
                    if($value && $t=strtotime($value)) {
                        $df = ($fd['type']=='datetime')?(tdz::$dateFormat.' '.tdz::$timeFormat):(tdz::$dateFormat);
                        $value = date($df, $t);
                    }
                } else {
                    if(isset($schema['form'][$fn]['choices'])) {
                        $ffd = $schema['form'][$fn];
                        $co=false;
                        if(is_array($ffd['choices'])) {
                            $co = $ffd['choices'];
                        } else {
                            // make Tecnodesign_Form_Field::getChoices available
                        }
                        if(is_array($co) && isset($co[$value])) {
                            $value = $co[$value];
                        }
                    }
                }
            }
            if(is_array($link)) {
                if(isset($link[$fn])) {
                    if(!isset($replace)) {
                        $replace = $this->asArray(null, '{%s}');
                    }
                    $uid = tdz::xmlEscape(str_replace(array_keys($replace), array_values($replace), $link[$fn]));
                } else {
                    $uid = false;
                }
            }
            if(substr($fn, 0, 1)=='_') $fn = substr($fn,1);
            
            $s .= '<td class="f-'.$fn.'">'
                . (($uid && $checkbox)?('<input type="'.$checkbox.'" id="uid-'.$this->getPk().'" name="uid'.(($checkbox==='checkbox')?('[]'):('')).'" value="'.$uid.'" />'):(''))
                . (($uid)?('<a href="'.$url.$uid.$ext.$qs.'">'.$value.'</a>'):($value))
                .'</td>';
            if($uid) $uid=false;
            unset($label, $fn);
        }
        $s .= '</tr>';
        if($i+1>=$max) {
            $s .='</tbody></table>';
        }
        
        return $s;
    }
    
    public function renderField($fn, $fd=null, $xmlEscape=false)
    {
        $cn=get_class($this);
        if(is_null($fd)) {
            $fd=$cn::column($fn,true,true);
            if(!$fd) {
                $fd=array();
            }
        }
        if(strpos($fn, ' ')!==false) {
            $fn = substr($fn, strrpos($fn, ' ')+1);
        }
        $pm='preview'.ucfirst(tdz::camelize($fn));
        $m='get'.ucfirst(tdz::camelize($fn));
        $getRef = false;
        if(method_exists($this, $pm)) {
            $v = $this->$pm();
            if($xmlEscape) $xmlEscape = false;
        } else if(method_exists($this, $m)) {
            $v = $this->$m();
        } else {
            $v = $this[$fn];
            $getRef = true;
        }
        if(!$getRef) {
        } else if($v instanceof Tecnodesign_Collection) {
            $v = $v->getItems();
            if($v) $v=implode(', ', $v);
        } else if($v!==false && !is_null($v)) {
            if(isset($fd['multiple']) && $fd['multiple'] && is_string($v) && strpos($v, ',')!==false) {
                $v = preg_split('/\,/', $v, null, PREG_SPLIT_NO_EMPTY);
            }

            if(isset($fd['choices'])) {
                $choices=$fd['choices'];
                if(is_string($choices)) {
                    if(class_exists($choices)) {
                        $multiple=(isset($fd['multiple']) && $fd['multiple'])?(0):(1);
                        if(is_array($v)) {
                            $pk = $choices::pk();
                            if(is_array($pk)) $pk =array_shift($pk);
                            $v=array($pk=>$v);
                        }
                        $v = $choices::find($v,$multiple,'choices',false);
                        if(!$multiple && $v) $v = implode('; ', $v);
                    } else {
                        $choices = @eval('return '.$choices.';');
                    }
                }
                if(is_array($choices)) {
                    if(is_array($v)) {
                        $c = $v;
                        $r = array();
                        foreach($c as $v) {
                            $v = $choices[$v];
                            if(is_array($v)) $v = array_shift($v);
                            if($v) $r[] = $v;
                        }
                        $v = implode(', ', $v);
                    } else {
                        $v = (isset($choices[$v]))?($choices[$v]):('');
                        if(is_array($v)) $v = array_shift($v);
                    }
                }
                unset($choices);
            } else if(isset($fd['type']) && substr($fd['type'],0,4)=='date') {
                if($t=strtotime($v)) {
                    $df = ($fd['type']=='datetime')?(tdz::$dateFormat.' '.tdz::$timeFormat):(tdz::$dateFormat);
                    $v = date($df, $t);
                }
            }
        } else if(isset($fd['local']) && isset($fd['foreign'])) {
            // relation
            $v = (string) $this->getRelation($fn);
        }
        if($xmlEscape) {
            $v = str_replace(array('  ', "\n"), array('&#160; ', '<br />'), tdz::xmlEscape($v));
        }
        
        return $v;
    }
    
    public static function renderAs($val, $fn, $fd=null)
    {
        $cn=get_called_class();
        if(is_null($fd)) {
            $fd=$cn::column($fn,true,true);
            if(!$fd) return (is_array($val))?(implode(', ', $val)):($val);;
        }
        if((is_null($val) || $val==='') && !(isset($fd['choices']) && !is_string($fd['choices']) && isset($fd['choices'][$val]))) {
            return (is_array($val))?(implode(', ', $val)):($val);;
        }
        if(isset($fd['choices'])) {
            $choices=$fd['choices'];
            if(is_string($choices)) {
                $toFind = $val;
                if(isset($cn::$schema['relations'][$choices])) {
                    if(is_string($cn::$schema['relations'][$choices]['foreign'])) {
                        $toFind = array($cn::$schema['relations'][$choices]['foreign']=>$val);
                    }
                    if(isset($cn::$schema['relations'][$choices]['className'])) {
                        $choices = $cn::$schema['relations'][$choices]['className'];
                    }
                }
                if(class_exists($choices)) {
                    $multiple=(isset($fd['multiple']) && $fd['multiple'])?(0):(1);
                    if(is_array($val)) {
                        $multiple = 0;
                        $pk = $choices::pk();
                        if(is_array($pk)) $pk=array_shift($pk);
                        $val = array($pk => $val );
                        unset($pk);
                    }
                    $val = $choices::find($val,$multiple,'choices',false);
                    if($val instanceof Tecnodesign_Model) $val = (string) $val;
                    else if(is_array($val)) $val = implode(', ', $val);
                    $choices=array();
                } else if(strpos($choices, '(')) {
                    $choices = @eval('return '.$choices.';');
                } else {
                    if(method_exists($cn, $choices)) {
                        $choices = $cn::$choices();
                    }
                }
            }
            if($choices && is_array($val)) {
                foreach($val as $k=>$v) {
                    if(isset($choices[$v])) $val[$k] = (string) $choices[$v];
                    else if(is_array($v)) $val[$k] = array_shift($v);
                    else if(is_object($v)) $val[$k] = (string) $v;
                }
                $val = implode(', ', $val);

            } else if(isset($choices[$val])) {
                $val = $choices[$val];
                if(is_array($val)) $val = array_shift($val);
            }
            unset($choices);
        } else if(substr($fd['type'],0,4)=='date') {
            if($t=strtotime($val)) {
                $df = ($fd['type']=='datetime')?(tdz::$dateFormat.' '.tdz::$timeFormat):(tdz::$dateFormat);
                $val = date($df, $t);
            }
            unset($t, $df);
        }
        if(is_array($val)) {
            $val = implode(', ', $val);

        }
        unset($fd, $cn);
        return $val;
    }  


    /**
     * Magic terminator. Returns the page contents, ready for output.
     * 
     * @return string page output
     */
    public function __toString()
    {
        $cn = get_called_class();
        $schema = $cn::$schema;
        if(isset($schema['scope']) && count($schema['scope'])>0) {
            $sc = (isset($schema['scope']['string']))?($schema['scope']['string']):(array_shift($schema['scope']));
            return implode(', ', $this->asArray($sc));
            $s = array();
            foreach($sc as $label=>$fn) {
                $s[$label] = $this->__get($fn);
            }
            return implode(', ', $s);
        } else if(!is_null($this->id)) {
            return (string) $this->id;
        }
        return '';
    }

    public function validate($schema, $value, $name=null)
    {
        $ovalue = $value;
        if (!is_null($name) && method_exists($this, 'validate'.ucfirst($name))) {
            $m = 'validate'.ucfirst($name);
            $value = $this->$m($value);
        }
        if ($schema['type']=='string') {
            $value = @(string) $value;
            if (isset($schema['size']) && $schema['size'] && strlen($value) > $schema['size']) {
                $value = mb_strimwidth($value, 0, (int)$schema['size'], '', 'UTF-8');
            }
        } else if($schema['type']=='int') {
            if (!is_numeric($value) && $value!='') {
                throw new Tecnodesign_Exception(tdz::t('This is not a valid value.', 'exception').' '.tdz::t('An integer number is expected.', 'exception'));
            }
            if($value!=='' && !is_null($value)) $value = (int) $value;
            if (isset($schema['min']) && $value < $schema['min']) {
                throw new Tecnodesign_Exception(array(tdz::t('%s is less than the expected minimum %s', 'exception'), $value, $schema['min']));
            }
            if (isset($schema['max']) && $value > $schema['max']) {
                throw new Tecnodesign_Exception(array(tdz::t('%s is more than the expected maximum %s', 'exception'), $value, $schema['max']));
            }
        } else if(substr($schema['type'], 0,4)=='date') {
            if($value) {
                $time = false;
                $d = false;
                if(!preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}/', $value)) {
                    $format = tdz::$dateFormat;
                    if (substr($schema['type'], 0, 8)=='datetime') {
                        $format .= ' '.tdz::$timeFormat;
                        $time = true;
                    }
                    $d = date_parse_from_format($format, $value);
                }
                if($d) {
                    $value = str_pad((int)$d['year'], 4, '0', STR_PAD_LEFT)
                        . '-' . str_pad((int)$d['month'], 2, '0', STR_PAD_LEFT)
                        . '-' . str_pad((int)$d['day'], 2, '0', STR_PAD_LEFT);
                    if($time) {
                        $value .= ' '.str_pad((int)$d['hour'], 2, '0', STR_PAD_LEFT)
                            . ':' . str_pad((int)$d['minute'], 2, '0', STR_PAD_LEFT)
                            . ':' . str_pad((int)$d['second'], 2, '0', STR_PAD_LEFT);
                    }
                } else if($d = strtotime($value)) {
                    //$nvalue = (substr($schema['type'], 0, 8)=='datetime')?(date('Y-m-d H:i:s', $d)):(date('Y-m-d', $d));
                }
            }
        }
        if (($value==='' || $value===null) && isset($schema['null']) && !$schema['null']) {
            $sch = $this->schema();
            $label = tdz::t(ucwords(str_replace('_', ' ', $name)), 'model-'.$sch['tableName']);
            throw new Tecnodesign_Exception(array(tdz::t('%s is mandatory and should not be blank.', 'exception'), $label));
        } else if($value==='') {
            $value = false;
        }
        return $value;
    }

    public static function __set_state($a, $underscore=false)
    {
        $M = new static();
        if(!is_array($a) && $a) {
            $pk = static::pk();
            if(is_array($pk)) $pk = array_shift($pk);
            $a = array($pk => $a);
        }
        foreach($a as $k=>$v) {
            $M->$k = $v;
            $M->_original[$k] = $v;
        }
        return $M;
    }

    public function __call($name, $arguments) {
        $column = tdz::uncamelize($name);
        $fn = substr($column, 0, 4);
        $column = substr($column, 4);
        if ($fn=='set_') {
            return $this->__set($column, $arguments[0]);
        } else if ($fn=='get_') {
            return $this->__get($column);
        }
        throw new Tecnodesign_Exception(array("Method %s is not available at %s", $name, get_called_class()));
    }

    public static function __callStatic($name, $arguments) {
        if(substr($name, 0, 6)=='findBy' && strlen($name)>6) {
            $name = explode('-', preg_replace('/(Or|And)([A-Z])/', '-$1-$2', substr($name,6)));
            $pipe = '';
            $args = array();
            foreach($name as $cfn) {
                if ($cfn=='Or') {
                    $pipe = '|';
                } else if($cfn=='And') {
                    $pipe = '';
                } else {
                    if(!isset($arguments[0])){
                        throw new Exception("Wrong argument count for [{$m}]");
                    }
                    $fn = $pipe.tdz::uncamelize(lcfirst($cfn));
                    $args[$fn]=array_shift($arguments);
                }
            }
            if(isset($arguments[0])){
                throw new Exception("Wrong argument count for [{$m}]. Need less arguments.");
            }
            return static::find($args, 0);
        }
    }
    
    
    public function getOriginal($fn, $fallback=true)
    {
        if(!array_key_exists($fn, $this->_original)) {
            if($fallback) {
                $this->_original[$fn] = $this->$fn;
            } else {
                return false;
            }
        }
        return $this->_original[$fn];
    }

    public function get($fn)
    {
        if (isset($this->$fn)) {
            return $this->$fn;
        } else {
            return false;
        }
    }
    
    /**
     * Magic getter. Searches for a get$Name method, or gets the stored value in
     * $_vars.
     *
     * @param string $name parameter name, should start with lowercase
     * 
     * @return mixed the stored value, or method results
     */
    public function __get($name)
    {
        $m='get'.ucfirst(tdz::camelize($name));
        $ret = false;
        @list($firstName,$ref)=explode('.', $name, 2);
        if (method_exists($this, $m)) {
            $ret = $this->$m();
        } else if(strstr('ABCDEFGHIJKLMNOPQRSTUVWXYZ!', substr($name, 0, 1))) {
            if(!is_null($this->$firstName)) {
                if($ref) return $this->$firstName->$ref;
                else return $this->$firstName;
            } else {
                if (isset(static::$schema['relations'][$firstName])) {
                    return $this->getRelation($firstName, $ref);
                }
            }
        } else if (isset($this->$name)) {
            $ret = $this->$name;
        } else if($firstName && $ref && isset($this->$firstName) && isset($this->{$firstName}[$ref])) {
            $ret = $this->{$firstName}[$ref];
        }
        return $ret;
    }
    /**
     * ArrayAccess abstract method. Gets stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return mixed the stored value, or method results
     * @see __get()
     */
    public function offsetGet($name)
    {
        return $this->__get($name);
    }
    /**
     * Magic setter. Searches for a set$Name method, and stores the value in $_vars
     * for later use.
     *
     * @param string $name  parameter name, should start with lowercase
     * @param mixed  $value value to be set
     *
     * @return void
     */
    public function __set($name, $value)
    {
        if($name=='ROWSTAT') return $this;
        $mn=tdz::camelize($name, true);
        if(isset(static::$schema['columns'][$name]) && !array_key_exists($name, $this->_original)) {
            $this->_original[$name] = $this->$name;
        }
        @list($firstName,$ref)=explode('.', $name, 2);
        if (method_exists($this, $m='set'.$mn)) {
            $this->$m($value);
        } else if(isset(static::$schema['columns'][$name])) {
            $value = $this->validate(static::$schema['columns'][$name], $value, $name);
            $this->$name=$value;
        } else if(isset(static::$schema['relations'][$name])) {
            $this->setRelation($name, $value);
        } else if($firstName && $ref && method_exists($this, $m='set'.tdz::camelize($firstName, true))) {
            $this->$m(array($ref=>$value));
        // add other options for dotted.names?
        } else if($firstName && $ref && isset($this->$firstName) && (is_array($this->$firstName) || is_object($this->$firstName))) {
            $this->{$firstName}[$ref] = $value;
        } else if(static::$allowNewProperties || substr($name,0,1)=='_') {
            $this->$name=$value;
        } else {
            throw new Tecnodesign_Exception(array(tdz::t('Column "%s" is not available at %s.','exception'), $name, get_class($this)));
        }
        return $this;
    }
    /**
     * ArrayAccess abstract method. Sets parameters to the PDF.
     *
     * @param string $name  parameter name, should start with lowercase
     * @param mixed  $value value to be set
     * 
     * @return void
     * @see __set()
     */
    public function offsetSet($name, $value)
    {
        return $this->__set($name, $value);
    }

    /**
     * ArrayAccess abstract method. Searches for stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return bool true if the parameter exists, or false otherwise
     */
    public function __isset($name)
    {
        return isset($this->$name);
    }
    public function offsetExists($name)
    {
        return $this->__isset($name);
    }

    /**
     * ArrayAccess abstract method. Unsets parameters to the PDF. Not yet implemented
     * to the PDF classes — only unsets values stored in $_vars
     *
     * @param string $name parameter name, should start with lowercase
     * 
     * @return void
     */
    public function __unset($name)
    {
        unset($this->$name);
    }
    public function offsetUnset($name)
    {
        return $this->__unset($name);
    }

    
    
    /**
     * Iterator
     */
    public function rewind() {
        $this->_p = 0;
    }

    public function current() {
        return $this->{$this->key()};
    }

    public function key() {
        return implode('', array_slice(array_keys(self::$schema['columns']), $this->_p, 1));
    }

    public function next() {
        ++$this->_p;
    }

    public function valid() {
        return ($this->_p > 0 && $this->_p < $this->count());
    }
    
    public function count() {
        return count(self::$schema['columns']);
    }
    
}