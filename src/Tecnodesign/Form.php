<?php
/**
 * Form building, validation and output methods
 *
 * This package implements applications to build HTML forms
 *
 * PHP version 5.2
 *
 * @category  Form
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: Form.php 1282 2013-09-28 16:34:07Z capile $
 * @link      http://tecnodz.com/
 */

/**
 * Form building, validation and output methods
 *
 * This package implements applications to build HTML forms
 *
 * @category  Form
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */
class Tecnodesign_Form implements ArrayAccess
{
    protected $id=null, $method='post', $action='', $fields=null, $model=null, $err=null, $prefix='';
    public $buttons=array('submit'=>'*Send'), $attributes=array();
    private static $_instances=null;
    public static $enableStyles=false;
    private $uid;


    public function __construct($def)
    {
        if (isset($def['id'])) {
            $id = $def['id'];
            $this->prefix = $id;
            $this->id=$id;
        } else {
            $id = uniqid();
        }
        $this->register($id);
        if (isset($def['buttons'])) {
            if(!$def['buttons']) {
                $this->buttons = array();
            } else if(!is_array($def['buttons'])) {
                $this->buttons['submit']=$def['buttons'];
            } else {
                $this->buttons = $def['buttons'] + $this->buttons;
            }
        }
        if (!isset($def['action'])) {
            $this->action = tdz::getRequestUri();
        } else {
            $this->action = $def['action'];
        }
        if (isset($def['method'])) {
            $this->method = $def['method'];
        }
        if (isset($def['model'])) {
            $this->setModel($def['model']);
        }
        $this->fields=new arrayObject();
        if (isset($def['fields'])) {
            $last = '';
            foreach ($def['fields'] as $fn=>$fd) {
                if(is_string($fd)) {
                    if(!isset($before)) $before = '';
                    $before .= $fd;
                    continue;
                }
                $fd['prefix'] = $this->prefix;
                $fd['id'] = $fn;
                $last = $fn;
                if(isset($before)) {
                    if(!isset($fd['before'])) $fd['before'] = '';
                    
                    $fd['before'] = $before;
                    unset($before);
                }
                $this->fields[$fn]=new Tecnodesign_Form_Field($fd, $this);
                unset($def['fields'][$fn], $fd);
            }
            if(isset($before)) {
                $this->fields[$last]->after .= $before;
                unset($before);
            }
            unset($def['fields']);
        }

        if(isset($def['attributes']) && is_array($def['attributes'])) {
            $def += $def['attributes'];
        }
        foreach($def as $an=>$av){
            if(!isset($this->$an)) {
                $this->attributes[$an]=$av;
            }
        }
    }

    public function register($id=null)
    {
        if (is_null(self::$_instances)) {
            self::$_instances=new arrayObject();
        }
        if(!is_null($id)) {
            $this->uid = $id;
            self::$_instances[$this->uid]=$this;
        }
        return $this->uid;
    }

    public static function instance($id)
    {
        if(!$id) {
            $id = array_shift(array_keys((array)self::$_instances));
        }
        if(!isset(self::$_instances[$id])) {
            self::$_instances[$id] = new Tecnodesign_Form();
        }
        return self::$_instances[$id];
     }

    public static function addInstance($id, $form)
    {
        $form->register($id);
    }
    
    public static function getInstance($id)
    {
        if(isset(self::$_instances[$id])) {
            return self::$_instances[$id];
        }
    }
    
    protected function getEnctype()
    {
        if(!isset($this->attributes['enctype'])) {
            $this->attributes['enctype']='application/x-www-form-urlencoded';
            foreach($this->fields as $f) {
                if($f->type=='file' || ($f->accept && isset($f->accept['file']))) {
                    $this->attributes['enctype']='multipart/form-data';
                    break;
                }
            }
        }
        return $this->attributes['enctype'];
    }
    
    public function setPrefix($prefix='')
    {
        $this->prefix  = $prefix;
        foreach($this->fields as $fn=>$f) {
            $f->prefix=$this->prefix;
        }
    }

    public function getField($fn)
    {
        if($p=strpos($fn, '[')) {
            return false;
            /*
            if(preg_match('/^([^\[]+)\[([0-9]+)\]\[([^\[]+)\](.*)/', $fn, $m)) {
                $f = $m[1];
                $i = $m[2];
                $n = $m[3].$m[4];
                if(isset($this->fields[$f]) && $this->fields[$f]->type!='form') return false;
                tdz::log(__METHOD__, $m, var_export($this->fields[$f], true));
            } else {
                $fn = preg_replace('/\[.*$/', '', $fn);
            }
            */
        }
        if(isset($this->fields[$fn])) {
            return $this->fields[$fn];
        } else {
            return false;
        }
    }
    
    public function render($arg=array())
    {
        $tpl = false;
        if(isset($_SERVER['HTTP_TDZ_ACTION']) && $_SERVER['HTTP_TDZ_ACTION']=='refreshFields' && isset($_SERVER['HTTP_TDZ_PARAMS']) && ($fs = json_decode($_SERVER['HTTP_TDZ_PARAMS'], true))) {
            // check if it's this form
            $r=false;
            if(isset($_SERVER['HTTP_TDZ_TARGET']) && $_SERVER['HTTP_TDZ_TARGET']==$this->id) $r=true;
            else {
                $r=true;
                foreach($fs['f'] as $k) {
                    if(!isset($this->fields[$k]) && !$this->getField($k)) {
                        $r=false;
                        break;
                    }
                }
            }
            if($r) {
                $r=array();
                foreach($fs['d'] as $k=>$v) {
                    if($F=$this->getField($k))
                        $F->resetChoicesFilter()->setValue($v, false);
                    else unset($fs['d'][$k]);
                    unset($F, $k, $v);
                }
                foreach($fs['f'] as $k) {
                    if($F=$this->getField($k))
                        $r[$k]=$F->render(array('template'=>false));
                    unset($F, $k, $v);
                }
                tdz::output($r, 'json');
            }

        }

        if (isset($arg['template'])) {
            if(file_exists($arg['template'])) {
                $tpl = $arg['template'];
            } else {
                $app = tdz::getApp();
                if(file_exists($app->tecnodesign['templates-dir'].'/'.$arg['template'])) {
                    $tpl = $app->tecnodesign['templates-dir'].'/'.$arg['template'];
                }
            }
            unset($arg['template']);
        }
        if(!$tpl) {
            $tpl = substr(__FILE__, 0, strlen(__FILE__)-4).'/Resources/templates/form.php';
        }
        $this->getEnctype();
        $vars = array();
        foreach($this as $k=>$v){
            $vars[$k]=$v;
        }
        return tdz::exec(array('variables'=>$vars, 'script'=>$tpl));
    }
    
    /**
     * Validates the form (and the model & nested forms, if binded)
     *
     * @param array $values values to be validated
     * 
     * @return bool true on success, false on error — each form field will have 
     *              the referred error in this case
     */
    public function validate($values=array())
    {
        $valid = true;
        if($this->getEnctype()=='multipart/form-data') {
            $values = tdz::postData($values);
        }
        $values = tdz::fixEncoding($values);
        if($this->id && isset($values[$this->id]) && is_array($values[$this->id])) {
            $values = $values[$this->id];
        }
        foreach($this->fields as $fn=>$fv) {
            if(!$fv->setValue($this->_value($fn, $values))) {
                $valid = false;
            }
            unset($fn, $fv);
        }
        return $valid;
    }

    private function _value($fn, $d)
    {
        if($fn==='') return $d;
        else if(isset($d[$fn])) return $d[$fn];
        else if(($slug=tdz::slug($fn)) && isset($d[$slug])) {
            return $d[$slug];
        } else if(strpos($fn, '[')!==false) {
            $p = strpos($fn, '[');
            $fn0 = substr($fn, 0, $p);

            if(isset($d[$fn0]) && is_array($d[$fn0])) {
                $fn1 = substr($fn, $p+1);
                $p = strpos($fn1, ']');
                $fn2 = substr($fn1, 0, $p);
                if(!isset($d[$fn0][$fn2])) return false;
                return $this->_value(substr($fn1, $p+1), $d[$fn0][$fn2]);
            }


        }
        return false;
    }

    public function resetErrors()
    {
        $this->err='';
        foreach($this->fields as $fn=>$fv) {
            $e = $fv->resetError();
        }
    }
    
    public function getError($array=false)
    {
        $s=($array)?(array()):('');
        foreach($this->fields as $fn=>$fv) {
            $e = $fv->getError();
            if ($e) {
                if($array) $s[$fn] = implode('; ', $e);
                else $s .= '<div id="error_'.$fn.'">'.implode('<br />', $e).'</div>';
            }
        }
        return $s;
    }

    public function getModel()
    {
        if($this->model) {
            return self::model($this->model);
        }
    }

    public function setModel($o)
    {
        if($o instanceof Tecnodesign_Model) {
            if(is_null(self::$models)) {
                self::$models = array();
            }
            if(self::$models && in_array($o, self::$models)) {
                $id = array_search($o, self::$models);
            } else if($o->isNew()) {
                $id = get_class($o).'#new-'.$this->uid;
            } else {
                $id = get_class($o).'#'.$o->getPk();
                if(isset(self::$models[$id])) {
                    $id .= '-'.$this->uid;
                }
            }
            $this->model = $id;
            self::$models[$id] = $o;
        }
        return $this;
    }

    protected static $models; 
    public static function model($id)
    {
        // $id is $cn($pk)
        if(isset(self::$models) && isset(self::$models[$id])) return self::$models[$id];
        return null;
    } 

    /**
     * Get current form values
     *
     * @return array form values, indexed by key
     */
    public function getData()
    {
        $d = array();
        foreach($this->fields as $fn=>$fv) {
            $d[$fn]=$fv->getValue();
        }
        return $d;
    }

    /**
     * Magic terminator. Returns the page contents, ready for output.
     * 
     * @return string page output
     */
    function __toString()
    {
        try {
            return $this->render();
        } catch(Exception $e) {
            tdz::log($e->getMessage());
            return '';
        }
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
        if (method_exists($this, $m)) {
            $this->$m($value);
        } else if(isset($this->$name) || is_null($this->$name)) {
            $this->$name=$value;
        } else if($value instanceof Tecnodesign_Form_Field) {
            $this->fields[$name] = $value;
        } else if(is_array($value)) {
            $this->fields[$name] = new Tecnodesign_Form_Field($value);
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
    public function  __get($name)
    {
        $m='get'.ucfirst($name);
        $ret = false;
        if (method_exists($this, $m)) {
            $ret = $this->$m();
        } else if (isset($this->$name)) {
            $ret = $this->$name;
        } else if(isset($this->fields[$name])) {
            $ret = $this->fields[$name];
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
    public function offsetExists($name)
    {
        return isset($this->fields[$name]);
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
     * ArrayAccess abstract method. Unsets parameters to the PDF. Not yet implemented
     * to the PDF classes — only unsets values stored in $_vars
     *
     * @param string $name parameter name, should start with lowercase
     * 
     * @return void
     */
    public function offsetUnset($name)
    {
        if(isset($this->fields[$name])) unset($this->fields[$name]);
        else unset($this->$name);
    }

}