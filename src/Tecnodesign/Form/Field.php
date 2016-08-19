<?php
/**
 * Form Field building, validation and output methods
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
 * @version   SVN: $Id: Field.php 1298 2013-12-12 02:33:05Z capile $
 * @link      http://tecnodz.com/
 */

/**
 * Form Field building, validation and output methods
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
class Tecnodesign_Form_Field implements ArrayAccess
{
    public static $hashMethods=array('datetime'=>'date("Ymd/His_").tdz::slug($name,"._")', 'time'=>'microtime(true)', 'md5'=>'md5_file($dest)', 'sha1'=>'sha1_file($dest)', 'none'=>'$name'),
        $dateInputType='date', $datetimeInputType='datetime-local', $emailInputType='email', $numberInputType='number', $rangeInputType='range', $urlInputType='url', $searchInputType='search';
    
    /**
     * Common attributes to each form field. If there's a set$Varname, then it'll 
     * be used to check the validity of the added information.
     */
    protected $prefix=false, $id=false, $type='text', $form, $bind, $attributes=array(), $placeholder=false, $scope=false,
        $label=false, $choices=false, $choicesFilter, $tooltip=false, $renderer=false, $error=false, $filters=false, $class='',
        $template=false, $rules=false, $_className, $multiple=false, $required=false, $html_labels=false, $messages=null,
        $disabled=false, $readonly=false, $size=false, $min_size=false, $value, $range=false, $decimal=0, $accept=false, $toAdd=null,
        $insert=true, $update=true, $before=false, $fieldset=false, $after=false, $next, $default;
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
    public function setBind($name, $return=false)
    {
        $M = $this->getModel();
        if(!$M) return false;
        if(strpos($name, ' ')!==false) $name = substr($name, strrpos($name, ' ')+1);
        $schema = $this->getSchema();
        if($schema && (isset($schema['columns'][$name]) || isset($schema['relations'][$name]))) {
            $this->bind = $name;
            if (isset($schema['relations'][$name]) && $schema['relations'][$name]['type']=='one') {
                $this->bind = $schema['relations'][$name]['local'];
            }
            if($return) {
                $return = array();
                $return['required']=(isset($fd['null']) && !$fd['null']);
                if(isset($schema['columns'][$name])) {
                    $return = static::properties($schema['columns'][$name], $M->isNew());
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
        } else if(substr($name, 0, 1)=='_' || property_exists($M, $name) || (($cm=tdz::camelize($name, true)) && method_exists($M, 'get'.$cm) && method_exists($M, 'set'.$cm))) {
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
        return str_replace('-', '_', tdz::textToSlug($name, '§,'));
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
            $id = tdz::textToSlug($this->id);
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
                $this->value = $this->getModel()->{$this->bind};
                if($this->value instanceof Tecnodesign_Collection) {
                    $this->value = ($this->value->count()>0)?($this->value->getItems()):(array());
                }
            } catch(Exception $e) {
                tdz::log(__METHOD__.': '.$e->getMessage());
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
                } else {
                    @eval("\$value = {$m};");
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
        $M = $this->getModel();
        $fn = ($cn!=$this->name)?($this->name):($cn);
        $m = 'validate'.ucfirst(tdz::camelize($fn));
        if(method_exists($M, $m)) {
            $newvalue = $M->$m($value);
            if(!is_bool($newvalue)) $value = $newvalue;
            unset($newvalue);
        }
        if($value!==$this->value) {
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
        if(!$value && !$this->required){
            $value=null;
            return $value;
        }
        if($this->multiple && strpos($value, ',')!==false) {
            $join=false;
            if(!is_array($value)) {
                $value = explode(',', $value);
                $join = true;
                $count=0;
                foreach($value as $k=>$v) {
                    if($v) {
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
        $R = $this->getValue();
        $M = $this->getModel();
        $schema = $M::schema();
        if($this->choices && is_string($this->choices) && isset($schema['relations'][$this->choices]) && $schema['relations'][$this->choices]['local']==$this->bind) {
            $this->bind = $this->choices;
            $this->choices=null;
        }
        $rel = $schema['relations'][$this->bind];
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
        $sid = $scope = (!$this->scope)?('subform'):($this->scope);
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
        foreach($scope as $fn) {
            if(!isset($add[$fn]) && isset($cn::$schema['columns'][$fn]) && !isset($cn::$schema['columns'][$fn]['primary'])) {
                $bnull[$fn]='';
            }
            unset($fn);
        }
        foreach($value as $i=>$v) {
            $v += $bnull;
            if(isset($R[$i])) {
                $O = $R[$i];
                $v += $O->asArray();
                unset($R[$i]);
            } else {
                $v += $add;
                $O = new $cn(null, true, false);
            }
            try {
                $F = $O->getForm($sid, true);
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
            $size = (is_array($value))?(count($value)):(strlen($value));
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

    public function checkFile($value=false, $message='')
    {
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
            if($this->accept) {
                $max = (isset($this->accept['max']))?($this->accept['max']):($max);
                $size = (isset($this->accept['size']))?($this->accept['size']):($size);
                $type = (isset($this->accept['type']))?($this->accept['type']):($type);
                $hash = (isset($this->accept['hash']))?($this->accept['hash']):($hash);
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
                $this->accept['type']=$type;
            }
            try {
                if($max && count($value)>$max) {
                    throw new Tecnodesign_Exception(array(tdz::t('You are only allowed to upload up to %s files.', 'exception'), $max));
                }
                $new = array();
                foreach($value as $i=>$upload) {
                    if(isset($upload[0]) && count($upload)==1) {
                        $upload = $upload[0];
                    }
                    /**
                     * Result should be [disk-name]|[user-name]
                     * Multiple files are listed one per line
                     */
                    if(!isset($upload['error']) || $upload['error']==4) {
                        // no upload made, skipping
                        $value[$i] = false;
                        continue;
                    }
                    $name = preg_replace('#[\?\#\$\%,\|/\\\\]+#', '', preg_replace('#[\s]+#', ' ', tdz::encodeUTF8($upload['name'])));
                    if($upload['error']>0) {
                        throw new Tecnodesign_Exception(tdz::t('Could not read uploaded file.', 'exception'));
                    } else if($size && $upload['size']>$size) {
                        throw new Tecnodesign_Exception(array(tdz::t('Uploaded file exceeds the limit of %s.', 'exception'), tdz::formatBytes($size)));
                    } else if ($type && !in_array($upload['type'], $type)) {
                        throw new Tecnodesign_Exception(tdz::t('This file format is not supported.', 'exception'));
                    }
                    $file = eval("return {$hfn};");
                    $dest = $uploadDir.'/'.$file;
                    $dir = dirname($dest);
                    if(!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    if(!is_uploaded_file($upload['tmp_name']) || !copy($upload['tmp_name'], $dest)) {
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



    public function checkEmail($value, $message='')
    {
        $value = trim($value);
        if($value && !tdz::checkEmail($value)) {
            $message = tdz::t('This is not a valid e-mail address.', 'exception');
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
        if(is_array($value) && $this->multiple && $this->type!='form') {
            $value = implode(',', $value);
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
                    if(tdz::$perfmon) tdz::log(__METHOD__.'-->'.$choices.'  '.tdz::formatNumber(microtime(true)-$t, 5));
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
                    $cr=array();
                    if($this->_choicesCollection->count()) {
                        foreach($this->_choicesCollection->getItems() as $r) {
                            $cr[$r->getPk()]=$r;
                            unset($r);
                        }
                    }
                    $this->choices = $cr;
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
        $input = '';
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
                $arg['value']->setQuery(false);
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
        }
        
        if (!isset($arg['template'])) {
            $arg['template'] = dirname(__FILE__).'/Resources/templates/subform.php';
        }
        $class = '';
        if($jsinput) {
            $jsinput = ' data-template="'.tdz::xmlEscape($jsinput).'" data-prefix="'.$prefix.'"';
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
        if(strpos($arg['class'], 'app-file-preview')!==false) {
            if($arg['value']) {
                $s .= '<span class="text">'.$this->filePreview($arg['id']).'</span>';
            }
        }
        if(strpos($arg['class'], 'app-image-preview')!==false) {
            if($arg['value']) {
                $s .= '<span class="text">'.$this->filePreview($arg['id'], true).'</span>';
            }
        }
        $h = $this->renderHidden($arg);
        unset($arg['template']);
        $a = $arg;
        $a['value']='';
        $s .= $h.$this->renderText($a);
        return $s;
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
                        $s .= '<a href="'.tdz::xmlEscape($link).'"><img src="'.tdz::xmlEscape($link).'" title="'.tdz::xmlEscape($fname).'" alt="'.tdz::xmlEscape($fname).'" /></a>';
                    } else {
                        $s .= '<a href="'.tdz::xmlEscape($link).'">'.tdz::xmlEscape($fname).'</a>';
                    }
                } elseif (file_exists($uploadDir.'/'.$fname)) { //Compatibilidade com dados de framework anteriores
                    $hash = $prefix.md5($fname);
                    $link = $url.$hash.'='.urlencode($fname);
                    if(isset($_GET[$hash]) && $_GET[$hash]==$fname) {
                        tdz::download($uploadDir.'/'.$fname);
                    }
                    if ($img) {
                        $s .= '<a href="'.tdz::xmlEscape($link).'"><img src="'.tdz::xmlEscape($link).'" title="'.tdz::xmlEscape($fname).'" alt="'.tdz::xmlEscape($fname).'" /></a>';
                    } else {
                        $s .= '<a href="'.tdz::xmlEscape($link).'">'.tdz::xmlEscape($fname).'</a>';
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
            $arg['value'] = date(tdz::$dateFormat, $t);
        }
        if(Tecnodesign_Form::$enableStyles) {
            tdz::$variables['style'][Tecnodesign_Form::$enableStyles]=tdz::$assetsUrl.'/tecnodesign/css/datepicker.less';
        }
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
        $df = tdz::$dateFormat;
        
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
            $arg['value'] = date('Y-m-d\TH:i:s', $t);
        }
        if(Tecnodesign_Form::$enableStyles) {
            tdz::$variables['style'][Tecnodesign_Form::$enableStyles]=tdz::$assetsUrl.'/tecnodesign/css/datepicker.less';
        }
        return $this->renderText($arg);
    }
    
    public function renderColor(&$arg)
    {
        $arg['type']='color';
        
        return $this->renderText($arg);
    }

    public function renderString(&$arg, $enableChoices=true)
    {
        return $this->renderText($arg, $enableChoices);
    }
    
    public function renderText(&$arg, $enableChoices=true)
    {
        $a = array('type'=>(isset($arg['type']))?($arg['type']):('text'), 'id'=>$arg['id'], 'name'=>$arg['name'], 'value'=>(string)$arg['value']);
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
                $dl .= '<option value="'.tdz::xmlEscape($v).'" />';
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
        $i=0;
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
            $ovalue = preg_split('/\s*\,\s*/', $ovalue);
        }
        $opt = $this->getChoices();
        if(count($opt)==1 && !implode('', $opt)) $opt=array();
        foreach ($opt as $k=>$v) {
            $value = $k;
            $label = false;
            $group = false;
            $attrs = '';
            $sc = '';
            if(is_object($v) && $v instanceof Tecnodesign_Model) {
                $value = $v->pk;
                $label = (string)$v;
                $group = $v->group;
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
                $dl .= '<div class="'.tdz::textToSlug($k).'" label="'.tdz::xmlEscape($k).'"><span class="label">'.tdz::xmlEscape($k).'</span>'.implode('', $v).'</div>';
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
            $term = tdz::textToSlug($s);
            foreach($o as $k=>$v) {
                $value = $v;
                if(is_array($v)) {
                    $value = $v['label'];
                }
                $slug = tdz::textToSlug((string)$value);
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
                    $value=$v->pk;
                    $group = $v->group;
                    $label = $v->label;
                    if(!$label) {
                        $label = (string) $v;
                    }
                    if($group) {
                        $ro[$value]=array('value'=>$value, 'label'=>$label, 'group'=>$group);
                    } else {
                        $ro[$value]=$label;
                    }
                } else if(!is_string($v)) {
                    if(is_array($v)) {
                        $v = $v['label'];
                    }
                    $ro[$k]=$v;
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
        if(isset($_SERVER['HTTP_TDZ_ACTION']) && $_SERVER['HTTP_TDZ_ACTION']=='choices') {
            $m=false;
            if(strpos($arg['id'], '§')!==false) {
                $p = '/^'.str_replace('§', '[0-9]+', $arg['id']).'$/';
                $m = preg_match($p, $_SERVER['HTTP_TDZ_TARGET']);
                unset($p);
            } else {
                $m = $_SERVER['HTTP_TDZ_TARGET']==$arg['id']; 
            }
            if($m) {
                unset($m);
                tdz::cacheControl('no-cache',0);
                tdz::output($this->ajaxChoices((isset($_SERVER['HTTP_TDZ_TERM']))?($_SERVER['HTTP_TDZ_TERM']):('')), 'json');
            }
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
        $values = preg_split('/\s*\,\s*/', $this->value, null, PREG_SPLIT_NO_EMPTY);
        foreach ($this->getChoices() as $k=>$v) {
            $value = $k;
            $label = false;
            $group = false;
            $attrs = '';
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
            } else if(is_object($v) && $v instanceof Tecnodesign_Model) {
                $value = $v->pk;
                $label = $v->label;
                if(!$label) {
                    $label = (string)$v;
                }
                $group = $v->group;
            } else {
                $label = (string) $v;
            }
            if(in_array($value, $values)){
                $attrs .= ' selected="selected"';
            }
            $dl = '<option value="' . tdz::xmlEscape($value) . '"' . $attrs
                . '>' . tdz::xmlEscape($label) . '</option>';
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