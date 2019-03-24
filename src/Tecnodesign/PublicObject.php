<?php
/**
 * Schema-based Public object
 *
 * This base class implements ArrayAccess and automatic property validation using Schemas
 *
 * PHP version 5.6
 *
 * @category  Ui
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2019 Tecnodesign
 * @license   https://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      https://tecnodz.com/
 */
class Tecnodesign_PublicObject implements ArrayAccess, Tecnodesign_AutoloadInterface
{
    const SCHEMA_PROPERTY='meta';

    /**
     * Object initialization can receive an array as the initial values
     */
    public function __construct($o=null)
    {
        $schema = static::SCHEMA_PROPERTY;
        if(!is_null($o) && property_exists(get_called_class(), $schema)) {
            if(is_object($o) && ($o instanceof ArrayAccess)) $o = (array) $o;
            $schemaClass = (static::$$schema)?(get_class(static::$$schema)):('Tecnodesign_Schema');
            if(is_array($o)) $schemaClass::apply($this, $o, static::$$schema);
        }
    }

    public static function staticInitialize()
    {
        $schema = static::SCHEMA_PROPERTY;
        if(property_exists(get_called_class(), $schema)) {
            $schemaClass = (static::$$schema)?(get_class(static::$$schema)):('Tecnodesign_Schema');
            static::$$schema = $schemaClass::loadSchema(get_called_class());
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
            if(isset($Schema->properties[$name])) {
                $value = $Schema::validateProperty($Schema->properties[$name], $value, $name);
            } else if(!isset($Schema->patternProperties) || !preg_match($Schema->patternProperties, $name)) {
                throw new Tecnodesign_Exception(array(tdz::t('Column "%s" is not available at %s.','exception'), $name, get_class($this)));
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
        return property_exists($this, $name);
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
        if(isset(static::$$schema[$name]['alias'])) $name = static::$$schema[$name]['alias'];
        return $this->offsetSet($name, null);
    }
}