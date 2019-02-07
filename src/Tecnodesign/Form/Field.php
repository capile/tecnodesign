<?php
/**
 * Form Field building, validation and output methods
 *
 * This package implements applications to build HTML forms
 *
 * PHP version 5.4
 *
 * @category  Form
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2017 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Form_Field implements ArrayAccess
{
    public static 
        $hashMethods=array(
            'datetime'=>'date("Ymd/His_").tdz::slug($name,"._")',
            'time'=>'microtime(true)',
            'md5'=>'md5_file($dest)',
            'sha1'=>'sha1_file($dest)',
            'none'=>'$name',
        ),
        $dateInputType='date',
        $dateInputFormat,
        $datetimeInputType='datetime-local',
        $datetimeInputFormat,
        $emailInputType='email',
        $numberInputType='number',
        $rangeInputType='range',
        $urlInputType='url',
        $searchInputType='search',
        $phoneInputType='tel',
        $enableMultipleText=true,
        $allowedProperties=array('on'),
        $tmpDir='/tmp'
        ;
    
    /**
     * Common attributes to each form field. If there's a set$Varname, then it'll 
     * be used to check the validity of the added information.
     */
    protected 
        $prefix=false,          // prefix to be added to the form field, useful for CSRF and subforms
        $id=false,              // field ID, usually automatically created from key index
        $type='text',           // field type, must have a corresponding function render$Type
        $form,                  // form instance id
        $bind,                  // model this field is conected to, accepts relations
        $alias,                 // supports bind from the model side
        $attributes=array(),    // element attributes, usually class names and data-*
        $placeholder=false,     // placeholder text
        $scope=false,           // scope to be used in references and sub forms
        $label=false,           // label, if not set will be build from $name
        $choices=false,         // for select, checkbox and radio types, the acceptable options (method or callback)
        $choicesFilter,         // filter for the choices, usually based on another property
        $serialize,             // if the contents should be serialized, and by which serialization method
        $tooltip=false,         // additional tooltips to be shown on focus
        $renderer=false,        // use another renderer instead of the template, accepts callbacks
        $error=false,           // field errors
        $filters=false,         // filters this field choices based on another field's value
        $dataprop,              // 
        $class='',              // container class names (attribute value, use spaces for multiple classes)
        $template=false,        // custom template, otherwise, guess from $type
        $rules=false,           // validation rules, regular expression => message
        $_className,            // class name
        $multiple=false,        // for select and checkboxes, if accepts multiple values
        $required=false,        // if this field is mandatory (raises errors)
        $html_labels=false,     // if true, labels and other template contents won't be escaped
        $messages=null,         // 
        $disabled=false,        // should updates be disabled?
        $readonly=false,        // makes this readonly
        $size=false,            // size, in bytes, for the contents of this field, for numeric types use $range
        $min_size=false,        // minimum size, in bytes, for the contents of this field, for numeric types use $range
        $value,                 // value of the field
        $range=false,           // range valudation rules = array($min, $max)
        $decimal=0,             // decimal values accepted
        $accept=false,          // content types accepted, used for file uploads
        $toAdd=null,            // for subforms
        $insert=true,           
        $update=true,           
        $before=false,          // content to be displayed before the field
        $fieldset=false,        // fieldset label this field belongs to
        $after=false,           // content to be displayed after the field
        $next,                  // tab order (use field name)
        $default,               // default field value
        $query;
    public static $labels = array('blank'=>'—'), $maxOptions=500;

    public function __construct($def=array(), $form=false)
    {
        $schema = false;
        if ($form) {
            $this->setForm($form);
            $model = $form->model;
            if ($model) {
                $cn = get_class($model);
                $this->_className=$cn;
                $schema = $this->getSchema();
                if(!isset($def['bind']) && isset($schema['columns'][$def['id']])) {
                    $def['bind'] = $def['id'];
                }
            }
        }
        if (isset($def['bind'])) {
            $bdef = $this->setBind($def['bind'], true);
            if(is_array($bdef)) $def += $bdef;
            unset($bdef);
            /*
            if(!isset($def['value']) && isset($model) && isset($model[$def['bind']])) {
                $def['value'] = $model[$def['bind']];
            }
            */
        }
        $val = '';
        if(isset($def['value'])) {
            $val = $def['value'];
            unset($def['value']);
        }
        if($def) {
            $def = static::properties($def);
            foreach ($def as $name=>$value) {
                $this->__set($name, $value);
            }
        }
        if($val!='') {
            $this->setValue($val);
        }
    }

    public function setForm($F)
    {
        $this->form = $F->register();
    }

    public function getForm()
    {
        return Tecnodesign_Form::instance($this->form);
    }

    public function getModel()
    {
        return Tecnodesign_Form::instance($this->form)->model;
    }
    
    public function getSchema()
    {
        $cn = false;
        if(is_null($this->_className)) {
            if($this->form && $this->getModel()) {
                $cn = get_class($this->getModel());
                if($cn instanceof Tecnodesign_Model) {
                    $this->_className = $cn;
                } else {
                    $cn = false;
                }
            }
        } else {
            $cn=$this->_className;
        }
        if($cn) {
            return $cn::$schema;
        }
        return false;
    }
    
    
    public function setMessages($msgs=array())
    {
        if(is_array($msgs)) {
            if(!is_array($this->messages)) {
                $this->messages = array();
            }
            foreach($msgs as $k=>$v) {
                $this->messages[$k]=$v;
            }
        }
    }
    
    
    /**
     * Binds field to $form->model column or relation
     */
    public function setBind($name, $return=false, $recursive=3)
    {
        $M = $this->getModel();
        if(!$M) return false;
        if(strpos($name, ' ')!==false) $name = substr($name, strrpos($name, ' ')+1);

        $schema = $this->getSchema();

        if(($p=strpos($name, '.')) && isset($schema['columns'][substr($name, 0, $p)]['serialize'])) $fd = $schema['columns'][substr($name, 0, $p)];

        if($schema && (isset($fd) || isset($schema['columns'][$name]) || isset($schema['relations'][$name]))) {
            $this->bind = $name;
            if (isset($schema['relations'][$name]) && $schema['relations'][$name]['type']=='one') {
                $this->bind = $schema['relations'][$name]['local'];
            }
            if($return) {
                $return = array();
                if(!isset($fd) && isset($schema['columns'][$name])) $fd=$schema['columns'][$name];
                $return['required']=(isset($fd['null']) && !$fd['null']);
                if(isset($fd)) {
                    $return = static::properties($fd, $M->isNew());
                } else {
                    $rel = $schema['relations'][$name];
                    if($rel['type']=='one') {
                        $return['type']='select';
                        $return['choices']=$name;
                    } else {
                        $return['type']='form';
                    }
                }
                unset($M, $name, $schema, $rel, $fd);
                return $return;
            }
        } else if(isset($M::$schema['form'][$name]['bind']) && preg_replace('/^.*\s([^\s]+)$/', '$1', $M::$schema['form'][$name]['bind'])!=$name && $recursive--) {
            return $this->setBind($M::$schema['form'][$name]['bind'], $return, $recursive);
        } else if(substr($name, 0, 1)=='_' || property_exists($M, $name) || $M::$allowNewProperties || (($cm=tdz::camelize($name, true)) && method_exists($M, 'get'.$cm) && method_exists($M, 'set'.$cm))) {
            $this->bind = $name;
            unset($M, $name, $schema);
            return array();
        } else {
            throw new Tecnodesign_Exception(array(tdz::t('Field name "%s" is bound to non-existing model'), $name));
        }
    }
    
    
    public function setUpdate($update)
    {
        $this->update = (bool) $update;
        if($this->bind) {
            if(!$this->update && !$this->getModel()->isNew()) {
                $this->disabled=true;
            }
        }
    }
    
    public function setInsert($insert)
    {
        $this->insert = (bool) $insert;
        if($this->bind) {
            if(!$this->insert && $this->getModel()->isNew()) {
                $this->disabled=true;
            }
        }
    }
    
    
    public function setPlaceholder($str) {
        if(substr($str, 0, 1)=='*') {
            $tlib = ($this->bind && ($schema=$this->getSchema()))?('model-'.$schema['tableName']):('form');
            $str = tdz::t(substr($str, 1), $tlib);
        }
        $this->placeholder = $str;
    }
    
    
    public function setType($type)
    {
        if($type=='') {
            $type = 'text';
        }
        if(!method_exists($this, 'render'.ucfirst($type))) {
            throw new Tecnodesign_Exception(array(tdz::t('Field type "%s" is not available.'), $type));
        }
        $this->type = $type;
    }
    
    public static function id($name)
    {
        return str_replace('-', '_', tdz::slug($name, '§,', true));
    }

    public function getId()
    {
        return static::id($this->getName(false));
        /*
        $id = '';
        if ($this->prefix) {
            $id .= tdz::textToSlug($this->prefix).'_';
        }
        if (is_null($this->id)) {
            $this->id = 'f'.uniqid();
        }
        $id .= tdz::textToSlug($this->id);
        return $id;
        */
    }

    public function getName($useAttributes=true)
    {
        $name = '';
        if (is_null($this->id)) {
            $this->id = 'f'.uniqid();
        }
        if($useAttributes && isset($this->attributes['name'])) {
            $id = $this->attributes['name'];
        } else {
            $id = $this->id;//tdz::slug($this->id);
        }
        if ($this->prefix) {
            $name = $this->prefix.'['.$id.']';
        } else {
            $name = $id;
        }        
        if(($this->multiple && $this->type!='form') || $this->type == 'file') {
            $name.='[]';
        }
        return $name;
    }
    
    public function getValue()
    {
        if(is_null($this->value) && $this->bind) {
            try {
                $M = $this->getModel();
                if(method_exists($M, $m='get'.\tdz::camelize($this->bind, true))) {
                    $this->value = $M->$m();
                } else {
                    $this->value = $M->{$this->bind};
                }
                if($this->value instanceof Tecnodesign_Collection) {
                    $this->value = ($this->value->count()>0)?($this->value->getItems()):(array());
                }
            } catch(Exception $e) {
                $this->value = false;
            }
            if(($this->value===false || is_null($this->value)) && !is_null($this->default)) {
                $this->value = $this->default;
            }
        }
        return $this->value;
    }
    
    public function setValue($value=false, $outputError=true)
    {
        $this->error=array();
        $value = $this->parseValue($value);

        foreach($this->getRules() as $m=>$message) {
            $msg = '';
            try {
                if(substr($m, 0, 6)=='model:') {
                    $fn = substr($m,0,6);
                    $tg = $this->getModel();
                } else if(strpos($m, '::') && !strpos($m, '(')) {
                    list($tg, $fn) = explode('::', $m);
                } else {
                    $fn = 'check'.ucfirst($m);
                    $tg = $this;
                }
                if(method_exists($tg, $fn)) {
                    if(is_object($tg)) {
                        $value = $tg->$fn($value, $message);
                    } else {
                        $value = $tg::$fn($value, $message);
                    }
                //} else {
                //    \tdz::log('[DEPRECATED] is this necessary? ', "eval(\$value = {$m});");
                //    @eval("\$value = {$m};");
                }
                unset($tg, $fn);
                if($value===false && $outputError) {
                    if(count($this->errors)==0) {
                        $msg = sprintf(tdz::t($message, 'exception'), $this->getLabel(), $value);
                        $this->error[$msg]=$msg;
                        throw(new Tecnodesign_Exception($msg));
                    }
                    break;
                }
            } catch(Exception $e) {
                if($outputError) {
                    $msg = $e->getMessage();
                    //$msg .= var_export($value, true)." {$m};";
                    $this->error[$msg] = $msg;
                    //$cn = get_class((object) $this->getModel());
                    //tdz::log($cn.": Could not set '".print_r($value, true)."' to {$this->id}\n because of errors in {$m}: {$msg}");
                }
                break;
            }
        }

        if($this->type=='form' && $this->bind) {
            if(!is_null($this->toAdd) && isset($this->toAdd[$this->bind])) {
                foreach($value as $oid=>$mvalue) {
                    foreach($this->toAdd[$this->bind] as $fn=>$fv) {
                        if($fv!='') {
                            $value[$oid][$fn]=$fv;
                        }
                    }
                }
            }
        }
        if(isset($this->filters) && is_array($this->filters)) {
            $F = $this->getForm();
            foreach($this->filters as $fn=>$w) {
                if(isset($F[$fn])) {
                    if($value) {
                        if(!is_array($F[$fn]->choicesFilter)) $F[$fn]->choicesFilter=array();
                        $F[$fn]->choicesFilter[$w]=$value;
                    } else if(isset($F[$fn]->choicesFilter[$w])) {
                        unset($F[$fn]->choicesFilter[$w]);
                    }
                }
                unset($fn, $w);
            }
            unset($F);
        }
        $update = $this->value;
        $this->value = $value;
        if($this->bind) {
            $o = $this->getModel();
            if(isset($o::$schema['relations'][$this->bind])) {
                if($update) {
                    $o->setRelation($this->bind, $update, true);
                }
                // map bindings
                $o->setRelation($this->bind, $value);
            }
        }

        if(count($this->error)>0) {
            return false;
        }
        $this->error = false;
        return true;
    }

    public function resetError()
    {
        $this->error=false;
    }

    public function checkRequired($value, $message='')
    {
        if($this->disabled) {
            $value = $this->getValue();
        }
        if($value=='') {
            throw new Tecnodesign_Exception(array(tdz::t($message, 'exception'), $this->getLabel(), $value));
        }
        return $value;
    }
    
    public function checkModel($value, $message='')
    {
        if(is_null($this->value)) {
            $this->getValue();
        }
        if($this->disabled) {
            return $this->value;
        } else if(!$this->bind) {
            return false;
        }
        $cn = $this->bind;
        $fn = ($cn!=$this->name)?($this->name):($cn);
        $M = $this->getModel();
        $serialize = null;
        if($this->prefix) {
            $p0 = preg_replace('/[\[\.\]].+/', '', $this->prefix);
            if(isset($M::$schema['columns'][$p0]['serialize'])) {
                $serialize = $M::$schema['columns'][$p0]['serialize'];
            }
            if($serialize) {
                $cn = preg_replace('/[\[\]]+/', '.', $this->prefix).$cn;
            }
        }

        $m = 'validate'.tdz::camelize($fn, true);
        if(method_exists($M, $m) || method_exists($M, $m='validate'.tdz::camelize($cn, true))) {
            $newvalue = $M->$m($value);
            if(!is_bool($newvalue)) $value = $newvalue;
            unset($newvalue);
        }
        if($value!==$this->value || $M->$cn!==$value) {
            $value = $M->$cn = $value;
        }
        unset($cn, $M, $fn, $m);
        return $value;
    }
    
    public function checkChoices($value, $message='')
    {
        if($value===false) {
            return false;
        }
        if($this->type=='form') return $value;
        if(tdz::isempty($value) && !$this->required){
            $value=null;
            return $value;
        }
        if($this->multiple && (is_array($value) || strpos($value, ',')!==false)) {
            $join=false;
            if(!is_array($value)) {
                $value = explode(',', $value);
                $join = true;
            }
            $count=0;
            foreach($value as $k=>$v) {
                if(!tdz::isempty($v)) {
                    if(!$this->checkChoices($v, $message)) {
                        return false;
                    } else {
                        $count++;
                    }
                }
            }
            if($count==0 && $this->required) {
                return false;
            } else {
                if($join) {
                    $value = implode(',', $value);
                }
                return $value;
            }
        } else {
            if (!$this->getChoices($value)) {
                throw new Tecnodesign_Exception(array(tdz::t($message, 'exception'), $this->getLabel(), $value));
            }
        }
        return $value;
    }
    
    public function checkForm($value, $message='')
    {
        if(!is_array($value)) {
            $value = array();
            //throw new Tecnodesign_Exception(array(tdz::t($message, 'exception'), $this->getLabel(), $value));
        }
        $valid = true;
        $M = $this->getModel();
        $schema = $M::schema();
        $sid = $scope = (!$this->scope)?('subform'):($this->scope);

        if(!isset($schema['relations'][$this->bind]) && $this->choices && is_string($this->choices) && isset($schema['relations'][$this->choices]) && $schema['relations'][$this->choices]['local']==$this->bind) {
            $this->bind = $this->choices;
            $this->choices=null;
        }
        if($this->bind && isset($schema['relations'][$this->bind])) {
            $rel = $schema['relations'][$this->bind];
            $R = $this->getValue();
            if(!$R) {
                $R = $M->getRelation($this->bind, null, null, false);
                if(!$R) $R=array();
            }
            if(is_object($R)) {
                if($R instanceof Tecnodesign_Collection) {
                    $R = $R->getItems();
                    if(!$R) $R=array();
                } else if($R instanceof Tecnodesign_Model) {
                    $R = array($R);
                    $M->setRelation($this->bind, $R);
                }
            } else if(!$R) {
                $R=array();
            }
            if(count($value)==0 && count($R)==0) {
                return $value;
            }
            $add=array();
            if($this->bind && isset($schema['relations'][$this->bind])) {
                if(isset($rel['params'])) {
                    $add = $rel['params'];
                }
                $cn = (isset($rel['className']))?($rel['className']):($this->bind);
                if ($rel['type']=='one') {
                    $this->size=1;
                }
                if(!is_array($rel['foreign'])) {
                    $fk[] = $rel['foreign'];
                    $add[$rel['foreign']] = $M->{$rel['local']};
                } else {
                    $fk = $rel['foreign'];
                    foreach($rel['foreign'] as $i=>$fn) {
                        $ln = $rel['local'][$i];
                        $add[$fn] = $M->{$ln};
                    }
                }
                if(count($add) > 0) {
                    if(is_null($this->toAdd)) {
                        $this->toAdd=array();
                    }
                    $this->toAdd[$this->bind]=$add;
                }
                $vcount = count($value);
            }
            $new = ($M->isNew())?($rel['foreign']):(false);
            unset($M, $vcount, $schema);
            if(!is_array($scope)) $scope = $cn::columns($scope);
            if(!$scope) $scope = array_keys($cn::$schema['columns']);
            $bnull = array();
            foreach($scope as $label=>$fn) {
                if(is_array($fn)) {
                    if(isset($fn['bind'])) {
                        $fn = $fn['bind'];
                    } else {
                        continue;
                    }
                }
                if($p=strrpos($fn, ' ')) $fn = substr($fn, $p+1);
                if(!isset($add[$fn]) && ((isset($cn::$schema['columns'][$fn]) && !isset($cn::$schema['columns'][$fn]['primary'])) || substr($fn, 0, 1)=='_')) {
                    $bnull[$fn]='';
                }
                unset($fn);
            }

            foreach($value as $i=>$v) {
                $v += $bnull;
                if(isset($R[$i])) {
                    $O = $R[$i];
                    if(is_array($O)) {
                        $v += $O;
                        $O = new $cn($O, false, false);
                    } else {
                        // check if $pk changed, if it did, remove old record
                        if($pk = $O->getPk(true)) {
                            $pkdel = true;
                            foreach($pk as $pkf=>$pkv) {
                                if(!($pkv && isset($v[$pkf]) && $v[$pkf]!=$pkv)) {
                                    $pkdel = false;
                                    unset($pk[$pkf], $pkf, $pkv);
                                    break;
                                }
                                unset($pk[$pkf], $pkf, $pkv);
                            }
                            unset($pk);
                            if($pkdel) {
                                $R[microtime()]=$O;
                                unset($O);
                                $O = new $cn($v, true, false);
                            } else {
                                $v += $O->asArray();
                            }
                        } else {
                            $v += $O->asArray();
                        }
                    }
                    unset($R[$i]);
                } else {
                    $v += $add;
                    $O = new $cn(null, true, false);
                }
                try {
                    $F = $O->getForm($sid, true);
                    $F->prefix = $this->getName().'['.$i.']';
                    if(is_array($new)) {
                        foreach($new as $fn) $F[$fn]->disabled=true;
                    } else if($new) {
                        if(isset($F[$new]))
                            $F[$new]->disabled=true;
                    }
                    if(!$F->validate($v)) {
                        $valid = false;
                    }
                    $value[$i] = $O;
                } catch(Exception $e) {
                    throw new Tecnodesign_Exception(array(tdz::t($message, 'exception').' '.$e->getMessage(), $this->getLabel(), $value));
                }
                unset($F, $O, $v);
            }

            unset($bnull);
            if($R) {
                foreach($R as $i=>$O) {
                    $O->delete(false);
                    $value[] = $O;
                    unset($O, $R[$i], $i);
                }
            }
        } else if($this->bind && $this->serialize && ($fo=$this->getSubForm())) {
            if(!$value) {
                $value = array();
            } else if(!is_array($value)) {
                $value = tdz::unserialize($value, $this->serialize);
            }
            $p0 = $fo['prefix'];
            $fo['prefix'] = $p0;

            foreach($value as $i=>$o) {
                unset($value[$i]);
                $fo['id'] = $p0.'['.$i.']';
                $F = new Tecnodesign_Form($fo);
                if(!$F->validate($o)) {
                    $valid = false; 
                    break;
                } else {
                    $value[$i] = $F->getData();
                }
            }
        } else {
            $valid = false;
        }
        if(!$valid) {
            $this->setError(sprintf(tdz::t($message, 'exception'), $this->getLabel()));
            //throw new Tecnodesign_Exception(array(tdz::t($message, 'exception'), $this->getLabel(), $value));
            //return false;
        }
        return $value;
    }
    
    public function checkSize($value, $message='')
    {
        if($this->type=='form') {
            if(is_object($value)) {
                if($value instanceof Tecnodesign_Model) {
                    $size = 1;
                } else if(($value instanceof Tecnodesign_Model) || method_exists($value, 'count')) {
                    $size = $value->count();
                } else {
                    $size = count((array)$value);
                }
            } else {
                $size = (is_array($value))?(count($value)):(0);
            }
            $message = '%s should have at least %s items.';
        } else {
            if($this->type=='float' || $this->type=='decimal' || $this->type=='number') {
                $value = (string)(float) $value;
            } else if($this->type=='int' && abs($value)>0) {
                $value = (string)(int) $value;
            }
            if(is_array($value)) {
                $size = count($value);
            } else if(function_exists('mb_strlen')) {
                $size = mb_strlen($value, 'UTF-8');
            } else {
                $size = strlen($value);
            }
        }
        if (($this->min_size && $size < $this->min_size && $size>0) || ($this->size && $size > $this->size)) {
            if(is_array($message)) {
                $message[0]=tdz::t($message[0], 'exception');
                $err = $message;
            } else {
                $err = array(tdz::t($message, 'exception'));
            }
            $err[] = $this->getLabel();
            $err[] = $this->min_size;
            $err[] = $this->size;
            $err[] = $value;
            throw new Tecnodesign_Exception($err);
        }
        return $value;
    }

    public function checkRange($value, $message='')
    {
        $r = $this->range;
        $err = null;
        if(substr($this->type, 0, 4)=='date') {
            $v = tdz::strtotime($value);
            if(!is_int($r[0])) $r[0] = tdz::strtotime($r[0]);
            if(!is_int($r[1])) $r[1] = tdz::strtotime($r[1]);
        } else if(is_numeric($value)) {
            $v = $value;
        } else {
            $err = array('%s should be a number.');
        }

        if(!$err && (!($v >= $r[0]) || !($v <= $r[1]))) {
            $err = $message;
        }
        if($err) {
            if(!is_array($err)) {
                $err = array(tdz::t($err, 'exception'));
                $err[] = $this->range[0];
                $err[] = $this->range[1];
                $err[] = $this->getLabel();
                $err[] = $value;
            } else {
                $err[0] = tdz::t($err[0], 'exception');
            }
            throw new Tecnodesign_Exception($err);
        }

        return $value;
    }

    public function checkFile($value=false, $message='')
    {
        // check ajax uploader
        if(isset($_SERVER['HTTP_TDZ_ACTION']) && $_SERVER['HTTP_TDZ_ACTION']=='Upload' && ($upload=Tecnodesign_App::request('post', '_upload')) && $upload['id']==$this->getId()) {
            static $timeout = 60;
            // check id
            $U=tdz::getUser();
            $ckey = 'upload-'.hash('sha256', $upload['uid'].':'.$U->getSessionId().':'.preg_replace('/(ajax|_index)=[0-9]+/', '', tdz::requestUri()));
            $size = $upload['end'] - $upload['start'];
            if(!($u=Tecnodesign_Cache::get($ckey, $timeout))) {
                $f = tempnam(self::$tmpDir, $ckey);
                $u = array(
                    'id'=>$upload['id'],
                    'name'=>$upload['file'],
                    'file'=>$f,
                    'size'=>$upload['total'],
                    'wrote'=>$size,
                );
                Tecnodesign_Cache::set($ckey, $u, $timeout);
            } else {
                $u['wrote'] += $size;
                Tecnodesign_Cache::set($ckey, $u, $timeout);
            }

            $data = $upload['data'];
            $upload['data'] = substr($data, 0, 100).'...';

            if(strpos($data, ',')!==false) $data = substr($data, strpos($data, ',')+1);
            $fp=fopen($u['file'],"r+");
            fseek($fp, $upload['start']);
            $r = fwrite($fp, base64_decode($data), $size);
            fclose($fp);
            if(!$r || $r!=$size) {
                tdz::log('[INFO] Problem writing '.$u['file'].'. Expected to write '.$size.' bytes, but wrote '.$r);
            }

            $R = array('size'=>$size, 'total'=>$u['wrote'], 'expects'=>$u['size']);
            if($u['wrote']>=$u['size']) {
                $R['id'] = $upload['id'];
                $R['value'] = 'ajax:'.$ckey.'|'.$upload['file'];
                $R['file'] = $upload['file'];
            }
            tdz::output($R, 'json');
        }

        if(is_array($value)){
            if(isset($value['name'])) {
                $value = array($value);
            }
            $uploadDir = tdz::uploadDir();

            $max  = false;
            $type = false;
            $size = false;
            $hash = false;
            $thumb = false;
            $ext  = false;
            if($this->accept) {
                $max = (isset($this->accept['max']))?($this->accept['max']):($max);
                $size = (isset($this->accept['size']))?($this->accept['size']):($size);
                $type = (isset($this->accept['type']))?($this->accept['type']):($type);
                $hash = (isset($this->accept['hash']))?($this->accept['hash']):($hash);
                $ext = (isset($this->accept['extension']))?($this->accept['extension']):($ext);
                $thumb = (isset($this->accept['thumbnail']))?($this->accept['thumbnail']):($thumb);
            }
            if(!$hash || !isset(self::$hashMethods[$hash])) {
                $hash = 'datetime';
            }
            $hfn = self::$hashMethods[$hash];
            if($type && !is_array($type)) {
                $type = preg_split('/[\s\,\;]+/', $type, null, PREG_SPLIT_NO_EMPTY);
            }
            if($type && isset($type[0])) {
                $types = array();
                foreach($type as $ts) {
                    $multiple = false;
                    if(substr($ts, -1)=='*') {
                        $ts = substr($ts, 0, strlen($ts)-1);
                        $multiple = true;
                    } else if(substr($ts, -1)=='/') {
                        $multiple = true;
                    }
                    if($multiple) {
                        foreach(tdz::$formats as $ext=>$tn) {
                            if(substr($tn, 0, strlen($ts))==$ts) {
                                $types[$tn]=$tn;
                            }
                        }
                    } else {
                        $types[$ts]=$ts;
                    }
                }
                $type = $types;
                unset($types);
                $this->accept['type']=$type;
            }
            try {
                if($max && count($value)>$max) {
                    throw new Tecnodesign_Exception(array(tdz::t('You are only allowed to upload up to %s files.', 'exception'), $max));
                }
                $new = array();
                foreach($value as $i=>$upload) {
                    if(is_array($upload) && count($upload)==1) {
                        $upload = array_shift($upload);
                    }

                    if(!is_array($upload) && substr($upload, 0, 5)=='ajax:') {
                        $upload = array('_'=>$upload);
                    }
                    if(isset($upload['_']) && substr($upload['_'], 0, 5)=='ajax:') {
                        $uid = substr($upload['_'], 5, strpos($upload['_'], '|') -5);

                        //$U=tdz::getUser();
                        //if($u=$U->getAttribute($uid)) {
                        if($u=Tecnodesign_Cache::get($uid)) {
                            if(file_exists($u['file'])) {
                                $upload['tmp_name'] = $u['file'];
                                $upload['error'] = 0;
                                $upload['name'] = $u['name'];
                                $upload['size'] = $u['size'];
                                $upload['type'] = tdz::fileFormat($u['name']);
                                if(!$upload['type']) $upload['type'] = tdz::fileFormat($u['file']);
                                $upload['ajax'] = true;
                            }
                            Tecnodesign_Cache::delete($uid);
                        }
                        unset($U);
                    }
                    /**
                     * Result should be [disk-name]|[user-name]
                     * Multiple files are listed one per line
                     */
                    if(!isset($upload['error']) || $upload['error']==4) {
                        // no upload made, skipping
                        if(isset($upload['_'])) {
                            $new[$i] = $upload['_'];
                        } else {
                            $value[$i] = false;
                        }
                        continue;
                    }
                    $name = preg_replace('#[\?\#\$\%,\|/\\\\]+#', '', preg_replace('#[\s]+#', ' ', tdz::encodeUTF8($upload['name'])));
                    if($upload['error']>0) {
                        throw new Tecnodesign_Exception(tdz::t('Could not read uploaded file.', 'exception'));
                    } else if($size && $upload['size']>$size) {
                        throw new Tecnodesign_Exception(array(tdz::t('Uploaded file exceeds the limit of %s.', 'exception'), tdz::formatBytes($size)));
                    }
                    $file = $dest = $upload['tmp_name'];
                    $file = eval("return {$hfn};");
                    if($ext && strpos($dest, '.')===false) {
                        $ext = null;
                        if(strpos($upload['name'], '.') && isset(tdz::$formats[$ext=strtolower(substr($upload['name'], strrpos($upload['name'], '.')+1))])) {
                            if(tdz::$formats[$ext]!=$upload['type']) {
                                $ext = null;
                            }
                        } else {
                            $ext = null;
                        }
                        if(!$ext && !($ext=array_search($upload['type'], tdz::$formats))) {
                            if(preg_match('/\.([a-z0-9]){,5}$/i', $upload['name'], $m)) {
                                $ext = strtolower($m[1]);
                            }
                        }
                        if($ext) $file .= '.'.$ext;
                    }
                    if ($type && !in_array($upload['type'], $type) && !in_array(substr($upload['type'], 0, strpos($upload['type'], '/')),$type) && !in_array($ext, $type)) {
                        throw new Tecnodesign_Exception(tdz::t('This file format is not supported.', 'exception'));
                    }

                    $dest = $uploadDir.'/'.$file;
                    $dir = dirname($dest);
                    if(!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    if(isset($upload['ajax'])) {
                        @rename($upload['tmp_name'], $dest);
                        if(!file_exists($dest)) throw new Tecnodesign_Exception(tdz::t('Could not read uploaded file.', 'exception'));
                    } else if(!is_uploaded_file($upload['tmp_name']) || !copy($upload['tmp_name'], $dest)) {
                        throw new Tecnodesign_Exception(tdz::t('Could not read uploaded file.', 'exception'));
                    }
                    $new[$i]="{$file}|{$upload['type']}|{$name}";
                }
                $value = implode(",", $new);
            } catch(Exception $e) {
                $msg = $e->getMessage();
                $this->error[$msg]=$msg;
                $value = false;
            }
        }
        if(!$value && $this->bind && ($schema=$this->getSchema()) && isset($schema['columns'][$this->bind])) {
            $value=$this->getModel()->{$this->bind};
            if(!$value) $value=null;
        }
        return $value;
    }

    public function checkDns($value, $message='')
    {
        $value = trim($value);
        if($value && !tdz::checkDomain($value, array('SOA'), false)) {
            $message = tdz::t('This is not a valid domain.', 'exception');
            $this->error[$message]=$message;
        }
        return $value;
    }

    public function checkIp($value, $message='')
    {
        $value = trim($value);
        if($value && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) {
            $message = tdz::t('This is not a IP address.', 'exception');
            $this->error[$message]=$message;
        }
        return $value;
    }

    public function checkIpBlock($value, $message='')
    {
        static $err = 'This is not a valid IP block.';
        $ip = trim($value);
        if($ip) {
            $mask = null;
            if($p=strpos($ip, '/')) {
                $ip = substr($ip, 0, $p);
                $mask = substr($ip, $p+1);
            }
            if(!filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) {
                $ip = false;
            } else if(!is_numeric($mask)) {
                $ip = false;
            }
        }
        if($value && !filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)) {
            $message = tdz::t('This is not a IP address.', 'exception');
            $this->error[$message]=$message;
        }
        return $value;
    }

    public function checkEmail($value, $message=null)
    {
        if(is_array($value)) {
            if($this->multiple && $value) {
                foreach($value as $i=>$o) {
                    $value[$i] = $this->checkEmail($o, $message);
                    unset($i, $o);
                }
                return $value;
            }
            $value = implode(',', $value);
        }
        $value = trim($value);
        if($value && !tdz::checkEmail($value, false)) {
            if(!$message) {
                $message = tdz::t('This is not a valid e-mail address.', 'exception');
            }
            $this->error[$message]=$message;
        }
        return $value;
    }

    public function checkDate($value, $message='')
    {
        
        if($value != '' && !preg_match('/^[0-9]{4}(-[0-9]{1,2}(-[0-9]{1,2}([ T][0-9]{1,2}(:[0-9]{1,2}(:[0-9]{1,2}(\.[0-9]+)?)?)?)?)?)?$/', $value)) {            
            $value = date('Y-m-d H:i:s', tdz::strtotime($value, false));
        }
        return $value;        
    }

    public function checkDatetime($value, $message='')
    {
        if($value != '' && !preg_match('/^[0-9]{4}(-[0-9]{1,2}(-[0-9]{1,2}([ T][0-9]{1,2}(:[0-9]{1,2}(:[0-9]{1,2}(\.[0-9]+)?)?)?)?)?)?$/', $value)) {
            $value = date('Y-m-d H:i:s', tdz::strtotime($value));
        }
        return $value;
    }

    public function getRules()
    {
        if(!is_array($this->rules)) {
            $this->rules=array();
        }
        $rules = array();
        $m = ucfirst($this->type);
        if (method_exists($this, 'check'.$m)) {
            $rules[$this->type]='This is not a valid value.';
        }
        if($m!='None') {
            if($this->required) {
                $rules['required']='Is mandatory and should not be blank.';
            }
            if($this->bind) {
                $rules['model']='This is not a valid value.';
            } else if ($this->form && $this->getModel() && method_exists($this->getModel(), ($m='validate'.tdz::camelize($this->id, true)))) {
                $rules['model:'.$m]='This is not a valid value.';
            }
            if($this->choices) {
                $rules['choices']='This is not a valid choice.';
            }
            if($this->min_size && $this->size) {
                $rules['size']=array("Should have between %s and %s characters.", $this->min_size, $this->size);
            } else {
                if($this->size) {
                    $rules['size']=array("Should be smaller than %s characters.", $this->size);
                } else if($this->min_size) {
                    $rules['size']=array("Should be greater than %s characters.", $this->min_size);
                }
            }
            if($this->range && is_array($this->range) && count($this->range)==2) {
                $rules['range'] = array("Should be between %s and %s.", $this->range[0], $this->range[1]);
            }
        }
        $rules = $this->rules + $rules;
        if(is_array($this->messages)) {
            foreach($rules as $rn=>$m) {
                if(isset($this->messages[$rn])) {
                    $rules[$rn]=$this->messages[$rn];
                }
            }
        }
        return $rules;
    }

    public static function properties($fd, $new=null)
    {
        if(isset($fd['null'])) {
            $fd['required'] = !$fd['null'];
            unset($fd['null']);
        }
        if(isset($fd['primary'])) {
            $fd['required'] = true;
            unset($fd['primary']);
        }
        if(isset($fd['increment'])) {
            if($fd['increment']=='auto') {
                $fd['type']='hidden';
                $fd['required']=false;
            }
            unset($fd['increment']);
        }
        if(isset($fd['max'])) {
            //@TODO: set this properly
            unset($fd['max']);
        }
        if(isset($fd['min-size'])) {
            //@TODO: set this properly
            unset($fd['min-size']);
        }
        if(isset($fd['size']) && $fd['size']>0) $fd['size']=(int)$fd['size'];
        if(!isset($fd['type'])) $fd['type']='text';

        if(substr($fd['type'], -3)=='int' || $fd['type']=='float' || $fd['type']=='decimal') {
            $fd['type']='number';
        } else if($fd['type']=='string' && ((isset($fd['name']) && strpos($fd['name'], 'password')!==false)||(isset($fd['id']) && strpos($fd['id'], 'password')!==false))) {
            $fd['type']='password';
            if(!$new) {
                $fd['required']=false;
            }
        } else if($fd['type']=='string') {
            $fd['type']='text';
        }
        return $fd;
    }
    
    /**
     * Value adjustment
     * 
     * Whenever a form is posted, the values might need adjustment, like to convert search strings to keys, arrays to strings.
     *
     */
    public function parseValue($value=false)
    {
        if(substr($this->type, 0, 4)=='date') {
            if(is_array($value)) {
                ksort($value);
                $value = implode('-', $value);
            }
            if(substr($value, 0, 10)=='0000-00-00' || substr($value, 0, 2)=='--') {
                $value = '';
            } else if(preg_match('/^0{4}|[\-]0{1,2}[\-\s]/', $value)) {
                $value = preg_split('/[\-\s\:T]/', $value);
            }
            if(is_array($value)) {
                if(!implode('', $value)) {
                    return null;
                }
                ksort($value);
                foreach($value as $k=>$v){
                    if(!(int)$v) {
                        $value[$k] = ($k==0)?('1970'):('01');
                        if($k>1) {
                            break;
                        }
                    }
                }
                if(count($value)>3) { // datetime
                    $value = implode('-', array_slice($value, 0, 3)).' '.implode(':', array_slice($value, 3));
                } else {
                    $value = implode('-', $value);
                }
            }
        }
        if(is_array($value) && $this->type=='file') {
            $value = $this->checkFile($value);
        }
        if($this->multiple) {
            if(is_array($value)) $value = array_filter($value);
            if(tdz::isempty($value)) $value = null;
        } else if($this->type!='form') {
            if(is_array($value)) {
                $value = ($this->serialize)?(tdz::serialize($value, $this->serialize)):(tdz::implode($value));
            } else if($value===false) {
                $value = '';
            }
        }
        if($value===false){
            $value=null;
        }
        return $value;
    }
    
    public function getError()
    {
        return $this->error;
    }
    
    public function getClass()
    {
        $cn = $this->class;
        if($this->readonly) {
            $cn .= ' readonly';
        }
        if($this->disabled) {
            $cn .= ' disabled';
        }
        return trim($cn);
    }
    
    public function setError($msg)
    {
        if(!is_array($this->error)) {
            $this->error = array();
        }
        $this->error[$msg]=$msg;
        return $this;
    }
    
    public function countChoices($check=null)
    {
        return $this->getChoices($check, true);
    }
    
    private $_choicesCollection=null;
    private $_choicesTranslated=false;
    public function getChoices($check=null, $count=false)
    {
        if(!$this->choices) {
            if($this->bind && ($M=$this->getModel()) && method_exists($M, ($m='choices'.tdz::camelize($this->bind,true)))) $this->choices = $M->$m($check, $count);
            if(!$this->choices) $this->choices=array();
            unset($M, $m);
        }
        if (!is_array($this->choices)) {
            $choices = $this->choices;
            if (is_string($choices) && class_exists($choices)) {
                if($this->choicesFilter && is_subclass_of($choices, 'Tecnodesign_Model')) {
                    $cn = $choices;
                    if(isset($cn::$schema['events']['active-records'])) {
                        if(!is_array($cn::$schema['events']['active-records'])) $cn::$schema['events']['active-records']=array($cn::$schema['events']['active-records']);
                        if(!isset($cn::$schema['events']['original-active-records'])) $cn::$schema['events']['original-active-records']=$cn::$schema['events']['active-records'];
                        $cn::$schema['events']['active-records']=array_merge($cn::$schema['events']['active-records'],$this->choicesFilter);
                    } else {
                        $cn::$schema['events']['original-active-records']=array();
                        $cn::$schema['events']['active-records']=$this->choicesFilter;
                    }
                }
                /*
                if($this->bind) { // try relation first
                    try {
                        $m = $this->getModel();
                        $cn = get_class($m);
                        if(isset($cn::$schema['relations'][$choices])) {
                            //if(isset($cn::$schema['relations'][$choices]['className'])) {
                            //    $choices = $cn::$schema['relations'][$choices]['className'];
                            //}
                            $r = $m->getRelation($choices);
                        }
                        unset($m);
                    } catch(Exception $e) {
                        tdz::log($e->getMessage());
                    }
                }
                */
                if(!is_null($check) && !is_array($check)) {
                    return $choices::find($check, 1, 'choices');
                }
                if(is_null($this->_choicesCollection)) {
                    $t = microtime(true);
                    $this->choices = $choices::find($check, 0, 'choices');
                    if(!$this->choices) {
                        $this->choices=new Tecnodesign_Collection();
                    }
                }
            } else if(is_string($this->choices)) {
                if(strpos($this->choices, '(')) {
                    $this->choices = @eval('return '.$this->choices.';');
                } else {
                    $M = $this->getModel();
                    if(method_exists($M, $this->choices)) {
                        $m = $this->choices;
                        $this->choices = $M->$m();
                    } else {
                        $this->choices = @eval('return '.$this->choices.';');
                    }
                    unset($M, $m);
                }
            }
            if($this->choices instanceof Tecnodesign_Collection) {
                $this->_choicesCollection = $this->choices;
            } else if(!is_array($this->choices)) {
                $this->choices = array();
            }
            if($this->_choicesCollection) {
                if(!is_null($check) && !is_array($check) && $this->_choicesCollection->getQueryKey()) {
                    return $this->_choicesCollection[$check];
                } else if($count) {
                    return $this->_choicesCollection->count();
                }
                if(!$this->_choicesCollection->getQueryKey()) {
                    $this->choices=array();
                    if($this->_choicesCollection->count()) {
                        foreach($this->_choicesCollection->getItems() as $r) {
                            $this->choices[$r->getPk()]=$r;
                            unset($r);
                        }
                    }
                }
            }
        }
        if(!is_null($check) && !is_array($check)) {
            if(isset($this->choices[$check])) {
                return $this->choices[$check];
            }
            return false;
        } else if($count) {
            return count($this->choices);
        }
        if(!$this->_choicesTranslated) {
            $schema=$this->getSchema();
            $tlib = ($this->bind)?('model-'.$schema['tableName']):('field');
            foreach($this->choices as $k=>$v) {
                if(is_array($v) && isset($v['value'])) {
                    $v = $v['value'];
                } else if(is_array($v)) {
                    while(is_array($v)) {
                        $v = array_shift($v);
                    }
                }
                $val = $v;
                if(substr($val, 0, 1)=='*') {
                    $val = tdz::t(substr($val, 1), $tlib);
                    if(is_array($v)) {
                        $this->choices[$k]['value']=$val;
                    } else {
                        $this->choices[$k]=$val;
                    }
                }
            }
        }
        return $this->choices;
    }
    
    public function getLabel()
    {
        $ttable = false;
        if ($this->label===false && $this->type!='hidden') {
            $id = ($this->bind)?($this->bind):($this->id);
            $label = ucwords(strtr(tdz::uncamelize($id), '-_', '  '));
            $ttable = 'labels';
            if($schema=$this->getSchema()) {
                $ttable = 'model-'.$schema['tableName'];
            }
            $this->label = tdz::t(trim($label), $ttable);
        }
        if(substr($this->label, 0, 1)=='*' && strlen($this->label)>1) {
            if(!$ttable) {
                $ttable = 'labels';
                if($schema=$this->getSchema()) {
                    $ttable = 'model-'.$schema['tableName'];
                }
            }
            $this->label = tdz::t(substr($this->label, 1), $ttable);
        }
        return $this->label;
    }

    public function resetChoicesFilter()
    {
        if(is_string($this->choices)) {
            $cn = $this->choices;
            if(isset($cn::$schema['events']['original-active-records'])) {
                $cn::$schema['events']['active-records']=$cn::$schema['events']['original-active-records'];
            }
        }
        return $this;
    }
    
    public function render($arg=array())
    {
        $base = array('id', 'name', 'value', 'error', 'label', 'class');
        foreach ($base as $k) {
            if (!isset($arg[$k])) {
                $m = 'get'.ucfirst($k);
                $arg[$k]=$this->$m();
            }
        }
        if($this->filters) {
            if($this->prefix) {
                $this->attributes['data-filters']=$this->prefix.'['
                    . ((is_array($this->filters))?(implode('],'.$this->prefix.'[',array_keys($this->filters))):($this->filters))
                    . ']';
            } else {
                $this->attributes['data-filters']=(is_array($this->filters))?(implode(',',array_keys($this->filters))):($this->filters);
            }
        }
        if($this->next) {
            $n = explode(',',$this->next);
            foreach($n as $k=>$v) {
                $n[$k] = (substr($v,0,1)=='!')?('!'.static::id($this->prefix.substr($v,1))):(static::id($this->prefix.$v));
                unset($k, $v);
            }
            $this->attributes['data-next']=implode(',',$n);
        }
        $run = array(
            'variables' => $arg,
        );
        $input=false;
        if($this->bind) {
            $m = tdz::camelize('render-'.$this->id.'FormField');
            $M = $this->getModel();
            if(method_exists($M, $m)) {
                $input = $M->$m($arg);
            }
            unset($M, $m);
        }
        if(!$input) {
            $m = 'render' . ucfirst($this->type);
            if (!method_exists($this, $m)) {
                $m = 'renderText';
            }
            $input = $this->$m($arg);
        }
        if(!$input) {
            return $input;
        }
        $tpl = false;
        if (isset($arg['template'])) {
            if($arg['template']===false) {
                return $input;
            }
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
            $tpl = dirname(__FILE__).'/Resources/templates/field.php';
        }
        $run['script']=$tpl;
        $run['variables']['input']=$input;
        $run['variables']['field']=$this;
        $run['variables']['before']=$this->before;
        $run['variables']['after']=$this->after;
        $s = tdz::exec($run);
        if (!isset($arg['indent']) || !$arg['indent']) {
            //$s = preg_replace('/\r?\n\s*/', '', $s);
        }
        return $s;
    }
    
    public function renderForm(&$arg)
    {
        $input = '<input type="hidden" id="'.tdz::xml($arg['id']).'" name="'.tdz::xml($arg['name']).'" />';
        $jsinput = '';
        $prefix ='';
        $bind = $this->bind;
        $schema=$this->getSchema();
        if($this->choices && is_string($this->choices) && isset($schema['relations'][$this->choices]) && $schema['relations'][$this->choices]['local']==$bind) {
            $bind = $this->choices;
            $this->bind = $bind;
            $this->choices=null;
        }
        if($bind && isset($schema['relations'][$bind])) {
            $M = $this->getModel();
            $cn = get_class($M);
            if(!isset($arg['value'])) $arg['value'] = $M->getRelation($bind, null, null, false);
            if(!is_array($arg['value']) && !($arg['value'] instanceof Tecnodesign_Collection)) {
                if($arg['value']) $arg['value'] = array($arg['value']);
                else $arg['value']=array();
            }
            $rc = (isset($schema['relations'][$bind]['className']))?($schema['relations'][$bind]['className']):($bind);
            if($arg['value'] instanceof Tecnodesign_Collection) {
                $arg['value'] = $arg['value']->getItems();
            }
            foreach($arg['value'] as $id=>$value) {
                if(!is_object($value)) {
                    $arg['value'][$id] = $rc::__set_state($value);
                } else if(is_object($value) && $value->isDeleted()) {
                    unset($arg['value'][$id]);
                }
            }
            $fk=array();
            $scope = (!$this->scope)?('subform'):($this->scope);
            if(isset($schema['relations'][$bind])) {
                $rel = $schema['relations'][$bind];
                if ($rel['type']=='one') {
                    $this->size=1;
                }
                $fkvalues=array();
                if(!is_array($rel['foreign'])) {
                    $fk[] = $rel['foreign'];
                    $fkvalues[$rel['foreign']] = $M->{$rel['local']};
                } else {
                    $fk = $rel['foreign'];
                    foreach($rel['foreign'] as $i=>$fn) {
                        $ln = $rel['local'][$i];
                        $fkvalues[$fn] = $M->{$ln};
                    }
                }
                $cn = (isset($rel['className']))?($rel['className']):($bind);
                $scope = (!$this->scope)?('subform'):($this->scope);
                $model = new $cn;
                foreach($fkvalues as $fn=>$fv) {
                    if($fv) {
                        $model->$fn=$fv;
                    } else if($fv===false || $fv=='') {
                        unset($fkvalues[$fn]);
                    }
                }
                
                if(!$this->min_size || !$this->size) {
                    $form = $model->getForm($scope, false);

                    // get the template for issuing new fields with js
                    $jsinput = '<div class="item">';
                    $fid = $this->getId();
                    $prefix = $this->getName();
                    foreach($form->fields as $fn=>$f) {
                        $id = ($f->bind)?($f->bind):($f->id);
                        if(in_array($id, $fk)) {
                            $f->type = 'none';
                        }
                        $f->prefix = $prefix.'[§]';
                        $jsinput .= $f->render();
                    }
                    $jsinput .= '</div>';
                    //$jsinput = json_encode($jsinput);
                    //$jsinput = '<script type="text/javascript">/*<![CDATA[*/ var f__'.$fid.'='.$jsinput.'; /*]]>*/</script>';
                    //tdz::set('script', $jsinput);
                }
                unset($model);
            }
            unset($M);
            
            if($this->min_size && count($arg['value']) < $this->min_size) {
                while(count($arg['value']) < $this->min_size) {
                    if($bind) {
                        $arg['value'][]=new $cn($fkvalues);
                    } else {
                        $arg['value'][]=array();
                    }
                }
            }
            if($this->size > count($arg['value'])) {
                if(is_object($arg['value']) && $arg['value'] instanceof Tecnodesign_Collection) {
                    $arg['value']=$arg['value']->getItem(0, $this->size, true);
                } else {
                    $arg['value']=array_slice($arg['value'], 0, $this->size);
                }
            }
            
            if($arg['value']) {
                foreach ($arg['value'] as $i=>$model) {
                    $form = $model->getForm($scope, !$model->isNew());
                    $input .= '<div class="item '.(($i%2)?('even'):('odd')).'">';
                    foreach($form->fields as $fn=>$f) {
                        $id = ($f->bind)?($f->bind):($f->id);
                        if(in_array($id, $fk)) {
                            $f->type = 'none';
                        }
                        $f->prefix = "{$this->getName()}[{$i}]";
                        $input .= $f->render();
                    }
                    $input .= '</div>';
                    unset($model, $i);
                }
            }
        } else if($this->serialize && ($fo=$this->getSubForm())) {

            // input for javascript
            $prefix = $this->getName();
            $fo['id'] = $prefix.'[§]';
            $form = new Tecnodesign_Form($fo);
            $jsinput = '<div class="item">';
            foreach($form->fields as $fn=>$f) {
                $jsinput .= $f->render();
                unset($fn, $f);
            }
            $jsinput .= '</div>';

            $value = $this->getValue();

            if(!is_array($value)) {
                $value = tdz::unserialize($value, $this->serialize);
            }

            // loop for each entry and add to $input
            if(is_array($value)) {
                foreach($value as $i=>$o) {
                    $fo['id'] = $prefix.'['.$i.']';
                    $form = new Tecnodesign_Form($fo);
                    $input .= '<div class="item '.(($i%2)?('even'):('odd')).'">';
                    foreach($form->fields as $fn=>$f) {
                        if($f->bind) {
                            $id = $f->bind;
                            if(strpos($id, '.') && substr(str_replace('.', '_', $id), 0, strlen($this->id)+1)==$this->id.'_') {
                                $id = substr($id, strlen($this->id)+1);
                            }
                        } else {
                            $id = $F->id;
                        }
                        if(isset($o[$id])) {
                            $f->setValue($o[$id]);
                        }
                        $input .= $f->render();
                    }
                    $input .= '</div>';
                }
            }
        }
        
        if (!isset($arg['template'])) {
            $arg['template'] = dirname(__FILE__).'/Resources/templates/subform.php';
        }
        $class = '';
        if($jsinput) {
            //$jsinput = ' data-template="'.tdz::xmlEscape($jsinput).'" data-prefix="'.$prefix.'"';
            $jsinput = ' data-template="'.htmlspecialchars($jsinput, ENT_QUOTES, 'UTF-8', true).'" data-prefix="'.$prefix.'"';
            if($this->multiple) {
                $class .= ' multiple';
            }
            if($this->min_size) {
                $jsinput .= ' data-min="'.$this->min_size.'"';
            }
            if($this->size) {
                $jsinput .= ' data-max="'.$this->size.'"';
            }
        }
        $a=array('class'=>'subform items');
        if($this->attributes){
            $a+=$this->attributes;
            if(isset($this->attributes['class'])) {
                $a['class'].=' '.$this->attributes['class'];
            }
        }
        if($input || $jsinput) {
            $attr=$jsinput;
            foreach($a as $k=>$v) {
                $attr.=' '.$k.'="'.$v.'"';
            }
            $input = '<div'.$attr.'>'.$input.'</div>';
        }
        //$input = ($input || $jsinput)?('<div class="subform items"'.$jsinput.'>'.$input.'</div>'):('');
        return $input;
    }

    public function getSubForm($scope=null)
    {
        if(!$scope && $this->scope) {
            $scope = $this->scope;
        }
        $M = $this->getModel();
        if(is_string($scope) && !isset($M::$schema['scope'][$scope])) return null;
        $columns = $M::columns($scope);
        foreach($columns as $fn=>$fd) {
            unset($columns[$fn]);
            if(!is_array($fd)) {
                $fd = $M::column($fd);
                if(!$fd || !is_array($fd)) {
                    continue;
                }
            }
            if(is_int($fn)) {
                if(isset($fd['label'])) {
                    $fn = $fd['label'];
                } else if(isset($fd['id'])) {
                    $fn = $M::fieldLabel($fd['id']);
                } else {
                    continue;
                }
            }
            if(!isset($fd['bind'])) {
                if(isset($fd['id'])) {
                    $fd['bind'] = $fd['id'];
                } else {
                    $fd['bind'] = $fn;
                }
            }
            if(!isset($fd['label'])) {
                $fd['label'] = $M::fieldLabel($fd['bind']);
            }


            $columns[$fn] = $fd;
        }
        if(!$columns) return null;

        $fo = array(
            'fields'=>$columns,
            'prefix'=>$this->prefix.$this->id,
            'model'=>$this->getModel(),
        );
        return $fo;

    }
    
    public function renderEmail(&$arg)
    {
        $arg['type']=self::$emailInputType;
        $arg['data-type']='email';
        return $this->renderText($arg);
    }

    public function renderUrl(&$arg)
    {
        $arg['type']=self::$urlInputType;
        $arg['data-type']='url';
        return $this->renderText($arg);
    }

    public function renderDns(&$arg)
    {
        $arg['type']='text';
        $arg['data-type']='dns';
        return $this->renderText($arg);
    }

    public function renderSearch(&$arg)
    {
        $arg['type']=self::$searchInputType;
        $arg['data-type']='search';
        return $this->renderText($arg);
    }

    public function renderFile(&$arg)
    {
        $arg['type']='file';
        $this->getForm()->attributes['enctype']='multipart/form-data';
        if($this->multiple) {
            $this->attributes['multiple']=true;
            //$arg['name'].='[]';
        }
        $s='';
        if($this->accept && is_array($this->accept)) {
            if(isset($this->accept['uploader'])) {
                $this->attributes['data-uploader'] = (is_bool($this->accept['uploader']))?(\tdz::requestUri()):($this->accept['uploader']);
            }
            if(isset($this->accept['type'])) {
                $type = $this->accept['type'];
                if($type && !is_array($type)) {
                    $type = preg_split('/[\s\,\;]+/', $type, null, PREG_SPLIT_NO_EMPTY);
                }
                if($type && isset($type[0])) {
                    $aa = array();
                    foreach($type as $ts) {
                        if(substr($ts, -1)=='*' || strpos($ts, '/')) {
                            $aa[] = $ts;
                        } else if(substr($ts, -1)=='/') {
                            $aa[]= $ts.'*';
                        } else {
                            $aa[]='.'.$ts;
                        }
                    }
                    $this->attributes['accept'] = implode(',',$aa);
                }
                unset($type);
            }
            if(isset($this->accept['size']) && is_numeric($this->accept['size'])) {
                $this->attributes['data-size'] = $this->accept['size'];
            }
        }
        if(strpos($arg['class'], 'app-file-preview')!==false) {
            if($arg['value']) {
                $s .= '<span class="text tdz-f-file">'.$this->filePreview($arg['id']).'</span>';
            } else {
                $s .= '<span class="text"></span>';
            }
        }
        if(strpos($arg['class'], 'app-image-preview')!==false) {
            $s .= '<span class="text">'
                . (($arg['value'])?($this->filePreview($arg['id'], true)):(''))
                . '</span>';
        }
        $h = $this->renderHidden($arg);
        unset($arg['template']);
        $a = $arg;
        $a['value']='';
        if(isset($a['required'])) unset($a['required']);
        $s .= $h.$this->renderText($a);
        return $s;
    }

    public static function uploadedFile($s, $array=false)
    {
        $uploadDir = tdz::uploadDir();
        $fpart = explode('|', $s);
        $fname = array_pop($fpart);
        if(count($fpart)>=1 && ($ftmp=preg_replace('/[^a-zA-Z0-9\-\_\.]+/', '', $fpart[0])) && (file_exists($f=$uploadDir.'/'.$ftmp) || (file_exists($f=self::$tmpDir.'/'.$ftmp)))) {
            if($array) return array('name'=>$fname, 'file'=>$f);
            return $f;
        } else if(file_exists($f=$uploadDir.'/'.$fname)) {
            if($array) return array('name'=>$fname, 'file'=>$f);
            return $f;
        }
    }
    
    public function filePreview($prefix='', $img = false)
    {
        $s='';
        if($this->value){
            $files = explode(',', $this->value);
            $url = (isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING'])?($_SERVER['REQUEST_URI'].'&'):(tdz::scriptName(true).'?');
            $uploadDir = tdz::uploadDir();
            foreach($files as $i=>$fdesc) {
                $fpart = explode('|', $fdesc);
                $fname = array_pop($fpart);
                $s .= ($i>0)?(', '):('');
                if(count($fpart)>=1 && file_exists($uploadDir.'/'.$fpart[0])) {
                    $hash = $prefix.md5($fpart[0]);
                    $link = $url.$hash.'='.urlencode($fname);
                    if(isset($_GET[$hash]) && $_GET[$hash]==$fname) {
                        tdz::download($uploadDir.'/'.$fpart[0], null, $fname, 0, true);
                    }
                    if ($img) {
                        $s .= '<a href="'.tdz::xmlEscape($link).'" download="'.$fname.'"><img src="'.tdz::xmlEscape($link).'" title="'.tdz::xmlEscape($fname).'" alt="'.tdz::xmlEscape($fname).'" /></a>';
                    } else {
                        $s .= '<a href="'.tdz::xmlEscape($link).'">'.tdz::xmlEscape($fname).'</a>';
                    }
                } elseif (file_exists($f=$uploadDir.'/'.$fname) || ($this->bind && method_exists($M=$this->getModel(), $m='get'.tdz::camelize($this->bind, true).'File') && file_exists($f=$M->$m()))) { //Compatibilidade com dados de framework anteriores
                    $hash = $prefix.md5($fname);
                    $link = $url.$hash.'='.urlencode($fname);
                    if(isset($_GET[$hash]) && $_GET[$hash]==$fname) {
                        tdz::download($f, null, $fname, 0, true);
                    }
                    if ($img) {
                        $s .= '<a href="'.tdz::xmlEscape($link).'" download="'.$fname.'"><img src="'.tdz::xmlEscape($link).'" title="'.tdz::xmlEscape($fname).'" alt="'.tdz::xmlEscape($fname).'" /></a>';
                    } else {
                        $s .= '<a href="'.tdz::xmlEscape($link).'" download="'.$fname.'">'.tdz::xmlEscape($fname).'</a>';
                    }
                } else {
                    $s .= '<span>'.tdz::xmlEscape($fname).'</span>';
                }
            }
        }
        return $s;
    }

    public function renderNumber(&$arg)
    {
        $arg['type']=self::$numberInputType;
        $arg['data-type']='number';
        return $this->renderText($arg);
    }

    public function renderTel(&$arg)
    {
        $arg['type']='tel';
        return $this->renderText($arg);
    }

    public function renderRange(&$arg)
    {
        $arg['type']=self::$rangeInputType;
        $arg['data-type']='range';
        return $this->renderText($arg);
    }

    public function renderPassword(&$arg)
    {
        $arg['type']='password';
        $arg['data-type']='password';
        $arg['value']='';
        return $this->renderText($arg);
    }

    public function renderDate(&$arg)
    {
        $arg['type']=self::$dateInputType;
        $arg['data-type']='date';
        if(isset($arg['value']) && $arg['value']) {
            if(is_array($arg['value'])) {
                $arg['value']=$this->parseValue($arg['value']);
            }
            $t = strtotime($arg['value']);
            if(!self::$dateInputFormat) {
                self::$dateInputFormat = tdz::$dateFormat;
            }
            $arg['value'] = date(self::$dateInputFormat, $t);
        }
        /*
        if(Tecnodesign_Form::$enableStyles) {
            tdz::$variables['style'][Tecnodesign_Form::$enableStyles]=tdz::$assetsUrl.'/tecnodesign/css/datepicker.less';
        }
        */
        return $this->renderText($arg);
    }

    public function renderDateSelect(&$arg)
    {
        $a = array('id'=>$arg['id'], 'name'=>$arg['name']);
        if($this->placeholder) {
            $a['placeholder'] = $this->placeholder;
        }
        $bv = array('required', 'readonly', 'disabled');
        foreach ($bv as $attr) {
            $value = $this->$attr;
            if ($value) {
                $a[$attr] = true;
            }
        }
        $a += $this->attributes;
        $values = array();
        if(isset($arg['value']) && $arg['value']) {
            if(is_array($arg['value'])) {
                $arg['value']=$this->parseValue($arg['value']);
            } else if(preg_match('/^([0-9]{4})\-([0-9]{1,2})\-([0-9]{1,2})((\s|T)([0-9]{1,2})(\:[0-9]{1,2})(\:[0-9]{1,2}))?/', $arg['value'], $m)) {
                $values['y']=$m[1];
                $values['m']=str_pad($m[2], 2, '0', STR_PAD_LEFT);
                $values['d']=str_pad($m[3], 2, '0', STR_PAD_LEFT);
                if(isset($m[6])){
                    $values['h']=str_pad($m[6], 2, '0', STR_PAD_LEFT);
                    if(isset($m[7])){
                        $values['i']=str_pad(substr($m[7],1), 2, '0', STR_PAD_LEFT);
                    }
                    if(isset($m[8])){
                        $values['s']=str_pad(substr($m[8],1), 2, '0', STR_PAD_LEFT);
                    }
                }
            } else {
                $t = strtotime($arg['value']);
                list($values['y'], $values['m'], $values['d'])=explode(',',date('Y,m,d', $t));
            }
        }
        $values+=array(
            'y'=>'',
            'm'=>'',
            'd'=>'',
        );
        if(!$this->range || !is_array($this->range)){
            $this->range = array();
        }
        if(!isset($this->range[0]) || is_bool($this->range[0])) {
            $this->range[0]=0;
        } else if(!is_int($this->range[0])) {
            $this->range[0]=strtotime($this->range[0]);
        }
        if(!isset($this->range[1]) || is_bool($this->range[1])) {
            $this->range[1]=time();
        } else if(!is_int($this->range[1])) {
            $this->range[1]=strtotime($this->range[1]);
        }
        if($this->range[0]>$this->range[1]) {
            $this->range = array($this->range[1], $this->range[0]);
        }
        $rd = $this->range[1] - $this->range[0];
        if(!self::$dateInputFormat) {
            self::$dateInputFormat = tdz::$dateFormat;
        }
        $df = self::$dateInputFormat;
        
        $yf = (strpos($df, 'Y'))?('Y'):('y');
        $input=array();
        $input[$yf] = '<span class="input date-year"><select ';
        $ay=$a;
        $ay['id'].='_0';
        $ay['name'].='[0]';
        foreach ($ay as $attr=>$value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input[$yf] .= $attr . '="' . tdz::xmlEscape($value) . '" ';
        }
        $input[$yf].='><option value="" class="placeholder">'.tdz::t('Year', 'form').'</option>';
        if($this->range[1] > time()) {
            for($y=date('Y', $this->range[0]);$y<=date('Y', $this->range[1]);$y++) {
                $input[$yf] .= '<option value="'.$y.'"'.(((int)$values['y']==$y)?(' selected="selected"'):('')).'>'.(($yf=='Y')?($y):(substr($y, -2))).'</option>';
            }
        } else {
            for($y=date('Y', $this->range[1]);$y>=date('Y', $this->range[0]);$y--) {
                $input[$yf] .= '<option value="'.$y.'"'.(((int)$values['y']==$y)?(' selected="selected"'):('')).'>'.(($yf=='Y')?($y):(substr($y, -2))).'</option>';
            }
        }
        $input[$yf].='</select></span>';

        $mfs=array('F'=>true, 'm'=>false, 'M'=>true, 'n'=>false );
        $mf='m';
        $mt=false;
        foreach($mfs as $f=>$t){
            if(strpos($df, $f)) {
                $mf=$f;$mt=$t;break;
            }
        }
        $input[$mf] = '<span class="input date-month"><select ';
        $ay=$a;
        $ay['id'].='_1';
        $ay['name'].='[1]';
        foreach ($ay as $attr=>$value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input[$mf] .= $attr . '="' . tdz::xmlEscape($value) . '" ';
        }
        $input[$mf].='><option value="" class="placeholder">'.tdz::t('Month', 'form').'</option>';
        $ms=array_fill(1,12,true);
        if($rd < 86400*365) {
            /**
             * @TODO: filter when there's less than a year
             */
        }
        foreach($ms as $m=>$use) {
            if($use){
                $mk=str_pad($m, 2, '0', STR_PAD_LEFT);
                if($mf=='m') {
                    $mv = $mk;
                } else if($mf=='n') {
                    $mv = $m;
                } else {
                    $t=mktime(0, 0, 0, $m, 1, 2011);
                    $mv=date($mf, $t);
                    if($mt) {
                        $mv = tdz::t($mv, 'form');
                    }
                }
                $input[$mf] .= '<option value="'.$mk.'"'.(((int)$values['m']==$m)?(' selected="selected"'):('')).'>'.$mv.'</option>';
            }
        }
        $input[$mf].='</select></span>';

        if (strpos($df, 'j')!==false) {
            $f = 'j';
        } else {
            $f = 'd';
        }
        $input[$f] = '<span class="input date-day"><select ';
        $ay=$a;
        $ay['id'].='_2';
        $ay['name'].='[2]';
        foreach ($ay as $attr=>$value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input[$f] .= $attr . '="' . tdz::xmlEscape($value) . '" ';
        }
        $input[$f].='><option value="" class="placeholder">'.tdz::t('Day', 'form').'</option>';
        $ds=array_fill(1,31,true);
        if($rd < 86400*31) {
            /**
             * @TODO: filter when there's less than a month
             */
        }
        foreach($ds as $d=>$use) {
            if($use){
                $dk=str_pad($d, 2, '0', STR_PAD_LEFT);
                if($f=='d') {
                    $dv = $dk;
                } else {
                    $dv = $d;
                }
                $input[$f] .= '<option value="'.$dk.'"'.(((int)$values['d']==$d)?(' selected="selected"'):('')).'>'.$dv.'</option>';
            }
        }
        $input[$f].='</select></span>';
        
        // add time/seconds
        $uk = array();
        foreach($input as $k=>$v) {
            $uk[]='---'.$k.'---';
        }
        $df = str_replace(array_keys($input), $uk, $df);
        $input = str_replace($uk, array_values($input), $df);
        return $input;
    }

    public function renderDatetime(&$arg)
    {
        $arg['type']=self::$datetimeInputType;
        $arg['data-type']='datetime';
        if(isset($arg['value']) && $arg['value'] && ($t = tdz::strtotime($arg['value']))) {
            if(self::$datetimeInputType=='text') {
                if(!self::$datetimeInputFormat) {
                    self::$datetimeInputFormat = tdz::$dateFormat.' '.tdz::$timeFormat;
                }
                $arg['value'] = date(self::$datetimeInputFormat, $t);
            } else {
                $arg['value'] = date('Y-m-d\TH:i:s', $t);
            }
        }
        /*
        if(Tecnodesign_Form::$enableStyles) {
            tdz::$variables['style'][Tecnodesign_Form::$enableStyles]=tdz::$assetsUrl.'/tecnodesign/css/datepicker.less';
        }
        */
        return $this->renderText($arg);
    }
    
    public function renderColor(&$arg)
    {
        $arg['type']='color';
        
        return $this->renderText($arg);
    }

    public function renderPhone(&$arg)
    {
        $arg['type']=self::$phoneInputType;
        $arg['data-type']='phone';
        return $this->renderText($arg);
    }

    public function renderString(&$arg, $enableChoices=true)
    {
        return $this->renderText($arg, $enableChoices);
    }
    
    public function renderText(&$arg, $enableChoices=true, $enableMultiple=null)
    {
        if($this->multiple && ($enableMultiple || (is_null($enableMultiple) && static::$enableMultipleText))) {
            $v0 = $value = $arg['value'];
            if(!is_array($value) && $value) {
                if($this->serialize && ($nv=\tdz::unserialize($value))) {
                    $value = $nv;
                    unset($nv);
                } else {
                    $value = preg_split('/\s*\,\s*/', $value, null, PREG_SPLIT_NO_EMPTY);
                }
            } else if(!$value) {
                $value = array();
            }
            $s = '';
            if($this->min_size && $this->min_size > count($value)) {
                $value = array_fill(count($value) -1, $this->min_size - count($value), '');
            }
            foreach($value as $i=>$o) {
                $arg['value'] = $o;
                $s .= '<div class="item">'.$this->renderText($arg, $enableChoices, false).'</div>';
                unset($value[$i], $i, $o);
            }

            $arg['value']='';
            $jsinput = '<div class="item">'.$this->renderText($arg, $enableChoices, false).'</div>';

            $s = '<div class="input items" data-template="'.htmlspecialchars($jsinput, ENT_QUOTES, 'UTF-8', true).'" data-prefix=""'
                . (($this->min_size)?(' data-min="'.$this->min_size.'"'):(''))
                . (($this->size)?(' data-max="'.$this->size.'"'):(''))
                . '>'
                . $s
                . '</div>'
                ;

            $arg['value'] = $v0;
            return $s;
        }
        $a = array('type'=>(isset($arg['type']))?($arg['type']):('text'), 'id'=>$arg['id'], 'name'=>$arg['name'], 'value'=>(string)$arg['value']);
        if($this->size && !isset($this->attributes['maxlength'])) {
            $this->attributes['maxlength']=$this->size;
        }
        foreach($arg as $an=>$av) {
            if(substr($an, 0, 5)=='data-') $a[$an]=$av;
        }
        if($this->placeholder) {
            $a['placeholder'] = $this->placeholder;
        }
        $bv = array('required', 'readonly', 'disabled');
        foreach ($bv as $attr) {
            $value = $this->$attr;
            if ($value) {
                $a[$attr] = true;
            }
        }
        $a += $this->attributes;
        $input = '<input ';
        foreach ($a as $attr=>$value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input .= $attr . '="' . tdz::xmlEscape($value) . '" ';
        }
        $dl = '';
        if($enableChoices && !is_null($this->choices)) {
            foreach ($this->getChoices() as $k=>$v) {
                $label = false;
                if(is_object($v) && $v instanceof Tecnodesign_Model) {
                    $label = (string)$v;
                } else if (is_array($v) || is_object($v)) {
                    $firstv=false;
                    foreach ($v as $vk=>$vv) {
                        if(!$firstv) {
                            $firstv = $vv;
                        }
                        if($vk=='label') {
                            $label = $vv;
                        }
                    }
                    if(!$label && $firstv) {
                        $label = $firstv;
                    }
                    unset($firstv);
                } else {
                    $label = $v;
                }
                $dl .= '<option value="'.tdz::xml($label).'" />';
                unset($label, $k, $v);
            }
            if ($dl) {
                $input .= 'list="l__'.$arg['id'].'" ';
                $dl = '<datalist id="l__'.$arg['id'].'">'.$dl.'</datalist>';
            }
        }
        $input .= '/>'.$dl;
        return $input;
    }

    public function renderHtml(&$arg)
    {
        $this->attributes['data-format']='html';
        return $this->renderTextarea($arg);
    }

    public function renderTextarea(&$arg)
    {
        $a = array('id'=>$arg['id'], 'name'=>$arg['name']);
        if($this->placeholder) {
            $a['placeholder'] = $this->placeholder;
        }
        if($this->size && !isset($this->attributes['maxlength'])) {
            $this->attributes['maxlength']=$this->size;
        }
        $bv = array('required', 'readonly', 'disabled');
        foreach ($bv as $attr) {
            $value = $this->$attr;
            if ($value) {
                $a[$attr] = true;
            }
        }
        $a += $this->attributes;
        $input = '<textarea ';
        foreach ($a as $attr=>$value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input .= $attr . '="' . tdz::xmlEscape($value) . '" ';
        }
        $input .= '>'.tdz::xmlEscape($arg['value']).'</textarea>';
        
        return $input;
    }    
    public function renderNone()
    {
    }

    public function renderHiddenText(&$arg)
    {
        if (!isset($arg['template'])) {
            $arg['template'] = dirname(__FILE__).'/Resources/templates/field.php';
        }
        $input = $this->renderHidden($arg);
        $input .= $this->placeholder;
        return $input;
    }

    public function renderHidden(&$arg)
    {
        $a = array('type'=>'hidden', 'id'=>$arg['id'], 'name'=>$arg['name'], 'value'=>$arg['value']);
        $bv = array('required', 'readonly', 'disabled');
        foreach ($bv as $attr) {
            $value = $this->$attr;
            if ($value) {
                $a[$attr] = true;
            }
        }
        $a += $this->attributes;
        $input = '<input ';
        foreach ($a as $attr=>$value) {
            if ($attr!='value' && is_bool($value)) {
                $value = var_export($value, true);
            } else if(is_array($value) && isset($value['value'])) {
                $value = $value['value'];
            }
            $input .= $attr . '="' . tdz::xmlEscape($value) . '" ';
        }
        $input .= '/>';
        if (!isset($arg['template'])) {
            $arg['template'] = dirname(__FILE__).'/Resources/templates/hidden.php';
        }
        return $input;
    }

    public function renderRadio(&$arg)
    {
        $this->multiple=false;
        return $this->renderCheckbox($arg, 'radio');
    }

    public function renderBool(&$arg)
    {
        return $this->renderCheckbox($arg, 'checkbox');
    }

    public function renderCheckbox(&$arg, $type='checkbox')
    {
        //$a = array('id'=>$arg['id']);
        $a = array('type'=>$type, 'name'=>$arg['name']);
        $bv = array('required', 'readonly', 'disabled');
        foreach ($bv as $attr) {
            $value = $this->$attr;
            if ($value) {
                $a[$attr] = true;
            }
        }
        $a += $this->attributes;
        $input = '<input';
        foreach ($a as $attr=>$value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input .= ' ' . $attr . '="' . tdz::xmlEscape($value) . '"';
        }
        //$input .= '>';
        if (!$this->choices) {
            // render as a bool
            if($this->placeholder) {
                $this->choices=array(1=>$this->placeholder);
            } else {
                $this->choices = array(1=>'');
            }
        }
        $options = array();
        /*
         * get something to reset buttons
        if (!$this->required || !$this->value) {
            $blank = ($this->placeholder)?($this->placeholder):(self::$labels['blank']);
            $options[] = '<option class="placeholder" value="">'.$blank.'</option>';
        }
         */
        /**
         *  Modificação executada dia 30/10 pois o getOriginal sempre
         *  mantinha o valor original do model ao invés de setar o novo 
         *  valor.
         * if($this->bind) {
            $ovalue = $this->getModel()->getOriginal($this->bind);
            if(is_null($ovalue) && $this->default) $ovalue = $this->default;
        } else {
            $ovalue = $this->value;
        }*/        
        $ovalue = $arg['value'];

        if(!is_array($ovalue)) {
            $ovalue = ($this->serialize)?(tdz::unserialize($ovalue, $this->serialize)):(preg_split('/\s*\,\s*/', $ovalue));
            if(!is_array($ovalue)) $ovalue = array($ovalue);
        } else {
            $opk = null;
            foreach($ovalue as $i=>$o) {
                if(is_object($o) && ($o instanceof Tecnodesign_Model)) {
                    $v = $o->getPk(true);
                    $ovalue[$i] = array_pop($v);
                    unset($i, $o, $v);
                } else break;
            }
        }
        $opt = $this->getChoices();
        if(count($opt)==1 && !implode('', $opt)) $opt=array();
        $i=0;
        foreach ($opt as $k=>$v) {
            $value = $k;
            $label = false;
            $group = false;
            $attrs = '';
            $sc = '';
            if(is_object($v) && $v instanceof Tecnodesign_Model) {
                $value = $v->pk;
                $label = (string)$v;
                $group = (isset($v->_group))?($v->_group):($v->group);
            } else if (is_array($v) || is_object($v)) {
                $firstv=false;
                foreach ($v as $vk=>$vv) {
                    if(!$firstv) {
                        $firstv = $vv;
                    }
                    if($vk=='value') {
                        $value = $vv;
                    } else if($vk=='label') {
                        $label = $vv;
                    } else if($vk=='group') {
                        $group = $vv;
                    } else if($vk=='class') {
                        $sc = ' '.$vv;
                    } else if($vk=='disabled' || $vk=='readonly') {
                        if($vv) $attrs.=' disabled="disabled"';
                    } else if(!is_int($vk)) {
                        $attrs.=' data-'.$vk.'="'.tdz::xmlEscape($vv).'"';
                    }
                }
                if(!$label && $firstv) {
                    $label = $firstv;
                }
            } else {
                $label = $v;
            }
            if(in_array($value, $ovalue)){
                $attrs .= ' checked="checked"';
                $sc = ' on'; 
            }
            if ($label) {
                if(!$this->html_labels) {
                    $label = tdz::xmlEscape($label);
                }
                $id = $arg['id'].'-'.($i++);
                $dl = "<label for=\"{$id}\"><span class=\"{$type}{$sc}\">{$input} id=\"{$id}\" value=\"" . tdz::xmlEscape($value) . '"' . $attrs
                    . ' /></span>' . $label . '</label>';
            } else {
                $id = $arg['id'];
                $dl = "<span class=\"{$type}{$sc}\">{$input} id=\"{$id}\" value=\"" . tdz::xmlEscape($value) . '"' . $attrs . ' /></span>';
            }
            if ($group) {
                $options[$group][]=$dl;
            } else {
                $options[] = $dl;
            }
            unset($opt[$k], $v);
        }
        if(!isset($k)) {
            $attrs = '';
            $sc = '';
            $value = ($arg['value'])?($arg['value']):('1');
            if($this->value) {
                $attrs .= ' checked="checked"';
                $sc = ' on'; 
            }
            $id = $arg['id'];
            $options[] = "<span class=\"{$type}{$sc}\">{$input} id=\"{$id}\" value=\"" . tdz::xmlEscape($value) . '"' . $attrs . ' /></span>';
        }
        $dl = '';
        foreach ($options as $k=>$v) {
            if (is_array($v)) {
                $dl .= '<div class="'.tdz::slug($k).'" label="'.tdz::xml($k).'"><span class="label">'.tdz::xml($k).'</span>'.implode('', $v).'</div>';
            } else {
                $dl .= $v;
            }
        }
        return $dl;
    }

    private function ajaxChoices($s)
    {
        // search results
        $filtered = false;
        $w = null;
        if(is_string($this->choices) && class_exists($this->choices)) {
            $this->_choicesCollection = null;
            $cn = $this->choices;
            $scope = $cn::columns((!$this->scope)?('choices'):($this->scope), (is_numeric($s))?(null):(array('string')));
            $filtered = true;
            $w=array();
            foreach($scope as $fn) {
                if(is_array($fn) && isset($fn['bind'])) $fn=$fn['bind'];
                if(strpos($fn, ' ')) $fn = preg_replace('/\s+(as\s+)?[a-z0-9\_]+$/i', '', $fn);
                if(preg_match('/\[([^\]]+)\]/', $fn, $m)) $fn = $m[1];
                $w["|{$fn}%="]=$s;
            }
        } else {
            if(!is_null($this->_choicesCollection)) {
                $cn = $this->_choicesCollection->getClassName();
            } else if($this->choices instanceof Tecnodesign_Collection) {
                $this->_choicesCollection = $this->choices;
                $cn = $this->choices = $this->_choicesCollection->getClassName();
            } else {
                return array();
            }
            $scope = $cn::columns((!$this->scope)?('choices'):($this->scope), (is_numeric($s))?(null):(array('string')));
            $filtered = true;
            $sql = $this->_choicesCollection->getQuery();
            if($s) {
                if(!mb_detect_encoding($s, 'UTF-8', true)) $s = tdz::encodeUTF8($s);
                $w=array();
                foreach($scope as $fn) {
                    $w["|{$fn}*="]="t.{$fn} like '%".tdz::sqlEscape($s, false)."%'";
                }
                $osql = '';
                if(preg_match('/ order by(.+)$/', $sql, $m)) {
                    $osql = ' order by'.preg_replace('/ t[0-9]*\./', ' t.', $m[1]);
                    $sql = substr($sql, 0, strlen($sql) - strlen($m[0]));
                }
                $sql = 'select t.* from ('.$sql.') as t where '.implode(' or ', $w).$osql;
            }
            $this->choices = $this->_choicesCollection = new Tecnodesign_Collection(null, $cn, $sql, $this->_choicesCollection->getQueryKey());
            $w = null;
        }
        $o = $this->getChoices($w);
        if(!$filtered) {
            $term = tdz::slug($s);
            foreach($o as $k=>$v) {
                $value = $v;
                if(is_array($v)) {
                    $value = $v['label'];
                }
                $slug = tdz::slug((string)$value);
                if(strpos($slug, $term)===false) {
                    unset($o[$k]);
                }
                if(!is_string($v)) {
                    $o[$k]=(string)$value;
                }
            }
            $ro=$o;
            unset($o);
        } else {
            if($o instanceof Tecnodesign_Collection && $o->getQuery()) {
                $o = $o->getItems();
            }
            $ro=array();
            foreach($o as $k=>$v) {
                if(is_object($v) && $v instanceof Tecnodesign_Model) {
                    if(isset($v::$schema['scope']['choices']['value']) && isset($v::$schema['scope']['choices']['label'])) {
                        $ro[]=$v->asArray('choices');
                    } else {
                        $value=$v->pk;
                        $group = $v->group;
                        $label = $v->label;
                        if(!$label) {
                            $label = (string) $v;
                        }
                        if($group) {
                            $ro[]=array('value'=>$value, 'label'=>$label, 'group'=>$group);
                        } else {
                            $ro[]=array('value'=>$value, 'label'=>$label);
                        }
                    }
                } else if(!is_string($v)) {
                    if(is_array($v)) {
                        $v = $v['label'];
                    }
                    $ro[]=array('value'=>$v, 'label'=>$k);
                } else {
                    break;
                }
                unset($k, $v);
            }
            unset($o);
        }
        return $ro;
    }

    public function renderSelect(&$arg)
    {
        $a = array('id'=>$arg['id'], 'name'=>$arg['name']);
        $bv = array('required', 'readonly', 'disabled', 'multiple');
        foreach ($bv as $attr) {
            $value = $this->$attr;
            if ($value) {
                $a[$attr] = true;
            }
        }
        if($this->multiple) {
            $this->attributes['class']=(isset($this->attributes['class']))?($this->attributes['class'].' multiple'):('multiple');
        }
        $a += $this->attributes;
        $choices = $this->choices;
        if(Tecnodesign_App::request('headers', 'z-action')=='choices') {
            $m=false;
            $tg = urldecode(Tecnodesign_App::request('headers', 'z-target'));
            if(strpos($arg['id'], '§')!==false) {
                $p = '/^'.str_replace('§', '[0-9]+', $arg['id']).'$/';
                $m = preg_match($p, $tg);
                unset($p);
            } else if($tg==$arg['id']) {
                $m = true; 
            }
            if($m) {
                unset($m, $tg);
                tdz::cacheControl('no-cache',0);
                tdz::output($this->ajaxChoices(urldecode(Tecnodesign_App::request('headers', 'z-term'))), 'json');
            }
            unset($m, $tg);
        }
        if(isset($this->attributes['data-datalist-api']) || $this->countChoices() > self::$maxOptions) {
            if(isset($this->attributes['data-datalist-api']) && $this->attributes['data-datalist-api']) {
                if(substr($this->attributes['data-datalist-api'],0,1)!='/' && substr($this->attributes['data-datalist-api'],0,4)!='http') {
                    $this->attributes['data-datalist-api'] = tdz::scriptName(true).'/'.$this->attributes['data-datalist-api'];
                }
                if($this->prefix) {
                    $p = static::id($this->prefix).'_';
                    $this->attributes['data-datalist-api'] = str_replace('$', '$'.$p, $this->attributes['data-datalist-api']);
                    $this->attributes['data-prefix'] = $p;
                    unset($p);
                }

            }
            if(is_string($choices)) {
                $this->choices = $choices;
                $this->_choicesCollection=null;
            }

            
            $ia = $arg;
            $ha=$arg;
            $ia['type']='search';
            $ia['id']='q__'.$ia['id'];
            $ia['name']='q__'.$ia['name'];
            $oa = $this->attributes;
            foreach($oa as $k=>$v) {
                if(substr($k, 0, 13)=='data-datalist') unset($oa[$k]);
                unset($k, $v);
            }
            //if(isset($this->attributes['data-callback'])) unset($this->attributes['data-callback']);
            if(!isset($this->attributes['data-datalist'])) $this->attributes['data-datalist']='self';
            $input = '';
            if($arg['value']) {
                if($this->multiple) {
                    $values = $arg['value'];
                    $ha['value']=array();
                    if(!is_array($arg['value']) && !is_object($arg['value'])) {
                        $arg['value']=preg_split('/\s*\,\s*/', $arg['value'], null, PREG_SPLIT_NO_EMPTY);
                    }
                    foreach($arg['value'] as $v) {
                        if(is_object($v) && $v instanceof Tecnodesign_Model) {
                            $value=$v->pk;
                            $group = $v->group;
                            $label = $v->label;
                            if(!$label) {
                                $label = (string) $v;
                            }
                            $label = ($group)?('<strong>'.tdz::xmlEscape($group).'</strong> '.tdz::xmlEscape($label)):(tdz::xmlEscape($label));
                        } else {
                            $value = $v;
                            $label = tdz::xmlEscape($this->getChoices($v));
                        }
                        $ha['value'][]=$value;
                        $input .= "<span class=\"ui-button selected-option\" data-value=\"{$value}\">{$label}</span>";
                    }
                    $ha['value']=implode(',', $ha['value']);
                    $ia['value']='';
                } else {
                    $ia['value']=$this->getChoices($arg['value']);
                    if($ia['value']) {
                        $ha['value']=$arg['value'];
                    }
                }
            }
            $input .= $this->renderText($ia, false);
            $this->attributes = $oa;
            $input .= $this->renderHidden($ha);
            return $input;
        }

        $input = '<select';
        foreach ($a as $attr=>$value) {
            if (is_bool($value)) {
                $value = var_export($value, true);
            }
            $input .= ' ' . $attr . '="' . tdz::xmlEscape($value) . '"';
        }
        $input .= '>';
        $options = array();
        if (!$this->multiple && (!$this->required || !$this->value)) {
            $blank = ($this->placeholder)?($this->placeholder):(self::$labels['blank']);
            $options[] = '<option class="placeholder" value="">'.$blank.'</option>';
        }
        $values = (!is_array($this->value))?(preg_split('/\s*\,\s*/', $this->value, null, PREG_SPLIT_NO_EMPTY)):($this->value);

        if($values) {
            $ref = null;
            if($this->bind && ($sc=$this->getSchema()) && isset($sc['relations'][$this->bind])) {
                $rel = $sc['relations'][$this->bind];
                $cn = (isset($rel['className']))?($rel['className']):($this->bind);
                $rpk = $cn::pk($cn::$schema, true);
                $ref = array_pop($rpk);
            }
            foreach($values as $k=>$v) {
                if(is_object($v) && $v instanceof Tecnodesign_Model) {
                    $values[$k] = ($ref && isset($v->$ref))?($v->$ref):($v->pk);
                } else {
                    break;
                }
            }
        }

        $dprop = null;
        if($this->dataprop) {
            $dprop = (!is_array($this->dataprop))?(explode(',', $this->dataprop)):($this->dataprop);
        }

        foreach ($this->getChoices() as $k=>$v) {
            $value = $k;
            $label = false;
            $group = false;
            $attrs = '';
            if(is_object($v) && $v instanceof Tecnodesign_Model && isset($v::$schema['scope']['choices']['value']) && isset($v::$schema['scope']['choices']['label'])) {
                $v = $v->asArray('choices');
            }
            if (is_array($v)) {
                $firstv=false;
                foreach ($v as $vk=>$vv) {
                    if(!$firstv) {
                        $firstv = $vv;
                    }
                    if($vk=='value') {
                        $value = $vv;
                    } else if($vk=='label') {
                        $label = $vv;
                    } else if($vk=='group') {
                        $group = $vv;
                    } else if(!is_int($vk)) {
                        $attrs.=' data-'.$vk.'="'.tdz::xmlEscape($vv).'"';
                    }
                }
                if(!$label && $firstv) {
                    $label = $firstv;
                }
                /*
                if($dprop) {
                    foreach($dprop as $dn) if(isset($v[$dn])) $attrs .= ' data-'.$dn.'="'.\tdz::xmlEscape($v->{$dn}).'"';
                }
                */
            } else if(is_object($v) && $v instanceof Tecnodesign_Model) {
                $label = $v->label;
                if(!$label) {
                    $label = (string)$v;
                }
                $group = (isset($v->_group))?($v->_group):($v->group);
                if($dprop) {
                    $value = $v->getPk(true);
                    foreach($dprop as $dn) {
                        if(isset($value[$dn])) unset($value[$dn]);
                        $attrs .= ' data-'.$dn.'="'.\tdz::xmlEscape($v->{$dn}).'"';
                    }
                    $value = implode(',', $value);
                } else {
                    $value = $v->pk;
                }
            } else {
                $label = (string) $v;
            }
            if(in_array($value, $values)){
                $attrs .= ' selected="selected"';
            }
            $dl = '<option value="' . tdz::xmlEscape($value) . '"' . $attrs
                . '>' . tdz::xmlEscape(strip_tags($label)) . '</option>';
            if ($group) {
                $options[$group][]=$dl;
            } else {
                $options[] = $dl;
            }            
        }
        $dl = '';
        foreach ($options as $k=>$v) {
            if (is_array($v)) {
                $dl .= '<optgroup label="'.tdz::xmlEscape($k).'">'.implode('', $v).'</optgroup>';
            } else {
                $dl .= $v;
            }
        }
        $input .= $dl . '</select>';
        return $input;
    }
    


    /**
     * CSRF implementation (beta)
     */
    public function renderCsrf(&$arg)
    {
        $ua = (isset($_SERVER['HTTP_USER_AGENT']))?($_SERVER['HTTP_USER_AGENT']):('unknown');
        $arg['value'] = tdz::encrypt(md5($ua).":".TDZ_TIME);
        if($this->placeholder){
            $this->choices=array($arg['value'] => $this->placeholder);
            $arg['value'] = '0';
            $s = $this->renderHidden($arg);
            unset($arg['template']);
            $arg['type'] = 'checkbox';
            return $s.$this->renderCheckbox($arg, 'checkbox');
        } else {
            $arg['type']='hidden';
            return $this->renderHidden($arg);
        }
    }

    public function checkCsrf($value, $message='')
    {
        if($value && ($d=tdz::decrypt($value))) {
            @list($h, $t) = explode(':', $d, 2);
            $ua = (isset($_SERVER['HTTP_USER_AGENT']))?($_SERVER['HTTP_USER_AGENT']):('unknown');
            if(md5($ua)==$h && $t && $t +3600 > TDZ_TIME) {
                return $value;
            }
        }
        throw new Tecnodesign_Exception(array(tdz::t($message, 'exception'), $this->getLabel(), $value));
    }

    public function setPrefix($s)
    {
        $this->prefix=$s;
    }
    

    /**
     * Magic terminator. Returns the page contents, ready for output.
     * 
     * @return string page output
     */
    function __toString()
    {
        return $this->render();
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
        } else if(property_exists($this,$name)) {
            $this->$name=$value;
        } else if(static::$allowedProperties && (static::$allowedProperties===true || in_array($name, static::$allowedProperties))) {
            $this->$name = $value;
        } else {
            throw new Tecnodesign_Exception(array('Method or property not available: "%s"', $name));
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
        return isset($this->$name);
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
        unset($this->$name);
    }

}