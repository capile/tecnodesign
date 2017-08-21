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
class Tecnodesign_Studio
{
    /**
     * These are the basic configuration settings. Update them by creating a 
     * configuration file named: config/autoload.Tecnodesign_Studio.ini
     * (only if necessary)
     */
    public static 
        $app,               // updated at runtime, this is the main application alias, used internally (also by other classes)
        $webInterface=true,
        $checkOrigin=true,  // prevents sending user details to external origins, use 2 to prevent even to unknown origins
        $private=array(),   // updated at runtime, indicates when a cache-control: private,nocache should be sent
        $page,              // updated at runtime, actual entry id rendered
        $connection,        // connection to use, set to false to disable database
        $params=array(),    // updated at runtime, general params
        $cacheTimeout=1800, // configurable, cache timeout, use false to disable caching, 0 means forever
        $staticCache=3600,  // configurable, store static previews of entries
        $home='/_/studio',  // configurable, where to load E-Studio interface
        $uid='/_me',        // configurable, where to load a json to check if a user is authenticated
        $uploadDir='studio/uploads', // configurable, relative to TDZ_VAR
        $index='studio/index.db', // configurable, relative to TDZ_VAR
        $response=array(    // configurable, this will be added to the App response (passed to the template)
            'script'=>array(),// make sure this is .min.js in production environments
            'style'=>array(),
        ),
        $templateRoot,
        $languages=array(),
        $ignore=array('.meta', '.less', '.md', '.yml'),
        $indexIgnore=array('js', 'css', 'font', 'json', 'studio');
    const VERSION = 1.1; 

    /**
     * This is a Tecnodesign_App, the constructor is loaded once and then cached (until configuration changes)
     *
     * Should anything else be cached?
     */
    public function __construct($config, $env='prod')
    {
        // this needs to be better, switching to command line
        $version = (float) tdz::getApp()->studio['version'];
        if($version<self::VERSION) {
            if($version<self::VERSION) {
                Tecnodesign_Studio_Install::upgrade($version);
                tdz::save($if, Tecnodesign_Yaml::dump(array('id'=>'Tecnodesign_Studio','version'=>self::VERSION)));
            }
            Tecnodesign_Cache::set('e-studio', self::VERSION);
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

        if(is_null(self::$connection)) {
            if(isset(self::$app->studio['connection'])) {
                self::$connection = self::$app->studio['connection'];
            }
        }
        if(isset($_GET['!rev'])) {
            self::$params['!rev'] = $_GET['!rev'];
            self::$staticCache = 0;
        } else if(self::$cacheTimeout && self::$staticCache) {
            self::getStaticCache();
        }
        tdz::$translator = 'Tecnodesign_Studio::translate';

        // try the interface
        if($sn==self::$home || strncmp($sn, self::$home, strlen(self::$home))===0) {
            if($sn!=self::$home) tdz::scriptName($sn);
            return self::_runInterface();
        } else if(isset($_SERVER['HTTP_TDZ_SLOTS']) || $sn==self::$uid) {
            tdz::cacheControl('private', static::$cacheTimeout);
            tdz::output(json_encode(self::uid()), 'json');
        } else if(self::ignore($sn)) {
            self::error(404);
        } else if($E=self::page($sn)) {
            $E->render();
            unset($E);
            return true;
        //} else if(tdzAsset::run($sn)) {
        } else if(TDZ_CLI && $sn=='studio') {
            Tecnodesign_Studio_Task::run(self::$app->request('argv'));
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
            if(substr($url, 0, 1)=='.') $url = '/studio'.$url;
            else if(substr($url, 0, 1)!='/') $url = '/'.$url;
            if(!tdzAsset::run($url, TDZ_ROOT.'/src/Tecnodesign/Resources/assets', true) 
                && !tdzAsset::run($url, TDZ_ROOT.'/src/Tecnodesign/App/Resources/assets', true)
                && (substr($url, -4) == '.css' || substr($url, -3) == '.js') 
                && !tdzAsset::run($url, TDZ_VAR.'/cache/minify', true)) {
                self::error(404);
            }
        } else {
            if(!($U=tdz::getUser()) || !$U->isAuthenticated() || !($U->isSuperAdmin() || ($c=self::credential(array('studio','edit','previewUnpublished'))) && $U->hasCredential($c, false))) {
                return self::error(403);
            }

            if(isset($_SERVER['HTTP_TDZ_ACTION']) && $_SERVER['HTTP_TDZ_ACTION']=='Interface') {
                tdz::scriptName(self::$home);
                tdz::$translator = 'Tecnodesign_Studio::translate';
                Tecnodesign_App::response('layout', 'layout');
                tdz::$variables['document-root'] = dirname(__FILE__).'/Resources/assets';
                Tecnodesign_App::response('script', array('/z.js','/studio.js','/interface.js'));
                Tecnodesign_App::response('style', array('/studio.less'));
                return Tecnodesign_Studio_Interface::run();
            } else if($url=='/p') {
                return self::listProperties();
            } else {
                tdz::scriptName(self::$home);
                tdz::$translator = 'Tecnodesign_Studio::translate';
                Tecnodesign_App::response('layout', 'layout');
                tdz::$variables['document-root'] = dirname(__FILE__).'/Resources/assets';
                tdz::$assetsUrl = self::$home;
                Tecnodesign_App::response('script', array('/z.js','/studio.js','/interface.js'));
                Tecnodesign_App::response('style', array('/studio.less'));
                return Tecnodesign_Studio_Interface::run();
            }
        }

        self::error(404);
    }

    public static function listProperties()
    {
        $R=array();
        $U=tdz::getUser();
        if(!$U || !$U->isAuthenticated()) return false;
        // current properties
        $C = array(
            'new'=>($U->isSuperAdmin() || ($c=self::credential('new')) && $U->hasCredential($c, false)),
            'newTemplate'=>($U->isSuperAdmin() || ($c=self::credential('newTemplate')) && $U->hasCredential($c, false)),
            'edit'=>($U->isSuperAdmin() || ($c=self::credential('edit')) && $U->hasCredential($c, false)),
            'editTemplate'=>($U->isSuperAdmin() || ($c=self::credential('editTemplate')) && $U->hasCredential($c, false)),
            'publish'=>($U->isSuperAdmin() || ($c=self::credential('publish')) && $U->hasCredential($c, false)),
            'delete'=>($U->isSuperAdmin() || ($c=self::credential('delete')) && $U->hasCredential($c, false)),
        );
        //     *   editContentTypePhp: Developer

        if($C['edit'] && ($d = Tecnodesign_App::request('post', 'c'))) {
            foreach($d as $i=>$id) {
                $p = 'edit';
                $o = tdzContent::find($id,1,array('content_type'));
                if($o && $o->pageFile && substr(basename($o->pageFile), 0, 6)=='_tpl_.' && !$C['editTemplate']) $o=null;
                if($o && !isset($C[$p='editContentType'.ucfirst($o->content_type)])) {
                    $C[$p] = ($U->isSuperAdmin() || ($c=self::credential($p)) && $U->hasCredential($c, false));
                    unset($c);
                }
                if(!$C[$p]) $o = null;
                unset($p);
                $k = 'c/'.$id;
                if(!$o) {
                    $R[$k] = null;
                    unset($d[$i], $id, $k, $o);
                    continue;
                }
                $R[$k] = array('update'=>true, 'delete'=>$C['delete']);
                if($C['publish'] && !$o->pageFile) {
                    if($o->published) {
                        $R[$k]['unpublish'] = true;
                    } else {
                        $R[$k]['publish'] = true;
                    }
                }
                $R[$k]['type'] = $o->content_type;
                if($o->pageFile && substr($o->pageFile, 0, strlen(tdzEntry::$pageDir))==tdzEntry::$pageDir) $R[$k]['id'] = substr($o->pageFile, strlen(tdzEntry::$pageDir));
                unset($d[$i], $id, $k, $o);
            }
        }
        if($C['new'] && ($d = Tecnodesign_App::request('post', 's'))) {
            foreach($d as $i=>$id) {
                $k = 's/'.$id;
                $R[$k] = array('new'=>true,'id'=>$id);
                unset($d[$i], $id, $k, $o);
            }
        }
        tdz::output($R, 'json', false);
        self::$app->end();
        return true;
    }

    public static function content($page, $checkLang=true, $checkTemplates=true)
    {
        static $langs;
        if(!file_exists($page)) return;
        $slotname = tdzEntry::$slot;
        $pos = '00000';
        $pn = basename($page);
        //if(substr($pn, 0, strlen($link)+1)==$link.'.') $pn = substr($pn, strlen($link)+1);
        $pp = explode('.', $pn);
        $tpl = ($pp[0]=='_tpl_');
        if($tpl && !$checkTemplates) return false;
        array_shift($pp);
        $ext = strtolower(array_pop($pp));
        if(count($pp)>0) {
            $lang = array_pop($pp);
            if(is_null($langs)) {
                if(isset(self::$app->tecnodesign['languages'])) {
                    $langs = self::$app->tecnodesign['languages'];
                }
                unset($app);
                if(!$langs) $langs=array();
            }
            if(!in_array($lang, $langs)) {
                $pp[]=$lang;
            } else {
                if(!in_array($lang, Tecnodesign_Studio::$languages)) Tecnodesign_Studio::$languages[array_search($lang, $langs)]=$lang;
                if($checkLang && $lang!=tdz::$lang) return false;
            }
            unset($lang);
        }
        if(count($pp)>0) {
            $tmp = array_shift($pp);
            if(preg_match('/^[^a-z]/i', $tmp)) {
                $pos = $tmp;
            } else {
                $slotname = $tmp;
                if(count($pp)>0) {
                    $pos = array_shift($pp);
                }
            }
            unset($tmp);
        }
        if(!isset(tdzContent::$contentType[$ext])) return false;
        else if(is_array(tdzContent::$disableExtensions) && in_array($ext, tdzContent::$disableExtensions)) continue;
        $p = file_get_contents($page);
        if(!$p) return false;
        $m = tdzEntry::meta($p);
        if($m) {
            $meta = Tecnodesign_Yaml::load($m);
            if(isset($meta['credential'])) {
                if(!($U=tdz::getUser()) || !$U->hasCredential($meta['credential'], false)) {
                    return false;
                }
                $c = (!is_array($meta['credential']))?(array($meta['credential'])):($meta['credential']);
                if(!is_array(Tecnodesign_Studio::$private)) Tecnodesign_Studio::$private = $c;
                else Tecnodesign_Studio::$private = array_merge($c, Tecnodesign_Studio::$private);
                unset($U);
            }
        }
        $id = substr($page, strlen(TDZ_VAR)+1);
        $C = new tdzContent(array(
            'id'=>tdz::hash($id, null, 'uuid'),
            'slot'=>$slotname,
            'content'=>$p,
            'content_type'=>$ext,
            //'position'=>$id,
            'modified'=>filemtime($page),
            //'_position'=>$pos,
        ));
        $C->pageFile = $id;
        if(isset($meta['attributes']) && is_array($meta['attributes'])) {
            $C->attributes = $meta['attributes'];
            unset($meta['attributes']);
        }
        if(isset($meta) && $meta) {
            $C->_meta = $meta;
            if(isset($meta['credential'])) unset($meta['cdredential']);
        }
        if(!is_null($pos)) $C->_position = $slotname.$pos;
        if(isset($meta)) {
            Tecnodesign_Studio::addResponse($meta);
            foreach(tdzContent::$schema['columns'] as $fn=>$fd) {
                if(isset($meta[$fn])) $C->$fn = $meta[$fn];
                unset($fd, $fn);
            }
            unset($meta);
        }
        return $C;
    }

    public static function setStaticCache()
    {
        $r = array(
            'c'=>Tecnodesign_App::response('cache-control'),
            'h'=>Tecnodesign_App::response('headers'),
            'r'=>Tecnodesign_App::$result,
        );
        Tecnodesign_Cache::set('studio/cache/'.md5(tdz::scriptName()).'.'.tdz::$lang, $r, self::$cacheTimeout);
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
        Tecnodesign_Cache::set('studio/cache/'.md5(tdz::scriptName()), $r, self::$cacheTimeout);
        unset($r);
    }

    public static function getStaticCache()
    {
        $ckey = 'studio/cache/'.md5(tdz::scriptName()).'.'.tdz::$lang;
        $r = Tecnodesign_Cache::get($ckey, self::$cacheTimeout);
        if(!$r) {
            $ckey = 'studio/cache/'.md5(tdz::scriptName());
            $r = Tecnodesign_Cache::get($ckey, self::$cacheTimeout);
        }

        if($r && is_array($r)) {
            if(!isset($r['h']['Content-Type'])) $r['h']['Content-Type']='text/html;charset=UTF8';
            if(is_array($r['c'])) {
                Tecnodesign_Cache::delete($ckey);
                return false;
            }
            tdz::cacheControl($r['c'], self::$cacheTimeout);
            foreach($r['h'] as $k=>$v) {
                header($k.': '.$v);
                unset($k, $v);
            }
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
        if(Tecnodesign_Studio::$checkOrigin) {
            $allow = tdz::buildUrl('');
            $from = null;
            if(isset($_SERVER['HTTP_ORIGIN'])) $from = $_SERVER['HTTP_ORIGIN'];
            else if(isset($_SERVER['HTTP_REFERER'])) $from = $_SERVER['HTTP_REFERER'];
            else if(Tecnodesign_Studio::$checkOrigin>1) return false;
            if($from && substr($from, 0, strlen($allow))!=$allow) {
                return false; 
            }
        }

        $U = tdz::getUser();
        if($U) {
            $r = $U->asArray();
            if(self::$webInterface && (($U->isAuthenticated() && ($U->isSuperAdmin()) || (($ec= self::credential('studio')) && $U->hasCredential($ec, false))))) {
                $r['plugins']=array(
                    'studio'=>array(self::$home.'.min.js?interface',self::$home.'.min.css'),
                );
            }
            if($U->isAuthenticated() && ($cfg=Tecnodesign_Studio::$app->user)) {
                if(isset($cfg['export']) && is_array($cfg['export'])) {
                    foreach($cfg['export'] as $k=>$v) {
                        $r[$k] = $U->$v;
                        unset($cfg['export'][$k], $k, $v);
                    }
                }
                unset($cfg);
            }
        } else {
            $r = array();
        }

        return $r;
    }

    public static function ignore($url)
    {
        if(!is_array(self::$ignore)) return false;
        if(substr(basename($url), 0, 1)=='.') return true;
        if(preg_match('#//+|\:#', $url)) return true;
        foreach(self::$ignore as $p) {
            if(strpos($url, $p)!==false) return true;
            unset($p);
        }
        return false;
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
        //$url = preg_replace('#\.\.+#', '.', $url);
        //$url = preg_replace('#\.+/+#', '', $url);
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
        self::$private = array();
        self::addResponse(self::$response);
        if(is_null($published)) {
            // get information from user credentials
            if(self::$connection && ($U=tdz::getUser()) && $U->hasCredential($c=self::credential('previewUnpublished'), false)) {
                $published = false;
                self::$private = (is_array($c))?($c):(array($c));
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
        $E=null;
        if(self::$connection && $E=tdzEntry::find($f,1,$scope,false,array('type'=>'desc','version'=>'desc'))) {
            unset($f, $published);
            return $E;
        }
        if(!$E && ($E=tdzEntry::findPage($url, false, true))) {
            if(!self::$connection) self::checkIndex();
            unset($f, $published);
        }
        if(!$E && !$exact && substr($url, 0, 1)=='/' && strlen($url)>1) {
            $f['Content.content_type']=tdzContent::$multiviewContentType;
            $u = $url;
            while(strlen($u)>1) {
                $u = preg_replace('#/[^/]+$#', '', $u);
                $f['link'] = $u;
                if(self::$connection && ($E=tdzEntry::find($f,1,$scope,false,array('type'=>'desc')))) {
                    unset($f, $published);
                    break;
                } else if($u && ($E=tdzEntry::findPage($u, true))) {
                    unset($f, $published);
                    break;
                }
            }
            if($E) {
                //tdz::scriptName($u);
            }
        }
        if($E) {
            //if(self::$cacheTimeout) Tecnodesign_Cache::set('e-url'.$url, $E, self::$cacheTimeout);
            return $E;
        }
        unset($f, $url, $published, $E);
        return false;
    }

    public static function template($url=null)
    {
        $E = new tdzEntry(array('link'=>$url),false, false);
        $C = $E->getRelatedContent();
        unset($E);
        $tpl = array();
        if($C) {
            foreach($C as $i=>$o) {
                if(!isset($tpl[$o->slot]))$tpl[$o->slot]='';
                $r = $o->render();
                $tpl[$o->slot] .= $o->render();
                unset($C[$i], $o, $i);
            }
            $slotelements = array('header'=>true,'footer'=>true,'nav'=>true);
            foreach($tpl as $slotname=>$slot) {
                $tpl[$slotname] = "<div id=\"{$slotname}\">".tdz::get('before-'.$slotname).'<div>'.$slot.'</div>'.tdz::get($slotname).tdz::get('after-'.$slotname)."</div>";
                if(isset($slotelements[$slotname]) && $slotelements[$slotname]) {
                    $tpl[$slotname] = "<{$slotname}>{$tpl[$slotname]}</{$slotname}>";
                }
            }
            $tpl['slots'] = array_keys($tpl);
            tdz::$variables+=$tpl;
        }
        $d = TDZ_ROOT.'/src/Tecnodesign/Studio/Resources/templates/';
        if(is_null(tdz::$tplDir)) {
            tdz::$tplDir = array(Tecnodesign_Studio::$app->tecnodesign['templates-dir'], $d);
        } else  if(!in_array($d, tdz::$tplDir)) {
            tdz::$tplDir[] = $d;
        }
        unset($d);
        return $tpl;
    }

    public static function error($code=500)
    {
        if(!Tecnodesign_Studio::$app) Tecnodesign_Studio::$app = tdz::getApp();
        if(isset(tdz::$variables['route']['layout'])  && tdz::$variables['route']['layout']) {
            $layout = tdz::$variables['route']['layout'];
        } else {
            $layout = self::templateFile(tdzEntry::$layout, 'layout');
        }
        self::template('/error'.$code);
        return Tecnodesign_Studio::$app->runError($code, $layout);
    }

    public static function checkIndex()
    {
        static $index;
        if(is_null($index)) {
            $index = false;
            if(!file_exists($db=TDZ_VAR.'/'.self::$index) || filemtime($db)+360 < time()) {
                $index = true;
            }
            if($index) {
                /*
                Tecnodesign_App::afterRun(array(
                    'callback'=>array('Tecnodesign_Studio_Task', 'run'),
                    'arguments'=>array('sync'),
                ));
                */
            }
        }
    }

    public static function indexDb($db=null)
    {
        $cid = tdz::getApp()->studio['connection'];
        if(is_null($db)) $db=TDZ_VAR.'/'.self::$index;
        tdz::$database[$cid] = array('dsn'=>'sqlite:'.$db);
        tdz::$database = array('studio'=>array('dsn'=>'sqlite:'.$db))+tdz::$database;
        $conn = tdz::connect($cid);
        return tdz::$database['studio'];
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
            if(isset(self::$app->studio['credential'])) {
                self::$credentials = self::$app->studio['credential'];
            }
            if(!self::$credentials && self::$cacheTimeout) self::$credentials = Tecnodesign_Cache::get('studio/credentials', self::$cacheTimeout);
            if(!self::$credentials || !is_array(self::$credentials)) {
                self::$credentials=array();
                if(self::$connection) {
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
                }
                if(self::$cacheTimeout) Tecnodesign_Cache::set('studio/credentials', self::$credentials, self::$cacheTimeout);
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
     * example: $template = Tecnodesign_App_Studio::templateFile($mytemplate, 'tdz_entry');
     */
    public static function templateFile($tpl)
    {
        $tpld = self::$app->tecnodesign['templates-dir'];
        $apps = self::$app->tecnodesign['apps-dir'];
        $template=false;
        foreach(func_get_args() as $tpl) {
            if($tpl && ((substr($tpl, 0, strlen($apps))==$apps && file_exists($tplf=$tpl.'.php')) || file_exists($tplf=$tpld.'/'.$tpl.'.php') || file_exists($tplf=TDZ_ROOT.'/src/Tecnodesign/Studio/Resources/templates/'.$tpl.'.php'))) {
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
            $ckey = 'studio/lang/'.$table.'.'.$lang;
            self::$t[$table] = (self::$cacheTimeout)?(Tecnodesign_Cache::get($ckey, self::$cacheTimeout)):(array());
            if(!self::$t[$table]) {
                if(file_exists($f=self::$app->tecnodesign['data-dir'].'/'.$ckey.'.yml')) {
                    self::$t[$table] = Tecnodesign_Yaml::load($f);
                }
                if(file_exists($f=TDZ_ROOT.'/src/Tecnodesign/Studio/Resources/lang/'.$table.'.'.$lang.'.yml') || file_exists($f=TDZ_ROOT.'/src/Tecnodesign/Studio/Resources/lang/'.$table.'.en.yml')) {
                    if(!self::$t[$table]) self::$t[$table] = array();
                    $trans = Tecnodesign_Yaml::load($f);
                    if($trans)
                        self::$t[$table] += $trans;
                }
                if(self::$cacheTimeout) Tecnodesign_Cache::set($ckey, self::$t[$table], self::$cacheTimeout);
            }
            unset($lang, $ckey);
        }
        if(isset(self::$t[$table][$s])) {
            return self::$t[$table][$s];
        } else if($alt) {
            //tdz::log('[Translate] no record for '.$s.' in '.$table.'.'.substr(tdz::$lang, 0, 2));
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
    if(!in_array($libdir = dirname(__FILE__).'/Studio/Resources/model', tdz::$lib)) tdz::$lib[]=$libdir;
    unset($libdir);
}

if(!defined('TDZ_ESTUDIO')) define('TDZ_ESTUDIO', Tecnodesign_Studio::VERSION);
