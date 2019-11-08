<?php
/**
 * Tecnodesign User Interface
 * 
 * This is an action for creating a CRUD-like interface for all available models
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Ui
{
    protected static $_base=null;
    protected static $_ui=null;
    protected static $_uig=null;
    protected static $_model=null;
    protected static $_action='review';
    protected static $_id=null;
    protected static $_filter = null;
    public static $qs = '';
    public static $actions = array('create'=>'create', 'update'=>'update', 'delete'=>'delete'),
        $actionLinks=array('create'=>false);
    public $arguments;

    public function __toString()
    {
        return self::$_model.'/'.self::$_action.'/'.self::$_id;
    }
    
    public static function crud($arg=array())
    {
        $app = tdz::getApp();
        $ui = $app->getObject('Tecnodesign_Ui');
        $ui->setBase();
        $sn = tdz::scriptName();
        $fsn = tdz::scriptName(true);
        if(isset($arg['model']) && strlen($fsn)>strlen($sn) && substr($fsn, 0, strlen($sn)+1)==$sn.'/') {
            $params = preg_split('#/+#', substr($fsn, strlen($sn)+1), null, PREG_SPLIT_NO_EMPTY);
            if(isset($params[0])) {
                $arg['action'] = array_search($params[0], self::$actions);
                if($arg['action'] && isset($params[1])) {
                    $arg['id']=$params[1];
                }
            }
        }
        try {
            $tpl = $ui->renderCrud($arg);
            if(isset($arg['model'])) {
                $ui->linkModel = false;
                $ui->nav = false;
            }
            $s = $app->runTemplate($tpl, tdz::$variables['variables']);
        } catch(Exception $e) {
            tdz::log($e->getMessage());
            $s = $app->runError(500);
        }
        return $s;
    }

    public function renderCrud($arg=array())
    {
        $app = tdz::getApp();
        self::$actionLinks=array('create'=>false );
        $this->app=false;
        $this->form=false;
        $this->error=false;
        $this->model=false;
        $this->class=false;
        $this->search=false;
        $this->list=false;
        $this->nav=true;
        $this->linkModel=true;
        $this->buttons=true;
        $this->arguments=$arg;
        if(isset($_SERVER['QUERY_STRING'])) self::$qs='?'.$_SERVER['QUERY_STRING'];
        if(is_null(self::$_ui) && class_exists($this->arguments['model'])) {
            $cn = $this->arguments['model'];
            $tn = $cn::$schema['tableName'];
            $this->arguments['model'] = $tn;
            $credentials = (isset($cn::$schema['ui-credentials']))?($cn::$schema['ui-credentials']):(false);
            self::$_ui = array($this->arguments['model']=>array('label'=>$cn::label(),'class'=>$cn, 'ui-credentials'=>$credentials));
        } else {
            $this->getModelGroups();
        }
        if($this->arguments['model']) {
            if(!isset(self::$_ui[$this->arguments['model']])) {
                unset($this->arguments['model']);
            } else {
                self::$_model = $this->arguments['model'];
                $this->class = self::$_ui[self::$_model]['class'];
                $c = self::$_ui[$this->arguments['model']]['ui-credentials'];                
                if(!$c 
                    || ($this->arguments['action'] && (!isset($c[$this->arguments['action']]) || !$c[$this->arguments['action']])) 
                    || (!is_array($c) && !tdz::getUser()->hasCredential($c))) {
                    return 'error403';
                }
            }
        }
        if(!isset($this->arguments['action'])) {
            
        }
        
        if($this->arguments['action']!='') {
            if($k = array_search($this->arguments['action'], self::$actions)) self::$_action = $k;
            else self::$_action = $this->arguments['action'];
        } else {
            self::$_action = 'review';
        }
        
        $this->ui = $this;
        if(!self::$_model) {
            return 'ui-home';
        }
        if(is_null(self::$_base)) {
            $response = Tecnodesign_App::response();
            self::$_base = $response['route']['url'];
        }
        tdz::scriptName(self::$_base.'/'.self::$_model);
        $smm = 'renderUi'.ucfirst(self::$_action);
        $sm = 'render'.ucfirst(self::$_action);
        $result = false;
        $o = false;
        if($this->arguments['id']) {
            $cn = $this->class;
            $this->model=$cn::find($this->arguments['id'], 1);
            if(!$this->model) {
                unset($this->arguments['id']);
            }
        }
        if($this->arguments['id'] && $this->model && method_exists($this->model, $smm)) {
            $result = $this->model->$smm($this);
        } else if(method_exists($this, $sm)) {
            $result = $this->$sm($arg);
        }
        if($this->class) {
            if(self::$actionLinks && isset(self::$_ui[self::$_model]['ui-credentials'])) {
                foreach(self::$_ui[self::$_model]['ui-credentials'] as $pn=>$e) {
                    if(!$e && isset(self::$actionLinks[$pn])) unset(self::$actionLinks[$pn]);
                }
                if(self::$_ui[self::$_model]['ui-credentials']['review']) {
                    $this->getResults($this->class);
                }
            }
        }
        if($result===false) {
            return 'error404';
        }
        // process updates
        if(!$result || !is_string($result)) {
            $result = 'ui-'.self::$_action;
        }
        return $result;
    }
    
    public function getResults($cn=null)
    {
        if(!$this->search) {
            if(!$cn) $cn = self::$_ui[self::$_model]['class'];
            $this->search = $this->getSearchForm($cn);
            $labels = $cn::columns('review');

            $po=(isset($cn::$schema['order']))?($cn::$schema['order']):(false);
            if(isset($_GET['o']) && in_array($_GET['o'], $labels)) {
                $d = (isset($_GET['d']) && $_GET['d']=='desc')?('desc'):('asc');
                $cn::$schema['order']=array($_GET['o']=>$d);
            }

            $this->list = $cn::find();
            $count = ($this->list)?($this->list->count()):(0);

            if (!is_null(self::$_filter)) {
                $this->list = $cn::find(self::$_filter,0);
                $subcount=($this->list)?($this->list->count()):(0);
                $this->search .= '<p class="app-search-count">'.sprintf(tdz::t('There are %s records for this query in %s.', 'ui'), $subcount, $count).'</p>';
            } else if($count) {
                $this->search .= '<p class="app-search-count">'.sprintf(tdz::t('There are %s records available.', 'ui'),$count).'</p>';
            } 
            if($po) $cn::$schema['order']=$po;
            else unset($cn::$schema['order']);
        }
    }

    
    public function renderReview($arg=array())
    {
        $this->tn = $this->model = $tn = $arg['model'];
        $cn = self::$_ui[self::$_model]['class'];
        if(!$cn) $cn = tdz::camelize(ucfirst($tn));
        $this->class = $cn;
        $this->labels = $cn::columns('review');
        $pk = $cn::pk();
        // check permissions
        self::$actionLinks = array('create'=>false, 'update'=>$pk, 'delete'=>$pk );      
    }

    public function renderCreate($arg=array())
    {
        $this->tn = $this->model = $tn = $arg['model'];
        $cn = self::$_ui[self::$_model]['class'];
        if(!$cn) $cn = tdz::camelize(ucfirst($tn));
        $this->model = new $cn();
        $this->form =$this->model->getForm('create');
        $this->error=false;
        $derr = 'There was an error while processing your request. Please check the error messages and try again.';
        if(count($_POST)>0) {
            if($this->form->validate($_POST)) {
                try {
                    $this->model->isNew(true);
                    $this->model->save();
                    if(is_null(self::$_base)) {
                        $response = Tecnodesign_App::response();
                        self::$_base = $response['route']['url'];
                    }
                    $link = self::$actions['update'];
                    $link = "{self::$_base}/{self::$_model}/{$link}/{$this->model->getPk()}";
                    tdz::getUser()->setMessage(sprintf(tdz::t('The entry %s was successfully created.', 'ui'), '<em>'.$this->model->__toString('create').'</em>'));
                    tdz::redirect($link);
                } catch(Exception $e) {
                    $this->error = tdz::t($derr, 'exception').' '.$e->getMessage();
                }
            } else {
                $this->error = tdz::t($derr, 'exception');
            }
        }
        // check permissions
        self::$actionLinks = array('create'=>false );
    }

    public function renderUpdate($arg=array())
    {
        $this->tn = $this->model = $tn = $arg['model'];
        $cn = self::$_ui[self::$_model]['class'];
        if(!$cn) $cn = tdz::camelize(ucfirst($tn));
        $model = false;
        if ($arg['id']!='') {
            $model = $cn::find($arg['id']);
        }
        if(!$model) {
            tdz::log("Not found: {$cn}::find({$arg['id']})");
            return false;
        }
        $pk = $cn::pk();
        self::$_id = $model->getPk();
        $this->model = $model;
        $this->form = $this->model->getForm('update');
        $error=false;
        $derr = 'There was an error while processing your request. Please check the error messages and try again.';
        if(count($_POST)>0) {
            try {
                if($this->form->validate($_POST)) {
                    $this->model->save();
                    if(is_null(self::$_base)) {
                        $response = Tecnodesign_App::response();
                        self::$_base = $response['route']['url'];
                    }
                    $link = tdz::getRequestUri();
                    tdz::getUser()->setMessage(sprintf(tdz::t('The entry %s was successfully updated.', 'ui'), '<em>'.$model->__toString('update').'</em>'));
                    tdz::redirect($link);
                } else {
                    $error = tdz::t($derr, 'exception');
                }
            } catch(Exception $e) {
                $error = tdz::t($derr, 'exception').' '.$e->getMessage();
            }
        }
        $this->error = $error;
        // check permissions
        self::$actionLinks = array('create'=>false, 'delete'=>$pk );
    }

    public function renderDelete($arg=array())
    {
        $this->tn = $this->model = $tn = $arg['model'];
        $cn = self::$_ui[self::$_model]['class'];
        if(!$cn) $cn = tdz::camelize(ucfirst($tn));
        $model = false;
        if ($arg['id']!='') {
            $model = $cn::find($arg['id']);
        }
        if(!$model) {
            tdz::log("Not found: {$cn}::find({$arg['id']})");
            return false;
        }
        $pk = $cn::pk();
        self::$_id = $model->getPk();
        $this->model = $model;
        $rs = $model->__toString('delete');
        $before = '<div class="alert message"><h3>'.tdz::t('Do you wish to remove this entry:', 'ui').'</h3><p class="delete-record">'.$rs.'</p></div>';
        $this->form = new Tecnodesign_Form(array('fields'=>array(
            '_id'=>array('type'=>'checkbox', 'choices'=>array(self::$_id=>tdz::t('Yes, please remove this entry.', 'ui')), 'before'=>$before , 'value'=>self::$_id, 'label'=>'')
        )));
        $this->form->buttons['submit']=tdz::t('Delete', 'ui');
        $error=false;
        $derr = 'There was an error while processing your request. Please check the error messages and try again.';
        if(count($_POST)>0) {
            try {
                if($this->form->validate($_POST)) {
                    $this->model->delete();
                    $this->model->save();
                    if(is_null(self::$_base)) {
                        $response = Tecnodesign_App::response();
                        self::$_base = $response['route']['url'];
                    }
                    tdz::getUser()->setMessage(sprintf(tdz::t('The entry %s was successfully removed.', 'ui'), '<em>'.$rs.'</em>'));
                    tdz::redirect(self::$_base);
                }
            } catch(Exception $e) {
                $error = tdz::t($derr, 'exception').' '.$e->getMessage();
            }
        }
        $this->error = $error;
        // check permissions
        self::$actionLinks = array('create'=>false, 'update'=>$pk );
    }


    
    /**
     * Magic setter: looks for response object to set or retrieve data
     */
    public function __set($name, $value)
    {
        if(!isset(tdz::$variables['variables'])) {
            tdz::$variables['variables'] = array();
        }
        tdz::$variables['variables'][$name]=$value;
        return tdz::$variables['variables'][$name];
    }

    /**
     * Magic getter: looks for response object to set or retrieve data
     */
    public function __get($name)
    {
        if(isset(tdz::$variables['variables'][$name])) {
            return tdz::$variables['variables'][$name];
        }
        return false;
    }
    
    public function getModels()
    {
        if(is_null(self::$_ui)) {
            $m = array();
            $user = tdz::getUser();
            foreach(Tecnodesign_Database::getModels() as $tn=>$cn) {
                if(!class_exists($cn)) {
                    continue;
                }
                $schema = $cn::$schema;
                $credentials = false;
                if (isset($schema['ui-credentials'])) {
                    $credentials = $schema['ui-credentials'];
                } else continue;
                $prop = array('label'=>$cn::label(),'class'=>$cn, 'ui-credentials'=>$credentials);
                if(isset($schema['ui-properties'])) {
                    $prop += $schema['ui-properties'];
                    if(isset($prop['enabled']) && !$prop['enabled']) continue;
                }
                /**
                 * Validation was moved to menu methods
                 *
                if (isset($schema['ui-credentials'])) {
                    // check user credentials against
                    $valid = false;
                    if(isset($schema['ui-credentials'][self::$_action])) {
                        $cred = $schema['ui-credentials'][self::$_action];
                        if(!is_array($cred)) {
                            if(!$cred || $user->isAutenticated()) {
                                $valid = true;
                            }
                        } else if($user->hasCredential($cred)) {
                            $valid = true;
                        }
                    }
                    if(!$valid) {
                        continue;
                    }
                }
                */
                $m[$tn]=$prop;
            }
            self::$_ui = $m;
            Tecnodesign_App::response('cache', true);
        }
        return self::$_ui;
    }
    
    public function getModelLabel($tn, $fallback='Unknown')
    {
        if(is_null(self::$_ui)) {
            $this->getModels();
        }
        if(is_object($tn)) {
            $cn = get_class($tn);
            $tn = $cn::$schema['tableName'];
        } else if(preg_match('/[A-Z]/', $tn)) {
            $cn = $tn;
            $tn = $cn::$schema['tableName'];
        }
        if(isset(self::$_ui[$tn])) {
            return self::$_ui[$tn]['label'];
        } else {
            return $fallback;
        }
    }
    
    public function getModelGroups()
    {
        if(is_null(self::$_uig)) {
            $models = $this->getModels();
            // grouping models
            $gmodels = array();
            foreach($models as $tn=>$t) {
                $cn = $t['class'];
                if(isset($cn::$schema['group'])) {
                    $p = $cn::$schema['group'];
                } else if(strpos($tn, '_')!==false) {
                    $p = substr($tn, 0, strpos($tn, '_'));
                } else {
                    $p = $tn;
                }
                $gmodels[$p][$tn]=array('label'=>$t['label'], 'ui-credentials'=>$t['ui-credentials']);
            }
            self::$_uig = $gmodels;
            Tecnodesign_App::response('cache', true);
        }
        return self::$_uig;
    }
    
    public function setBase($url=null)
    {
        if(is_null($url)) {
            $url = tdz::scriptName();
        }
        while(substr($url, -1)=='/') {
            $url = substr($url, 0, strlen($url) -1);
        }
        self::$_base = $url;
    }
    
    public function getLink($link)
    {
        if(is_null(self::$_base)) {
            $response = Tecnodesign_App::response();
            self::$_base = $response['route']['url'];
        }
        if(substr($link, 0, 1)!='/') {
            $link = self::$_base.'/'.$link;
        }
        $link;
        return $link;
    }
    
    
    public function linkTo($link, $label)
    {
        $link = $this->getLink($link);
        return "<a href=\"{$link}\">{$label}</a>";
    }
    
    public function getNavigation($class='')
    {
        //verify credentials to protect the menu of blank groups
        $user = tdz::getUser();
        $uc=array();
        $groups = array();        
        foreach ($this->getModelGroups() as $p=>$mg) {            
            foreach($mg as $tn=>$m) {
                if($m['ui-credentials']) {
                    $c = (is_array($m['ui-credentials']))?(implode(',',$m['ui-credentials'])):(var_export($m['ui-credentials'],true));
                    if(!isset($uc[$c])){
                        $uc[$c] = $user->hasCredential($m['ui-credentials']);
                    }
                    if(!$uc[$c]) {
                        continue;
                    }
                } else {
                    continue;
                }
                $groups[$p][$tn]=$m;
            }            
        }
        //Draw menu
        $s  = '<span id="toggle-menu">«</span><ul>';
        foreach ($groups as $p=>$mg) {            
            $label = (isset(self::$_ui[$p]))?(self::$_ui[$p]['label']):(tdz::t($p, 'ui'));
            $s .= '<li><span class="ui-nav-header">'.$label.'</span>';

            ksort($mg);
            $mc=0;
            $sm = '<ul>';
            foreach($mg as $tn=>$m) {                
                $mc++;
                $sm .= "<li>{$this->linkTo($tn, $m['label'])}</li>";
            }
            $sm .= '</ul></li>';
            if($mc) {
                $s .= $sm;
            }
        }        
        $s .= '</ul>';
        
        if ($class) {
            $s = "<div class=\"{$class}\">{$s}</div>";
        }
        return $s;
    }
    
    public function getButtons($actions=null)
    {
        if(is_null($actions)) {
            $actions = self::$actionLinks;
        }
        $model = ($this->linkModel)?(self::$_model.'/'):('');
        $idactions=array();
        $oactions=array();
        $link = '';
        $pk=array();
        $s = '';

        $c=(isset(self::$_ui[self::$_model]['ui-credentials']))?(self::$_ui[self::$_model]['ui-credentials']):(false);
        foreach($actions as $an=>$scope) {
            if(!$c || !isset($c[$an]) || !$c[$an]) continue;
            if ($scope) {
                if($link=='') {
                    $pk=$scope;
                    $link = $model.$an;
                }
                $idactions[$an]=tdz::t(ucfirst(self::$actions[$an]), 'ui');
            } else {
                $oactions[$an]=tdz::t(ucfirst(self::$actions[$an]), 'ui');
            }
        }
        $checkbox = (count($idactions)>0);
        $s .= '<div class="ui-buttons">';
        foreach ($oactions as $an=>$label) {
            $s .= $this->linkTo("{$model}".self::$actions[$an], $label);
        }
        if(self::$_id) {
            $s .= '<span class="ui-id-actions">';
            foreach ($idactions as $an=>$label) {
                $an = self::$actions[$an];
                $id = (self::$_id)?('/'.self::$_id):('');
                $s .= $this->linkTo("{$model}{$an}{$id}".self::$qs, $label, array('onclick'=>'return ui.post()', 'class'=>'disabled'));
            }
            $s .= '</span>';
        }
        $s .= '</div>';
        return $s;
    }
    
    public function getSearchForm($model=null)
    {
        if(!$model) {
            $model=self::$_ui[self::$_model]['class'];
        }
        $link = ($this->tn && $this->linkModel)?("$this->tn/"):('');
        $action = $this->getLink($link);
        $cols = $model::columns('search');
        //exit(var_dump($cols));
        $choices = array();
        foreach($cols as $k => $v) {
            if(is_numeric($k)) $k=ucwords(str_replace('_', ' ', $v));
            $choices[$v] = tdz::t($k, 'model-'.$this->tn);
        }
        $cfg_form = array(
            'method' => 'get',
            'action' => $action,
            'buttons'=> array('submit' => '*Search'),
            'class'=>'app-search-form',
            'fields' => array(
                'q' => array (
                    'label' => tdz::t('Search for', 'ui-labels'),
                    'placeholder' => tdz::t('Search for', 'ui-labels'),
                    'type'  => 'text',
                ),
                'w' => array (
                    'label' => tdz::t('Search at', 'ui-labels'),
                    'placeholder' => tdz::t('Search at', 'ui-labels'),
                    'type' => 'select',
                    'choices' => $choices
                )
            )
        );
        
        $form = new Tecnodesign_Form($cfg_form);
        if (isset($_GET['q']) || isset($_GET['o'])) {
            try {
                if ($form->validate($_GET)) {
                    $w=array_keys($choices);
                    $d=$form->getData();
                    self::$qs='?q='.urlencode($d['q']);
                    if(isset($d['w']) && isset($w[$d['w']])) {
                        self::$qs.='&'.urlencode($d['w']);
                        $w=array($d['w']);
                    }
                    // page and sorting
                    if(isset($_GET['p'])) self::$qs.='&p='.(string)$_GET['p'];
                    if(isset($_GET['o'])) self::$qs.='&o='.(string)$_GET['o'];
                    if(isset($_GET['d'])) self::$qs.='&d='.(string)$_GET['d'];
                    self::$_filter=array();
                    $ops = array();//array($d['q']);
                    $q = explode('-', tdz::slug($d['q']));
                    $d['q']=strtolower($d['q']);
                    foreach($q as $qw) {
                        if($d['q']==$qw) continue;
                        $ops[]=$qw;
                    }
                    foreach($w as $fn) {
                        self::$_filter['|'.$fn.'*=']=$ops;
                        /*
                        foreach($ops as $i=>$op) {
                            self::$_filter['|'.str_repeat(' ', $i).$fn.'*=']=$op;
                            
                        }*/
                    }
                }
            } catch(Exception $e) {
                $error = $e->getMessage();
            }
        }
        $this->search = $form;
        return $form;
    }
}