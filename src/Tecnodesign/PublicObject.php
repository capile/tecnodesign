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
            if(is_array($o)) Tecnodesign_Schema::apply($this, $o, static::$$schema);
        }
    }

    public static function staticInitialize()
    {
        $schema = static::SCHEMA_PROPERTY;
        if(property_exists(get_called_class(), $schema)) {
            static::$$schema = Tecnodesign_Schema::loadSchema(get_called_class());
        }
    }

    public function resolveAlias($name)
    {
        if(($schema = static::SCHEMA_PROPERTY) && is_object(static::$$schema)) {
            $i = 10;
            while(property_exists(static::$$schema, $name) && isset(static::$$schema->$name['alias']) && $i--) {
                $name = static::$$schema->$name['alias'];
            }
        }
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
        $name = $this->resolveAlias($name);
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