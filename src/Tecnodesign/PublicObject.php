<?php
/**
 * Schema-based Public object
 * 
 * This base class implements ArrayAccess and automatic property validation using Schemas
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_PublicObject implements ArrayAccess
{
    const SCHEMA_PROPERTY='meta';
    const AUTOLOAD_CALLBACK='staticInitialize';

    /**
     * Object initialization can receive an array as the initial values
     */
    public function __construct($o=null)
    {
        $schema = static::SCHEMA_PROPERTY;
        if(!is_null($o) && property_exists(get_called_class(), $schema)) {
            if(is_object($o) && ($o instanceof ArrayAccess)) $o = (array) $o;
            $schemaClass = (static::${$schema})?(get_class(static::${$schema})):('Tecnodesign_Schema');
            if(is_array($o)) $schemaClass::apply($this, $o, static::${$schema});
        }
    }

    public static function staticInitialize()
    {
        $schema = static::SCHEMA_PROPERTY;
        if(property_exists(get_called_class(), $schema)) {
            $schemaClass = (static::${$schema})?(get_class(static::${$schema})):('Tecnodesign_Schema');
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
            return tdz::serialize($r, $serialize);
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
    public function &offsetGet($name)
    {
        $name = $this->resolveAlias($name);
        if (method_exists($this, $m='get'.ucfirst(tdz::camelize($name)))) {
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
    public function offsetSet($name, $value)
    {
        $name = $this->resolveAlias($name);
        if (method_exists($this, $m='set'.tdz::camelize($name))) {
            $this->$m($value);
        } else if(property_exists(get_called_class(), $schema = static::SCHEMA_PROPERTY)) {
            // validate schema, when available
            $Schema = static::$$schema;
            if($Schema) {
                if(isset($Schema->properties[$name])) {
                    $value = $Schema::validateProperty($Schema->properties[$name], $value, $name);
                } else if(!isset($Schema->patternProperties) || !preg_match($Schema->patternProperties, $name)) {
                    throw new Tecnodesign_Exception(array(tdz::t('Column "%s" is not available at %s.','exception'), $name, get_class($this).'????'.var_export($Schema, true)));
                }
            }
            $this->$name = $value;
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
        $name = $this->resolveAlias($name);
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
        $schema = static::SCHEMA_PROPERTY;
        if(isset(static::${$schema}[$name]['alias'])) $name = static::${$schema}[$name]['alias'];
        return $this->offsetSet($name, null);
    }
}
