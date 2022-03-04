<?php
/**
 * PDF creation and manipulation
 * 
 * This package extends TCPDF (www.tcpdf.org) and FPDI and includes several methods
 * to make the PDF creation process as simple as possible, with many resources
 * available.
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
 */

class Tecnodesign_Pdf implements ArrayAccess
{
    /**
     * Constructor default options
     *
     * @var array $_options
     */
    private $PDF;
    private $_options=array(
        'orientation'=>'P',
        'unit'=>'mm',
        'format'=>'A4',
        'unicode'=>true,
        'encoding'=>'UTF-8',
        'diskcache'=>false,
        'output'=>false,
    );
    private $_cache = null;
    private $_vars=array();

    /**
     * Class constructor
     *
     * Extends the default constructor, replacing the list of parameters for named
     * arguments in an array. All parameters are optional. The available parameters
     * are:
     *   orientation: 'P' (default)|'L'
     *   unit: 'mm'(default)|'pt'|'cm'|'px'
     *   format: 'A4'(default)|'Letter'
     *   unicode: true (default)|false
     *   encoding: 'UTF-8' (default)|(valid encodings),
     *   diskcache: false (default) — enables disk cache
     *   output: false (default)| (filename) — output file name
     *
     * @param array $options PDF default parameters.
     */
    function __construct($options=array())
    {
        foreach ($options as $k => $v) {
            if (isset($this->_options[$k])) {
                $this->_options[$k] = $v;
            }
        }

        $this->PDF = new Tecnodesign_Pdf_Wrapper (
            $this->_options['orientation'], $this->_options['unit'],
            $this->_options['format'], $this->_options['unicode'],
            $this->_options['encoding'], $this->_options['diskcache'],
            false, $this
        );
        //$this->PDF->Parent = $this;
        $this->PDF->SetCellPadding(0);
        $tagvs = array(
            'p' => array(0=>array('h'=>0, 'n'=>0), 1=>array('h'=>0, 'n'=>0)),
        );
        $this->PDF->setHtmlVSpace($tagvs);
        $this->PDF->setCellHeightRatio(1);
        $this->PDF->setFontSubsetting(false);
        //$this->PDF->setImageScale(0.47);
    }

    /**
     * Magic terminator. Returns the PDF file name, ready for output.
     * 
     * @return string PDF file path or false in case of errors
     */
    function __toString()
    {
        $this->PDF->Output($this->_options['output'], 'F');
        if (file_exists($this->_options['output'])) {
            return $this->_options['output'];
        }

        return false;
    }

    /**
     * Magic methods. Fixes the uppercase inconsistency for TCPDF methods. By rule
     * the methods should start with lowercase.
     *
     * @param string $name      method name
     * @param array  $arguments method arguments
     * 
     * @return mixed the method results
     */
    public function __call($name, $arguments)
    {
        $m=lcfirst($name);
        $M=ucfirst($name);
        if (method_exists($this->PDF, $name)) {
            return tdz::objectCall($this->PDF, $name, $arguments);
        } else if (method_exists($this->PDF, $m)) {
            return tdz::objectCall($this->PDF, $m, $arguments);
        } else if (method_exists($this->PDF, $M)) {
            return tdz::objectCall($this->PDF, $M, $arguments);
        } else if (substr($m, 0, 3)=='set') {
            return $this->__set(substr($m, 3), $arguments[0]);
        } else if (substr($m, 0, 3)=='get') {
            return $this->__get(substr($m, 3));
        } else {
            tdz::log('Unknow method: '.$name, $arguments);
        }
    }
    
    public function option($name, $value=null)
    {
        if(!is_null($value)) {
            $this->_options[$name]=$value;
        }
        if(isset($this->_options[$name]))
            return $this->_options[$name];
        return null;
    }

    public function data($name, $value=null)
    {
        if(!is_null($value)) {
            $this->_vars[$name]=$value;
        }
        if(isset($this->_vars[$name]))
            return $this->_vars[$name];
        return null;
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
    public function  __set($name, $value)
    {
        $m='set'.ucfirst($name);
        $M='Set'.ucfirst($name);
        if (method_exists($this->PDF, $m)) {
            $this->$m($value);
        } else if (method_exists($this->PDF, $M)) {
            $this->$M($value);
        } else if(property_exists($this->PDF, $name)) {
            $this->PDF->$name = $value;
        }
        $this->_vars[$name]=$value;
    }

    /**
     * Magic getter. Searches for a get$Name method, or gets the stored value in
     * $_vars.
     *
     * @param string $name parameter name, should start with lowercase
     * 
     * @return mixed the stored value, or method results
     */
    public function  __get($name)
    {
        if (isset($this->_vars[$name])) {
            $ret = $this->_vars[$name];
        } else if(method_exists($this->PDF, $m='get'.ucfirst($name))) {
            $ret = $this->PDF->$m();
        } else if(property_exists($this->PDF, $name)) {
            $ret = $this->PDF->$name;
        }
        return $ret;
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
        return (isset($this->_vars[$name]) || isset($this->PDF->$name));
    }
    /**
     * ArrayAccess abstract method. Gets stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return mixed the stored value, or method results
     * @see __get()
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($name)
    {
        return $this->__get($name);
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
    public function offsetSet($name, $value): void
    {
        $this->__set($name, $value);
    }
    /**
     * ArrayAccess abstract method. Unsets parameters to the PDF. Not yet implemented
     * to the PDF classes — only unsets values stored in $_vars
     *
     * @param string $name parameter name, should start with lowercase
     * 
     * @return void
     */
    public function offsetUnset($name): void
    {
        unset($this->_vars[$name]);
    }
}