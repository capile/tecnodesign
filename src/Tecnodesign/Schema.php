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
class Tecnodesign_Schema implements ArrayAccess
{
    const JSON_SCHEMA_VERSION='draft-07';

    public static 
        $errorInvalid='This is not a valid value for %s.',
        $errorInteger='An integer number is expected.',
        $errorMinorThan='%s is less than the expected minimum %s',
        $errorGreaterThan='%s is more than the expected maximum %s',
        $error;

    protected 
        $database,
        $className,
        $tableName,
        $view,
        $properties,
        $patternProperties=array('/^_/'=>array('type'=>'text')),
        $overlay,
        $scope,
        $relations,
        $events,
        $orderBy,
        $groupBy;

    protected static $meta=array(
        'database'=>array('type'=>'string'),
        'className'=>array('type'=>'string'),
        'tableName'=>array('type'=>'string'),
        'view'=>array('type'=>'string'),
        'properties'=>array('type'=>'array'),
        'patternProperties'=>array('type'=>'array'),
        'overlay'=>array('type'=>'array'),
        'scope'=>array('type'=>'array'),
        'relations'=>array('type'=>'array'),
        'events'=>array('type'=>'array'),
        'orderBy'=>array('type'=>'array'),
        'groupBy'=>array('type'=>'array'),
        'columns'=>array('alias'=>'properties'),
        'form'=>array('alias'=>'overlay'),
    );

    public function __construct($o=null)
    {
        if($o) {
            if(is_array($o)) static::apply($this, $o, static::$meta);
        }
    }

    public static function apply($Model, $values, $meta=null)
    {
        if(is_object($Model)) {
            if(!$meta && ($Model instanceof Tecnodesign_Model)) $meta = $Model::$schema['columns'];
        } else if(is_array($Model)) {
            $arr = $Model;
            $Model = false;
        } else {
            $Model = false;
            $arr = array();
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
            } else {
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

            if(strpos($n, ' ')) $n = substr($n, strrpos($n, ' ')+1);

            if($ref->patternProperties) {
                foreach($ref->patternProperties as $re=>$addDef) {
                    if(preg_match($re, $n)) {
                        if(!is_string($def)) $def = $addDef;
                        else $base += $addDef;
                    }
                    unset($re, $addDef);
                }
            }

            if(is_array($def)) {
                if($base) $def += $base;
                if($overlay && isset($def['bind'])) {
                    if(isset($ref->overlay[$n])) {
                        $def = $ref->overlay[$n] + $def;
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

    public function toJsonSchema($scope=null, $properties=null)
    {
        // available scopes might form full definitions (?)

        $cn = $this->className;
        $fo = $this->properties($scope);

        $R=array('type'=>'object','properties'=>array(), 'required'=>array());
        /*
        $R += array(
            '$schema'=>'http://json-schema.org/draft-07/schema#',
            '$id'=>tdz::buildUrl($this->link().$qs),
            'title'=>(isset($this->text['title']))?($this->text['title']):($cn::label()),
        );
        */

        $types = array('boolean'=>'boolean', 'bool'=>'boolean', 'array'=>'object', 'integer'=>'number', 'number'=>'number');

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
                'description'=>(isset($fd['description']))?($fd['description']):($fd['label']),
                'type'=>$type,
            );
            if(isset($fd['null']) && !$fd['null']) $R['required'][] = $fn;
        }


        return $R;
    }

    /**
     * ArrayAccess abstract method. Gets stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return mixed the stored value, or method results
     */
    public function &offsetGet($name)
    {
        if(isset(static::$meta[$name]['alias'])) $name = static::$meta[$name]['alias'];
        if (method_exists($this, $m='get'.ucfirst(tdz::camelize($name)))) {
            return $this->$m();
        } else if (isset($this->$name)) {
            return $this->$name;
        }
        return null;
    }
    /**
     * ArrayAccess abstract method. Sets parameters to the PDF.
     *
     * @param string $name  parameter name, should start with lowercase
     * @param mixed  $value value to be set
     * 
     * @return void
     */
    public function offsetSet($name, $value)
    {
        if(isset(static::$meta[$name]['alias'])) $name = static::$meta[$name]['alias'];
        if (method_exists($this, $m='set'.tdz::camelize($name))) {
            $this->$m($value);
        } else if(!property_exists($this, $name)) {
            throw new Tecnodesign_Exception(array(tdz::t('Column "%s" is not available at %s.','exception'), $name, get_class($this)));
        } else {
            $this->$name = $value;
        } 
        unset($m);
        return $this;
    }

    /**
     * ArrayAccess abstract method. Searches for stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return bool true if the parameter exists, or false otherwise
     */
    public function offsetExists($name)
    {
        if(isset(static::$meta[$name]['alias'])) $name = static::$meta[$name]['alias'];
        return isset($this->$name);
    }

    /**
     * ArrayAccess abstract method. Unsets parameters to the PDF. Not yet implemented
     * to the PDF classes — only unsets values stored in $_vars
     *
     * @param string $name parameter name, should start with lowercase
     */
    public function offsetUnset($name)
    {
        if(isset(static::$meta[$name]['alias'])) $name = static::$meta[$name]['alias'];
        return $this->offsetSet($name, null);
    }
}