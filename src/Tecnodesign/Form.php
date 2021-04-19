<?php
/**
 * Form building, validation and output methods
 * 
 * This package implements applications to build HTML forms
 * 
 * PHP version 7.2+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.4
 */

class Tecnodesign_Form implements ArrayAccess
{
    /**
     * @var Tecnodesign_Form_Field[]
     */
    protected $fields;

    protected $id, $method = 'post', $action = '', $model, $err, $prefix = '', $limits, $parent;
    public $buttons = array('submit' => '*Send'), $attributes = array('class' => 'z-form'), $before, $after;
    private static $_instances;
    public static $assets='Z.Form',
        $enableLimits,
        $defaultLimits=[
            'requests'=>10,         // how many requests will be enabled per time set below
            'time'=>60,             // time to measure requests rate limiting
            'key'=>'session+ip',    // how to key the rate limiting, options are 'session', 'ip', 'user', 'url' or a combination of these (use + to rate limit both individually, | for combined)
            'fieldname'=>'__req',   // field name to include for rate limiting
            'fieldset'=>null,       // should rate-limiting be rendered within fieldsets? enter false to remove or the fieldset name to isolate in a proper fieldset
            'attributes'=>null,     // attributes the fieldset field should use
            'timeout'=>3600,        // how long to keep keys for rate limiting, also expires form after this number of seconds
            'warn-level'=>0.5,      // after this threshold, warn user about rate limiting
            'captcha-level'=>0.75,  // after this threshold, display a captcha in addition to the rate limiting
            'error-status'=>null,   // http status to send when limit is reached, usually 429
            'accept-failed'=>null,  // whether the form data should be accepted if it fails 
            'skip-actions'=>[],     // actions to skip in limit checking
            'recaptcha'=>null,
        ];

    private $_uid;

    /**
     * @deprecated use Tecnodesign_App::$assets instead
     */
    public static $enableStyles = false;

    public function __construct($formConfig=[])
    {
        if (isset($formConfig['id'])) {
            $id = $formConfig['id'];
            $this->prefix = $id;
            $this->id = $id;
        } else {
            $id = uniqid();
        }
        $this->register($id);

        if(isset($formConfig['limits'])) {
            $this->setLimits($formConfig['limits']);
        } else if(static::$enableLimits) {
            $this->setLimits(static::$enableLimits);
        }

        if (isset($formConfig['buttons'])) {
            if (!$formConfig['buttons']) {
                $this->buttons = array();
            } elseif (!is_array($formConfig['buttons'])) {
                $this->buttons['submit'] = $formConfig['buttons'];
            } else {
                $this->buttons = $formConfig['buttons'] + $this->buttons;
            }
        }

        if (!isset($formConfig['action'])) {
            $this->action = tdz::getRequestUri();
        } else {
            $this->action = $formConfig['action'];
        }

        if (isset($formConfig['method'])) {
            $this->method = $formConfig['method'];
        }

        if (isset($formConfig['model'])) {
            $this->setModel($formConfig['model']);
        }

        $this->fields = new arrayObject();
        if (isset($formConfig['fields'])) {
            $last = null;
            foreach ($formConfig['fields'] as $fieldName => $fieldDefinition) {
                if (is_string($fieldDefinition)) {
                    if (!isset($before)) {
                        $before = '';
                    }
                    $before .= $fieldDefinition;
                    continue;
                }

                // Checks if the user has access to the field
                if (isset($fieldDefinition['credential'])) {
                    if (!$this->checkCredential($fieldDefinition['credential'])) {
                        continue;
                    }
                    unset($fieldDefinition['credential']);
                }

                $fieldDefinition['prefix'] = $this->prefix;

                // PHP doesn't parse post values with .
                $fieldName = str_replace('.', '_', $fieldName);

                $fieldDefinition['id'] = $fieldName;
                $last = $fieldName;
                if (isset($before)) {
                    if (!isset($fieldDefinition['before'])) {
                        $fieldDefinition['before'] = '';
                    }

                    $fieldDefinition['before'] = $before . $fieldDefinition['before'];
                    unset($before);
                }
                $this->fields[$fieldName] = new Tecnodesign_Form_Field($fieldDefinition, $this);
                unset($formConfig['fields'][$fieldName], $fieldDefinition);
            } // endforeach ($def['fields'] as $fieldName => $fieldDefinition)

            if ($last && isset($before)) {
                $this->fields[$last]->after .= $before;
                unset($before);
            }
            unset($formConfig['fields']);
        }

        if (isset($formConfig['attributes']) && is_array($formConfig['attributes'])) {
            $formConfig += $formConfig['attributes'];
        }

        foreach ($formConfig as $an => $av) {
            if (!isset($this->$an)) {
                $this->attributes[$an] = $av;
            }
        }

        Tecnodesign_App::$assets[] = static::$assets;
    }

    public static function userToken($key='form-token')
    {
        $U = tdz::getUser();
        $uid = $U->getAttribute($key);
        if(!$uid) {
            $uid = tdz::salt();
            $U->setAttribute($key, $uid);
            $U->store();
        }
    }

    /**
     * Rate limiting configuration
     *
     * Acceptable params are:
     * requests (default: 10)          - how many requests will be enabled per time set below
     * time  (default: 60)             - time to measure requests rate limiting
     * key  (default: session+ip)      - how to key the rate limiting, options are 'session', 'ip', 'user', 'url' or a combination of these (use + to rate limit both individually, | for combined)
     * fieldname  (default: __req)     - field name to include for rate limiting
     * attributes (default: null)      - attributes for the should rate-limiting rendering
     * timeout  (default: 3600)        - how long to keep keys for rate limiting, also expires form after this number of seconds
     * warn-level (default: 0.5)       - after this threshold, warn user about rate limiting
     * captcha-level (default: 0.75)   - after this threshold, display a captcha in addition to the rate limiting
     *
     * returns previous rate-limiting configuration
     */
    public function setLimits($limits=null)
    {
        $r = $this->limits;
        if(!$limits && !is_array($limits)) {
            $this->limits = null;
        } else {
            if(!is_array($limits)) $limits = [];
            if(!$this->limits) $this->limits = static::$defaultLimits;
            foreach(static::$defaultLimits as $k=>$v) {
                if(array_key_exists($k, $limits)) $this->limits[$k] = $limits[$k];
                unset($k, $v);
            }
        }
        return $r;
    }

    /**
     * Validates the rate limiting control fields
     */
    public function checkLimits($post=null)
    {
        if(!$this->limits) return true;
        if(!isset($this->limits['keys'])) $this->getLimits();
        if(isset($this->limits['error']) && $this->limits['error']) {
            return false;
        }

        // recaptcha
        if(isset($this->limits['recaptcha']) && $this->limits['recaptcha']) {
            $rc = $this->limits['recaptcha'];
            $rckey = null;
            if(is_array($rc) && isset($rc['secret-key'])) $rckey = $rc['secret-key'];
            else if(isset(tdz::$variables['recaptcha-secret-key'])) $rckey = tdz::$variables['recaptcha-secret-key'];

            if($rckey) {
                $rcfail = null;
                if(!isset($post['g-recaptcha-response'])) $rcfail = true;
                else {
                    $Q = Tecnodesign_Query_Api::runStatic('https://www.google.com/recaptcha/api/siteverify', '', http_build_query(['secret'=>$rckey, 'response'=>$post['g-recaptcha-response']]));

                    if(!$Q || !isset($Q['success']) || !$Q['success']) {
                        $rcfail = true;
                        tdz::log('[INFO] Failed reCAPTCHA validation: '.tdz::serialize($Q, 'json'));
                    }
                }
                if($rcfail) {
                    $this->limits['error'] = tdz::t('You need to confirm you\'re not a robot to continue.', 'exception');
                    return false;

                }
            }
        }

        if($post && isset($post[$this->limits['fieldname']]) && ($pk=$post[$this->limits['fieldname']])) {
            $timeout = $this->limits['timeout'];
            if(!$timeout) $timeout = 3600;
            $time = $this->limits['time'];
            if(!$time) $time = 60;
            if($timeout < $time) $timeout = $time;
            $now = time();
            $add = [];
            $k = md5(implode(':', array_keys($this->limits['keys'])));
            if(($salt=Tecnodesign_Cache::get('f-limit-current/'.$k, $timeout)) && $pk==$salt) {
                Tecnodesign_Cache::delete('f-limit-current/'.$k);
                $salt = tdz::salt();
                Tecnodesign_Cache::set('f-limit-current/'.$k, $salt, $timeout);
                $add[$salt] = [$now,0];
                $fn = $this->limits['fieldname'];
                $this->limits['fields'][$fn]->setValue($salt);
            }
            $valid = true;
            $action = (isset($this->limits['skip-actions'])) ?Tecnodesign_App::request('headers', 'z-action') :null;
            foreach($this->limits['keys'] as $k=>$ks) {
                $ks = Tecnodesign_Cache::get('f-limit/'.$k, $timeout) +$add;
                if(isset($ks[$pk]) && $ks[$pk][1]==0 && $ks[$pk][0] + $time >= $now) {
                    if($action && in_array($action, $this->limits['skip-actions'])) {
                        unset($ks[$pk]);
                    } else {
                        $ks[$pk][1] = 1;
                    }
                    Tecnodesign_Cache::set('f-limit/'.$k, $ks, $timeout);
                } else {
                    $valid = false;
                }
            }
            if($valid) return true;
        }

        $this->limits['error'] = tdz::t('This request is expired or invalid. Please try sending this request again.', 'exception');
        return false;
    }

    /**
     * Renders the rate limiting control fields
     */
    public function getLimits()
    {
        if(!$this->limits) return null;
        // get existing keys and check the keys limits
        $key = $this->limits['key'];
        if(!$key) return;
        $keys = preg_split('/\++/', $key, null, PREG_SPLIT_NO_EMPTY);
        $timeout = $this->limits['timeout'];
        if(!$timeout) $timeout = 3600;
        $time = $this->limits['time'];
        if(!$time) $time = 60;
        if($timeout < $time) $timeout = $time;
        $now = time();
        //$post = Tecnodesign_App::request('post', $this->limits['fieldname']);
        $salt = null;
        $warn = false;
        $captcha = false;

        if(!isset($this->limits['keys'])) {
            $this->limits['keys'] = [];
            $this->limits['error'] = false;
            $this->limits['warn'] = false;
            $this->limits['captcha'] = false;
            $this->limits['reqs'] = 0;
            $this->limits['level'] = 0;
            foreach($keys as $k) {
                $ks=explode('|', $k);
                $k = str_replace('|', '-', $k);
                $session = in_array('session', $ks);
                $user = in_array('user', $ks);

                if($session || $user) {
                    if(!isset($U)) $U = tdz::getUser();
                    if($session) $k .= '-'.$U->getSessionId(true);
                    if($user) $k .= '--'.(string)$U->uid();
                }
                if(in_array('ip', $ks)) $k .= '_'.(string)Tecnodesign_App::request('ip');
                if(in_array('url', $ks)) $k .= '__'.tdz::scriptName(true);

                $rks = Tecnodesign_Cache::get('f-limit/'.$k, $timeout);
                if(!$rks) $rks = [];
                $this->limits['keys'][$k]=$rks;
            }

            $k = md5(implode(':', array_keys($this->limits['keys'])));
            if(!($salt=Tecnodesign_Cache::get('f-limit-current/'.$k, $timeout))) {
                $salt = tdz::salt();
                Tecnodesign_Cache::set('f-limit-current/'.$k, $salt, $timeout);
            }

            foreach($this->limits['keys'] as $k=>$ks) {
                $reqs = 0;
                if($rks) {
                    foreach($rks as $rk=>$rt) {
                        if($rt[0] + $timeout < $now) {
                            unset($rks[$rk]);
                        } else if($rt[1]>0 && $rt[0] + $time > $now) {
                            $reqs++;
                        }
                    }
                }
                $rks[$salt] = [$now,0];
                $this->limits['keys'][$k]=$rks;
                if($reqs > $this->limits['reqs']) $this->limits['reqs'] = $reqs;
                Tecnodesign_Cache::set('f-limit/'.$k, $rks, $timeout);
            }
            $fn = $this->limits['fieldname'];
            $csrf = ['id'=>$fn, 'value'=>$salt, 'type'=>'hidden'];
            if(isset($this->limits['attributes'])) $csrf += $this->limits['attributes'];
            $this->limits['fields'] = [$fn=>new Tecnodesign_Form_Field($csrf)];

            if($this->limits['reqs']) {
                $this->limits['level'] = $this->limits['reqs'] / $this->limits['requests'];
                if($this->limits['level'] > 1.0) {
                    // add error
                    $this->limits['error'] = tdz::t('You\'ve sent more requests than allowed in a short period of time. Please wait some time before retrying to send this request.', 'exception');
                } else if($this->limits['level'] >= $this->limits['warn-level']) {
                    // add warning
                    $this->limits['warn'] = tdz::t('You\'re sending a lot of requests to this page and risks reaching the rate limit for it.', 'exception');
                }
                if($this->limits['level'] > $this->limits['captcha']) {
                    // add captcha
                    $this->limits['captcha'] = true;
                }
            }
        }

        return $this->limits['fields'];

    }

    /**
     * Credentials for specific fields
     *
     * Just add the property 'credential' with the array of valid credentials.
     * Optionally, assign the credential as the key and one or more keywords:
     * insert, update, delete as the value (as string). For example:
     *
     * 'private-field'=>array(
     *    'credential'=> array( 'admin', 'user'=>'update,delete', )
     *    ... other field properties ...
     *  )
     */
    public function checkCredential($c)
    {
        $U = tdz::getUser();
        if(is_bool($c) || is_int($c)) {
            if(!$c) return true;
            else return $U->isAuthenticated();
        } else if(!$U->isAuthenticated()) {
            return false;
        } else if(!is_array($c)) {
            return $U->hasCredential($c);
        } else if(!$c) {
            return false;
        }

        $mode = null;

        $auth = false; // must have at least one
        foreach($c as $i=>$o) {
            if(is_string($i) && $i) {
                if(!$U->hasCredential($i)) continue;
                if(!$mode) {
                    $M = $this->getModel();
                    if($M->isNew()) $mode = 'insert';
                    else if($M->isDeleted()) $mode = 'delete';
                    else $mode = 'update';
                    unset($M);
                }
                if(strpos($o, $mode)===false) {
                    if($mode!='insert' || strpos($o, 'new')===false) continue;
                }
            } else if(!$U->hasCredential($o)) {
                continue;
            }
            return true;
        }
        return $auth;
    }

    public function register($id=null)
    {
        if (is_null(self::$_instances)) {
            self::$_instances=new arrayObject();
        }
        if(!is_null($id)) {
            $this->_uid = $id;
            self::$_instances[$this->_uid]=$this;
        }
        return $this->_uid;
    }

    public static function instance($id=null, $newForm=[])
    {
        if(!$id) {
            $id = array_keys((array)self::$_instances)[0];
        }
        if(!isset(self::$_instances[$id])) {
            self::$_instances[$id] = new Tecnodesign_Form($newForm);
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

    public function setParent($id=null)
    {
        if(is_string($id) && $id!=$this->_uid && isset(self::$_instances[$id])) {
            $this->parent = $id;
            return $id;
        }
    }

    public function getParent()
    {
        if($id=$this->parent) {
            if(!isset(self::$_instances[$id])) {
                $id = null;
                $this->parent = null;
            }
            return $id;
        }
    }

    public function getParentForm()
    {
        if(($id=$this->parent) && isset(self::$_instances[$id])) {
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
        if(Tecnodesign_App::request('headers', 'z-action')=='refreshFields' && ($zp=Tecnodesign_App::request('headers', 'z-params')) && ($fs = json_decode($zp, true))) {
            // check if it's this form
            $r=false;
            if(Tecnodesign_App::request('headers', 'z-target')==$this->id) $r=true;
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
        $this->getLimits();
        $vars = array('Form'=>$this);
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
        if($this->getEnctype()==='multipart/form-data') {
            $values = tdz::postData($values);
        }
        $req = (Tecnodesign_App::request('headers', 'z-action')==='Form.Validate');
        $values = tdz::fixEncoding($values);
        $valid = true;
        if(!$this->checkLimits($values)) {
            if(isset($this->limits['accept-failed']) && $this->limits['accept-failed']) {
                $valid = false;
            } else if(!$req) {
                return false;
            }
        }
        if($this->id && isset($values[$this->id]) && is_array($values[$this->id])) {
            $values = $values[$this->id];
        }
        foreach($this->fields as $fn=>$fv) {
            if(!$fv->setValue($this->_value($fn, $values), true, true)) {
                $valid = false;
            }
            unset($fn, $fv);
        }
        if($req) {
            tdz::output((string)$this);
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

    public function setModel($model)
    {
        if($model instanceof Tecnodesign_Model) {
            if(is_null(self::$models)) {
                self::$models = array();
            }
            if(self::$models && in_array($model, self::$models)) {
                $id = array_search($model, self::$models);
            } else if($model->isNew()) {
                $id = get_class($model).'#new-'.$this->_uid;
            } else {
                $id = get_class($model).'#'.$model->getPk();
                if(isset(self::$models[$id])) {
                    $id .= '-'.$this->_uid;
                }
            }
            $this->model = $id;
            self::$models[$id] = $model;
        } else {
            $id = uniqid();
            $this->model = $id;
            self::$models[$id] = $model;
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
