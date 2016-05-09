<?php
/**
 * e-Studio: Tecnodesign's Content Management System
 *
 * This package implements a stand-alone Content Management System.
 *
 * PHP version 5.2
 *
 * @category  App
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: Studio.php 1210 2013-05-05 12:28:45Z capile $
 * @link      http://tecnodz.com/
 */

/**
 * e-Studio: Tecnodesign's Content Management System
 *
 * Stand-alone Content Management System.
 *
 * @category  App
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */
class Tecnodesign_App_Studio
{
    public static $enableCache=false;
    private $_env=null;
    public static $instance = null;
    protected static $app = null;
    protected static $permissions=array();
    public static $response=array(
      'script'=>array('', 'tecnodesign/js/jquery.js', 'tecnodesign/js/tdz.js', 'tecnodesign/js/e-studio-plugin.js', 'tecnodesign/js/json2.js'),
      'style'=>array('', 'tecnodesign/css/e-studio.less', 'tecnodesign/css/tdz.less'),
    );
    protected static $actions=array(
      'n'=>'new',
      'e'=>'edit',
      'q'=>'search',
      'p'=>'preview',
      'x'=>'publish',
      'd'=>'delete',
      'u'=>'auth',
    );
    protected static $models=array(
      'e'=>'Entry',
      'c'=>'Content',
    );
    
    public function __construct($config, $env='prod')
    {
        $this->_env = $env;
        if(!is_array($config)) {
            $config = array();
        }
        $cfile = dirname(__FILE__).'/Studio/Resources/config/app.yml';
        $config = tdz::config($config, $cfile, $env, 'studio');
        $app = tdz::getApp();
        if(isset($config['symfony-app'])) {
            $sfconfig = tdz::config($app->tecnodesign['apps-dir'].'/apps/'.$config['symfony-app'].'/config/app.yml', $env, 'e-studio');
            $sfconfig = str_replace(array('%SF_DATA_DIR%','%SF_ROOT_DIR%'), array($app->tecnodesign['apps-dir'].'/data',$app->tecnodesign['apps-dir']), $sfconfig);
            $config = ($sfconfig != '') ? ($sfconfig + $config) : ($config);
            $config['document_root']=realpath($config['document_root']);
            $config['upload_dir']=(substr($config['upload_dir'],0,1)!='/')?(realpath($app->tecnodesign['apps-dir'].'/'.$config['upload_dir'])):(realpath($config['upload_dir']));
        }
        $app->__set('studio', $config);
        $this->addConfig($app);
    }
    
    public function addConfig($app)
    {
        $tdz = $app->tecnodesign;
        // routes
        $ui = $app->studio['ui-url'];
        $tdz['routes']['/e-studio']=array(
            'class'=>'Tecnodesign_App_Studio',
            'method'=>'runLegacyAction',
            'additional-params'=>true,
        );
        $tdz['routes'][$ui]=array(
            'class'=>'Tecnodesign_App_Studio',
            'method'=>'runAction',
            'additional-params'=>true,
            'params'=>array(
                array(
                  'name'=>'action',
                  'choices'=>array_keys(self::$actions),
                  'required'=>true,
                ),
                array(
                  'name'=>'model',
                  'choices'=>array_keys(self::$models),
                  'required'=>false,
                ),
                array(
                  'name'=>'id',
                  'required'=>false,
                ),
            ),
        );
        $tdz['routes'][$ui]+=Tecnodesign_App::$defaultController;
        if($app->studio['install']) {
            if(!is_dir($tdz['data-dir'].'/e-studio')) {
                // add e-studio installer routes
                $tdz['routes']['e-studio-install']=array(
                  'class'=>'Tecnodesign_App_Studio_Install',
                  'method'=>'install',
                );
                $tdz['routes']['e-studio-install']+=Tecnodesign_App::$defaultController;
                $tdz['routes']['e-studio-update-schema']=array(
                  'class'=>'Tecnodesign_App_Studio_Install',
                  'method'=>'updateSchema',
                );
                $tdz['routes']['e-studio-update-schema']+=Tecnodesign_App::$defaultController;
            }
        }
        $tdz['routes'][$app->studio['assets-url'].'/tecnodesign/js/loader.js']=array(
            'class'=>'Tecnodesign_App_Studio',
            'method'=>'runLegacyAction', // 'runJsLoader',
        );
        $tdz['routes']['.*']=array(
            'class'=>'Tecnodesign_App_Studio',
            'method'=>'runPreviewEntry',
        );
        $tdz['routes']['.*']+=Tecnodesign_App::$defaultController;
        if(!class_exists('tdzEntry')) {
            if(!is_array($tdz['lib-dir'])) {
                $tdz['lib-dir'] = array($tdz['lib-dir']);
            }
            $lib = dirname(__FILE__).'/Studio/Resources/model';
            $tdz['lib-dir'][] = $lib;
            tdz::$lib[] = $lib;
            $sep = (isset($_SERVER['WINDIR']))?(';'):(':');
            @ini_set('include_path', ini_get('include_path').$sep.$lib);
        }
        $app->__set('tecnodesign', $tdz);
    }
    
    public function run()
    {
        self::$app = tdz::getApp();
        Tecnodesign_App_Studio::$instance=$this;
    }
    
    /**
     * Gets user preferred language
     */
    public function getLanguage($language=null)
    {
        if (!isset(self::$response['language']))
        {
            $this->setLanguage($language);
        }
        return self::$response['language'];
    }
    
    /**
     * Sets user language according to browser preferences
     */
    public function setLanguage($language=null)
    {
        $e = self::$app->studio;
        if(!isset($e['languages']) || !is_array($e['languages'])) {
            $e['languages']=array();
        }
        if (count($e['languages'])<2) {
        } else if (!is_null($language) && isset($e['languages'][$language])) {
            self::$response['language'] = $language;
        } else if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $accept = preg_split('/(;q=[0-9\.]+|\,)\s*/', $_SERVER['HTTP_ACCEPT_LANGUAGE'], null, PREG_SPLIT_NO_EMPTY);
            foreach ($accept as $lang) {
                if (isset($e['languages'][$lang])) {
                    self::$response['language'] = $lang;
                }
                if (strlen($lang)>2) {
                    $lang = substr($lang,0,2).'_'.strtoupper(substr($lang,3));
                    if (isset($e['languages'][$lang])) {
                        self::$response['language'] = $lang;
                    }
                }
                if (isset(self::$response['language'])) {
                    break;
                }
            }
        }
        if (!isset(self::$response['language'])) {
            if(isset($e['default_language'])) {
                self::$response['language'] = $e['default_language'];
            } else if(count($e['languages'])>0) {
                $languages = array_keys($e['languages']);
                self::$response['language'] = $languages[0];
            } else {
                self::$response['language'] = 'en';
            }
        }
        tdz::$lang = str_replace('_', '-', self::$response['language']);
        return self::$response['language'];
    }


    /**
     * Page preview
     * 
     * @return string html
     */
    public function runLegacyAction($request)
    {
        /*
        forwarded to symfony while methods aren't created at tecnodesign framework
        */
        $env = 'prod';
        $appname = self::$app->studio['symfony-app'];
        require_once(self::$app->tecnodesign['apps-dir'].'/config/ProjectConfiguration.class.php');
        $configuration = ProjectConfiguration::getApplicationConfiguration($appname, $env, false);
        sfContext::createInstance($configuration,$appname)->dispatch();
        exit();
    }    

    /**
     * Page preview
     * 
     * @return string html
     */
    public function runAction($request)
    {
        $a = self::$actions[$request['action']];
        $p=$a;
        $o=null;
        $false = 'error403';
        $arg=array('action'=>$a);
        $model=false;
        if($request['model']) {
            $model = ucfirst(self::$models[$request['model']]);
            $p .= $model;
            $cn = 'tdz'.$model;
            $arg['model']=$cn;
            if(isset($cn::$schema['events']['active-records'])) unset($cn::$schema['events']['active-records']);
            if($request['id']){
                $o = $cn::find($request['id']);
                if(!$o) {
                    return $false;
                }
                $arg['object']=$o;
            }
        }
        if($p!='auth' && !self::hasPermission($p, $o)) {
            return $false;
        }

        $ba = 'run'.ucfirst($a);
        $m = 'run'.ucfirst($p);
        if(!method_exists($this, $m)) {
            if(method_exists($this, $ba)) $m=$ba;
            else {
                tdz::log('Studio::'.$m.' was not defined!');
                return $false;
            }
        }
        $this->setLanguage();
        Tecnodesign_Form::$enableStyles=999;
        tdz::$variables['style']=self::$response['style'];
        tdz::$variables['script']=self::$response['script'];
        tdz::$variables['variables']['action']=$a;
        tdz::$variables['variables']['model']=$model;
        tdz::$variables['layout']=self::templateFile('e-studio');
        $tpl = $this->$m($arg);
        if($tpl===false) return false;
        $tpl=self::templateFile($tpl, 'e-studio-base', 'e-studio-'.$a);
        tdz::$variables += self::$response;
        tdz::$variables['variables']+=self::$response['variables'];
        return $tpl;
    }

    public static function title($title=null)
    {
        if(!is_null($title)) self::$response['title']=self::$response['variables']['title']=$title;
        return self::$response['title'];
    }

    public static function config($name, $value=null)
    {
        if(!is_null($value)) self::$app->studio[$name]=$value;
        return self::$app->studio[$name];
    }

    public function runNew($arg)
    {
        $cn=$arg['model'];
        $on=str_replace('tdz', '', $cn);
        self::title(tdz::t('New '.strtolower($on), 'e-studio'));
        $e=new $cn();
        $fo=$e->getForm('studio-new');
        $fo->attributes['class']='studio-form studio-form-new';
        $fo->buttons = array('submit'=>'<span class="studio-icon studio-button-new"></span>'.tdz::t('Create', 'form'));
        $s = '';
        if(count($_POST)>0) {
            try {
                if($fo->validate($_POST)) {
                    $e->version=1;
                    $e->save();
                    $eurl = self::$app->studio['ui-url'].'/e/'.array_search($on, self::$models).'/'.$e->id;
                    tdz::redirect($eurl);
                }
            } catch (Exception $e) {
                tdz::log(__METHOD__.':'.$e->getMessage());
                $err = $fo->getError();
                if(!$err) {
                    $s .= '<div class="studio-error">'.tdz::t('There was an error while processing your request. Please try again or contact technical support.', 'e-studio').'</div>';
                }
            }
            // remove errors
            //$fo->resetErrors();
        }
        if($fo) $s .= $fo->render();
        self::$response['variables']['content']=$s;
        return true;
    }

    public function runEdit($arg)
    {
        $cn=$arg['model'];
        $on=str_replace('tdz', '', $cn);
        if($on=='Entry') {
            $types=self::config('entry_types');
            $type = $types[$arg['object']->type]['label'];
            if(substr($type, 0, 1)=='*') {
                $type = tdz::t(strtolower(substr($type,1)), 'model-'.$cn::$schema['tableName']);
            }
        } else {
            $type = tdz::t($on, 'e-studio');
        }
        self::title(tdz::t('Edit', 'e-studio').' '.$type);
        $e=new $cn($arg['object']->asArray(),true,false);
        $fo=$e->getForm('studio-edit');
        if($arg['object']->expired) {
            $fo->attributes['class']='studio-form studio-form-delete';
            $fo->buttons = array(
                'submit'=>'<span class="studio-icon studio-button-edit"></span>'.tdz::t('Restore', 'e-studio'),
            );
        } else {
            $fo->attributes['class']='studio-form studio-form-edit';
            $fo->buttons = array(
                'submit'=>'<span class="studio-icon studio-button-edit"></span>'.tdz::t('Update', 'e-studio'),
                'delete'=>'<button type="button" class="delete" onclick="return tdz.eFormDelete(this,\''.sprintf(tdz::t('Are you sure you want to remove this %s?', 'e-studio'), $type).'\')"><span class="studio-icon studio-button-delete"></span>'.tdz::t('Delete', 'e-studio').'</button>',
            );
        }
        $s = '';
        if(count($_POST)>0) {
            try {
                if($fo->validate($_POST)) {
                    $msg = 'Congrats, %s was successfully updated!';
                    $redirect=false;
                    if($arg['action']=='delete') {
                        $e->expired=date('Y-m-d H:i:s',TDZ_TIME);
                        $msg = 'You made it, %s was removed!';
                        $redirect=true;
                    } else if($e->expired) {
                        $e->expired=null;
                        $msg = 'Great, got %s back!';
                        $redirect=true;
                    }
                    $redirect=true;
                    $cn::$schema['events']['before-insert']=$cn::$schema['events']['before-update'];
                    $cn::$schema['actAs']['before-insert']=$cn::$schema['actAs']['before-update'];
                    $e->save();
                    self::$response['variables']['message']='<div class="studio-message studio-success">'.sprintf(tdz::t($msg, 'e-studio'), $type).'</div>';
                    if($redirect) {
                        tdz::getUser()->setMessage(self::$response['variables']['message']);
                        $eurl = self::$app->studio['ui-url'].'/e/'.array_search($on, self::$models).'/'.$e->id;
                        tdz::redirect($eurl);
                    }
                }
            } catch (Exception $e) {
                tdz::log(__METHOD__.':'.$e->getMessage());
                $err = $fo->getError();
                if(!$err) {
                    self::$response['variables']['message'] = '<div class="studio-message studio-error">'.tdz::t('There was an error while processing your request. Please try again or contact technical support.', 'e-studio').'</div>';
                }
            }
        }
        if($fo) $s .= $fo->render();
        self::$response['variables']['content']=$s;
        return true;
    }


    public static function forceNew($o, $a, $conn)
    {
        $cn = get_class($o);
        if($a=='before-save') {
            if(!$o->isNew()) {
                if($o->isDeleted()) {
                    $cn::$schema['events']['before-insert']=$cn::$schema['events']['before-delete'];
                    $cn::$schema['actAs']['before-insert']=$cn::$schema['actAs']['before-delete'];
                } else {
                    $cn::$schema['events']['before-insert']=$cn::$schema['events']['before-update'];
                    $cn::$schema['actAs']['before-insert']=$cn::$schema['actAs']['before-update'];
                }
                $o->isNew(true);
            } else {
                if(!$o->version) $o->version=1;
            }
        }
        /*
        if($a!='before-insert') {
            //$cn::$schema['events']['before-insert']=$cn::$schema['events']['before-update'];
            $cn::$schema['actAs'][$a]=$cn::$schema['actAs']['before-update'];
        }
        */
        return true;
    }

    public function runDelete($arg)
    {
        return $this->runEdit($arg);
    }


    /**
     * Checks if a user exists and returns its information, along with 
     * system messages.
     */
    public function runAuth($request)
    {
        if($_SERVER['HTTP_TDZ_ACTION']=='auth') {
            $ret = array('id'=>false);
            $u=tdz::getUser();
            if($u->isAuthenticated()) {
                $o = $u;
                $ret['id']=$o->id;
                $ret['user']=(string) $o;
            }
            $m = $u->getMessage();
            if($m) {
                $u->deleteMessage();
                $ret['message']=$m;
            }
            $o=null;
            $ret['request']=$request;
            $a=array();
            foreach(self::$actions as $alias=>$an) {
                if($an=='action') {
                    continue;
                }
                foreach(self::$models as $malias=>$mn) {
                    $pub = ($an=='preview')?('Unpublished'):('');
                    $p = "{$an}{$mn}{$pub}";
                    if(self::hasPermission($p, $o)) {
                        $a[$p]="$alias/$malias";
                    }
                }
            }
            if(count($a)>0) {
                $ret['actions']=$a;
            }
            tdz::cacheControl('nocache, private', 0);
            tdz::output($ret, 'json');
        }
        return false;
    }
    
    
    /**
     * Page preview
     * 
     * @return string html
     */
    public function runPreviewEntry($request)
    {
        $app = self::$app;
        // get preferred language
        $language = $this->getLanguage();
        // get user hash
        $user=tdz::getUser();
        $userHash = ($user)?(implode('-', $user->getCredentials())):('');
        if(isset($request['object'])) {
            $entry = $request['object'];
            $url = $entry->link;
        } else {
            $entry = false;
            $url=tdz::scriptName();
        }
        if (count($_POST) > 0) {
            tdz::cacheControl('private, must-revalidate', 0);
        } else if (!$userHash) {
            tdz::set('cache-control', 'public');
        }
        $ckey='pages/'.$language.'/'.md5($userHash.'/'.$url);
        $cmod=Tecnodesign_Cache::getLastModified($ckey, 0, true);
        $updated=tdzEntry::timestamp(array('tdzEntry', 'tdzContent'));
        if(isset($app->tecnodesign['response']) && is_array($app->tecnodesign['response'])) {
            self::$response = tdz::mergeRecursive($app->tecnodesign['response'], self::$response);
        }
        self::$response += array(
            'entry'=>$entry,
            'ajax'=>false,
            'download'=>false,
            'layout'=>false,
            'optimize'=>false,
        );
        tdz::$assetsUrl = $app->studio['assets-url'];
        $ci=false;
        if (false && $cmod && $updated && $cmod>$updated) {
            $ci=Tecnodesign_Cache::get($ckey);
            if(is_array($ci)) {
                $ci += self::$response;
                self::$response = $ci;
            }
            if(!file_exists(self::$response['layout'].'.php')) {
                self::$response['layout']=false;
            }
        }
        $editor = self::hasPermission('previewEntryUnpublished');
        if(!self::$response['entry']) {
            self::$response['entry'] = tdzEntry::match($url, false, !$editor);
        }

        if((!self::$response['entry'] || self::$response['entry']->link!=$url) && preg_match('/\.([^\.\/]+)\.(jpe?g|png|gif)$/i', $url, $m) && isset($app->studio['assets_optimize_actions'][$m[1]])) {
            $nurl = substr($url, 0, strlen($url) - strlen($m[0])).'.'.$m[2];
            $ne = tdzEntry::match($nurl, false, !$editor);
            if(!$ne) { 
                // check for file under htdocs
            } else {
                self::$response['entry']=$ne;
                self::$response['optimize']=$m[1];
                self::$response['layout']=self::$response['entry']->filePreview(self::$response['optimize']);
                self::$response['download']=self::$response['entry']->format;
                if(!self::$response['download'])self::$response['download']=true;
            }
        }

        if(!self::$response['entry']) {
            self::$response['entry'] = tdzEntry::match($url, false, !$editor);
        }
        if(!self::$response['entry'] && $editor && isset($request['id']) && $request['id']!='') {
            self::$response['entry'] = tdzEntry::find($request['id']);
            if(self::$response['entry'] && self::$response['entry']->type=='entry' && self::$response['entry']->link) {
                $url=self::$response['entry']->link;
            }
        }

        if(self::$response['entry'] && !self::hasPermission('preview', self::$response['entry'])) {
            return 'error403';
        }
        if(self::$response['entry'] && isset($_SERVER['HTTP_TDZ_SLOTS']) && preg_match('/[1-9][0-9]{9,}$/',$_SERVER['QUERY_STRING'])) {
            self::$response['ajax']=true;
            $this->ajaxPreview();
        }

        if(!self::$response['layout']) {
            if (self::$response['entry']) {
                self::$response['layout']=self::$response['entry']->render($request);
            } else {
                //tdz::debug(self::$response, var_export($editor, true), __METHOD__.','.__LINE__);
                self::$response['layout']=$this->previewAsset($url);
            }
            Tecnodesign_Cache::set($ckey, self::$response, $app->studio['cache_timeout']);
        }
        $prefix_url = $app->studio['prefix_url'];
        //$this->getResponse()->setHttpHeader('Last-Modified', $this->getResponse()->getDate($timestamp));
        if(self::$response['layout']) {
            if(self::$response['download']) {
                $format=self::$response['download'];
                if(strlen($format)<2)$format=false;
                tdz::download(self::$response['layout'], $format, basename($url));
                exit();
            }
            /*
            $this->message=$this->getUser()->getFlash('message');
            if($this->message=='' && file_exists($layout.'.php')) {
                $fl=fopen($layout.'.php','r');
                $fh=fread($fl,14);
                fclose($fl);
                if(substr($fh,6)=='//static')
                {
                  $etag=md5_file($layout.'.php');
                  tdz::getBrowserCache($etag, filemtime($layout.'.php'),sfConfig::get('app_e-studio_cache_timeout'));
                }
            }
            */
            if(self::$response['entry']) {
                tdz::scriptName(self::$response['entry']->link);
            }
            if(self::$response['entry']) {
                self::$response['javascript']['e-studio']=$app->studio['ui-assets-url'].'/js/loader.js?link='.urlencode(self::$response['entry']->link);
            } else {
                self::$response['javascript']['e-studio']=$app->studio['ui-assets-url'].'/js/loader.js';
            }
            self::$response['language']=$language;
            if(isset($app->studio['symfony-app'])) {
                self::$response['script'][]=$app->studio['ui-assets-url'].'/js/e-studio.js';
                self::$response['script'][]=self::$response['javascript']['e-studio'];
                self::$response['style'][]=$app->studio['ui-assets-url'].'/css/e-studio.css';;
            }
            Tecnodesign_App::response(self::$response);
            return true;
        }
        return 'error404';
    }
    

    public function runJsLoader()
    {
        self::$response['tdz']=self::cmsInfo();
        return 'e-studio-loader';
    }

    public static function cmsInfo()
    {
        $e = (isset(self::$response['entry']) && self::$response['entry'])?(self::$response['entry']):(false);
        return array(
            'user'=> (string) tdz::getUser(),
            'poll'=> self::$app->studio['poll'],
            'language'=>tdz::$lang,
            'dir'=>tdz::$assetsUrl.'/tecnodesign',
            'link'=>($e)?($e->link):(''),
            'entry'=>($e)?($e->id):(''),
            'tinymce_css'=>self::$app->studio['tinymce_css'],
            'ui'=>'/e-studio',
            'l'=>Tecnodesign_Yaml::load(dirname(__FILE__).'/Studio/Resources/config/labels.'.substr(tdz::$lang, 0, 2).'.yml'),
        );
    }

    public function ajaxPreview()
    {
        if(!self::$response['ajax']) return false;
        $lastmod = tdzEntry::timestamp(array('tdzEntry', 'tdzContent'));
        $result=array();
        if(isset($_SERVER['HTTP_TDZ_SLOTS'])) {
            $s = array();
            parse_str($_SERVER['HTTP_TDZ_SLOTS'],$s);
            if(!is_array($s)) return false;
            $w = array();
            $wp=array();
            $i=1;
            $result['page']['updated']=$lastmod;
            $u = tdz::getUser();
            if(!isset($s['page']) && (!$u->isAuthenticated() || count($u->getCredentials())==0)) {
                tdz::output($result, 'json');
            }
            if(!self::$response['entry'] || (isset($s['page']) && $s['page']>=$lastmod)) {
                tdz::output('{}', 'json');
            }
            if(isset($s['userinfo'])) {
                unset($s['userinfo']);
                $result['tdz'] = self::cmsInfo();
            }

            $edit=self::hasPermission('edit', self::$response['entry']); // $this->entry->hasPermission('edit');
            $new=self::hasPermission('new', self::$response['entry']);
            $publish=self::hasPermission('publish', self::$response['entry']);
            $editable=($u->isAuthenticated() && count($u->getCredentials())>0 && ($edit||$new||$publish));
  
            if($editable) {
                $prop=array();
                if($new) $prop[]='new';
                if(self::$response['entry']->id>0 && $edit)$prop[]='edit';
                if(self::hasPermission('search', self::$response['entry'])) {
                    $prop[]='search';
                    $prop[]='files';
                }
                if(self::hasPermission('users', self::$response['entry']))
                    $prop[]='users';
                  else
                    $prop[]='user';
                if(self::$response['entry']->id>0 && $publish) {
                    $prop[]='publish';
                    if(self::$response['entry']->published!='') $prop[]='unpublish';
                }
                $prop=implode(' ',$prop);
                if($prop!='') $result['page']['prop']=$prop;
            }
  
            $result['slots']=array();
            foreach($s as $id=>$lastupdated) {
                $w[] = "c.slot=:slot{$i}";
                $wp['slot'.$i]=$id;
                $i++;
                if($new) $result['slots'][$id]=array('prop'=>'new');
            }
            //$contents = self::$response['entry']->getContent(implode(' or ', $w),$wp);
            $contents = self::$response['entry']->getRelatedContent();
            foreach($contents as $content) {
                $slot = $content->slot;
                if(!isset($result['slots'][$slot]))$result['slots'][$slot]=array();
                if(!isset($result['slots'][$slot]['contents']))$result['slots'][$slot]['contents']=array();
                $id='c'.$content->id;
                $result['slots'][$slot]['contents'][]=$id;
  
                $updated=strtotime($content->updated);
                $o=array('updated'=>$updated);
                if(!(isset($s[$slot]) && is_array($s[$slot]) && isset($s[$slot][$id]) && $updated<=$s[$slot][$id])) $o['html']=tdzContent::preview($content);
  
                if($editable) {
                    $o['prop']=array();
                    if($new && self::hasPermission('newContent', self::$response['entry'])) $o['prop'][]='new';
                    if($edit && self::hasPermission('editContent', self::$response['entry']))$o['prop'][]='edit';
                    if($publish && self::hasPermission('publishContent', self::$response['entry'])) {
                        $pub=$content->published;
                        if($pub) $pub=strtotime($pub);
                        if(!$pub || $pub<$updated) $o['prop'][]='publish';
                        if($pub) $o['prop'][]='unpublish';
                    }
                    $o['prop']=implode(' ',$o['prop']);
                    if($o['prop']=='') unset($o['prop']);
                }
                if(count($o)>1) {
                    if(!isset($result['contents']))$result['contents']=array();
                    $result['contents'][$id]=$o;
                }
            }
        }
        tdz::output(json_encode($result), 'json');
    }    
    public function previewAsset($url)
    {
        $root = self::$app->tecnodesign['document-root'];
        if(file_exists($root.$url)) {
            tdz::download($root.$url, null, null, 0, false, false, false);
            self::$app->end();
        }
        $assets = self::$app->studio['assets-url'];
        $optimize = (isset(self::$app->studio['assets-optimize']) && self::$app->studio['assets-optimize'] && substr($url, 0, strlen($assets))==$assets);
        if($optimize && preg_match('/^(.*\.)([^\.\/]+)\.([^\.\/]+)$/', $url, $m) && file_exists($root.$m[1].$m[3]) && isset(self::$app->studio['assets-optimize-actions'][$m[2]])) {
            $method = self::$app->studio['assets-optimize-actions'][$m[2]];
            $file = $root.$m[1].$m[3];
            $ext = strtolower($m[3]);
            if(in_array($ext, $method['extensions']) || in_array('*', $method['extensions'])) {
                $args=array($file);
                if(isset($method['params'])) {
                    $args[] = $method['params'];
                } else if(isset($method['arguments'])) {
                    $args = array_merge($args, $method['arguments']);
                }
                $result = call_user_func_array(array('tdz', $method['method']), $args);
                if($result) {
                    tdz::output($result, tdz::fileFormat($url), false);
                }
                self::$app->end();
            }
        }
        self::$app->runError(404);
    }
    
    
    /**
     * Credentials checking
     */
    public static function hasPermission($role='previewEntry', $o=null)
    {
        $credentials=self::getPermission($role, $o);
        if(is_bool($credentials)) {
            return $credentials;
        } else {
            return tdz::getUser()->hasCredential($credentials, false);
        }
    }
    
    /**
     * Credentials parsing
     */
    public static function getPermission($role='previewEntry', $o=null)
    {
        $object = 'Entry';
        $published = '';

        // first it must recurse through all parents and global config to get all credentials that match the desired permission
        $valid_roles = array('all','new','edit','publish','delete','preview','search');
        $valid_objects = array('Entry','Content','Permission','ContentType');
        $valid_status = array('Published','Unpublished');
        if(preg_match_all('/[A-Z][a-z]+/',$role,$m)) {
            $role=str_replace($m[0],array(),$role);
            if(isset($m[0][0]) && in_array($m[0][0],$valid_objects)) {
                $object=array_shift($m[0]);
            }
            if(isset($m[0][0]) && in_array($m[0][0],$valid_status)) {
                $published=array_shift($m[0]);
            }
        }
        if(!in_array($role,$valid_roles))return false;
        if(!in_array($object,$valid_objects))return false;
        if(!in_array($published,$valid_status)) {
            if($o && $o->published=='') {
                $published='Unpublished';
            } else if($o) {
                $published='Published';
            }
        }
        $ckey = $role.$object.$published;
        if($o) {
            $ckey .= ':'.$o->id;
        }
        if(isset(self::$permissions[$ckey])){
            return self::$permissions[$ckey];
        }

        $order=array("{$role}{$object}{$published}", "{$role}{$object}", "{$role}{$published}", "{$role}", 'all');
        $ep=array();

        if($o) {
            $entry = $o;

            $q = false;

            // cache query results per entry
            $recurse=3;
            while($recurse && $entry) {
                $id=(is_object($entry))?($entry->id):($entry);
                $permissions = tdzPermission::find(array('entry'=>$id));
                if($permissions && count($permissions) > 0) {
                    foreach($permissions as $k=>$v) {
                        $rk=array_search($v['role'], $order);
                        if($rk!==false && !isset($ep[$rk])) {
                            $ep[$rk]=$v['credentials'];
                        }
                    }
                    if(isset($ep[0])) break;
                }
                if(!$recurse--)break;
                break;// not implemented
                $entry = $o->getParent();
            }
        }
        if(!isset($ep[0])) {
            $d=self::$app->studio['permissions'];
            foreach($d as $r=>$c) {
                $rk=array_search($r, $order);
                if($rk!==false && !isset($ep[$rk])) {
                    $ep[$rk]=$c;
                }
            }
        }
        $c=false;
        ksort($ep);
        foreach($ep as $r=>$c) break;
        if(!is_array($c)) {
            $c = preg_split('/[\s\n]+/', $c, null, PREG_SPLIT_NO_EMPTY);
        }
        $cs=implode('',$c);
        $result=false;
        if($cs=='*') {
            $result=true;
        } else if($cs=='') {
            $result=false;
        } else {
            $result=$c;
        }

        self::$permissions[$ckey] = $credentials=$result;
        return $credentials;
    }
    /**
     * Find current template file location, or false if none are found, accepts multiple arguments, processed in order.
     * example: $template = Tecnodesign_App_Studio::templateFile($mytemplate, 'tdz_entry');
     */
    public static function templateFile($tpl)
    {
        $tpld = self::$app->tecnodesign['templates-dir'];
        $apps = self::$app->tecnodesign['apps-dir'];
        $template=false;
        foreach(func_get_args() as $tpl) {
            if($tpl && ((substr($tpl, 0, strlen($apps))==$apps && file_exists($tplf=$tpl.'.php')) || file_exists($tplf=$tpld.'/'.$tpl.'.php') || file_exists($tplf=TDZ_ROOT.'/src/Tecnodesign/App/Studio/Resources/templates/'.$tpl.'.php'))) {
                $template = $tplf;
                break;
            }
        }
        return $template;
    }

    public static function updateVersion($o, $action, $conn)
    {
        $cn=get_class($o);
        $vt=$cn::$schema['tableName'];
        if(!$vt || substr($vt, -8)!='_version') return true;
        $t=str_replace('_version', '', $vt);

        $c=array_keys($cn::$schema['columns']);
        $sql = 'select * from '.$t.' where id='.$o->id;
        tdz::setConnection('', $conn);
        $r=tdz::query($sql);
        if($r && count($r)>0) {
            $sql = "update {$t}, {$vt} set ";
            foreach($c as $fn) {
                $sql .= " {$t}.{$fn}={$vt}.{$fn},";
            }
            $sql = substr($sql, 0, strlen($sql)-1).' ';
            $sql .= " where {$t}.id={$vt}.id and {$vt}.version='{$o->version}'";
        } else {
            $sql = "insert into "
                . $t
                . '('.implode(',', $c).') '
                . 'select '.implode(',', $c).' from '.$vt.' where id='.$o->id.' and version='.$o->version;
        }
        return true;
        $conn->exec($sql);
    }

    public static function getRelation($o, $rn, $order='')
    {
        $cn = get_class($o);
        $rel = $cn::$schema['relations'][$rn];
        $rcn=(isset($rel['class']))?($rel['class']):($rn);
        if(class_exists('tdz'.$rn)) $rcn='tdz'.$rn;
        $rtn = $rcn::$schema['tableName'];
        $rschema = $rcn::$schema;
        $order = ($order)?(' order by '.$order):('');
        $q = "select c.* from {$rtn} as c"
            . " inner join (select c.id, max(c.version) as version from {$rtn} as c group by c.id order by c.version desc) as q"
            . " on q.id=c.id and q.version=c.version and c.expired is null and c.{$rel['foreign']}='{$o[$rel['local']]}'".$order;
        $c = new Tecnodesign_Collection(null, $rcn, $q);
        $c->setQuery(false);
        return $c;
    }

}