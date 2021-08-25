<?php
/**
 * Tecnodesign Model
 * 
 * Full database abstraction ORM.
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Model implements ArrayAccess, Iterator, Countable
{
    const SCHEMA_PROPERTY='schema';
    const AUTOLOAD_CALLBACK='staticInitialize';
    public static 
        $schema,
        $allowNewProperties = false,
        $prepareWhere,
        $keepCollection = false,
        $transaction=true,
        $formAsLabels,
        $keySeparator='-',
        $listSeparator=', ',
        $queryAllowedChars='@.-_',
        $relationDepth=3,
        $boxTemplate     = '<div$ATTR>$INPUT</div>',
        $headingTemplate = '<hr /><h3>$LABEL</h3>',
        $previewTemplate = '<dl><dt>$LABEL</dt><dd>$INPUT</dd></dl>',
        $queryBatchLimit = 500,
        $auditLog,
        $schemaClass='Tecnodesign_Schema_Model';

    protected static 
        $found=array(),
        $_conn=null,
        $_typesChoices = array('select','checkbox','radio');

    protected
        $_original = array(),
        $_new = false,
        $_update,
        $_delete,
        $_relation,
        $_query,
        $_connected,
        $_p = 0,
        $_forms,
        // for MSSQL-based objects
        $rowstat,
        $ROWSTAT;
    private $_collection;

    protected static $stats=array();

    public static function staticInitialize()
    {
        $scn = static::$schemaClass;
        if(isset(static::$schema['ref'])) {
            $cn = static::$schema['ref'];
            unset(static::$schema['ref']);
            $source = static::$schema;
            static::$schema = $scn::loadSchema($cn);
            foreach($source as $k=>$v) {
                if(is_array($v) && isset(static::$schema[$k]) && is_array(static::$schema[$k])) {
                    static::$schema[$k] = $v + static::$schema[$k];
                } else {
                    static::$schema[$k] = $v;
                }
            }
            static::$schema->className = get_called_class();
        } else {
            static::$schema = $scn::loadSchema(get_called_class());
        }
        if(static::$schema && static::$auditLog) static::$schema->audit = static::$auditLog;
    }

    /**
     * Class constructor: you can create a new instance based on an associative array with the values
     */
    public function __construct($vars=array(), $insert=null, $save=null)
    {
        if(!isset(self::$stats[get_class($this)])) self::$stats[get_class($this)]=1;
        else self::$stats[get_class($this)]++;
        if (is_array($vars)) {
            foreach($vars as $k=>$v) {
                $this->__set($k, $v);
                unset($k, $v);
            }
        }
        if(!is_null($insert)) {
            $this->isNew($insert);
            if($insert && is_null($save)) {
                $save = true;
            }
        } else if(!isset($vars['_new'])) {
            $this->_new = null;
        }
        $this->initialize();
        if(!is_null($save) && $save) {
            $this->save();
        }
    }

    public function __destruct()
    {
        unset($this->_forms, $this->_query);
        if(static::$schema && static::$schema->relations) {
            foreach(static::$schema->relations as $rn=>$rel) {
                $this->unsetRelation($rn);
                unset($rn, $rel);
            }
        }

        @self::$stats[get_class($this)]--;
    }

    /**
     * Gets the timestampable last update
     */
    public static function timestamp($tns=null)
    {
        return static::queryHandler()->timestamp($tns);
    }

    public function __sleep()
    {
        if(!is_null($this->_collection)) {
            $this->_collection=null;
        }
        $ret = get_object_vars($this);
        $not = array('_forms', '_collection', '_delete', '_query', '_original', '_p', 'rowstat', 'ROWSTAT');
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
        } else if(!($schema=Tecnodesign_Cache::get('schema/'.$cn, tdz::$timeout))) {
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
            if(!($S = static::sourceSchema($schema['tableName']))) {
                $schema = new Tecnodesign_Schema_Model($schema);
            }
            Tecnodesign_Cache::set('schema/'.$cn, $S, tdz::$timeout);
        }

        return $schema;
    }

    public static function sourceSchema($tn=null, $base=[])
    {
        if(!$tn && isset($base['tableName'])) $tn=$base['tableName'];
        else if(!$tn && isset(static::$schema)) $tn=static::$schema->tableName;
        if(!$tn) return null;

        $H = static::queryHandler();
        if(!method_exists($H, 'getTableSchema')) return false;

        if($schema=$H->getTableSchema($tn)) {
            return new Tecnodesign_Schema_Model($schema + $base);
        }
    }

    /**
     * Retrieves the columns used for the primary key in this model
     * Unless $array evals to true or there are multiple PKs this will return the
     * result as a string (array otherwise)
     */
    public static function pk($schema=null, $array=null, $skipUid=false)
    {
        $update=false;
        if(!$schema) {
            $schema = static::$schema;
            $update = !$array;
        }

        $pk=array();
        if($schema) {
            if(!$skipUid && isset($schema->scope['uid'])) {
                $pk = (is_array($schema->scope['uid']))?($schema->scope['uid']):(array($schema->scope['uid']));
            } else if($schema->properties) {
                foreach($schema->properties as $fn=>$fd) {
                    if(is_array($fd)) {
                        $fd = new Tecnodesign_Schema_Property($fd);
                        $schema->properties[$fn] = $fd;
                    }
                    if ($fd->increment=='auto') {
                        $pk[] = $fn;
                    } else if($fd->primary) {
                        $pk[]=$fn;
                    } else {
                        break;
                    }
                }
            }
            unset($schema);
        }
        if($array) return $pk;
        else if(count($pk)==1) $pk = $pk[0];

        if($update) static::$schema['scope']['uid']=$pk;
        return $pk;
    }

    /**
     * Retrieves values listed for the primary key in this object
     * Unless $array evals to true this will return the PK(s) as
     * a string (associative array otherwise)
     */
    public function getPk($array=null)
    {
        $pk = static::pk(static::$schema, true);
        $r = array();
        $b = ($array && is_string($array))?($array.'.'):('');
        foreach($pk as $fn) {
            if($p=strrpos($fn, ' ')) $fn = substr($fn, $p+1);
            $r[$b.$fn]=(!isset($this->$fn) && method_exists($this, $m='get'.tdz::camelize($fn, true)))?($this->$m()):($this->$fn);
        }
        if($array) return $r;
        return implode(static::$keySeparator, $r);
    }

    public static function formFields($scope=false, $allowText=false)
    {
        $cn = get_called_class();
        if(!static::$schema->overlay) {
            static::$schema->overlay = [];
            $fk=array();
            if(static::$schema->relations) {
                foreach(static::$schema->relations as $rn=>$rel) {
                    if($rel['type']=='one') {
                        if(is_array($rel['local'])) {
                            $fk[array_pop($rel['local'])] = $rn;
                        } else {
                            $fk[$rel['local']]=$rn;
                        }
                    }
                }
            }
            if(static::$schema->properties) {
                foreach(static::$schema->properties as $fn=>$fd) {
                    if (isset($fd['increment']) && $fd['increment']) {
                        continue;
                    }
                    if(!isset(static::$schema->overlay[$fn]['bind'])) static::$schema->overlay[$fn]=array('bind'=>$fn);
                    if (isset($fk[$fn])) {
                        static::$schema->overlay[$fn]['format']='select';
                        static::$schema->overlay[$fn]['choices']=$fk[$fn];
                    }
                }
            }
        }
        $fo = static::$schema->overlay;
        if($scope) {
            if(!is_array($scope)) $scope = static::columns($scope);
            $sfo = array();
            foreach($scope as $label=>$fn) {
                if(is_string($fn) && strpos($fn, '<')!==false) {
                    $sfo[] = $fn;
                    continue;
                }
                $fd = array();
                if(is_array($fn)) {
                    $fd = $fn;
                    if(isset($fd['bind'])) $fn=$fd['bind'];
                    else $fn=$label;
                }
                if(isset($fd['credential'])) {
                    if(!isset($U)) $U=tdz::getUser();
                    if(!$U || !$U->hasCredentials($fd['credential'], false)) continue;
                    unset($fd['credential']);
                }
                if(preg_match('/^`?([a-z0-9\_\.]+)`?( +as)? ([a-z0-9\_]+)$/i', $fn, $m)) {
                    $fn = $m[3];
                    if(!isset(static::$schema->overlay[$fn])) {
                        $col = static::column($m[1], true);
                        if($col) {
                            static::$schema->overlay[$fn] = $col;
                            if(isset(static::$schema->overlay[$fn]['bind'])) static::$schema->overlay[$fn]['bind'] = $fn; // or $m[1]?
                        }
                        unset($col);
                    }
                }
                if(strpos($fn, ' ')!==false && substr($fn, 0, 2)!='--') $fn = substr($fn, strrpos($fn, ' ')+1);

                if(!isset($fd['label']) && !is_int($label)) {
                    $fd['label'] = $label;
                }

                if(isset($fd['label']) && substr($fd['label'], 0, 1)=='*')  {
                    if(!isset($translate)) $translate = 'model-'.static::$schema->tableName;
                    $label = $fd['label'] = tdz::t(substr($fd['label'], 1), $translate);
                }

                if(isset($fd['fieldset']) && substr($fd['fieldset'], 0, 1)=='*')  {
                    if(!isset($translate)) $translate = 'model-'.static::$schema->tableName;
                    $fd['fieldset'] = tdz::t(substr($fd['fieldset'], 1), $translate);
                }

                if(isset($fd['id'])) $fid = $fd['id'];
                else if(static::$formAsLabels && !is_int($label)) $fid = $label;
                else $fid = $fn;

                if($fid===false && is_array($fd)) {
                    $sfo[]=$fd;
                } else if(isset(static::$schema->overlay[$fn])) {
                    $fd=$fd+static::$schema->overlay[$fn];
                    if(isset(static::$schema->properties[$fn])) {
                        $fd+=static::$schema->properties[$fn]->value();
                    }
                    $sfo[$fid]=$fd;
                } else if(isset(static::$schema->properties[$fn])) {
                    $fd+=static::$schema->properties[$fn]->value();
                    $sfo[$fid]=$fd;
                } else if($fd && isset($fd['type'])) {
                    $sfo[$fid] = $fd;
                } else if(substr($fn, 0, 1)=='_' || (static::$allowNewProperties && substr($fn, 0, 2)!='--')) {
                    $sfo[$fid] = array('type'=>'text','bind'=>$fn, 'label'=>$label)+$fd;
                } else if($allowText) {
                    if(substr($fn, 0, 2)=='--' && substr($fn, -2)=='--') {
                        $class = $label;
                        $label = substr($fn, 2, strlen($fn)-4);
                        $fn = str_replace(array('$LABEL', '$ID', '$INPUT', '$CLASS', '$ERROR'), array($label, $fn, $label, $class, ''), static::$headingTemplate);
                        unset($class);
                    }
                    $sfo[] = $fn;
                    continue;
                } else {
                    continue;
                }

                if(isset($sfo[$fid]) && !isset($sfo[$fid]['label']) && !is_int($label)) {
                    $sfo[$fid]['label']=$label;
                }
                unset($label, $fn, $fd, $fid);
            }
            $fo = $sfo;
        }

        return $fo;
    }

    /**
     * retrieves the properties for the given scope
     */
    public static function columns($scope='default', $type=null, $expand=3, $clean=false)
    {
        if(!$scope) $scope = 'default';
        if(is_string($scope) && strpos($scope, '.') && !isset(static::$schema->scope[$scope]) && file_exists($f=TDZ_APP_ROOT.'/config/'.$scope)) {
            if(substr($scope, -4)==='.yml') {
                $S=Tecnodesign_Yaml::load($f);
            } else if(substr($scope, -5)==='.json') {
                $S=json_decode($f, true);
            }
            if($S) {
                static::$schema->scope[$scope] = $S;
                $scope = $S;
            }
            unset($S, $f);
        }

        $base = array();

        if(!is_array($scope)) {
            if($scope==='uid') {
                $scope = static::pk(static::$schema, true);
            } else {
                if (!isset(static::$schema->scope[$scope])) {
                    $labels = array();
                    foreach(static::$schema->properties as $fn=>$fd) {
                        if(is_array($fd)) {
                            $fd = new Tecnodesign_Schema_Property($fd);
                            $schema->properties[$fn] = $fd;
                        }
                        if (!$fd->increment) {
                            $labels[static::fieldLabel($fn)] = $fn;
                        }
                        unset($fn, $fd);
                    }
                    static::$schema->scope[$scope] = $labels;
                    unset($labels);
                }
                $scope = static::$schema['scope'][$scope];
            }
        }
        if(isset($scope['__default'])) {
            $base = $scope['__default'];
            unset($scope['__default']);
            foreach($scope as $label=>$fn) {
                if(is_string($fn)) {
                    if(preg_match('/^(scope::|--)/', $fn)) continue;
                    $fn = array('bind'=>$fn);
                }
                if($base) {
                    $fn += $base;
                }
                $scope[$label] = $fn;
                unset($fn, $label);
            }
        }
        if($type && $scope) {
            if(!is_array($type)) $type=array($type);
            foreach($scope as $k=>$fn) {
                $fd = $base;
                if(is_array($fn)) {
                    $fd = $fn + $fd;
                    if(isset($fn['bind'])) {
                        $fn=$fn['bind'];
                    }
                }
                if($fn && !isset($fd['bind'])) $fd['bind'] = $fn;
                if(strpos($fn, ' ')) $fn = preg_replace('/\s+(as\s+)?[a-z0-9\_]+$/i', '', $fn);

                if(isset($fd['type'])) {
                    $ftype = $fd['type'];
                } else if(isset(static::$schema->overlay[$fn]['type'])) {
                    $ftype = static::$schema->overlay[$fn]['type'];
                } else if(isset(static::$schema->properties[$fn]['type'])) {
                    $ftype = static::$schema->properties[$fn]['type'];
                } else {
                    $ftype = 'string';
                }
                if(!in_array($ftype,$type)) {
                    unset($scope[$k]);
                    continue;
                }

                unset($k, $fn, $fd, $ftype);
            }
        }
        if($expand && $scope) {
            $expand--;
            $r = array();
            if(!is_array($scope)) $scope = array($scope);
            foreach($scope as $k=>$fn) {
                $fd = null;
                if(is_array($fn)) {
                    if(!isset($fn['bind'])) {
                        if(!isset($fn['type']) && !isset($fn['format'])) {
                            unset($scope[$k], $fn, $k, $fd);
                            continue;
                        }
                        $fn['bind'] = false;
                    }
                    $fd = $fn + $base;
                    $fn=$fn['bind'];
                }
                if(is_null($fd)) $fd = array('bind'=>$fn)+$base;
                if(strpos($fn, ' ')) {
                    $fn0 = $fn;
                    $fn = substr($fn, strrpos($fn, ' ')+1);
                }

                if(!isset($fd['label']) && !is_int($k) && $k!=$fn) {
                    $fd['label'] = $k;
                }

                if(isset(static::$schema->overlay[$fn])) {
                    $fd += (array) static::$schema->overlay[$fn];
                }
                if(isset(static::$schema->properties[$fn])) {
                    if(is_array(static::$schema->properties[$fn])) {
                        static::$schema->properties[$fn] = new Tecnodesign_Schema_Property(static::$schema->properties[$fn]);
                    }
                    $fd += static::$schema->properties[$fn]->value();
                }

                if(isset($fd['label']) && substr($fd['label'], 0, 1)=='*')  {
                    if(!isset($translate)) $translate = 'model-'.static::$schema->tableName;
                    $fd['label'] = tdz::t(substr($fd['label'], 1), $translate);
                }

                if(isset($fn0)) {
                    if(!isset($fd['bind'])) $fd['bind'] = $fn0;
                    $fn = $fn0;
                    unset($fn0);
                }

                if(preg_match('/^([a-z0-9\-\_]+)::([a-z0-9\-\_\,]+)(:[a-z0-9\-\_\,\!]+)?$/i', $fn, $m)) {
                    if(isset($m[3])) {
                        if(!isset($U)) $U=tdz::getUser();
                        if(!$U || !$U->hasCredential(preg_split('/[\,\:]+/', $m[3], null, PREG_SPLIT_NO_EMPTY),false)) {
                            continue;
                        }
                    }
                    if($m[1]=='scope') {
                        $res = static::columns($m[2], $type, $expand, $clean);
                        if($res && is_array($res)) {
                            $r = array_merge($r, $res);
                        }
                    }
                } else {
                    if(isset($fd['credential'])) {
                        if(!isset($U)) $U=tdz::getUser();
                        if(!$U || !$U->hasCredentials($fd['credential'], false)) continue;
                    }
                    $r[$k] = (!$clean && isset($fd))?($fd):($fn);
                }
                unset($scope[$k], $fn, $k, $fd);
            }
            $scope = $r;
            unset($r);
        }
        if($clean) {
            foreach($scope as $i=>$fn) {
                if(is_array($fn) && isset($fn['bind'])) {
                    $scope[$i]=$fn['bind'];
                } else if(is_array($fn) || (substr($fn, 0, 2)=='--' && substr($fn, -2)=='--')) {
                    unset($scope[$i]);
                }
            }
        }
        return $scope;
    }

    public static function column($s, $applyForm=false, $relation=false)
    {
        $cn = get_called_class();
        if(strpos($s, '.')!==false && !($applyForm && isset($cn::$schema->overlay[$s]))) {
            while(strpos($s, '.')!==false) {
                list($rn,$s)=explode('.', $s, 2);
                if(isset($cn::$schema->relations[$rn])) {
                    $cn = (isset($cn::$schema->relations[$rn]['className']))?($cn::$schema->relations[$rn]['className']):($rn);
                } else {
                    return false;
                }
            }
        }
        if(strpos($s, ' ')) $s = substr($s, 0, strpos($s, ' '));
        $d=false;
        if(isset($cn::$schema->properties[$s])) {
            $d = $cn::$schema->properties[$s];
            $i = 10;
            while($i-- && $d->alias && isset($cn::$schema->properties[$d->alias])) {
                $d = $cn::$schema->properties[$d->alias];
            }
            $d = $d->value();
        } else if($relation && isset($cn::$schema->relations[$s])) {
            $d = $cn::$schema->relations[$s];
            if(!isset($d['className'])) $d['className']=$s;
        } else if(property_exists($cn, $s)) {
            $d = array('bind'=>$s);
        }
        if($applyForm && isset($cn::$schema->overlay[$s])) {
            if($d) $d = array_merge($d, $cn::$schema->overlay[$s]);
            else $d = $cn::$schema->overlay[$s];
        }
        if($d && isset($d['label']) && substr($d['label'], 0, 1)=='*')  {
            if(!isset($translate)) $translate = 'model-'.static::$schema->tableName;
            $d['label'] = tdz::t(substr($d['label'], 1), $translate);
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
    public function getForm($scope=null, $pk=false, $parentForm=null)
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
            foreach($fo['fields'] as $fn=>$fd) {
                if(is_array($fd) && isset($fd['on']) && !isset($fd['className']) && !$this->checkObjectProperties($fd['on'])) {
                    unset($fo['fields'][$fn]);
                }
            }
            if($pk && !$this->isNew()) {
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
            if($parentForm) {
                $F->setParent((is_string($parentForm)) ?$parentForm :$parentForm->uid);
            }
            unset($fo);
            $this->_forms[$hash] = $F->uid;
        }
        return $F;

    }


    public function lastQuery()
    {
        if(isset($this->_query)) return $this->_query->lastQuery();
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
            throw new Tecnodesign_Exception('Don\'t know how to update this schema, sorry...');
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
                    if(is_array($fn) && isset($fn[0]) && count($fn)>1 && is_object($fn[0]) && method_exists($fn[0], $fn[1])) {
                        $O = array_shift($fn);
                        $m = array_shift($fn);
                        if($fn) {
                            $result = call_user_func_array(array($O, $m), $fn);
                        } else {
                            $result = $O->$m();
                        }
                    } else if(method_exists($this, $fn)) {
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
                tdz::log('[INFO] '.__METHOD__.', '.$e->getLine().': '.get_class($this)."::{$fn}\nerror: ".$e->getMessage());
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
        $Q = static::queryHandler();
        $schema = $cn::$schema;
        $scope = $cn::pk();
        foreach($fields as $fn) {
            $sfn=$fn;
            $w=array();
            if (is_array($scope)) {
                foreach($scope as $sn) {
                    if ($sn!=$fn) {
                        $w[$sn.'-'.$this->$sn]="{$sn}=".tdz::sql($this->$sn);
                    } else break;
                }
            }
            if(count($w)>0) {
                $sfn .= ':'.implode(',',array_keys($w));
            }
            $sfn = $cn.'.'.$sfn;
            if(isset($cn::$increment[$sfn])) {
                $cn::$increment[$sfn]++;
            } else {
                $cn::$increment[$sfn]=1;
                $driver = $Q::DRIVER;
                $mssql = (in_array($driver, ['dblib', 'sqlsrv', 'odbc']));
                if(count($w)==0 && $mssql) {
                    $sql = "select ident_current('{$schema->tableName}') as next";
                } else {
                    $sql = "select coalesce(max({$fn}),0)+1 as next from {$schema->tableName}";
                    if(count($w)>0) {
                        $sql .= ' where '.implode(' and ', $w);
                    }
                }
                $next = $Q->queryColumn($sql);
                if($next) {
                    $cn::$increment[$sfn]=(int)$next[0];
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
        $vtn = ($schema->versionTableName)?($schema->versionTableName):($schema->tableName.'_version');
        $fns = implode(',', array_keys($schema->properties));
        $cn = get_class($this);
        $scope = $cn::pk();
        $w=array();
        if(!is_array($scope)) {
            $scope = array($scope);
        }
        foreach($scope as $fn) {
            $w[]="{$fn}=".tdz::sql($this->$fn);
        }
        $w = implode(' and ', $w);
        if(is_array($fields)) {
            $fields = array_shift($fields);
        }
        if(!isset($this->$fields)) $this->refresh([$this->$fields]);
        $version = (int) $this->$fields;
        $version++;
        $sqls = array(
            "update {$schema->tableName} set {$fields}={$version} where {$w}",
            "replace into {$vtn} ({$fns}) select {$fns} from {$schema->tableName} where {$w}",
        );
        $this->$fields=$version;
        $Q = static::queryHandler();
        foreach($sqls as $sql) {
            $Q->exec($sql, $conn);
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
            if(isset($this->{'__skip_timestamp_'.$fn}) && $this->{'__skip_timestamp_'.$fn}) continue;
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
        $scope = (isset($schema->scope['sortable']))?($schema->scope['sortable']):([]);
        $w = $ik = array();
        $fn='';
        foreach($scope as $sn) {
            if ($sn!=$fn) {
                $ik[]=$this->$sn;
                $w[]="{$sn}=".tdz::sql($this->$sn);
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
                $sql = "select ifnull(max({$fn}),0)+1 as next from {$schema->tableName}";
                if(count($w)>0) {
                    $sql .= ' where '.implode(' and ', $w);
                }
                if($next = static::queryHandler()->query($sql)) {
                    $cn::$increment[$fn.'-'.$ik]=$next[0]['next'];
                }
            }
            if(!isset($this->_original[$fn])) $this->_original[$fn]=$this->$fn;
            $this->$fn=$cn::$increment[$fn.'-'.$ik];
        }
        return true;
    }

    /**
     * Soft Delete Behavior
     */
    public function softDeleteTrigger($fields, $conn=null)
    {
        if($this->_delete && !(isset($this->__skip_soft_delete) && $this->__skip_soft_delete)) {
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
            return true;
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
            $auto = false;
            foreach($schema->properties as $fn=>$fv) {
                if(isset($fv->primary) && $fv->primary) {
                    if($fv->increment) $auto = true;
                    $pks[$fn] = $this->$fn;
                }
            }
            $hasPk=true;
            foreach ($pks as $pk) {
                if(tdz::isempty($pk)) {
                    $hasPk = false;
                    break;
                }
            }
            if($hasPk && !$auto) {
                $found = static::find($pks,1,array_keys($pks));
                if(!$found) $hasPk = false;
                unset($found);
            }
            $this->_new = !$hasPk;
        }
        return $this->_new;
    }

    public function isUpdated($update=null)
    {
        if (!is_null($update)) {
            $this->_update = $delete;
        }
        if(is_null($this->_update)) {
            $update = false;
            foreach($this->_original as $fn=>$fv) {
                if($fv!=$this->$fn) {
                    $update = true;
                    break;
                }
                unset($fn, $fv);
            }
            return $update;
        }
        return $this->_delete;
    }

    public function isDeleted($delete=null)
    {
        if (!is_null($delete)) {
            $this->_delete = $delete;
        }
        return $this->_delete;
    }

    public function refresh($scope=null)
    {
        $scope = static::columns($scope);
        $f = array();
        foreach($scope as $fn) {
            if(is_array($fn)) {
                if(isset($fn['bind'])) $fn = $fn['bind'];
                else continue;
            }
            $p = (strpos($fn, ' ')!==false)?(substr($fn, strrpos($fn, ' ')+1)):($fn);
            if(!isset($this->$p)) {
                $f[$p] = $fn;
            }
        }
        if($f) {
            if($M = $this::find($this->getPk(true),1,$f)) {
                foreach($f as $p=>$fn) {
                    if(isset($M->$p)) {
                        if(isset($this::$schema['columns'][$p]) && !isset($this->_original[$p])) {
                            $this->_original[$p]=$M->$p;
                        }
                        $this->safeSet($p, $M->$p, true);
                    }
                    unset($f[$p], $p, $fn);
                }
            }
            unset($M);
        }
        return $this;
    }

    public function asHtml($preview=null)
    {
        return tdz::xml((string)$this);
    }

    public function asArray($scope=null, $keyFormat=null, $valueFormat=false, $serialize=null)
    {
        $noscope = (is_null($scope));
        $schema = $this->schema();
        $result = array();
        if (!is_null($scope) && (is_array($scope) || isset($schema['scope'][$scope]))) {
            if(!is_array($scope)) $scope = $schema['scope'][$scope];
        } else if($schema->properties) {
            $scope = $schema->properties;
        }
        if(!$scope && $noscope) {
            return $this->_original;
        }
        if($scope && is_array($scope)) {
            foreach($scope as $fn=>$fv) {
                $type = 'string';
                if(is_string($fv) && isset($schema->properties[$fv]['bind']) && $schema->properties[$fv]['bind']!=$fv) {
                    $fv = $schema->properties[$fv]['bind'];
                    if(isset($schema->properties[$fv]['type'])) $type = $schema->properties[$fv]['type'];
                } else if(is_array($fv) || is_object($fv)) {
                    if(isset($fv['type'])) $type = $fv['type'];
                    $fv = (isset($fv['bind']))?($fv['bind']):($fn);
                }
                if(strpos($fv, ' ')) {
                    $fv = trim(substr($fv, strrpos($fv, ' ')+1));
                }
                if($valueFormat===true) {
                    $v = $this->renderField($fv);
                } else if($valueFormat) {
                    $v = sprintf($valueFormat, $this->$fv);
                } else {
                    $v = $this->$fv;
                    if(is_string($v) && is_numeric($v) && $type!='string') {
                        if($type==='int')$v = (int) $v;
                        else if($type=='number')$v=(float)$v; 
                        else if($type=='bool')$v=(bool)$v; 
                    }
                }
                if(!is_null($v) || $valueFormat!==false) {
                    if($keyFormat===true) {
                        $k = $this->fieldLabel($fn);
                    } else if($keyFormat) {
                        $k = sprintf($keyFormat, $fn);
                    } else {
                        $k = $fn;
                    }
                    if(is_array($v) && $serialize && isset($schema['columns'][$fn]['serialize'])) {
                        $v = tdz::serialize($v, $schema['columns'][$fn]['serialize']);
                    } else if($serialize===false && is_string($v) && isset($schema['columns'][$fn]['serialize'])) {
                        $nv = tdz::unserialize($v, $schema['columns'][$fn]['serialize']);
                        if($nv!==false && $nv!==null) $v = $nv;
                    }
                    if(!tdz::isempty($v) || $valueFormat!==false) {
                        $result[$k] = $v;
                    }
                    unset($k, $v);
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
        $rk = array();
        if(!$r) {
            if(is_array($ro['local'])) {
                foreach ($ro['local'] as $i => $n) {
                    $rk[$n]=$ro['foreign'][$i];
                    unset($i, $n);
                }
            } else {
                $rk[$ro['local']]=$ro['foreign'];
            }
        }
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
            } else {
                // remove sub arrays, acceptable for search queries, but the names would fail
                foreach($f as $k=>$v) {
                    if(is_int($k) && is_array($v)) {
                        unset($f[$k]);
                        foreach($v as $vk=>$vv) {
                            $f[$vk] = $vv;
                            unset($v[$vk], $vk, $vv);
                        }
                    }
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
                if(isset($rk[$k])) {
                    $f[$rk[$k]]=$v;
                } else {
                    $f[self::_rn($k, $rrn, $rp)]=$v;
                }
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
        $pre = null;
        if(preg_match('/^[\|\&]/i', $k, $m)) {
            $pre = $m[0];
            $k = substr($k, strlen($m[0]));
        }
        if(strpos($k, '.')) {
            if(substr($k, 0, strlen($s))==$s) {
                $k = substr($k, strlen($s));
            }
        }
        return $pre.$rrn.'.'.$k;
    }

    public function getRelationQuery($relation, $part=null, $scope=null)
    {
        $r = array();
        $rev = '';

        //enable multiple, dotted queries
        $sc = static::$schema;
        $rn = $relation;
        $rev = '';
        while($rn) {
            if($p=strpos($rn, '.')) {
                $reln = substr($rn, 0, $p);
                $rn = substr($rn, $p+1);
                $rev = ($rev)?($reln.'.'.$rev):($reln.'.');
            } else {
                $reln = $rn;
                $rn = '';
            }
            if(!isset($rcn)) {
                $relation = $reln;
            }
            if(!isset($sc['relations'][$reln])) {
                throw new Tecnodesign_Exception(array(tdz::t('Relation "%s" is not available at %s.','exception'), $reln, $rcn));
            }
            $rel = $sc['relations'][$reln];
            $rcn = (isset($rel['className']))?($rel['className']):($reln);
            $sc = $rcn::$schema;
            unset($p);
        }

        $rel = static::$schema['relations'][$relation];
        $r = array('where'=>array());
        if(is_array($rel['local'])) {
            $this->refresh($rel['local']);
            foreach($rel['local'] as $i=>$fn) {
                $v = $this->$fn;
                if($v !== null && $v!==false) {
                    $r['where'][$rev.$rel['foreign'][$i]]=$v;
                }
            }
        } else {
            $this->refresh(array($rel['local']));
            $v = $this->{$rel['local']};
            if($v !== null && $v!==false) {
                $r['where'][$rev.$rel['foreign']]=$v;
            }
        }
        if($r['where'] && isset($rel['params']) && is_array($rel['params'])) $r['where'] += $rel['params'];
        if($scope) {
            $r['select']=$rcn::columns($scope, null, 3, true);
        }
        if(isset($sc->orderBy)) $r['orderBy'] = $sc->orderBy;
        unset($sc, $rel);

        if($part) {
            if(isset($r[$part])) return $r[$part];
            return;
        } else if(!$r['where']) {
            return; // should not return a full query if there's no fk
        }

        return $rcn::query($r, $rcn);
    }

    public function getRelation($relation, $fn=null, $scope=null, $asCollection=true)
    {
        if($p=strpos($relation, '.')) {
            $fn0 = $fn;
            if(is_null($fn)) $fn = substr($relation, $p+1);
            else $fn = substr($relation, $p+1).'.'.$fn;
            $relation = substr($relation, 0, $p);
        }
        if (!isset(static::$schema['relations'][$relation])) {
            throw new Tecnodesign_Exception(array(tdz::t('Relation "%s" is not available at %s.','exception'), $relation, get_called_class()));
        }
        $rel = static::$schema['relations'][$relation];
        $rcn = (isset($rel['className']))?($rel['className']):($relation);
        if (!class_exists($rcn)) {
            throw new Tecnodesign_Exception(array(tdz::t('Class "%s" does not exist.','exception'), $rcn));
        }
        $limit = (int)($rel['type']=='one');
        $local = $rel['local'];
        if($this->isNew()) {
            $rk = 'insert';
        } else if(is_array($local)) {
            $rk = $this->getPk();
            foreach($local as $l) {
                $rk .= '/'.$this->$l;
            }
        } else {
            $rk = $this->getPk().'/'.$this->$local;
        }

        if($p && !isset($rcn::$schema['columns'][$fn])) {
            $relation .= '.'.$fn;
            $fn = $fn0;
        }

        if(!$this->_relation) $this->_relation = array();

        if(!isset($this->$relation) || !isset($this->_relation[$relation]) || $this->_relation[$relation]!=$rk) {
            $this->unsetRelation($relation);
            $this->_relation[$relation]=$rk;
            $Q = null;
            if($fn && !isset($rcn::$schema['columns'][$fn])) {
                $Q = $this->getRelationQuery($relation.'.'.$fn, null, $scope);
                if($Q) {
                    $fn = null;
                    $relation .= '.'.$fn;
                }
            }
            if(!$Q) $Q = $this->getRelationQuery($relation, null, $scope);
            if(!$Q) {
                $this->$relation = false;
            } else if($asCollection) {
                $this->$relation = new Tecnodesign_Collection(null, $Q->schema('className'), $Q, null);
            } else {
                $this->$relation = $Q->fetch(0, $limit);
                if(!$this->$relation && !is_array($this->$relation)) {
                    $this->$relation = array();
                }
            }
            unset($Q);
        }
        if($fn) {
            if(isset($this->$relation)) {
                $R = $this->$relation;
                if(is_object($R) && ($R instanceof Tecnodesign_Collection)) $R = $R->getItems();
                else if(is_object($R)) return $this->$relation[$fn];

                $r = [];
                foreach($R as $i=>$o) {
                    $r[] = $o->$fn;
                    unset($R[$i], $i, $o);
                }
                return $r;
            }
        } else if(!$asCollection && is_object($this->$relation) && $this->$relation instanceof Tecnodesign_Collection) {
            return $this->$relation->getItems();
        } else {
            return $this->$relation;
        }
    }

    public function delete($save=true)
    {
        $this->_delete = true;
        if($save) {
            $this->save();
        }
        return $this->_delete;
    }

    public function setNew($v=null)
    {
        if(!is_null($v)) $this->_new = (bool) $v;
        else $this->_new = $v;
        return $this;
    }

    public function setDelete($v=null)
    {
        if(!is_null($v)) $this->_delete = (bool) $v;
        else $this->_delete = $v;
        return $this;
    }

    /**
     * sets a relation (another model linked to this)
     *
     * must compare to existing relation, to figure out if there should be elements removed
     */
    public function setRelation($relation, $value, $raw=false)
    {
        if($raw) {
            $this->$relation = $value;
            return $value;
        }

        if(!isset(static::$schema['relations'][$relation])) return false;
        // gather relation information
        $rel = static::$schema['relations'][$relation];
        $local = (is_array($rel['local']))?($rel['local']):(array($rel['local']));
        $foreign = (is_array($rel['foreign']))?($rel['foreign']):(array($rel['foreign']));

        /**
         * fetch the original relation. should be organized as an array of objects
         */
        $this->getRelation($relation, null, null, false);
        if($this->$relation instanceof Tecnodesign_Collection) { // if it's a collection, expand
            $this->$relation = $this->$relation->getItems();
            if(!$this->$relation) $this->$relation = array();
        } else if($rel['type']=='many') {
            if($this->$relation instanceof Tecnodesign_Model) $this->$relation = array($this->$relation);
            else if(!$this->$relation) $this->$relation=array();
        }

        /**
         * value is the actual relation to be set, should also be an array of objects
         */
        if($value instanceof Tecnodesign_Collection) { // if it's a collection, expand
            $value = $value->getItems();
        } else if($value instanceof Tecnodesign_Model) {
            if($rel['type']=='many') {
                $value = array($value);
            }
        } else if(is_object($value)) {
            $value = (array) $value;
        } else if(is_string($value)) {
            $value = explode(',',$value);
        } else if(is_array($value)) {
            $value = array_values($value);
        } else {
            $value = null;
        }

        // let's make it simple, if nothing is changing...
        if($value == $this->$relation) {
            unset($value);
            return $this->$relation;
        }

        // past here $this->$relation will be $value
        // we must ensure that it updates original values
        $O = $this->$relation;
        $this->$relation = null;

        /**
         * this is a map of the relation foreign keys (and where they point to)
         * we must ensure that they are set in each object
         */
        $lorel = array();
        foreach($local as $i=>$o) {
            $lorel[$o]=$foreign[$i];
            unset($i);
        }
        $cn = (isset($rel['className']))?($rel['className']):($relation);
        $rpk = $cn::pk($cn::$schema, true);


        if($rel['type']=='many') {
            if(count($rpk)>$lorel) $rfn = $rpk[count($rpk)-1];
            else {
                foreach($cn::$schema['columns'] as $xfn=>$xfd) {
                    if(!in_array($xfn, $foreign) && isset($cn::$schema['form'][$xfn]['type']) && in_array($cn::$schema['form'][$xfn]['type'], static::$_typesChoices)) {
                        $rfn = $xfn;
                        unset($xfn, $xfd);
                        break;
                    }
                    unset($xfn, $xfd);
                }
            }
            $map = array();
            $oks = array();

            // loop for each original values, see which aren't in $values
            foreach($O as $i=>$R) {
                if(is_string($R)) continue;
                $oks[$i] = $i;
                $pk = null;
                if($R instanceof Tecnodesign_Model) {
                    $pk = implode(',',$R->getPk(true));
                    if(!$pk) $pk = implode(',',$R->asArray());
                } else {
                    foreach($rpk as $j=>$n) {
                        $pk .= (is_null($pk))?($R[$n]):(','.$R[$n]);
                        unset($j, $n);
                    }
                    if(!$pk) $pk = implode(',',$R);
                }
                if(isset($map[$pk])) $map[$pk] = false;
                else $map[$pk] = $i;
                unset($i, $R, $pk);
            }
            if(!is_array($value)) $value = array();
            foreach($value as $i=>$v) {
                // try direct comparison first -- if it's $v is in $ro, there's nothing to do
                if(is_string($v)) {
                    $v = new $cn(array($rfn=>$v));
                } else if(is_array($v)) {
                    $v = new $cn($v);
                }
                foreach($lorel as $ln=>$rn) {
                    if(isset($this->$ln) && !isset($v[$rn])) {
                        $v[$rn] = $this->$ln;
                    }
                }
                $pk = implode(',', $v->getPk(true));
                $k = null;
                if($pk!=='' && isset($map[$pk])) {
                    $k = $map[$pk];
                    unset($map[$pk]);
                } else if(in_array($v, $O)) {
                    $k = array_search($v, $O);
                } else {
                    //if($pk==='' || (strpos($pk, ',')!==false && is_null($v->_new))) $v->isNew(true);
                    $O[] = $v;
                }

                if(!is_null($k)) {
                    $R = $O[$k];
                    unset($oks[$k]);
                    if(!is_object($R)) {
                        $d = $R;
                        $R = $v;
                        $O[$k]=$v;
                        $checkp=false;
                    } else {
                        $d = $v->asArray();
                        $checkp=true;
                    }
                    if(is_array($d)) {
                        foreach($d as $fn=>$fv) {
                            if(!isset($R->$fn) || $R->$fn!=$fv) $R[$fn]=$fv;
                            unset($d[$fn], $fn, $fv);
                        }
                    }
                    if($checkp) {
                        if($v->isNew() && !$R->isNew()) $R->isNew(true);
                        if($v->isDeleted() && !$R->isDeleted()) $R->isDeleted(true);
                    }
                    unset($checkp, $R);
                }
                unset($value[$i], $i, $pk, $v);
            }

            // what was left at $ro should be deleted, which means it needs to be added to $value, with _delete = true
            foreach($oks as $i=>$k) {
                $R = $O[$k];
                if($R && is_object($R) && $R->getPk() && !$R->isNew()) {
                    $R->delete(false);
                }
                unset($oks[$i], $i, $k, $R);
            }
            unset($value);
        } else {
            if(is_object($value) && ($value instanceof Tecnodesign_Model)) {
                $O = $value;
            } else {
                if(!$O || !($O instanceof Tecnodesign_Model)) {
                    $O = new $cn($O);
                }
                foreach($value as $k=>$v){
                    $O[$k] = $v;
                }
            }
        }
        $this->$relation = $O;
        unset($O);
        return $this->$relation;
    }

    public function unsetRelation($rn)
    {
        if($this->_relation && isset($this->_relation[$rn])) unset($this->_relation[$rn]);
        if(isset($this->$rn)) {
            if(is_array($this->$rn)) {
                $a =& $this->$rn;
                foreach($a as $i=>$o) {
                    unset($a[$i], $i, $o);
                }
                unset($a);
            }
            $this->$rn = null;
        }
    }

    public static function queryHandler()
    {
        return Tecnodesign_Query::handler(get_called_class());
    }

    public static function connect($conn=null, $Q=null)
    {
        if(!$conn) {
            $conn = static::$schema->database;
        }
        if(is_null($Q)) $Q = static::queryHandler();
        $C = $Q->connect($conn);
        if($C && is_object($C)) return $C;
        return $Q;
    }


    public static function beginTransaction($conn=null, $Q=null)
    {
        if(is_null($Q)) $Q = static::queryHandler();
        if($Q && method_exists($Q, 'transaction')) {
            return $Q->transaction(null, $conn);
        }
    }

    public static function commitTransaction($trans, $conn=null, $Q=null)
    {
        if(is_null($Q)) $Q = static::queryHandler();
        if($Q && method_exists($Q, 'commit')) {
            return $Q->commit($trans, $conn);
        }
    }

    public static function rollbackTransaction($trans, $conn=null, $Q=null)
    {
        if(is_null($Q)) $Q = static::queryHandler();
        if($Q && method_exists($Q, 'rollback')) {
            return $Q->rollback($trans, $conn);
        }
    }

    public function save($beginTransaction=null, $relations=null, $conn=false)
    {
        $cn = get_class($this);
        if(is_null($beginTransaction)) {
            $beginTransaction = static::$transaction;
        }
        if(is_null($relations)) $relations = static::$relationDepth;
        if(tdz::$perfmon) $perfmon = microtime(true);
        try {
            $this->_query = static::queryHandler();
            if(!$conn) {
                $conn = $this->_query->connect($cn::$schema->database);
            }

            if($conn && is_object($conn) && method_exists($conn, 'setAttribute')) {
                $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            }
            if(!$this->runEvent('before-save', $conn)) {
                throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $cn::label()));
            }

            $trans = false;
            if ($beginTransaction) {
                $trans = $cn::beginTransaction($conn, $this->_query);
                $defaultTransaction=self::$transaction;
                self::$transaction=false;
            }

            if(is_null($this->_delete)) {
                $this->_delete = false;
            }
            if(is_null($this->_new)) {
                $this->isNew();
            }
            $new = $this->_new;
            $m = null;
            if ($this->_delete) {
                $m = 'delete';
            } else if ($this->_new) {
                $m = 'insert';
            } else {
                $m = 'update';
            }
            if(!$this->runEvent('before-'.$m, $conn)) {
                throw new Tecnodesign_Exception(array(tdz::t('Could not '.$m.' record at %s.', 'exception'), $cn::label()));
            }
            if ($this->_delete) {
                $m = 'delete';
            } else if ($this->_new) {
                $m = 'insert';
            } else {
                $m = 'update';
            }

            $r = null;
            if(method_exists($this->_query, $m)) {
                $r = $this->_query->$m($this, $conn);
            } else {
                $r = false;
            }
            if($r===false) {
                throw new Tecnodesign_Exception(array(tdz::t('Could not '.$m.' record at %s.', 'exception'), $cn::label()));
            }

            if ($m==='delete' && !$this->_delete) {
                $m = 'update';
            }
            if(!$this->runEvent('after-'.$m, $conn)) {
                throw new Tecnodesign_Exception(array(tdz::t('Could not '.$m.' record at %s.', 'exception'), $cn::label()));
            }

            // Run dependencies
            if($relations && $cn::$schema->relations) {
                $relations--;
                foreach ($cn::$schema->relations as $rcn=>$rd) {
                    $rc=(isset($rd['className']))?($rd['className']):($rcn);
                    if(!tdz::classFile($rc) || !isset($rc::$schema) || (isset($rc::$schema['type']) && $rc::$schema['type']=='view')) {
                        continue;
                    }
                    if(isset($this->$rcn)) {
                        $rel = $this->$rcn;
                        if(is_object($rel) && ($rel instanceof Tecnodesign_Collection)) {
                            unset($rel);
                            continue;
                        }
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
                            foreach($rel as $relk=>$relo) {
                                if(!is_object($relo) || !($relo instanceof self)) continue;
                                foreach($rv as $fn=>$fv) {
                                    $relo[$fn]=$fv;
                                }
                                $relo->save(false, $relations, $conn);
                                unset($rel[$relk], $relk, $relo);
                            }
                        }
                        unset($rel, $rv, $lfn, $rfn);
                    }
                }
            }
            if($trans) {
                if(!$cn::commitTransaction($trans, $conn, $this->_query)) {
                    throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $cn::label()));
                }
                self::$transaction=$defaultTransaction;
            }
            if(!$this->runEvent('after-save', $conn)) {
                throw new Tecnodesign_Exception(array(tdz::t('Could not save %s.', 'exception'), $cn::label()));
            }
        } catch(Exception $e) {
            tdz::log('[WARNING] Error while saving: '.$e->getMessage());

            $msg = ($e instanceof Tecnodesign_Exception)?($e->getMessage()):('');
            if(!(substr($msg, 0, 1)=='<' && strpos(substr($msg, 0, 100), 'tdz-i-msg'))) {
                if($msg) $msg = "\n".tdz::t('Issues are', 'exception').":\n".$msg;
                $msg = array(tdz::t('Could not save %s.', 'exception').$msg, $cn::label());
            }

            if (isset($trans) && $trans) {
                $cn::rollbackTransaction($trans, $conn, $this->_query);
                self::$transaction=$defaultTransaction;
            }
            throw new Tecnodesign_Exception($msg);
        }
        if(tdz::$perfmon>0) tdz::log('[INFO] '.__METHOD__.': '.tdz::formatNumber(microtime(true)-$perfmon).'s '.tdz::formatBytes(memory_get_peak_usage())." mem");
        return true;
    }

    public function auditLog($action, $pk, $data=null)
    {
        if(!($a=static::$schema->audit)) return;

        if(is_array($a) && isset($a[0]) && $a=['type'=>'file','file'=>$a[0]]);

        $log = [
            'uid'=>tdz::getUser()->uid(),
            'date'=>TDZ_TIMESTAMP,
            'source'=>Tecnodesign_App::request('ip'),
            'target'=>tdz::requestUri(),
            'database'=>static::$schema->database,
            'table'=>static::$schema->tableName,
            'action'=>$action,
            'key'=>$pk,
            'data'=>$data,
        ];
        if(!is_array($a) || !isset($a['type']) || $a['type']==='log') {
            // save to log
            if((is_string($a) && (file_exists($f=TDZ_VAR.'/'.$a) || touch($f=TDZ_APP_ROOT.'/'.$a)))
                || (is_array($a) && isset($a['file']) && (file_exists($f=TDZ_VAR.'/'.$a['file']) || touch($f=TDZ_APP_ROOT.'/'.$a['file'])))) {
                error_log(tdz::serialize($log, 'json')."\n", 3, $f);
            } else {
                tdz::log($log);
            }
        } else if(isset($a['type']) && $a['type']==='object' && isset($a['className']) && ($cn=$a['className'])) {
            new $cn($log, true, true);
        } else {
            tdz::log('[INFO] Unknown audit log option: '.tdz::serialize($a, 'json'), $log);
        }
    }

    public static function replace($data, $q=null, $scope=null, $save=true)
    {
        if(!$q) {
            $pk = static::pk(null, true);
            $q = [];
            foreach($pk as $fn) {
                if(!isset($data[$fn])) return null;
                $q[$fn] = $data[$fn];
                unset($fn);
            }
            unset($pk);
        }

        if(!($M=static::find($q,1,$scope))) {
            $cn = get_called_class();
            $M = new $cn($data, true, $save);
        } else {
            foreach($data as $fn=>$v) {
                if(isset(static::$schema->properties[$fn]) && $M->$fn==$v) {
                    continue;
                }
                $M->safeSet($fn, $v);
            }
            if($save && isset($M->_original) && $M->_original) {
                $M->isNew(false);
                $M->save();
            }
        }

        return $M;
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
        if(is_array($fn)) {
            $s = '';
            foreach($fn as $n) $s .= (($s)?($cn::$keySeparator):('')).$cn::fieldLabel($n, $translate);
            return $s;
        }
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
        if(!$groupBy && ($c = static::pk(null, true, true))) {
            $q['select'] = $c;
            //$q['select'] = array_merge($c, static::columns($scope, null, 3, true));
            unset($c);
        }
        $q['scope'] = $scope;
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
        else if(isset(static::$schema->orderBy)) $q['orderBy'] = static::$schema->orderBy;
        if(!is_null($groupBy) && !is_bool($groupBy)) $q['groupBy'] = $groupBy;
        else if(isset(static::$schema->groupBy)) $q['groupBy'] = static::$schema->groupBy;
        $q['limit'] = $limit;
        $Q = static::query($q);
        if(!$Q) {
            return false;
        } else if($limit==1) {
            $r = $Q->fetch(0, $limit);
            unset($Q);
            return ($r)?(array_shift($r)):(false);
        } else if(!$collection) {
            if(!$limit) return $Q->fetch();
            else return $Q->fetch(0, $limit);
        } else if(!$Q->count()) {
            return false;
        } else {
            return new Tecnodesign_Collection(null, get_called_class(), $Q, null);
        }
    }

    public static function query($q=null, $cn=null, $asArray=false)
    {
        if(!$cn) $cn = get_called_class();
        if($q) return Tecnodesign_Query::handler($cn)->find($q, $asArray);
        else return Tecnodesign_Query::handler($cn);
    }

    public static function fetch($pk)
    {
        if(method_exists($H=Tecnodesign_Query::handler($cn=get_called_class()),'preview')) {
            if(is_array($pk)) $pk = implode(static::$keySeparator, $pk);
            return $H->preview($pk);
        } else {
            return $cn::find($pk,1);
        }
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

    public function renderScope($scope=null, $xmlEscape=true, $box=null, $tpl=null, $sep=null, $excludeEmpty=null, $showOriginal=null)
    {
        static $flabel;
        $id = $scope;
        $s = '';
        if(!is_array($scope) && method_exists($this, $m='renderScope'.tdz::camelize($scope, true))) {
            $s = $this->$m();
            $scope = array();
        } else if(!is_array($scope)) {
            $scope = static::columns($scope, null, false);
        } else {
            $id = static::$schema['tableName'];
        }

        $nobox = !$box;
        if(!$box) $box = static::$boxTemplate;
        if(!$sep) $sep = static::$headingTemplate;
        if(!$tpl) $tpl = static::$previewTemplate;
        $a = '';
        $fs = array(); // split into fieldsets
        $hs = []; // headings for $excludeEmpty
        $fsn='';
        foreach($scope as $label=>$fn) {
            $fd = null;
            $overlay = false;
            if(!is_array($fn)) {
                $fd = static::column($fn, true, true);
                $overlay = true;
            }
            if(is_array($fn)) {
                $fd = $fn;
                if(isset($fd['bind'])) $fn=$fd['bind'];
                else $fn='';
            }
            if($fd) {
                if(isset($fd['fieldset']) && substr($fd['fieldset'], 0, 1)=='*')  {
                    if(!isset($translate)) $translate = 'model-'.static::$schema->tableName;
                    $fd['fieldset'] = tdz::t(substr($fd['fieldset'], 1), $translate);
                }

                if(isset($fd['label']) && is_int($label)) $label = $fd['label'];
                if(isset($fd['fieldset'])) {
                    $fsn = $fd['fieldset'];
                }
                if(isset($fd['credential'])) {
                    if(!isset($U)) $U=tdz::getUser();
                    if(!$U || !$U->hasCredentials($fd['credential'], false)) continue;
                }
            }

            if(is_integer($label)) $label = ($fn) ?static::fieldLabel($fn, false) :'';
            if(substr($label, 0, 1)=='*') {
                if(!isset($translate)) $translate = 'model-'.static::$schema->tableName;
                $label = tdz::t(substr($label, 1), $translate);
            }

            // headings
            if(!$fd && substr($fn, 0, 2)=='--' && substr($fn, -2)=='--') {
                $class = (!is_int($label))?($label):('');
                $label = substr($fn, 2, strlen($fn)-4);
                $fsn = $label;
                if(!isset($fs[$fsn])) $fs[$fsn]='';
                $fs[$fsn] .= str_replace(array('$LABEL', '$ID', '$INPUT', '$CLASS', '$ERROR'), array($label, $fn, $label, $class, ''), $sep);
                unset($class);
            } else {
                if($fsn && !isset($fs[$fsn]) && $fsn!=$flabel) {
                    $flabel = $fsn;
                    $class = tdz::slug($label);
                    if(strlen($class)>20) preg_replace('/(.{20}[^\-]+)\-.*/', '$1', $class);
                    $h[$fsn] = str_replace(array('$LABEL', '$ID', '$INPUT', '$CLASS', '$ERROR'), array($fsn, $fn, $fsn, $class, ''), $sep);
                }

                $class = '';
                $ftext = null;
                if(preg_match('/^([a-z0-9\-\_]+)::([a-z0-9\-\_\,]+)(:[a-z0-9\-\_\,\!]+)?$/i', $fn, $m)) {
                    if(isset($m[3])) {
                        if(!isset($U)) $U=tdz::getUser();
                        if(!$U || !$U->hasCredential(preg_split('/[\,\:]+/', $m[3], null, PREG_SPLIT_NO_EMPTY), false)) {
                            continue;
                        }
                    }
                    if($m[1]=='scope') {
                        $ftext = $this->renderScope($m[2], $xmlEscape, $box, $tpl, $sep, $excludeEmpty, $showOriginal);
                    } else if($m[1]=='sub') {
                        $class = $fn = $m[2];
                        $class .= ($class)?(' sub'):('sub');
                        $input = str_replace(
                            array('$LABEL', '$ID', '$INPUT', '$CLASS', '$ERROR'),
                            array($label, $fn, $label, $class, ''),
                            $sep);

                        $ftext = str_replace(
                            array('$LABEL', '$ID', '$INPUT', '$CLASS', '$ERROR', '$ATTR'),
                            array($label, $fn, $input, $class, '', ''),
                            $box);

                        $class = null;
                        unset($input);
                    } else {
                        $ftext = '';
                    }
                    $v = false;
                } else if(tdz::isempty($fn)) {
                    continue;
                } else {
                    if($p=strrpos($fn, ' ')) $fn = substr($fn, $p+1);
                    if($fd && $fn && !$overlay) {
                        $fd1 = static::column($fn,true,true);
                        if($fd1) $fd += $fd1;
                        unset($fd1);
                    } else if(!$fd && $fn) {
                        $fd=static::column($fn,true,true);
                    }
                    if(isset($fd['on']) && !isset($fd['className']) && !$this->checkObjectProperties($fd['on'])) {
                        continue;
                    }
                    $v = $this->renderField($fn, $fd, $xmlEscape);
                    if(is_array($v)) {
                        $v = tdz::implode($v, static::$listSeparator);
                    }
                    if($showOriginal && array_key_exists($fn, $this->_original)) {
                        $v0 = (isset($this->_original[$fn]))?($this->_original[$fn]):(null);
                        $v1 = (isset($this->$fn))?($this->$fn):(null);
                        if($v0!=$v1) {
                            $this->$fn = $v0;
                            $nv = $this->renderField($fn, $fd, $xmlEscape);
                            if(is_array($nv)) {
                                $nv = tdz::implode($v, static::$listSeparator);
                            }
                            if($nv!=$v) {
                                $v = '<span class="tdz-m-original">'.$nv.'</span>'
                                   . '<span class="tdz-m-value">'.$v.'</span>'
                                   ;
                            }
                            unset($nv);
                            $this->$fn = $v1;
                        }
                        unset($v0, $v1);
                    }
                    if(isset($fd['class'])) $class = $fd['class'];
                    if(isset($fd['type']) && $fd['type']=='interface') {
                        $ftext .= $v;
                    }
                }
                if(substr($label, 0, 2)=='a:') {
                    if($v && !$xmlEscape) $v = tdz::xmlEscape($v);
                    $a .= ' '.substr($label, 2).'="'.$v.'"';
                } else if(is_null($ftext) && $v!==false && !($excludeEmpty && !$v)) {
                    if(strpos($fn, ' ')) $fn = substr($fn, strrpos($fn, ' ')+1);
                    $ftext = str_replace(array('$LABEL', '$ID', '$INPUT', '$CLASS', '$ERROR'), array($label, $fn, $v, $class, ''), $tpl);
                }

                if($ftext) {
                    if(isset($fs[$fsn])) $fs[$fsn] .= $ftext;
                    else if(isset($h[$fsn])) $fs[$fsn] = $h[$fsn].$ftext;
                    else $fs[$fsn] = $ftext;
                }
                unset($scope[$label], $label, $fn, $v, $m, $class, $fd);
            }
        }
        $s .= implode('', $fs);
        unset($fs);
        if($nobox) return $s;
        $s = str_replace(array('$LABEL', '$ID', '$INPUT', '$CLASS', '$ERROR', '$ATTR'), array((strpos($box, '$LABEL')!==false)?(static::label()):(''), $id, $s, '', '', $a), $box);
        return $s;
    }

    public function checkObjectProperties($a)
    {
        if(!is_array($a)) return true;

        $keys = array();
        $rels = array();
        foreach($a as $fn=>$v) {
            if(strpos($fn, '.')) {
                $rels[$fn] = $v;
                unset($a[$fn]);
            } else if(!is_int($fn) && !isset($this->$fn)) {
                $keys[] = $fn;
            }
        }

        if($keys) $this->refresh($keys);
        foreach($a as $fn=>$check) {
            $v = $this->$fn;
            if(!tdz::isempty($check)) {
                if(is_array($check) && !in_array($v, $check)) return false;
                else if(!is_array($check) && $check!=$v) return false;
            } else if(!tdz::isempty($v)) {
                return false;
            }
        }
        if($rels) {
            $rels += $this->getPk(true);
            $ret = (($C=$this::find($rels,0,null,true)) && $C->count()>0);
            unset($C);
            return $ret;
        }
        return true;
    }

    public function renderUi($o=array())
    {
        static $group;
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

        $uid = null;
        if(isset($o['link-generator']) && method_exists($this, $o['link-generator'])) {
            $link = $this->{$o['link-generator']}();
            $uid = $this->getPk();
            $replace = false;
        } else if(isset($o['link'])) {
            $link = $o['link'];
        } else {
            $link = tdz::scriptName();
        }
        $linkScope = (isset($o['link-scope'])) ?$o['link-scope'] :null;
        $i = (isset($o['position']))?($o['position']):(0);
        $start = (isset($o['start']))?($o['start']):($i);
        $max = (isset($o['hits']))?($o['hits']):(20);

        $sf = null;
        if(!is_array($link) && isset(tdz::$variables['Interface']) && is_object(tdz::$variables['Interface'])) {
            $I = tdz::$variables['Interface'];
            $sf = Tecnodesign_App::request('get', $I::REQ_ORDER);
            if(strpos($sf, ',')) $sf = substr($sf, 0, strpos($sf, ','));
            if($sf && substr($sf, 0, 1)=='!') {
                $sd = 'desc';
                $sf = substr($sf,1);
            } else {
                $sd = 'asc';
            }
            $qs = Tecnodesign_App::request('query-string');
            if($qs && ($qs=preg_replace('/&?(ajax|'.$I::REQ_ORDER.')(=[^&]+)?/', '', $qs))) {
                $qs = preg_replace('/^[?&]+|&$/', '', $qs);
                $qsb = ($qs)?('?'.$qs.'&'):('?');
                $qs = ($qs)?('?'.$qs):('');
            } else {
                $qsb = '?';
            }
            $qslink = null;
            $link = preg_replace('/\?.*$/', '', $link);
        } else {
            $qs = Tecnodesign_Ui::$qs;
            $qsb = ($qs)?(substr($qs,1)):('');
            if($qsb) {
                $qsb = preg_replace('#&?(o|d|p)=[^&]*#', '', $qsb);
                if(substr($qsb, 0, 1)=='&') $qsb = substr($qsb,1);
            }
            $qsb=($qsb)?('?'.$qsb.'&'):('?');
            if(!(($sf=Tecnodesign_App::request('get', 'o')) && is_numeric($sf) && $sf<count($labels))) {
                $sf = (isset($schema->orderBy))?(array_keys($schema->orderBy)):(array(''));
                $sf=$sf[0];
            }
            if(!(($sd=Tecnodesign_App::request('get', 'd')) && ($sd=='asc' || $sd=='desc'))) {
                $sd=(isset($schema->orderBy[$sf]))?($schema->orderBy[$sf]):('asc');
            }
            $qslink = $qs;
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
        $groupBy = $groupClass=null;
        foreach($labels as $label=>$fn) {
            if(substr($label, 0, 2)=='a:') {
                $fn = ($p=strrpos($fn, ' '))?(substr($fn, $p+1)):($fn);
                $ba[substr($label,2)] = $this->$fn;
                unset($labels[$label], $p);
            } else if(substr($label, 0, 2)=='g:') {
                $fn = ($p=strrpos($fn, ' '))?(substr($fn, $p+1)):($fn);
                $groupBy = $fn;
                $groupClass = substr($label, 2);
                unset($labels[$label], $p);
            }
            unset($label, $fn);
        }

        $ext = (isset($o['extension']))?($o['extension']):('');

        if($i==$start) {
            if($groupBy && $group) $group=null;
            $s .= '<table class="app-list"'.$tattr.'><thead><tr>';
            $first = true;
            $so = 1;
            foreach($labels as $label=>$fn) {
                if(is_array($fn)) {
                    $fd = $fn;
                    if(isset($fd['bind'])) $fn=$fd['bind'];
                    else $fn=$label;
                    if(isset($fd['credential'])) {
                        if(!isset($U)) $U=tdz::getUser();
                        if(!$U || !$U->hasCredentials($fd['credential'], false)) continue;
                        unset($fd['credential']);
                    }
                } else {
                    $fd = null;
                }
                $sort = !preg_match('/\.[A-Z]/', $fn);
                $fid = (preg_match('/\s+_?([\_a-z0-9]+)$/i', $fn, $m))?($m[1]):(str_replace(array('`', '[', ']'), '', $fn));
                if(is_numeric($label)) $label = tdz::t(ucwords(str_replace('_', ' ', $fid)), 'model-'.$model);
                else if(substr($label,0,1)=='*') $label = tdz::t(substr($label,1), 'model-'.$model);

                if(isset($I)) {
                    $sc = $label;
                    $soa = $I::REQ_ORDER.'='.rawurlencode($sc);
                    $sod = $I::REQ_ORDER.'=!'.rawurlencode($sc);
                } else {
                    $sc = $so;
                    $soa = 'o='.urlencode($so).'&d=asc';
                    $sod = 'o='.urlencode($so).'&d=desc';
                }
                $s .= '<th class="c-'.$so.' f-'.$fid.(($sc==$sf)?(' ui-order ui-order-'.$sd):('')).'">'
                    . ((isset($first) && $checkbox==='checkbox')?('<input type="checkbox" data-callback="toggleInput" label="'.tdz::t('Select all', 'ui').'" data-label-alternative="'.tdz::t('Clear selection', 'ui').'" />'):(''))
                    ;
                if($sort && isset($o['show-sort-icons']) && $o['show-sort-icons']) {
                    $s .= $label
                        . (($sort)?('<a href="'.tdz::scriptName().$ext.tdz::xml($qsb.$soa).'" class="z-i--up icon asc"></a>'):(''))
                        . (($sort)?('<a href="'.tdz::scriptName().$ext.tdz::xml($qsb.$sod).'" class="z-i--down icon desc"></a>'):(''))
                        ;
                } else if($sort) {
                    $attrs = '';
                    $asort =  tdz::scriptName().$ext.tdz::xml($qsb.$soa);
                    if($sf==$sc) {
                        $label = '<strong>'.$label.'</strong>';
                        if($sd=='asc') {
                            $asort = tdz::scriptName().$ext.tdz::xml($qsb.$sod);
                            $attrs = ' class="z-i--up z-icon-right asc"';
                        } else {
                            $attrs = ' class="z-i--down z-icon-right desc"';
                        }
                    }
                    $s .= '<a href="'.$asort.'"'.$attrs.'>'.$label.'</a>';
                } else {
                    $s .= '<a>'.$label.'</a>';
                }

                $s .= '</th>';
                $so++;
                unset($label, $fn, $sort, $first);
            }
            $s .= '</tr></thead><tbody>';
        }
        if($groupBy && ($gv=$this->renderField($groupBy, null, true)) && $gv!=$group) {
            $group = $gv;
            if($i>$start) {
                $s .= '</tbody><tbody>';
            }
            $s .= '<tr><th colspan="'.count($labels).'" class="'.$groupClass.'">'.$gv.'</th></tr>';
        }
        if(!is_array($link)) {
            if($link!==false || $checkbox) {
                $uid = (isset($o['key'])) ?$this[$o['key']] :$this->getPk();//str_replace('-', ',', $this->getPk());
                if(isset($o['link-encode']) && $o['link-encode']) {
                    $uid = rawurlencode($uid);
                }
            } else {
                $uid=false;
            }
            $url = ($link)?($link.'/'):(false);
        } else {
            $url=false;
        }

        $qs = $qslink;
        if($qs=='?')$qs='';
        if(!isset($ba['class'])) $ba['class'] = (($i%2)?('even'):('odd'));
        $s .= '<tr';
        foreach($ba as $p=>$v) $s .= ' '.$p.'="'.$v.'"';
        $s .= '>';
        $first = true;
        foreach($labels as $label=>$fn) {
            if(is_array($fn)) {
                $fd = $fn;
                if(isset($fd['bind'])) $fn=$fd['bind'];
                else $fn=$label;
                if(isset($fd['credential'])) {
                    if(!isset($U)) $U=tdz::getUser();
                    if(!$U || !$U->hasCredentials($fd['credential'], false)) continue;
                    unset($fd['credential']);
                }
            } else {
                $fd = null;
            }

            $fn = trim($fn);
            if($p=strrpos($fn, ' ')) {
                $fn = substr($fn, $p+1);
            }
            $value = $this->renderField($fn, $fd, true);

            $fullurl = null;
            if(is_array($link)) {
                if(isset($link[$fn])) {
                    if(!isset($replace)) {
                        $replace = $this->asArray($linkScope, '{%s}');
                        if(isset($o['link-encode']) && $o['link-encode']) {
                            foreach($replace as $k=>$v) {
                                $replace[$k] = rawurlencode($v);
                            }
                        }
                    }
                    $fullurl = ($replace) ?str_replace(array_keys($replace), array_values($replace), $link[$fn]) :$link[$fn];
                } else {
                    $uid = false;
                    $url = false;
                }
            } else if($uid!==false && $url!==false) {
                $fullurl = $url.$uid.$ext.$qs;
            }

            if(substr($fn, 0, 1)=='_') $fn = substr($fn,1);
            $lattrs = null;
            if($fullurl && isset($o['link-class'])) $lattrs = ' class="'.\tdz::xml($o['link-class']).'"';

            $s .= '<td class="f-'.$fn.' '.(($uid!==false && $checkbox)?(' tdz-check'):('')).'">'
                . (($uid!==false && $checkbox)?('<input type="'.$checkbox.'" id="uid-'.tdz::xml($this->getPk()).'" name="uid'.(($checkbox==='checkbox')?('[]'):('')).'" value="'.$uid.'" />'):(''))
                . (($fullurl)?('<a href="'.tdz::xml($fullurl).'"'.$lattrs.'>'.$value.'</a>'):($value))
                .'</td>';

            if($uid!==false) $uid=false;
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

        if(isset($fd['credential'])) {
            if(!($U=tdz::getUser()) || !$U->hasCredentials($fd['credential'], false)) return;
            unset($fd['credential']);
        }
        $pm='preview'.ucfirst(tdz::camelize($fn));
        $m='get'.ucfirst(tdz::camelize($fn));
        $getRef = false;
        if(method_exists($this, $pm)) {
            $v = $this->$pm();
            if($xmlEscape) $xmlEscape = false;
        } else if(method_exists($this, $m)) {
            $v = $this->$m();
            $getRef = true;
        } else if(isset($fd['type']) && $fd['type']=='interface' && isset($fd['interface']) && isset($fd['bind']) && isset(static::$schema['relations'][$fd['bind']])) {
            $cI=Tecnodesign_Interface::current();
            $icn = ($cI)?(get_class($cI)):(Tecnodesign_Interface::$className);
            $I = new $icn($fd['interface'], $cI);
            $I->setSearch($this->getRelationQuery($fd['bind'], 'where'));
            if(isset($fd['action'])) $a = $fd['action'];
            else if(static::$schema['relations'][$fd['bind']]['type']=='one') $a = 'preview';
            else $a = 'list';
            if($cI) $I->setTitle(null);
            $I->setAction($a);
            $I->setUrl(tdz::scriptName(true).'/'.$fd['interface']);
            $v = $I->preview();
            $xmlEscape = false;
        } else if(isset($fd['type']) && $fd['type']=='file' && isset($fd['accept']['inline-preview']) && $fd['accept']['inline-preview'] && ($f=Tecnodesign_Image::base64Data($this[$fn]))) {
            $v = '<img src="'.((is_array($f))?(implode('" /><img src="', $f)):($f)).'" />';
            $xmlEscape = false;
        } else if(isset($fd['type']) && $fd['type']=='bool') {
            $v = ($this[$fn] > 0) ?tdz::t('Yes', 'interface') :tdz::t('No', 'interface');
        } else if(isset($fd['type']) && $fd['type']=='object') {
            $v = $this[$fn];
            if($v && !is_array($v)) $v = tdz::unserialize($v, (isset($fd['serialize'])) ?$fd['serialize'] :'json');
            if($v) {
                $v = preg_replace('/^---\n/', '', Tecnodesign_Yaml::dump($v));
            }
        } else {
            $v = $this[$fn];
            $getRef = true;
        }
        if(!$getRef) {
        } else if($v instanceof Tecnodesign_Collection) {
            return $this->renderRelation($v, $fn, $fd, $xmlEscape);
        } else if(!tdz::isempty($v)) {
            if(isset($fd['serialize']) && is_string($v) && substr($v, 0, 1)==='[' && substr($v, -1)===']') {
                $v = tdz::unserialize($v, $fd['serialize']);
            } else if(isset($fd['multiple']) && $fd['multiple'] && is_string($v) && strpos($v, ',')!==false) {
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
                        return $this->renderRelation($choices::find($v,$multiple,'choices',false), $choices, $fd, $xmlEscape);
                    } else if(method_exists($this, $choices)) {
                        $choices = $this->$choices();
                    } else if(tdz::$enableEval) {
                        $choices = @eval('return '.$choices.';');
                    } else {
                        tdz::log('[DEPRECATED] eval funcions are no longer supported. Please review the choices for '.$cn.'->'.$fn.': '.$choices);
                        $choices = [];
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
                            unset($v);
                        }
                        $v = implode(', ', $r);
                    } else {
                        $v = (isset($choices[$v]))?($choices[$v]):('');
                        if(is_array($v)) $v = array_shift($v);
                    }
                }
                unset($choices);
            } else if(isset($fd['type'])) {
                if(isset($fd['format']) && substr($fd['format'],0,4)=='date') {
                    if($t=strtotime($v)) {
                        $df = ($fd['format']=='datetime')?(tdz::$dateFormat.' '.tdz::$timeFormat):(tdz::$dateFormat);
                        $v = date($df, $t);
                    }
                } else if(isset($fd['format']) && $fd['format']=='html' && isset($fd['html_labels']) && $fd['html_labels']) {
                    $xmlEscape = false;
                    $v = tdz::safeHtml($v);
                } else if(substr($fd['type'], 0, 3)=='int') {
                    if(is_numeric($v)) $v = (int)$v;
                } else if($fd['type']=='number') {
                    if(preg_match('/^[^0-9]*(\.[0-9]+)?$/', $v)) $v = (float)$v;
                }
            }
        } else if(isset($fd['local']) && isset($fd['foreign'])) {
            // relation
            $scope = (is_array($fd) && isset($fd['scope']))?($fd['scope']):(null);
            return $this->renderRelation($this->getRelation($fn, null, $scope, false, $xmlEscape), $fn, $fd, $xmlEscape);
        }
        if($xmlEscape) {
            if(!is_int($v) && !is_float($v)) {
                $v = str_replace(array('  ', "\n"), array('&#160; ', '<br />'), tdz::xml($v));
            }
        }

        return $v;
    }

    public function renderRelation($v, $rn=null, $rd=null, $xmlEscape=false)
    {
        if(!$v) return;
        if(is_object($v) && ($v instanceof Tecnodesign_Collection)) $v=$v->getItems();
        if(is_array($v)) $v=implode(', ', $v);
        else $v=(string)$v;
        if($xmlEscape) {
            return str_replace(array('  ', "\n"), array('&#160; ', '<br />'), tdz::xml($v));
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
        } else if(isset($fd['format']) && substr($fd['format'],0,4)=='date') {
            if($t=strtotime($val)) {
                $df = ($fd['format']=='datetime')?(tdz::$dateFormat.' '.tdz::$timeFormat):(tdz::$dateFormat);
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
        $scope = $cn::$schema->scope;
        if(isset($scope) && count($scope)>0) {
            $sc = (isset($scope['string']))?($scope['string']):(array_shift($scope));
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

    public function validate($def, $value, $name=null)
    {
        $ovalue = $value;
        if (!is_null($name) && method_exists($this, $m='validate'.tdz::camelize($name, true))) {
            $value = $this->$m($value);
        }

        //@TODO: must handle this...
        if(($value===null || $value==='') && !isset($def['default']) && isset(static::$schema->overlay[$name]['default'])) $def['default'] = static::$schema->overlay[$name]['default'];

        return Tecnodesign_Schema::validateProperty($def, $value, $name);
    }

    public static function __set_state($a)
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


    public function getOriginal($fn, $fallback=true, $serialize=null)
    {
        if(!array_key_exists($fn, $this->_original)) {
            if($fallback) {
                $this->_original[$fn] = $this->$fn;
            } else {
                return $fallback;
            }
        }
        $v = $this->_original[$fn];
        if($serialize && !is_string($v) && !is_null($v) && !is_bool($v)) {
            if(isset(static::$schema['columns'][$fn]['serialize'])) $serialize=static::$schema['columns'][$fn]['serialize'];
            return tdz::serialize($v, $serialize);

        }
        return $v;
    }

    public function setOriginal($n, $v)
    {
        $this->_original[$n] = $v;
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
        $m='get'.tdz::camelize($name, true);
        $ret = false;
        $dot = strpos($name, '.');
        if($dot!==false) {
            @list($firstName,$ref)=explode('.', $name, 2);
        } else {
            $firstName = $name;
        }
        if (method_exists($this, $m)) {
            $ret = $this->$m();
        } else if(!isset(static::$schema->properties[$firstName]) && strstr('ABCDEFGHIJKLMNOPQRSTUVWXYZ!', substr($name, 0, 1))) {
            if($dot && isset($this->$firstName)) {
                if(is_object($this->$firstName)) return $this->$firstName->$ref;
                else if(isset($this->$firstName[$ref])) return $this->$firstName[$ref];
            } else if($dot && isset(static::$schema->relations[$firstName])) {
                return $this->getRelation($firstName, $ref);
            } else if(isset($this->$name)) {
                return $this->$name;
            } else if(isset(static::$schema->relations[$name])) {
                return $this->getRelation($name);
            }
        } else if (isset($this->$name)) {
            $ret = $this->$name;
        } else if($dot && $firstName && $ref && (isset($this->$firstName) || method_exists($this, $m='get'.tdz::camelize($firstName, true)))) {
            if(method_exists($this, $m='get'.tdz::camelize($firstName, true))) {
                $a = $this->$m();
            } else if(isset(static::$schema->properties[$firstName]['serialize']) && is_string($this->$firstName)) {
                $this->$firstName = tdz::unserialize($this->$firstName, static::$schema->properties[$firstName]['serialize']);
                $a = $this->$firstName;
            } else {
                $a = $this->$firstName;
            }
            if(isset($a[$ref])) {
                $ret = $a[$ref];
                unset($a);
            } else if(strpos($ref, '.')) {
                while($p=strpos($ref, '.')) {
                    $n = substr($ref, 0, $p);
                    if(!isset($a[$n])) {
                        unset($a);
                        break;
                    }
                    $a = $a[$n];
                    $ref = substr($ref, $p+1);
                    unset($n, $p);
                }
                if(isset($a[$ref])) {
                    $ret = $a[$ref];
                }
                unset($a);
            }
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
        return $this->safeSet($name, $value);
    }

    public function batchSet($values, $skipValidation=false)
    {
        if(is_object($values) && ($values instanceof $this)) {
            $fns = array_merge(array_keys(static::$schema->properties), array_keys(static::$schema->relations));
            foreach($fns as $fn) {
                if(isset($values->$fn) && $values->$fn!==$this->$fn) {
                    $this->safeSet($fn, $values->$fn, $skipValidation);
                }
            }
        } else if(is_array($values)) {
            foreach($values as $name=>$value) {
                $this->safeSet($name, $value, $skipValidation);
            }
        }
        return $this;
    }

    public function safeSet($name, $value, $skipValidation=false)
    {
        if($name=='ROWSTAT') return $this;
        if(substr($name,0, 1)=='`' && substr($name, -1)=='`') $name = substr($name, 1, strlen($name)-2);
        if(isset(static::$schema->properties[$name]) && ($a=static::$schema->properties[$name]->alias)) {
            $name = $a;
            unset($a);
        }
        $mn=tdz::camelize($name, true);

        if(isset(static::$schema->properties[$name]) && !array_key_exists($name, $this->_original)) {
            $this->_original[$name] = $this->$name;
        }
        if (strpos($name, '.') !== false) {
            list($firstName, $ref) = explode('.', $name, 2);
        } else {
            $firstName = $name;
            $ref = null;
        }

        if (method_exists($this, $m='set'.$mn)) {
            $this->$m($value);
        } else if(isset(static::$schema->properties[$name])) {
            if(!$skipValidation) {
                $value = $this->validate(static::$schema->properties[$name], $value, $name);
            }
            $this->$name=$value;
        } else if(isset(static::$schema->relations[$name])) {
            $this->setRelation($name, $value);
        } else if($firstName && $ref && method_exists($this, $m='set'.tdz::camelize($firstName, true))) {
            $this->$m(array($ref=>$value));
        // add other options for dotted.names?
        } else if($firstName && $ref && (isset($this->$firstName) || isset(static::$schema->properties[$firstName]))) {
            if(!($serialize=static::$schema->properties[$firstName]->serialize) && static::$schema->properties[$firstName]->type!='object') {
                if(is_array($this->$firstName) || is_object($this->$firstName)) {
                    $this->{$firstName}[$ref] = $value;
                }
            } else {
                if(isset(static::$schema->properties[$firstName]) && !array_key_exists($firstName, $this->_original)) {
                    $this->_original[$firstName] = $this->$firstName;
                }
                $a0 = $this->$firstName;
                if(is_string($a0) && $serialize) {
                    $a0 = tdz::unserialize($a0, $serialize);
                }
                if(!$a0) {
                    $a0 = array();
                }

                $a =& $a0;
                if(strpos($ref, '.')) {
                    while($p=strpos($ref, '.')) {
                        $n = substr($ref, 0, $p);
                        if(!isset($a[$n])) {
                            $a[$n] = array();
                        }
                        $a =& $a[$n];
                        $ref = substr($ref, $p+1);
                        unset($n, $p);
                    }
                }
                $a[$ref] = $value;
                $this->$firstName = $a0;
                unset($a);
            }

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
        if(isset($this->$name)) unset($this->$name);
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
