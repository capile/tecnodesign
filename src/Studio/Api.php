<?php
/**
 * Api
 * 
 * Formerly Interface, this enables application definitions using API specifications
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
namespace Studio;

use tdz as S;
use Tecnodesign_Exception as Exception;
use ArrayAccess;

class Api implements ArrayAccess
{
    /**
     * ArrayAccess abstract method. Gets stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return mixed the stored value, or method results
     */
    public function offsetGet($name)
    {
        if (method_exists($this, $m='get'.S::camelize($name, true))) {
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
        if (method_exists($this, $m='set'.S::camelize($name, true))) {
            $this->$m($value);
        } else if(!property_exists($this, $name)) {
            throw new Exception(array(tdz::t('Column "%s" is not available at %s.','exception'), $name, get_class($this)));
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
        return $this->offsetSet($name, null);
    }	
}