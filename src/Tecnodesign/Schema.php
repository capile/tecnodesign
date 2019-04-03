<?php
/**
 * Schema Builder/Parser
 *
 * This is an action for managing schemas for all available models
 *
 * PHP version 5.4
 *
 * @category  Ui
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2019 Tecnodesign
 * @license   https://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Schema extends Tecnodesign_PublicObject
{
    const JSON_SCHEMA_VERSION='draft-07';

    public static 
        $errorInvalid='This is not a valid value for %s.',
        $errorInteger='An integer number is expected.',
        $errorMinorThan='%s is less than the expected minimum %s',
        $errorGreaterThan='%s is more than the expected maximum %s',
        $errorMandatory='%s is mandatory and should not be a blank value.',
        $error;

    public static $meta;

    public static function loadSchema($cn, $meta=null)
    {
        static $timeout = 300;
        // load from cache
        $ckey = 'schema/'.$cn;
        if($Schema=Tecnodesign_Cache::get($ckey, $timeout)) {
            return $Schema;
        }

        $src = static::loadSchemaRef($cn);

        if(defined($cn.'::SCHEMA_PROPERTY')) {
            $schema = $cn::SCHEMA_PROPERTY;
            if(property_exists($cn, $schema) && ($d = $cn::$$schema) && (is_array($d) || (is_object($d) && ($d instanceof Tecnodesign_Schema)))) {
                if(is_object($d)) { // force a new object
                    $d = (array) $d;
                }
                $src = $src ?array_merge_recursive($d, $src) :($d);
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
            $Schema = new $schemaClass($src);
            Tecnodesign_Cache::set($ckey, $Schema, $timeout);
        } else {
            $Schema = null;
        }

        return $Schema;
    }

    public static function loadSchemaRef($ref)
    {
        $dirs = tdz::getApp()->config('schema-dir');
        if(!$dirs) {
            $dirs = array();
        } else if(!is_array($dirs)) {
            $dirs = array($dirs);
        }
        if(substr($ref, 0, 12)=='Tecnodesign_')
        array_unshift($dirs, TDZ_ROOT.'/schema');

        $src = array();
        $fname = tdz::slug(str_replace('\\', '_', $ref), '_', true);
        foreach($dirs as $dir) {
            if(file_exists($f=$dir.'/'.$fname.'.json')) {
                if($d=json_decode(file_get_contents($f), true)) {
                    $src = $src ?array_merge_recursive($src, $d) :$d;
                }
            }
            if(file_exists($f=$dir.'/'.$fname.'.yml')) {
                if($d=Tecnodesign_Yaml::load($f)) {
                    $src = $src ?array_merge_recursive($src, $d) :$d;
                }
            }
            unset($dir, $f, $d);
        }

        return $src;
    }

    public static function apply($Model, $values, $meta=null)
    {
        $allowUndeclared = false;
        if(is_object($Model)) {
            if(!$meta && ($Model instanceof Tecnodesign_Model)) $meta = $Model::$schema['columns'];
            if($Model instanceof Tecnodesign_Schema) {
                $allowUndeclared = true;
            }
        } else if(is_array($Model)) {
            $arr = $Model;
            $Model = false;
        } else {
            $Model = false;
            $arr = array();
        }
        if(is_object($meta) && ($meta instanceof Tecnodesign_Schema)) {
            $meta = $meta->properties;
        }

        foreach($values as $name=>$value) {
            if($meta) {
                $i = 10;
                while(isset($meta[$name]['alias']) && $i--) {
                    $name = $meta[$name]['alias'];
                }
                unset($i);
                if(isset($meta[$name])) {
                    $value = static::validateProperty($meta[$name], $value, $name);
                }
            } else if($allowUndeclared!==true) {
                unset($name, $value);
                continue;
            }
            if($Model) $Model->$name = $value;
            else if($arr) $arr[$name] = $value;
            unset($name, $value);
        }

        return ($Model)?($Model):($arr);
    }

    public static function validateProperty($def, $value, $name=null)
    {
        if(!isset($def['type']) || $def['type']=='string') {
            if(is_array($value)) {
                if(isset($def['serialize'])) {
                    $value = tdz::serialize($value, $def['serialize']);
                } else {
                    $value = tdz::implode($value);
                }
            } else {
                $value = @(string) $value;
            }
            if (isset($def['size']) && $def['size'] && strlen($value) > $def['size']) {
                $value = mb_strimwidth($value, 0, (int)$def['size'], '', 'UTF-8');
            }
        } else if($def['type']=='int') {
            if (!is_numeric($value) && $value!='') {
                $label = (isset($def['label']))?($def['label']):(tdz::t(ucwords(str_replace('_', ' ', $name)), 'labels'));
                throw new Tecnodesign_Exception(sprintf(tdz::t(static::$errorInvalid, 'exception'), $label).' '.tdz::t(static::$errorInteger, 'exception'));
            }
            if(!tdz::isempty($value)) $value = (int) $value;
            if (isset($def['min']) && $value < $def['min']) {
                $label = (isset($def['label']))?($def['label']):(tdz::t(ucwords(str_replace('_', ' ', $name)), 'labels'));
                throw new Tecnodesign_Exception(sprintf(tdz::t(static::$errorInvalid, 'exception'), $label).' '.sprintf(tdz::t(static::$errorMinorThan, 'exception'), $value, $def['min']));
            } else if (isset($def['max']) && $value > $def['max']) {
                $label = (isset($def['label']))?($def['label']):(tdz::t(ucwords(str_replace('_', ' ', $name)), 'labels'));
                throw new Tecnodesign_Exception(sprintf(tdz::t(static::$errorInvalid, 'exception'), $label).' '.sprintf(tdz::t(static::$errorGreaterThan, 'exception'), $value, $def['max']));
            }
        } else if(substr($def['type'], 0,4)=='date') {
            if($value) {
                $time = false;
                $d = false;
                if(!preg_match('/^[0-9]{4}\-[0-9]{2}\-[0-9]{2}/', $value)) {
                    $format = tdz::$dateFormat;
                    if (substr($def['type'], 0, 8)=='datetime') {
                        $format .= ' '.tdz::$timeFormat;
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
                    $value = (substr($def['type'], 0, 8)=='datetime')?(date('Y-m-d H:i:s', $d)):(date('Y-m-d', $d));
                }
            }
        }
        // @TODO: write other validators
        if(($value==='' || $value===null) && isset($def['default'])) {
            $value = $def['default'];
        }
        if (($value==='' || $value===null) && isset($def['null']) && !$def['null']) {
            $label = (isset($def['label']))?($def['label']):(tdz::t(ucwords(str_replace('_', ' ', $name)), 'labels'));
            throw new Tecnodesign_Exception(sprintf(tdz::t(static::$errorMandatory, 'exception'), $label));
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
                        if(!isset($U)) $U=tdz::getUser();
                        if(!$U || !$U->hasCredential(preg_split('/[\,\:]+/', $m[3], null, PREG_SPLIT_NO_EMPTY),false)) {
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
                    if(!isset($U)) $U=tdz::getUser();
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

    public function toJsonSchema($scope=null, &$R=array())
    {
        // available scopes might form full definitions (?)
        $fo = $this->properties($scope);
        $cn = $this->className;

        if(!is_array($R)) {
            $R += array(
                '$schema'=>'http://json-schema.org/draft-07/schema#',
                '$id'=>tdz::buildUrl($this->link().$qs),
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
            if(isset($cn::$schema['columns'][$bind])) $fd+=$cn::$schema['columns'][$bind];
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

    protected static function _jsonSchemaArray($fd, &$R=array())
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

    protected static function _jsonSchemaObject($fd, &$R=array())
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