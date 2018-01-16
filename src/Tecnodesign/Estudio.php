<?php
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
class Tecnodesign_Estudio
{
    /**
     * These are the basic configuration settings. Update them by creating a 
     * configuration file named: config/autoload.Tecnodesign_Estudio.ini
     * (only if necessary)
     */
    public static 
        $app,               // updated at runtime, this is the main application alias, used internally (also by other classes)
        $private,           // updated at runtime, indicates when a cache-control: private,nocache should be sent
        $page,              // updated at runtime, actual entry id rendered
        $automatedInstall=false,
        $params=array(),    // updated at runtime, general params
        $cacheTimeout=1800, // configurable, cache timeout, use false to disable caching, 0 means forever
        $staticCache=3600,  // configurable, store static previews of entries
        $home='/_estudio',  // configurable, where to load E-Studio interface
        $uid='/_me',        // configurable, where to load a json to check if a user is authenticated
        $uploadDir='estudio/uploads', // configurable, relative to TDZ_VAR
        $response=array(    // configurable, this will be added to the App response (passed to the template)
            'script'=>array('/_estudio/z.js'), // make sure this is .min.js in production environments
            //'style'=>array(),
        ),
        $languages=array();
    const VERSION = 1.1; 

    /**
     * This is a Tecnodesign_App, the constructor is loaded once and then cached (until configuration changes)
     *
     * Should anything else be cached?
     */
    public function __construct($config, $env='prod')
    {
        // this needs to be better, switching to command line
        if(self::$automatedInstall) {
            $version = (float) tdz::getApp()->estudio['version'];
            if($version<self::VERSION) {
                if($version<self::VERSION) {
                    Tecnodesign_Estudio_Install::upgrade($version);
                    tdz::save($if, Tecnodesign_Yaml::dump(array('id'=>'Tecnodesign_Estudio','version'=>self::VERSION)));
                }
                Tecnodesign_Cache::set('e-studio', self::VERSION);
            }
        }
    }

    /**
     * General purpose action, will trigger the relevant action based on the URL called
     *
     * @TODO: render intelligent responses at command line
     */
    public static function run()
    {
        self::$app = tdz::getApp();
        $req = self::$app->request();
        if($req['shell']) {
            tdz::scriptName($req['script-name']);
            self::$cacheTimeout = false;
            self::$staticCache  = false;
            chdir(TDZ_APP_ROOT);
        }
        $sn = $req['script-name'];

        if(isset(self::$app->tecnodesign['languages'])) tdz::$lang=self::language(self::$app->tecnodesign['languages']);

        if(isset($_GET['!rev'])) {
            self::$params['!rev'] = $_GET['!rev'];
            self::$staticCache = 0;
        } else if(self::$cacheTimeout && self::$staticCache) {
            self::getStaticCache();
        }
        tdz::$translator = 'Tecnodesign_Estudio::translate';

        // try the interface
        if($sn==self::$home || strncmp($sn, self::$home, strlen(self::$home))===0) {
            if($sn!=self::$home) tdz::scriptName($sn);
            return self::_runInterface();
        } else if(isset($_SERVER['HTTP_TDZ_SLOTS']) || $sn==self::$uid) {
            tdz::cacheControl('private,nocache', 0);
            tdz::output(json_encode(self::uid()), 'json');
        } else if($E=self::page($sn)) {
            $E->render();
            unset($E);
            return true;
        } else if(tdzAsset::run($sn)) {
        } else if($sn=='estudio') {
            Tecnodesign_Estudio_Task::run(self::$app->request('argv'));
        } else {
            self::error(404);
        }
        unset($sn);
        return false;
    }

    /**
     * Sets user language according to browser preferences
     */
    public function language($l=array())
    {
        if(!is_array($l)) {
            $lang = $l;
        } else if(count($l)<2) {
            $lang = $l[0];
        } else {
            $req = self::$app->request();
            if(substr($req['query-string'],0,1)=='!' && in_array($lang=substr($req['query-string'],1), $l)) {
                setcookie('lang',$lang,0,'/',false,false);
                tdz::redirect($req['script-name']);
            }
            unset($lang);
            if(!(isset($_COOKIE['lang']) && ($lang=$_COOKIE['lang']) && (in_array($lang, $l) || (strlen($lang)>2 && in_array($lang=substr($lang,0,2), $l))))) {
                unset($lang);
            }
            if (!isset($lang) && isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
                $accept = preg_split('/(;q=[0-9\.]+|\,)\s*/', $_SERVER['HTTP_ACCEPT_LANGUAGE'], null, PREG_SPLIT_NO_EMPTY);
                foreach ($accept as $lang) {
                    if (in_array($lang, $l) || (strlen($lang)>2 && in_array($lang=substr($lang,0,2), $l))) {
                        break;
                    }
                    unset($lang);
                }
                unset($accept);
            }
        }
        if(!isset($lang)) {
            $lang = tdz::$lang;
        }
        return $lang;
    }

    private static function _runInterface()
    {
        $url = substr(tdz::scriptName(), strlen(self::$home));
        if(strpos($url, '.')!==false) {
            if(substr($url, 0, 1)=='.') $url = '/estudio'.$url;
            else $url = '/'.$url;
            if(!tdzAsset::run($url, TDZ_ROOT.'/src/Tecnodesign/Resources/assets', true)) {
                self::error(404);
            }
        } else {
            if(!($U=tdz::getUser()) || !$U->isAuthenticated() || !($c=self::credential(array('estudio','edit','previewUnpublished'))) || !$U->hasCredential($c, false)) {
                return self::error(403);
            }
            tdz::scriptName(self::$home);
            tdz::$translator = 'Tecnodesign_Estudio::translate';
            Tecnodesign_App::response('layout', 'layout');
            Tecnodesign_App::response('script', array(self::$home.'/z.js'));
            Tecnodesign_App::response('style', array(self::$home.'/ui.min.css'));
            return Tecnodesign_Estudio_Interface::run();
        }

        self::error(404);
    }

    public static function setStaticCache()
    {
        $r = array(
            'c'=>Tecnodesign_App::response('cache-control'),
            'h'=>Tecnodesign_App::response('headers'),
            'r'=>Tecnodesign_App::$result,
        );
        Tecnodesign_Cache::set('estudio/cache/'.md5(tdz::scriptName()).'.'.tdz::$lang, $r, self::$cacheTimeout);
        unset($r);
    }

    public static function setStaticCacheDownload($f, $format=null)
    {
        $r = array(
            'c'=>Tecnodesign_App::response('cache-control'),
            'h'=>Tecnodesign_App::response('headers'),
            'f'=>$f,
        );
        if($format) $r['h']['Content-Type'] = $format;
        Tecnodesign_Cache::set('estudio/cache/'.md5(tdz::scriptName()), $r, self::$cacheTimeout);
        unset($r);
    }

    public static function getStaticCache()
    {
        $ckey = 'estudio/cache/'.md5(tdz::scriptName()).'.'.tdz::$lang;
        $r = Tecnodesign_Cache::get($ckey, self::$cacheTimeout);
        if(!$r) {
            $ckey = 'estudio/cache/'.md5(tdz::scriptName());
            $r = Tecnodesign_Cache::get($ckey, self::$cacheTimeout);
        }

        if($r && is_array($r)) {
            if(!isset($r['h']['Content-Type'])) $r['h']['Content-Type']='text/html;charset=UTF8';
            if(is_array($r['c'])) {
                Tecnodesign_Cache::delete($ckey);
                return false;
            }
            tdz::cacheControl($r['c'], self::$cacheTimeout);
            if(isset($r['f'])) {
                tdz::download($r['f'], $r['h']['Content-Type']);
            } else {
                tdz::output($r['r'], $r['h']['Content-Type'], false);
            }
            self::$app->end();
            return true;
        }
        unset($r);
    }

    /**
     * Authenticated user (got by tdz::getUser()) basic information
     * This response should not be cached by the server.
     */
    public static function uid()
    {
        self::$private=true;
        $U = tdz::getUser();
        if($U) {
            $r = $U->asArray();
            $ec= self::credential('estudio');
            if($ec && $U->isAuthenticated() && $U->hasCredential($ec, false)) {
                $r['plugins']=array(
                    'estudio'=>array(self::$home.'.min.js',self::$home.'.min.css'),
                );
            }
        } else {
            $r = array();
        }

        return $r;
    }

    /**
     * URL mapping to entries
     * 
     * Searches the database for entries that correspond to the given address. 
     * Allows multiviews, just like Apache behavior, on html pages with scripting enabled
     * 
     * @param  string $url          Address to be searched
     * @param  bool   $exact        If multiviews should be allowed
     * @param  bool   $published    If only published pages should be retrieved
     * @return        false || trdzEntry
     */
    public static function page($url, $exact=false, $published=null)
    {
        $url = tdz::validUrl($url);
        //$url = preg_replace('/\/(\.)*\/+/','/',$url);
        //$url = preg_replace('/\/\/+/','/',$url);
        if ($url=='') {
            return false;
        }
        $f=array(
            'link'=>$url,
            'type'=>tdzEntry::$previewEntryType,
            'expired'=>'',
        );
        static $scope = array('id','title','link','source','master','format','updated','published','version');
        self::$private = false;
        self::addResponse(self::$response);
        if(is_null($published)) {
            // get information from user credentials
            if(($U=tdz::getUser()) && $U->hasCredential(self::credential('previewUnpublished'), false)) {
                $published = false;
                self::$private = true;
                // replace tdzEntry by tdzEntryVersion and probe for latest version (?)
                if(isset(self::$params['!rev'])) {
                    $f['version'] = self::$params['!rev'];
                    if(substr(tdzEntry::$schema['table_name'], -8)!='_version') tdzEntry::$schema['table_name'] .= '_version';
                    if(substr(tdzContent::$schema['table_name'], -8)!='_version') tdzContent::$schema['table_name'] .= '_version';
                    self::$cacheTimeout = false;
                }
            } else {
                $published = true;
            }
        }
        /*
        if(self::$cacheTimeout && ($E=Tecnodesign_Cache::get('e-url'.$url, self::$cacheTimeout))) {
            if($published && (!$E->published || strtotime($E->published)>time())) {
                unset($E);
            } else {
                return $E;
            }
        }
        */

        if($published) {
            $f['published<'] = date('Y-m-d\TH:i:s');
        }
        if($E=tdzEntry::find($f,1,$scope,false,array('type'=>'desc','version'=>'desc'))) {
            unset($f, $published);
            return $E;
        }
        if(!$E && ($E=tdzEntry::findPage($url))) {
            unset($f, $published);
        }
        if(!$E && !$exact && $url[0]=='/' && strlen($url)>1) {
            $f['Content.content_type']=tdzContent::$multiviewContentType;
            while(isset($url[1])) {
                $url = preg_replace('#/[^/]+$#', '', $url);
                $f['link'] = $url;
                if($E=tdzEntry::find($f,1,$scope,false,array('type'=>'desc'))) {
                    unset($f, $published);
                    break;
                } else if($url && !$E && ($E=tdzEntry::findPage($url, true))) {
                    unset($f, $published);
                    break;
                }
            }
        }
        if($E) {
            //if(self::$cacheTimeout) Tecnodesign_Cache::set('e-url'.$url, $E, self::$cacheTimeout);
            return $E;
        }
        unset($f, $url, $published, $E);
        return false;
    }

    public static function error($code=500)
    {
        if(isset(tdz::$variables['route']['layout'])  && tdz::$variables['route']['layout']) {
            $layout = tdz::$variables['route']['layout'];
        } else {
            $layout = self::templateFile(tdzEntry::$layout, 'layout');
        }
        return Tecnodesign_Estudio::$app->runError($code, $layout);
    }

    /**
     * These credentials should be set at the tdzPermission table, without entries
     * To make them eligible as default values. The keypairs are:
     *   tdzPermission->role: tdzPermission->credentials (csv)
     *
     * basic privilege: is overriden by role/object-specific calls
     *   all: ~
     *
     * who is eligible to view the website. This directive is overriden by the next two directives
     *   preview: *
     *
     * only authorized users may preview unpublished content (example)
     *   previewUnpublished: Administrator,Developer,Editor,Author
     *
     * everyone may view published content
     *   previewPublished: *
     *
     * create new pages:
     *   new: Developer,Editor,Author
     *
     * only developers may add the "php" Content type. Note that the most specific
     * credential will be used. The cascading in this case would be:
     * newContentTypePhp > newContent(un)Published > newContent > newEntry(un)Published > newEntry > new(un)Published > new > all
     *   newContentTypePhp: Developer
     *
     * Template are content slots without any associated Entry
     *   newTemplate: Developer
     *
     * only admins may add new credentials
     *   newPermission: ~
     *
     * update content:
     *   edit: Administrator,Developer,Editor,Author
     *
     * Template are content slots without any associated Entry
     *   editTemplate: Developer
     *   editContentTypePhp: Developer
     *
     * remove contents/pages:
     *   delete: Administrator,Developer,Editor
     *
     * public content/pages:
     *   publish: Administrator,Editor
     * 
     * Authors may only publish content (and not the entry)
     *   publishContent: Administrator,Editor,Author
     *
     * only editors may unpublish one entry/content
     *   publishEntryPublished: Administrator,Editor
     *   publishContentPublished: Administrator,Editor
     *
     * CMS UI credentials
     *   search: Administrator,Developer,Editor,Author
     */
    private static $credentials;
    public static function credential($s)
    {
        if(is_null(self::$credentials)) {
            if(self::$cacheTimeout) self::$credentials = Tecnodesign_Cache::get('estudio/credentials', self::$cacheTimeout);
            if(!self::$credentials || !is_array(self::$credentials)) {
                self::$credentials=array();
                $ps = tdzPermission::find(array('entry'=>''),0,array('role','credentials'),false,array('updated'=>'desc'));
                if($ps) {
                    foreach($ps as $i=>$P) {
                        if(isset(self::$credentials[$P->role])) continue;
                        if(!$P->credentials || $P->credentials=='~') $c=false;
                        else if($P->credentials=='*') $c=true;
                        else $c = preg_split('/[\s\,\;]+/', $P->credentials, null, PREG_SPLIT_NO_EMPTY);
                        self::$credentials[$P->role] = $c;
                        unset($ps[$i], $P, $c);
                    }
                }
                if(self::$cacheTimeout) Tecnodesign_Cache::set('estudio/credentials', self::$credentials, self::$cacheTimeout);
            }
        }
        if(!self::$credentials) return null;
        if(is_array($s)) {
            foreach($s as $q) {
                $r = self::credential($q);
                if(!is_null($r)) return $r;
                unset($q);
            }
            return null;
        }
        if(isset(self::$credentials[$s])) {
            return self::$credentials[$s];
        }
        if(preg_match('/(.*[a-z])[A-Z][^A-Z]*$/', $s, $m)) {
            $s = $m[1];
            unset($m);
        } else {
            return null;
        }

        if($s) return self::credential($s);
        else return null;
    }

    public static function config($p)
    {
        $cn=null;
        if(substr($p, 0, 5)=='entry'){
            $cn = 'tdzEntry';
            $p = substr($p, 5);
            if(!property_exists($cn, $p)) $cn=null;
        }
        if($cn) {
            // translate
            return $cn::$p;
        }
        return false;
    }

    /**
     * Find current template file location, or false if none are found, accepts multiple arguments, processed in order.
     * example: $template = Tecnodesign_App_Estudio::templateFile($mytemplate, 'tdz_entry');
     */
    public static function templateFile($tpl)
    {
        $tpld = self::$app->tecnodesign['templates-dir'];
        $apps = self::$app->tecnodesign['apps-dir'];
        $template=false;
        foreach(func_get_args() as $tpl) {
            if($tpl && ((substr($tpl, 0, strlen($apps))==$apps && file_exists($tplf=$tpl.'.php')) || file_exists($tplf=$tpld.'/'.$tpl.'.php') || file_exists($tplf=TDZ_ROOT.'/src/Tecnodesign/Estudio/Resources/templates/'.$tpl.'.php'))) {
                $template = $tplf;
                break;
            }
        }
        return $template;
    }

    protected static $t;
    public static function translate($s, $table=null, $to=null, $from=null)
    {
        if($to && $to==$from) return $s;
        else return self::t($s, null, $table); 
    }

    public static function t($s, $alt=null, $table='lang')
    {
        if(!$table) $table = 'lang';
        if(is_null(self::$t)) {
            if(!self::$t)self::$t=array();
        }
        if(!isset(self::$t[$table])) {
            $lang = substr(tdz::$lang, 0, 2);
            $ckey = 'estudio/lang/'.$table.'.'.$lang;
            self::$t[$table] = (self::$cacheTimeout)?(Tecnodesign_Cache::get($ckey, self::$cacheTimeout)):(array());
            if(!self::$t[$table]) {
                if(file_exists($f=self::$app->tecnodesign['data-dir'].'/'.$ckey.'.yml')) {
                    self::$t[$table] = Tecnodesign_Yaml::load($f);
                }
                if(file_exists($f=TDZ_ROOT.'/src/Tecnodesign/Estudio/Resources/lang/'.$table.'.'.$lang.'.yml') || file_exists($f=TDZ_ROOT.'/src/Tecnodesign/Estudio/Resources/lang/'.$table.'.en.yml')) {
                    if(!self::$t[$table]) self::$t[$table] = array();
                    self::$t[$table] += Tecnodesign_Yaml::load($f);
                }
                if(self::$cacheTimeout) Tecnodesign_Cache::set($ckey, self::$t[$table], self::$cacheTimeout);
            }
            unset($lang, $ckey);
        }
        if(isset(self::$t[$table][$s])) {
            return self::$t[$table][$s];
        } else if($alt) {
            tdz::log('[Translate] no record for '.$s.' in '.$table.'.'.substr(tdz::$lang, 0, 2));
            return $alt;
        } else {
            //tdz::log('[Translate] no record or alternative for '.$table.'.'.substr(tdz::$lang, 0, 2).'.yml: '.$s.': "'.$s.'"');
            return Tecnodesign_Translate::message($s, $table);
        }
    }


    public static function li($list)
    {
        $s = '';
        if($list instanceof Tecnodesign_Collection) $list = $list->getItems();
        if($list && count($list)>0) {
            foreach($list as $e) {
                $c = ($e->id==self::$page)?(' class="current"'):('');
                $s .= '<li'.$c.'>'
                    . (($e['link'])?('<a'.$c.' href="'.tdz::xmlEscape($e['link']).'">'.tdz::xmlEscape($e['title']).'</a>'):(tdz::xmlEscape($e['title'])))
                    .  (($e instanceof tdzEntry)?(self::li($e->getChildren())):(''))
                    . '</li>';
            }
            if($s) {
                $s = '<ul>'.$s.'</ul>';
            }
        }
        return $s;
    }


    public static function addResponse($a)
    {
        static $toAdd=array('script','style','headers','variables');
        foreach($toAdd as $k) {
            if(isset($a[$k])) {
                if(!isset(tdz::$variables[$k])) {
                    tdz::$variables[$k] = (!is_array($a[$k]))?(array($a[$k])):($a[$k]);
                } else if(!is_array($a[$k])) {
                    if(!in_array($a[$k], tdz::$variables[$k])) tdz::$variables[$k][]=$a[$k];
                } else {
                    tdz::$variables[$k] = array_merge(tdz::$variables[$k], $a[$k]);
                }
                unset($a[$k], $k);
            }
        }
        if(!isset(tdz::$variables['variables'])) tdz::$variables['variables']=array();
        tdz::$variables['variables'] += $a;
    }
}

if(!class_exists('tdzEntry')) {
    if(!in_array($libdir = dirname(__FILE__).'/Estudio/Resources/model', tdz::$lib)) tdz::$lib[]=$libdir;
    unset($libdir);
}

if(!defined('TDZ_ESTUDIO')) define('TDZ_ESTUDIO', Tecnodesign_Estudio::VERSION);
