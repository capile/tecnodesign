<?php
/**
 * Schema
 * 
 * This is an actor for importing and implementing schemas for any object
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
 */

namespace Studio;

use ArrayAccess;
use Tecnodesign_Exception as Exception;
use Tecnodesign_Cache as Cache;
use Tecnodesign_Yaml as Yaml;
use Studio as S;

class Schema implements ArrayAccess
{
    const SCHEMA_PROPERTY='meta';
    const OBJECT_TYPE='schema';
    const AUTOLOAD_CALLBACK='staticInitialize';
    const JSON_SCHEMA_VERSION='draft-07';

    public static 
        $errorInvalid='This is not a valid value for %s.',
        $errorInteger='An integer number is expected.',
        $errorMinorThan='%s is less than the expected minimum %s',
        $errorGreaterThan='%s is more than the expected maximum %s',
        $errorMandatory='%s is mandatory and should not be a blank value.',
        $errorUnavailable='Column "%s" is not available at %s.',
        $allowUndeclared,
        $schemaClass='Studio\\Schema',
        $error,
        $timeout;//300

    public static $meta, $schemaDir;

    /**
     * Object initialization can receive an array as the initial values
     */
    public function __construct($o=null)
    {
        $schema = static::SCHEMA_PROPERTY;
        if(!is_null($o) && property_exists(get_called_class(), $schema)) {
            if(is_object($o) && ($o instanceof ArrayAccess)) $o = (array) $o;
            $schemaClass = (static::${$schema})?(get_class(static::${$schema})):(self::$schemaClass);
            if(is_array($o)) $schemaClass::apply($this, $o, static::${$schema});
        }
    }

    public static function staticInitialize()
    {
        $schema = static::SCHEMA_PROPERTY;
        if(property_exists(get_called_class(), $schema)) {
            $schemaClass = (static::${$schema})?(get_class(static::${$schema})):(self::$schemaClass);
            static::${$schema} = $schemaClass::loadSchema(get_called_class());
        }
    }

    public function resolveAlias($name)
    {
        if(($schema = static::SCHEMA_PROPERTY) && is_object($Schema=static::$$schema) && property_exists($Schema, 'properties')) {
            $i = 10;
            $oname = $name;
            while(isset($Schema->properties[$name]['alias']) && $i--) {
                $name = $Schema->properties[$name]['alias'];
            }
        }
        unset($Schema);
        return $name;
    }

    public function value($serialize=null)
    {
        $schema = static::SCHEMA_PROPERTY;
        $r = null;
        if(property_exists(get_called_class(), $schema)) {
            $Schema = static::${$schema};
            $type = $Schema->type;
            if(!$type && $Schema->properties) {
                $type = 'object';
            } else if(!$type) {
                $type = 'string';
            }
            if($type==='object') {
                $r = [];
                if($Schema->properties) {
                    foreach($Schema->properties as $name=>$def) {
                        if(isset($this->$name)) $r[$name] = $this->$name;
                    }
                }
            } else {
                $r = array_values((array)$this);
                if($type==='string') {
                    $r = (string) array_shift($r);
                } else if($type==='int') {
                    $r = (int) array_shift($r);
                }
            }
        }
        if($serialize) {
            return S::serialize($r, $serialize);
        }

        return $r;
    }

    /**
     * ArrayAccess abstract method. Gets stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return mixed the stored value, or method results
     */
    #[\ReturnTypeWillChange]
    public function &offsetGet($name)
    {
        $name = $this->resolveAlias($name);
        if (method_exists($this, $m='get'.ucfirst(S::camelize($name)))) {
            return $this->$m();
        } else if (isset($this->$name)) {
            return $this->$name;
        }
        $n = null;
        return $n;
    }

    public function __get($name)
    {
        return $this->offsetGet($name);
    }

    public function __set($name, $value)
    {
        return $this->offsetSet($name, $value);
    }

    public function batchSet($values, $skipValidation=false)
    {
        foreach($values as $name=>$value) {
            if($skipValidation) $this->$name = $value;
            else $this->__set($name, $value);
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
     */
    public function offsetSet($name, $value): void
    {
        $name = $this->resolveAlias($name);
        if (method_exists($this, $m='set'.S::camelize($name))) {
            $this->$m($value);
        } else if(property_exists(get_called_class(), $schema = static::SCHEMA_PROPERTY)) {
            // validate schema, when available
            $Schema = static::$$schema;
            if($Schema) {
                if(isset($Schema->properties[$name])) {
                    $value = $Schema::validateProperty($Schema->properties[$name], $value, $name);
                } else if(!isset($Schema->patternProperties) || !preg_match($Schema->patternProperties, $name)) {
                    throw new Exception(array(S::t(static::$errorUnavailable,'exception'), $name, get_class($this)));
                }
            }
            $this->$name = $value;
        } else if(!property_exists($this, $name)) {
            throw new Exception(array(S::t(static::$errorUnavailable,'exception'), $name, get_class($this)));
        } else {
            $this->$name = $value;
        }
        unset($m);
    }

    /**
     * ArrayAccess abstract method. Searches for stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return bool true if the parameter exists, or false otherwise
     */
    public function offsetExists($name): bool
    {
        $name = $this->resolveAlias($name);
        return isset($this->$name);
    }

    /**
     * ArrayAccess abstract method. Unsets parameters to the PDF. Not yet implemented
     * to the PDF classes — only unsets values stored in $_vars
     *
     * @param string $name parameter name, should start with lowercase
     */
    public function offsetUnset($name): void
    {
        $schema = static::SCHEMA_PROPERTY;
        if(isset(static::${$schema}[$name]['alias'])) $name = static::${$schema}[$name]['alias'];
        $this->offsetSet($name, null);
    }

    public static function isSchema($o)
    {
    	$cn = (is_object($o)) ?get_class($o) :$o;
    	return (defined($cn.'::OBJECT_TYPE') && $cn::OBJECT_TYPE=='schema');
    }

    public static function loadSchema($cn, $meta=null)
    {
        static $schemas=[];
        // load from cache
        $ref = null;
        if($p=strpos($cn, '#')) {
            $ref = substr($cn, $p+1);
            $cn = substr($cn, 0, $p);
        }
        unset($p);
        if(array_key_exists($cn, $schemas)) {
            return $schemas[$cn];
        }
        $ckey = 'schema/'.$cn;
        if(is_int(static::$timeout) && ($Schema=Cache::get($ckey, static::$timeout))) {
            return $Schema;
        }
        $src = static::loadSchemaRef($cn);

        if(defined($cn.'::SCHEMA_PROPERTY')) {
            $schema = $cn::SCHEMA_PROPERTY;
            if(property_exists($cn, $schema) && ($d = $cn::$$schema) && (is_array($d) || static::isSchema($d))) {
                if(is_object($d)) { // force a new object
                    $d = (array) $d;
                }
                $src = $src ?S::mergeRecursive($d, $src) :($d);
                unset($d);
            }
        }

        /**
         * If a meta is specified, it means the schema must pass a schema validation before being assigned.
         * It is meant to normalize old schemas and depreceted properties.
         */
        if($meta) {

        }

        if($src) {
            $schemaClass = get_called_class();
            static::expandSchemaRefs($src); 
            $Schema = new $schemaClass($src);
        } else {
            $Schema = null;
        }
        if(is_int(static::$timeout)) {
            Cache::set($ckey, $Schema, static::$timeout);
        }
        $schemas[] = $Schema;

        return $Schema;
    }

    public static function expandSchemaRefs(&$src)
    {
        if(isset($src['ref'])) {
            if(class_exists($src['ref']) && static::isSchema($src['ref'])) {
                $cn = $src['ref'];
                $src = $cn::loadSchema($cn, $src);
            } else if($ref=static::loadSchemaRef($src['ref'])) {
                $src = S::mergeRecursive($src, $ref);
                $src['refid'] = $src['ref'];
                unset($src['ref']);
            }
        }

        return $src;
    }

    public static function loadSchemaRef($ref)
    {
        if(is_null(static::$schemaDir)) {
            static::$schemaDir = S::getApp()->config('tecnodesign', 'schema-dir');
            if(!static::$schemaDir) {
                static::$schemaDir = array();
            } else if(!is_array(static::$schemaDir)) {
                static::$schemaDir = array(static::$schemaDir);
            }
        }
        if(preg_match('/^(Tecnodesign_|Studio)/', $ref) && !in_array($rootSchema=S_ROOT.'/data/schema', static::$schemaDir)) {
            static::$schemaDir[] = $rootSchema;
        }

        $src = array();
        $fname = S::slug(str_replace('\\', '_', $ref), '_', true);
        foreach(static::$schemaDir as $dir) {
            if(file_exists($f=$dir.'/'.$fname.'.json')) {
                if($d=json_decode(file_get_contents($f), true, 512)) {
                    $src = $src ?S::mergeRecursive($src, $d) :$d;
                } else {
                    S::log('[WARNING] Could not parse json file '.$f.': '.json_last_error_msg());
                }
            }
            if(file_exists($f=$dir.'/'.$fname.'.yml')) {
                if($d=Yaml::load($f)) {
                    $src = $src ?S::mergeRecursive($src, $d) :$d;
                }
            }
            unset($dir, $f, $d);
        }

        return $src;
    }

    public static function apply($Model, $values, $meta=null, $throw=true)
    {
        // move this to validateProperty of type object
        $allowUndeclared = false;
        if(is_object($Model)) {
            if(!$meta) {
                if(defined(get_class($Model).'::SCHEMA_PROPERTY')) {
                    $n = $Model::SCHEMA_PROPERTY;
                    $meta = $Model::$$n;
                }
                if(!$meta) $meta = [];
                if(!isset($meta['type'])) $meta['type'] = 'object';
            }
        } else if(is_array($Model)) {
            if(!$meta) $meta = array('type'=>'array');
            $arr = $Model;
            $Model = false;
        } else {
            if(!$meta) $meta = array('type'=>'string');
            $Model = false;
            $arr = array();
        }

        try {
            $values = static::validateProperty($meta, $values);
        } catch(\Exception $e) {
            S::log('[INFO] Could not apply values: '.$e->getMessage());
            if($throw) throw new Exception($e->getMessage());
        }
        if($Model && method_exists($Model, 'batchSet')) {
            $Model->batchSet($values, true);
        } else if(is_array($values)) {
            foreach($values as $n=>$v) {
                if($Model) $Model->$n = $v;
                else if($arr) $arr[$n] = $v;
            }
        }
        if($Model) return $Model;
        else if($arr) return $arr;
        else return $values;
    }

    public static function validateProperty($def, $value, $name=null)
    {
        if(!isset($def['type'])) $def['type'] = 'string';
        if($def['type']=='string') {
            if(is_array($value)) {
                if(isset($def['serialize'])) {
                    $value = S::serialize($value, $def['serialize']);
                } else {
                    $value = S::implode($value);
                }
            } else {
                $value = @(string) $value;
            }
            if (isset($def['size']) && $def['size'] && strlen($value) > $def['size']) {
                $value = mb_strimwidth($value, 0, (int)$def['size'], '', 'UTF-8');
            }
        } else if($def['type']=='int') {
            if (!is_numeric($value) && $value!='') {
                $label = (isset($def['label']))?($def['label']):(S::t(ucwords(str_replace('_', ' ', $name)), 'labels'));
                throw new Exception(sprintf(S::t(static::$errorInvalid, 'exception'), $label).' '.S::t(static::$errorInteger, 'exception'));
            }
            if(!S::isempty($value)) $value = (int) $value;
            if (isset($def['min']) && $value < $def['min']) {
                $label = (isset($def['label']))?($def['label']):(S::t(ucwords(str_replace('_', ' ', $name)), 'labels'));
                throw new Exception(sprintf(S::t(static::$errorInvalid, 'exception'), $label).' '.sprintf(S::t(static::$errorMinorThan, 'exception'), $value, $def['min']));
            } else if (isset($def['max']) && $value > $def['max']) {
                $label = (isset($def['label']))?($def['label']):(S::t(ucwords(str_replace('_', ' ', $name)), 'labels'));
                throw new Exception(sprintf(S::t(static::$errorInvalid, 'exception'), $label).' '.sprintf(S::t(static::$errorGreaterThan, 'exception'), $value, $def['max']));
            }
        } else if($def['type']==='object' || $def['type']==='array') {
            if(!is_object($value) && !is_array($value)) {
                $value = [$value];
            }
            $nreg = (isset($def['patternProperties'])) ?$def['patternProperties'] :null;
            if(isset($def['item'])) {
                $item = $def['item'];
                if(is_string($item)) {
                    foreach($value as $i=>$o) {
                        // type object should validate keys, if patternProperties is present, or is an object
                        if($nreg && !preg_match($nreg, $i) && !static::$allowUndeclared) {
                            unset($value[$i]);
                        } else {
                            $value[$i] = new $item($o);
                        }
                    }
                }
                unset($item);
            } else if(isset($def['properties']) || $nreg) {
                // if its an array, validate recursively (treat as schema)
                $meta = (isset($def['properties'])) ?$def['properties'] :null;
                foreach($value as $n=>$pvalue) {
                    if(isset($meta[$n]['alias'])) {
                        unset($value[$n]);
                        $i = 10;
                        while(isset($meta[$n]['alias']) && $i--) {
                            if(substr($meta[$n]['alias'], 0, 1)==='!') {
                                $pvalue = !$pvalue;
                                $n = substr($meta[$n]['alias'], 1);
                            } else {
                                $n = $meta[$n]['alias'];
                            }
                        }
                        unset($i);

                    }
                    if(isset($meta[$n])) {
                        if(isset($meta[$n]['trigger'][$pvalue])) {
                            $trigger = $meta[$n]['trigger'][$pvalue];
                            if(!is_array($trigger)) {
                                $trigger = array($n=>$trigger);
                            }
                            foreach($trigger as $tname=>$tvalue) {
                                $value[$tname] = $tvalue;
                            }
                            unset($trigger);
                        } else {
                            $value[$n] = static::validateProperty($meta[$n], $pvalue, $n);
                        }
                    } else if($nreg && !preg_match($nreg, $n) && !static::$allowUndeclared) {
                        unset($value[$n]);
                    }
                }
            }
        } else if(!isset($def['format'])) {
            $def['format'] = $def['type'];
            $def['type'] = 'string';
        }

        if(isset($def['format'])) {
            if(substr($def['format'], 0,4)=='date') {
                if($value) {
                    $time = false;
                    $d = false;
                    if(!preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}/', $value)) {
                        $format = S::$dateFormat;
                        if (substr($def['format'], 0, 8)=='datetime') {
                            $format .= ' '.S::$timeFormat;
                            $time = true;
                        }
                        $d = date_parse_from_format($format, $value);
                    }
                    if($d && !isset($d['errors'])) {
                        $value = str_pad((int)$d['year'], 4, '0', STR_PAD_LEFT)
                            . '-' . str_pad((int)$d['month'], 2, '0', STR_PAD_LEFT)
                            . '-' . str_pad((int)$d['day'], 2, '0', STR_PAD_LEFT);
                        if($time) {
                            $value .= ' '.str_pad((int)$d['hour'], 2, '0', STR_PAD_LEFT)
                                . ':' . str_pad((int)$d['minute'], 2, '0', STR_PAD_LEFT)
                                . ':' . str_pad((int)$d['second'], 2, '0', STR_PAD_LEFT);
                        }
                    } else if($d = strtotime($value)) {
                        $ms = (preg_match('/\.[0-9]+$/', $value, $m)) ?$m[0] :'';
                        $value = (substr($def['format'], 0, 8)=='datetime')?(date('Y-m-d\TH:i:s', $d).$ms):(date('Y-m-d', $d));
                    }
                }
            }
        }

        // @TODO: write other validators
        if(($value==='' || $value===null) && isset($def['default'])) {
            $value = $def['default'];
        }

        if(isset($def['required']) && $def['required']) {
            $nullable = false;
        } else if(isset($def['null']) && !$def['null']) {
            $nullable = false;
        } else {
            $nullable = true;
        }
        if (($value==='' || $value===null) && !$nullable) {
            $label = (isset($def['label']))?($def['label']):(S::t(ucwords(str_replace('_', ' ', $name)), 'labels'));
            throw new Exception(sprintf(S::t(static::$errorMandatory, 'exception'), $label));
        } else if($value==='') {
            $value = false;
        }
        return $value;
    }

    public function uid($expand=false)
    {
        //return $this->properties(null, false, array('primary'=>true), $expand);
        $r = array();
        foreach($this->properties as $n=>$d) {
            if($d && isset($d['primary']) && $d['primary']) {
                if($expand) $r[$n]=$d;
                else $r[] = $n;
            }
            unset($n, $d);
        }
        return $r;
    }

    public function properties($scope=null, $overlay=false, $filter=null, $expand=10, $add=array())
    {
        $R = array();
        if(is_string($scope)) {
            if(isset($this->scope[$scope])) $scope = $this->scope[$scope];
            else return $R;
        } else if(!$scope) {
            $scope = $this->properties;
        }
        if(!$scope || !is_array($scope)) return $R;

        if(!is_array($add)) $add=array();
        if(isset($scope['__default'])) {
            $add = $scope['__default'] + $add;
            unset($scope['__default']);
        }

        foreach($scope as $n=>$def) {
            $base = $add;
            $ref = $this;

            if(is_string($def)) {
                if(preg_match('/^([a-z0-9\-\_]+)::([a-z0-9\-\_\,]+)(:[a-z0-9\-\_\,\!]+)?$/i', $def, $m)) {
                    if(isset($m[3])) {
                        if(!isset($U)) $U=S::getUser();
                        if(!$U || !$U->hasCredential(preg_split('/[\,\:]+/', $m[3], -1, PREG_SPLIT_NO_EMPTY),false)) {
                            continue;
                        }
                    }
                    if($m[1]=='scope' && $expand) {
                        $R = array_merge($R, $ref->properties($m[2], $overlay, $filter, $expand--, $add));
                    }
                    unset($base, $n, $def);
                    continue;
                } else if(substr($def, 0, 2)=='--' && substr($def, -2)=='--') {
                    $add['fieldset'] = substr($def, 2, strlen($def)-4);
                    unset($base, $n, $def);
                    continue;
                } else {
                    $base['bind'] = $def;
                    if(preg_match('/^([^\s\`]+)(\s+as)?\s+[a-zA-Z0-9\_\-]+$/', $def, $m)) $def = $m[1];

                    while(strpos($def, '.')!==false) {
                        list($rn,$def)=explode('.', $def, 2);
                        if(isset($ref->relations[$rn])) {
                            $cn = (isset($ref->relations[$rn]['className']))?($ref->relations[$rn]['className']):($rn);
                            $ref = $cn::schema($cn, array('className'=>$cn), true);
                        } else {
                            $def = null;
                            break;
                        }
                    }
                    if($def!==null && isset($ref->properties[$def])) {
                        $def = $ref->properties[$def];
                        $i=10;
                        while(isset($def['alias'])) {
                            if(!isset($ref->properties[$def['alias']])) {
                                $def = array();
                                break;
                            } else {
                                $def = $def['alias'];
                                $i--;
                            }
                        }
                        unset($i);
                    } else {
                        $def = array('type'=>'string','null'=>true);
                    }
                }
            }

            /*
            if(!is_int($n)) $base['label'] = $n;

            if(is_array($def) && isset($def['bind'])) $n = $def['bind'];
            else if(isset($base['bind'])) $n = $base['bind'];
            else if(is_string($def)) $n = $def;
            */
            if(is_int($n)) {
                if(is_array($def) && isset($def['bind'])) $n = $def['bind'];
                else if(isset($base['bind'])) $n = $base['bind'];
                else if(is_string($def)) $n = $def;
            }

            $bind = (is_array($def) && isset($def['bind'])) ?$def['bind'] :$n;
            if(strpos($bind, ' ')) $bind = substr($bind, strrpos($bind, ' ')+1);

            if($ref->patternProperties) {
                foreach($ref->patternProperties as $re=>$addDef) {
                    if(preg_match($re, $bind)) {
                        if(!is_string($def)) $def = $addDef;
                        else $base += $addDef;
                    }
                    unset($re, $addDef);
                }
            }

            if(is_array($def)) {
                if($base) $def += $base;
                if($overlay && isset($def['bind'])) {
                    if(isset($ref->overlay[$bind])) {
                        $def = $ref->overlay[$bind] + $def;
                    }
                }
                if(isset($def['credential'])) {
                    if(!isset($U)) $U=S::getUser();
                    if(!$U || !$U->hasCredentials($def['credential'], false)) $def = null;
                }

                if($def) {
                    if($filter) {
                        foreach($filter as $p=>$value) {
                            if(!isset($def[$p]) || $def[$p]!=$value || (is_array($value) && !in_array($def[$p], $value))) {
                                $def = null;
                                break;
                            }
                        }
                    }

                    if($def) $R[$n] = $def;
                }
            }
            unset($base, $n, $def, $ref);
        }

        if($R && $expand===false) {
            $r = array();
            foreach($R as $n=>$d) {
                if(isset($d['bind'])) $r[$n]=$d['bind'];
                unset($n, $d);
            }
            return $r;
        }
        return $R;
    }

    public static function import($source, &$R=[])
    {
        static $fetch = [];

        $cache = false;

        if(is_string($source)) {
            if(!$R) {
                $cache = 'schemaref/'.md5($source);
                if($R=Cache::get($cache)) {
                    return $R;
                }
            }

            $hash = ($p=strpos($source, '#')) ?substr($source, $p+1) :null;
            if($hash) $source = substr($source, 0, $p);

            if(isset($fetch[$source])) $S = $fetch[$source];
            else {
                $s = file_get_contents($source);
                if(!$s || !($S=S::unserialize($s, 'json'))) return $R;

                $fetch[$source] = $S;
            }

            if($hash) {
                $hash = trim(str_replace('/', '.', $hash), '.');
                $S = S::extractValue($S, $hash);
                if(!$S) return $R;
            }

        } else {
            $S = $source;
        }

        if(!is_array($S)) return $R;

        foreach($S as $k=>$v) {
            if($k=='allOf') {
                if(is_array($v)) {
                    foreach($v as $i=>$o) {
                        static::import($o, $R);
                    }
                }
            } else if($k=='$ref') {
                static::import($v, $R);
            } else if($k=='properties' || $k=='items') {
                if(!isset($R[$k])) $R[$k] = [];
                foreach($v as $kk=>$vv) {
                    $R[$k][$kk] = static::import($vv);
                }
            } else {
                $R[$k] = $v;
            }
        }

        if($cache) {
            Cache::set($cache, $R);
        }

        return $R;

    }

    public function toJsonSchema($scope=null, &$R=array())
    {
        // available scopes might form full definitions (?)
        $fo = $this->properties($scope);
        $cn = $this->className;

        if(!is_array($R)) {
            $R += array(
                '$schema'=>'http://json-schema.org/draft-07/schema#',
                '$id'=>S::buildUrl($this->link().$qs),
                'title'=>(isset($this->text['title']))?($this->text['title']):($cn::label()),
            );
        }
        $R+=array('type'=>'object','properties'=>array(), 'required'=>array());

        $types = array(
            'bool'=>'boolean',
            'array'=>'object',
            'form'=>'object',
            'integer'=>'integer',
            'number'=>'number',
        );

        $properties=array(
            'label'=>'title',
            'description'=>'description',
            'placeholder'=>'description',
            'default'=>'default',
            'readonly'=>'readOnly',
        );

        foreach($fo as $fn=>$fd) {
            $bind = (isset($fd['bind']))?($fd['bind']):($fn);
            if($p=strrpos($bind, ' ')) $bind = substr($bind, $p+1);
            if(isset($cn::$schema->properties[$bind])) {
                if(is_object($cn::$schema->properties[$bind]) && static::isSchema($cn::$schema->properties[$bind])) {
                    $fd += (array) $cn::$schema['columns'][$bind];
                } else {
                    $fd += (array) $cn::$schema['columns'][$bind];
                }
            }
            $type = (isset($fd['type']) && isset($types[$fd['type']]))?($types[$fd['type']]):('string');
            if(isset($fd['multiple']) && $fd['multiple']) {
                if(isset($fd['type']) && $fd['type']=='array') $type = 'array'; 
                else $type = array($type, 'array');
            }
            $R['properties'][$fn]=array(
                'type'=>$type,
            );

            foreach($properties as $n=>$v) {
                if(isset($fd[$n]) && !isset($R['properties'][$fn][$n])) {
                    $R['properties'][$fn][$n] = $fd[$n];
                }
                unset($n, $v);
            }
            if(isset($fd['null']) && !$fd['null']) $R['required'][] = $fn;

            if(!is_array($type) && method_exists($this, $m='_jsonSchema'.ucfirst($type))) {
                $this->$m($fd, $R['properties'][$fn]);
            }

            if(isset($fd['choices']) && is_array($fd['choices'])) {
                $R['properties'][$fn]['enum'] = array_keys($fd['choices']);
            }
        }

        return $R;
    }

    protected function _jsonSchemaInteger($fd, &$R=array())
    {
        return $this->_jsonSchemaNumber($fd, $R);
    }

    protected function _jsonSchemaNumber($fd, &$R=array())
    {
        if(isset($fd['min_size'])) $R['minimum'] = $fd['min_size'];
        if(isset($fd['size'])) $R['maximum'] = $fd['size'];
        // exclusiveMaximum
        // exclusiveMinimum
        // multipleOf
    }

    protected function _jsonSchemaString($fd, &$R=array())
    {
        if(isset($fd['min_size'])) $R['minLength'] = $fd['min_size'];
        if(isset($fd['size'])) $R['maxLength'] = $fd['size'];
        // pattern

        static $format=array(
            'date'=>'date',
            'datetime'=>'date-time',
            'time'=>'time',
            'email'=>'email',
            'ipv4'=>'ipv4',
            'ipv6'=>'ipv6',
            'url'=>'uri',
        );
        if(isset($fd['type']) && isset($format[$fd['type']])) $R['format'] = $format[$fd['type']];
    }

    protected function _jsonSchemaArray($fd, &$R=array())
    {
        if(isset($fd['scope'])) {
            $R['items'] = $this->toJsonSchema($fd['scope'], $R);
        }
        // additionalItems
        // pattern
        if(isset($fd['min_size'])) $R['minItems'] = $fd['min_size'];
        if(isset($fd['size'])) $R['maxItems'] = $fd['size'];
        // uniqueItems
        // contains
    }

    protected function _jsonSchemaObject($fd, &$R=array())
    {
        if(isset($fd['scope'])) {
            $R = $this->toJsonSchema($fd['scope'], $R);
        }
        // maxProperties
        // minProperties
        // patternProperties
        // additionalProperties
        // dependencies
        // propertyNames
    }

}