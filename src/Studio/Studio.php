<?php
/**
 * Studio
 * 
 * Main application controller
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
 */
namespace Studio;

use Tecnodesign_App as App;
use Studio\Model\Entries as Entries;
use Studio\Model\Contents as Contents;
use Studio\Model\Permissions as Permissions;
use Studio\Model\Relations as Relations;
use Studio\Model\Index as Index;
use Tecnodesign_Studio_Asset as Asset;
use Tecnodesign_Collection as Collection;
use Tecnodesign_Form as Form;
use Tecnodesign_Translate as Translate;
use Tecnodesign_Query as Query;
use Tecnodesign_Yaml as Yaml;
use Studio as S;

class Studio
{
    /**
     * These are the basic configuration settings. Update them by creating a 
     * configuration file named: config/autoload.Studio_Studio.yml
     */
    public static 
        $app,               // updated at runtime, this is the main application alias, used internally (also by other classes)
        $automatedInstall,  // deprecated
        $internal,
        $webInterface       = true,
        $webButton,         // deprecated, use $webInterface instead
        $webInteractive,    // deprecated, use $webInterface instead
        $cliInterface=true, // enable command-line interface
        $resetInterfaceStyle=true,
        $resetInterfaceScript=true,
        $checkOrigin=1,     // prevents sending user details to external origins, use 2 to prevent even to unknown origins
        $allowOrigin=[],
        $private=[],        // updated at runtime, indicates when a cache-control: private,nocache should be sent
        $page,              // updated at runtime, actual entry id rendered
        $connection,        // connection to use, set to false to disable database
        $params=array(),    // updated at runtime, general params
        $cacheTimeout=false,// configurable, cache timeout
        $staticCache=false, // configurable, store static previews of entries
        $home='/_studio',   // configurable, where to load Studio interface
        $uid='/_me',        // configurable, where to load a json to check if a user is authenticated
        $uploadDir,         // deprecated, use S::uploadDir
        $index,             // custom database for indexes, set to null to disable indexing
        $indexTimeout=600,  // timeout to trigger new database indexing
        $response=array(    // configurable, this will be added to the App response (passed to the template)
        ),
        $assetsOptimizeUrl,
        $status,
        $templateRoot='web',
        $contentClassName,
        $languages=array(),
        $ignore=array('.meta', '.less', '.md', '.yml'),
        $indexIgnore=array('js', 'css', 'font', 'json', 'studio'),
        $allowedExtensions=array('.html'),
        $breadcrumbSeparator=' » ',
        $userMessage,
        $cli='studio',      // configurable, where to load Studio command-line interface
        $cliSkipScriptName=[],
        $interfaceClass='Studio\\Api',
        $interfaces = [
            'interfaces'=>'interfaces',
        ],
        $cliApps=[
            'start'=>['Studio\\Model\\Config','standaloneConfig'],
            'check'=>['Studio\\Model\\Index', 'checkConnection'],
            'index'=>['Studio\\Model\\Index','reindex'],
            'import'=>['Tecnodesign_Database','import'],
        ];
    const VERSION = 2.7;    // should match the development branch 

    /**
     * This is a App, the constructor is loaded once and then cached (until configuration changes)
     *
     * Should anything else be cached?
     */
    public function __construct($config, $env='prod')
    {
    }

    public static function documentRoot()
    {
        static $root;
        if(is_null($root)) {
            $root = preg_replace('#/+$#', '', S_VAR.'/'.Entries::$pageDir);
        }
        return $root;
    }

    public static function app()
    {
        if(!self::$app) {
            self::$app = S::getApp();
        }

        return self::$app;
    }

    /**
     * General purpose action, will trigger the relevant action based on the URL called
     *
     * @TODO: render intelligent responses at command line
     */
    public static function run()
    {
        static $cfg=['web_interface', 'cli_interface', ];

        self::app();
        $req = App::request();
        $sn = $req['script-name'];
        foreach($cfg as $n) {
            if(!is_null($b=self::config($n))) {
                $n = S::camelize($n);
                self::$$n = $b;
            }
        }

        if($req['shell']) {
            while(isset(self::$cliSkipScriptName[0]) && $sn==self::$cliSkipScriptName[0] && $req['argv']) {
                array_shift(self::$cliSkipScriptName);
                $sn = array_shift($req['argv']);
            }
            S::scriptName($sn);
            self::$cacheTimeout = false;
            self::$staticCache  = false;
            chdir(S_APP_ROOT);
        } else if(static::$webInterface) {
            App::$assets[] = '!Z.Studio';
            App::$assets[] = '!Z.Api';
            App::$assets[] = '!'.Form::$assets;
        }

        if($lang=self::$app->config('app', 'language')) {
            S::$lang = $lang;
        } else {
            if(!self::$languages) self::$languages=self::$app->config('app', 'languages');
            if(self::$languages) {
                self::language(self::$languages);
            }
        }

        if(is_null(self::$connection)) {
            if(isset(self::$app->studio['connection'])) {
                self::$connection = self::$app->studio['connection'];
            }
            if(self::$connection && self::$connection!='studio' && !Query::database('studio')) {
                S::$database['studio'] = S::$database[self::$connection];
            }
        }
        if(self::$connection==='studio' && !Query::database('studio')) {
            self::$connection = null;
        }
        if($rev=App::request('get', '!rev')) {
            self::$params['!rev'] = $rev;
            self::$staticCache = 0;
        } else if(self::$cacheTimeout && self::$staticCache) {
            self::getStaticCache();
        }
        S::$translator = 'Studio\\Studio::translate';

        if(!isset(static::$assetsOptimizeUrl)) static::$assetsOptimizeUrl = S::$assetsUrl;

        // try the interface
        if(static::$webInterface && ($sn==self::$home || strncmp($sn, self::$home, strlen(self::$home))===0)) {
            if($sn!=='/_studio' && self::config('enable_interface_index')) {
                $icn = self::$interfaceClass;
                $icn::$baseMap[$sn] = ['/_studio'];
            }
            static::$internal = true;
            S::scriptName($sn);
            S::cacheControl('private, no-cache', 0);
            return self::_runInterface();
        } else if(static::$cliInterface && self::$cli && S_CLI && substr($sn, 0, 1)==':' && isset(self::$cliApps[$sn=substr($sn,1)])) {
            // cli apps
            S::$variables['template'] = 'cli';
            App::response('layout', 'cli');
            if(isset(self::$cliApps[$sn])) {
                list($cn, $m) = self::$cliApps[$sn];
                return $cn::$m();
            }
            self::error(404);
        } else if(App::request('headers', 'tdz-slots') || $sn==self::$uid) {
            $cch = 'private';
            if(!static::$cacheTimeout) $cch .= ', no-cache';
            S::cacheControl($cch, static::$cacheTimeout);
            S::output(S::serialize(self::uid(), 'json'), 'json');
        } else if(self::ignore($sn)) {
            self::error(404);
        } else if($E=self::page($sn)) {
            $E->render();
            unset($E);
            if(!self::$private && !S::get('cache-control') && static::$cacheTimeout) {
                S::cacheControl('public', static::$cacheTimeout);
            }
            return true;
        } else if(substr($sn, 0, strlen(static::$assetsOptimizeUrl))==static::$assetsOptimizeUrl && Asset::run($sn)) {
            return true;
        } else {
            self::error(404);
        }
        unset($sn);
        return false;
    }

    /**
     * Sets user language according to browser preferences
     */
    public static function language($l=array())
    {
        if(!is_array($l)) {
            $lang = $l;
        } else if(count($l)<2) {
            $lang = $l[0];
        } else {
            $req = App::request();
            if(substr($req['query-string'],0,1)=='!' && in_array($lang=substr($req['query-string'],1), $l)) {
                setcookie('lang',$lang,0,'/',false,false);
                S::redirect($req['script-name'].'#'.$lang);
            }
            unset($lang);
            if(!(isset($_COOKIE['lang']) && ($lang=$_COOKIE['lang']) && (in_array($lang, $l) || (strlen($lang)>2 && in_array($lang=substr($lang,0,2), $l))))) {
                unset($lang);
            }
            if (!isset($lang) && ($langs=App::request('headers', 'accept-language'))) {
                $accept = preg_split('/(;q=[0-9\.]+|\,)\s*/', $langs, -1, PREG_SPLIT_NO_EMPTY);
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
            $lang = S::$lang;
        } else if($lang!=S::$lang) {
            S::$lang = $lang;
        }
        return $lang;
    }

    private static function _runInterface($url=null)
    {
        if(!$url) $url = substr(S::scriptName(), strlen(self::$home));
        if(strpos($url, '.')!==false && !strpos($url, '/', 1)) {
            if(substr($url, 0, 1)=='.') $url = '/studio'.$url;
            else if(substr($url, 0, 1)!='/') $url = '/'.$url;
            if(!Asset::run($url, S_ROOT.'/src/Z', true) 
                && (substr($url, -4) == '.css' || substr($url, -3) == '.js') 
                && !Asset::run($url, S_VAR.'/cache/minify', true)) {
                self::error(404);
            }
        } else {
            static $methods = array(
                '/s'=>'listInterfaces',
                //'/q'=>'listInterfaces',
            );
            if(!($U=S::getUser()) || !$U->isAuthenticated() || !($U->isSuperAdmin() || ($c=self::credential(array('studio','edit','previewUnpublished'))) && $U->hasCredential($c, false))) {
                return self::error(403);
            }

            $In=self::$interfaceClass;
            if(App::request('headers', 'z-action')=='Interface') {
                S::scriptName(self::$home);
                S::$translator = 'Studio\\Studio::translate';
                App::response('layout', 'layout');
                //S::$variables['document-root'] = dirname(__FILE__).'/Resources/assets';
                //App::response('script', array('/z.js','/studio.js','/interface.js'));
                //App::response('style', array('/studio.less'));
                return $In::run();
            } else if(isset($methods[$url])) {
                $m = $methods[$url];
                return self::$m();
            } else {
                S::scriptName(self::$home);
                S::$translator = 'Studio\\Studio::translate';
                App::$assets[] = 'Z.Studio';
                App::$assets[] = 'Z.Api';
                App::$assets[] = 'Z.Form';
                App::response('layout', 'studio');
                if(self::config('reset_interface_style')) App::response('style', []);
                if(self::config('reset_interface_script')) App::response('script', []);
                //S::$variables['document-root'] = dirname(__FILE__).'/Resources/assets';
                //S::$assetsUrl = self::$home;
                //App::response('script', array('/z.js','/studio.js','/interface.js'));
                //App::response('style', array('/studio.less'));
                return $In::run();
            }
        }
        self::error(404);
    }

    public static function listInterfaces()
    {
        $R = array();
        $p = App::request('post');

        if($p && is_array($p)) {
            foreach($p as $i=>$o) {
                if($r=static::interfaceAddress($o)) {
                    $R[] = $r;
                }
            }
        }

        $In=self::$interfaceClass;
        $In::headers();
        App::end($In::toJson($R));
    }

    public static function listProperties()
    {
        $R=array();
        $U=S::getUser();
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

        if($C['edit'] && ($d = App::request('post', 'c'))) {
            foreach($d as $i=>$id) {
                $p = 'edit';
                $o = Contents::find($id,1,array('content_type'));
                if($o && $o->source && substr(basename($o->source), 0, 6)=='_tpl_.' && !$C['editTemplate']) $o=null;
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
                if($C['publish'] && !$o->source) {
                    if($o->published) {
                        $R[$k]['unpublish'] = true;
                    } else {
                        $R[$k]['publish'] = true;
                    }
                }
                $R[$k]['type'] = $o->content_type;
                if($o->source && Entries::file($o->source)) $R[$k]['id'] = $o->source;
                unset($d[$i], $id, $k, $o);
            }
        }
        if($C['new'] && ($d = App::request('post', 's'))) {
            foreach($d as $i=>$id) {
                $k = 's/'.$id;
                $R[$k] = array('new'=>true,'id'=>$id);
                unset($d[$i], $id, $k, $o);
            }
        }
        S::output($R, 'json', false);
        self::$app->end();
        return true;
    }

    public static function content($page, $checkLang=true, $checkTemplates=true, $addResponse=true, $extAttr=[])
    {
        static $root;

        if(!file_exists($page)) return;

        $C = $id = $source = $entry = null;

        if($extAttr) {
            $source = ((isset($extAttr['src'])) ?$extAttr['src'] :'').substr($page, strlen($extAttr['file']));
            if($C=Contents::find(['source'=>$source],1,['id', 'entry'])) {
                $id = $C->id;
                if($C->entry) $entry = $C->entry;
                unset($C);
            }
            if(!$entry && ($C=Entries::find(['source'=>$source],1,['id']))) {
                $entry = $C->id;
                unset($C);
            }
        } else if(strpos($page, S_REPO_ROOT.'/')===0) {
            $source = preg_replace('#^/?([^/]+)/(.+)$#', '$1:$2', substr($page, strlen(S_REPO_ROOT)+1));
        } else {
            if(is_null($root)) $root = self::documentRoot();
            if((substr($page, 0, strlen($root))!==$root && substr($page, 0, strlen(static::$templateRoot))!==static::$templateRoot)) return;
            $source = substr($page, strlen(self::documentRoot()));
        }

        $slotname = Entries::$slot;
        $pos = '00000';
        $pn = basename($page);
        //if(substr($pn, 0, strlen($link)+1)==$link.'.') $pn = substr($pn, strlen($link)+1);
        $pp = explode('.', $pn);
        $tpl = ($pp[0]==='_tpl_');
        if($tpl && !$checkTemplates) return false;
        array_shift($pp);
        $ext = strtolower(array_pop($pp));
        if(count($pp)>0) {
            $lang = array_pop($pp);
            if(!preg_match('/^[a-z]{2}$/', $lang) || (self::$languages && !in_array($lang, self::$languages))) {
                $pp[]=$lang;
            } else {
                if($checkLang && $lang!=S::$lang) {
                    return false;
                }
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
        if(!isset(Contents::$contentType[$ext])) return false;
        else if(is_array(Contents::$disableExtensions) && in_array($ext, Contents::$disableExtensions)) return false;
        $p = file_get_contents($page);
        if(!$p) return false;
        $meta = null;
        if($m = Entries::meta($p)) {
            $meta = Yaml::load($m);
            if(isset($meta['credential'])) {
                if(!($U=S::getUser()) || !$U->hasCredential($meta['credential'], false)) {
                    return false;
                }
                $c = (!is_array($meta['credential']))?(array($meta['credential'])):($meta['credential']);
                if(!is_array(self::$private)) self::$private = $c;
                else self::$private = array_merge($c, self::$private);
                unset($U);
            }
        }

        $lmod = date('Y-m-d\TH:i:s', filemtime($page));
        $d = [
            'id'=>$id,
            'entry'=>$entry,
            'slot'=>$slotname,
            'content'=>$p,
            'content_type'=>$ext,
            'source'=>$source,
            'attributes'=>$meta,
            'content'=>$p,
            'position'=>$pos,
            'updated'=>$lmod,
            'published'=>$lmod,

            //'_position'=>$pos,
        ];
        if(!$d['id']) unset($d['id']);
        if($extAttr) {
            $d['created'] = date('Y-m-d\TH:i:s', filectime($page));
            $d['__skip_timestamp_created'] = true;
            $d['__skip_timestamp_updated'] = true;
            if($tpl) {
                $url = (isset($extAttr['url'])) ?$extAttr['url'] :'';
                if(substr($url, -1)==='/') $url = substr($url, 0, strlen($url)-1);
                $url .= preg_replace('#/_tpl_\..*#', '/', substr($page, strlen($extAttr['file'])));
                $d['ContentDisplay'][] = [
                    'link'=>$url,
                    'display'=>1,
                    'created'=>$d['created'],
                    'updated'=>$d['updated'],
                    '__skip_timestamp_created' => true,
                    '__skip_timestamp_updated' => true,
                ];
            }
        }
        $C = new Contents($d);
        if(!is_null($pos)) $C->_position = $slotname.$pos;

        if($addResponse && isset($meta) && $meta) {
            if(isset($meta['attributes'])) unset($meta['attributes']);
            if(isset($meta['credential'])) unset($meta['credential']);
            if($meta) static::addResponse($meta);
            foreach(Contents::$schema->properties as $fn=>$fd) {
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
            'c'=>App::response('cache-control'),
            'h'=>App::response('headers'),
            'r'=>App::$result,
        );
        Cache::set('studio/cache/'.md5(S::scriptName()).'.'.S::$lang, $r, self::$cacheTimeout);
        unset($r);
    }

    public static function setStaticCacheDownload($f, $format=null)
    {
        $r = array(
            'c'=>App::response('cache-control'),
            'h'=>App::response('headers'),
            'f'=>$f,
        );
        if($format) $r['h']['Content-Type'] = $format;
        Cache::set('studio/cache/'.md5(S::scriptName()), $r, self::$cacheTimeout);
        unset($r);
    }

    public static function getStaticCache()
    {
        $ckey = 'studio/cache/'.md5(S::scriptName()).'.'.S::$lang;
        $r = Cache::get($ckey, self::$cacheTimeout);
        if(!$r) {
            $ckey = 'studio/cache/'.md5(S::scriptName());
            $r = Cache::get($ckey, self::$cacheTimeout);
        }

        if($r && is_array($r)) {
            if(!isset($r['h']['Content-Type'])) $r['h']['Content-Type']='text/html;charset=UTF8';
            if(is_array($r['c'])) {
                Cache::delete($ckey);
                return false;
            }
            S::cacheControl($r['c'], self::$cacheTimeout);
            foreach($r['h'] as $k=>$v) {
                header($k.': '.$v);
                unset($k, $v);
            }
            if(isset($r['f'])) {
                S::download($r['f'], $r['h']['Content-Type']);
            } else {
                S::output($r['r'], $r['h']['Content-Type'], false);
            }
            self::$app->end();
            return true;
        }
        unset($r);
    }

    /**
     * Authenticated user (got by S::getUser()) basic information
     * This response should not be cached by the server.
     */
    public static function uid()
    {
        self::$private=true;
        if(self::$checkOrigin) {
            if(is_array(self::$allowOrigin) && !in_array($referer=S::buildUrl(''), self::$allowOrigin)) {
                self::$allowOrigin[] = $referer;
            }
            if(!($from=App::request('headers', 'origin')) && !($from=App::request('headers', 'referer')) && self::$checkOrigin>1) {
                return false;
            }

            if($from) {
                $valid = false;
                foreach(self::$allowOrigin as $allow) {
                    if($allow==='*' || substr($from, 0, strlen($allow))==$allow) {
                        $valid = true;
                        @header('access-control-allow-origin: '.$allow);
                        @header('access-control-allow-headers: x-requested-with');
                        @header('access-control-allow-credentials: true');
                        break; 
                    }
                }
                if(!$valid) return false;
            }
        }

        $U = S::getUser();

        if($U) {
            $r = $U->asArray();
            if(self::$webInterface && (($U->isAuthenticated() && ($U->isSuperAdmin()) || (($ec= self::credential('studio')) && $U->hasCredential($ec, false))))) {
                $r['plugins']=array(
                    'studio'=>array(
                        'home'=>self::$home,
                        'options'=>[
                        ],
                        'load'=>['z-studio','z-api'],
                    ),
                );
                if(static::$webButton!==false) $r['plugins']['studio']['options']['button'] = true;
                if(static::$webInteractive!==false) $r['plugins']['studio']['options']['interactive'] = true;
            }
            if($U->isAuthenticated() && ($cfg=self::$app->user)) {
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

        if(static::$userMessage && ($m=$U->getMessage(null, true))) {
            $mp = (is_string(static::$userMessage)) ?static::$userMessage :'message';
            $r[$mp] = $m;
        }

        if(S::scriptName(true)===self::$uid) {
            $cch = 'private';
            if(!static::$cacheTimeout) $cch .= ', no-cache';
            S::cacheControl($cch, static::$cacheTimeout);
            S::output(S::serialize($r, 'json'), 'json');
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
     * @return        false || Entries
     */
    public static function page($url, $exact=false, $published=null)
    {
        $url = S::validUrl($url);
        //$url = preg_replace('#\.\.+#', '.', $url);
        //$url = preg_replace('#\.+/+#', '', $url);
        //$url = preg_replace('/\/(\.)*\/+/','/',$url);
        //$url = preg_replace('/\/\/+/','/',$url);
        if ($url=='') {
            return false;
        }

        $f=array(
            'link'=>$url,
            'type'=>Entries::$previewEntryType,
            'expired'=>'',
        );
        static $scope = array('id','title','type','link','source','master','format','updated','published','version');
        self::$private = array();
        if(static::$response) static::addResponse(static::$response);
        $connEnabled = (self::connected('content'));
        if(is_null($published)) {
            // get information from user credentials
            if($connEnabled && ($U=S::getUser()) && $U->hasCredential($c=self::credential('previewUnpublished'), false)) {
                $published = false;
                self::$private = (is_array($c))?($c):(array($c));
            } else {
                $published = true;
            }
        }
        /*
        if(self::$cacheTimeout && ($E=Cache::get('e-url'.$url, self::$cacheTimeout))) {
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
        if($connEnabled && ($E=Entries::find($f, 1, $scope,false,array('type'=>'desc','published'=>'desc','version'=>'desc')))) {
            if($meta = $E::loadMeta($E->link)) {
                foreach($meta as $fn=>$v) {
                    if(property_exists($E, $fn)) {
                        if($fn=='layout' || $fn=='slots') $E::$$fn = $v;
                        else if(!$E->$fn) $E->$fn = $v;
                    }
                    unset($meta[$fn], $fn, $v);
                }
                unset($meta);
            }
            unset($f, $published);
            return $E;
        }
        if(!$E && ($E=Entries::findPage($url, false, true))) {
            unset($f, $published);
        } else if(preg_match('/('.str_replace('.', '\.', implode('|',self::$allowedExtensions)).')$/', $url, $m) && ($E=Entries::findPage(substr($url,0,strlen($url)-strlen($m[1])), false, true))) {
            $url = substr($url,0,strlen($url)-strlen($m[1]));
            unset($f, $published);
        }
        if(!$E && !$exact && substr($url, 0, 1)=='/' && strlen($url)>1) {
            $f['Contents.content_type']=Contents::$multiviewContentType;
            $u = $url;
            while(strlen($u)>1) {
                $u = preg_replace('#/[^/]+$#', '', $u);
                $f['link'] = $u;
                if($connEnabled && ($E=Entries::find($f,1,$scope,false,array('type'=>'desc')))) {
                    unset($f, $published);
                    break;
                } else if($u && ($E=Entries::findPage($u, true))) {
                    unset($f, $published);
                    break;
                }
            }
            if($E) {
                //S::scriptName($u);
            }
        }

        if($E) {
            self::checkIndex($E, 'e');
            //if(self::$cacheTimeout) Cache::set('e-url'.$url, $E, self::$cacheTimeout);
            return $E;
        }
        unset($f, $url, $published, $E);
        return false;
    }

    public static function template($url=null)
    {
        $E = new Entries(array('link'=>$url),false, false);
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
                $tpl[$slotname] = "<div id=\"{$slotname}\">".S::get('before-'.$slotname).'<div>'.$slot.'</div>'.S::get($slotname).S::get('after-'.$slotname)."</div>";
                if(isset($slotelements[$slotname]) && $slotelements[$slotname]) {
                    $tpl[$slotname] = "<{$slotname}>{$tpl[$slotname]}</{$slotname}>";
                }
            }
            $tpl['slots'] = array_keys($tpl);
            S::$variables+=$tpl;
        }
        self::templateDir();
        return $tpl;
    }

    public static function templateDir()
    {
        static $d = S_ROOT.'/data/templates';
        if(is_null(S::$tplDir)) {
            S::templateDir();
        }
        if(!in_array($d, S::$tplDir)) {
            S::$tplDir[] = $d;
        }
        return S::$tplDir;
    }

    public static function error($code=500)
    {
        if(!self::$app) self::$app = S::getApp();
        if(isset(S::$variables['route']['layout'])  && S::$variables['route']['layout']) {
            $layout = S::$variables['route']['layout'];
        } else {
            $layout = self::templateFile(Entries::$layout, 'layout');
        }
        static::$status = $code;
        self::template('/error'.$code);
        Entries::loadMeta('/error'.$code);

        return self::$app->runError($code, $layout);
    }

    public static function checkIndex($M, $interface=null)
    {
        if(self::$index) {
            // check if the studio connection should be set or changed to self::$index, if it's a string
            Index::check($M, $interface);
        }
    }

    /**
     * These credentials should be set at the Permission table, without entries
     * To make them eligible as default values. The keypairs are:
     *   Permission->role: Permission->credentials (csv)
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
            if(!self::$credentials && self::$cacheTimeout) self::$credentials = Cache::get('studio/credentials', self::$cacheTimeout);
            if(!self::$credentials || !is_array(self::$credentials)) {
                self::$credentials=array();
                $connEnabled = (self::$connection && self::config('enable_interface_credential'));
                if($connEnabled) {
                    $ps = Permissions::find(array('entry'=>''),0,array('role','credentials'),false,array('updated'=>'desc'));
                    if($ps) {
                        foreach($ps as $i=>$P) {
                            if(isset(self::$credentials[$P->role])) continue;
                            if(!$P->credentials || $P->credentials=='~') $c=false;
                            else if($P->credentials=='*') $c=true;
                            else $c = preg_split('/[\s\,\;]+/', $P->credentials, -1, PREG_SPLIT_NO_EMPTY);
                            self::$credentials[$P->role] = $c;
                            unset($ps[$i], $P, $c);
                        }
                    }
                }
                if(self::$cacheTimeout) Cache::set('studio/credentials', self::$credentials, self::$cacheTimeout);
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
            $cn = 'Studio\\Model\\Entries';
            $p = substr($p, 5);
            if(!property_exists($cn, $p)) $cn=null;
        }
        if($cn) {
            // translate
            return $cn::$p;
        } else if(self::app()) {
            if(isset(self::$app->studio[$p])) {
                return self::$app->studio[$p];
            } else if(property_exists(get_called_class(), $p=S::camelize($p))) {
                return self::$$p;
            }
        }
        return false;
    }

    public static function connected($prop=null)
    {
        return ($prop) ?(self::$connection && self::config('enable_interface_'.$prop)) :self::$connection;
    }

    public static function templateFiles($type)
    {
        static $r;

        if(is_null($r)) {
            $r = [];
            $shift = strlen('tdz_'.$type);
            foreach(self::templateDir() as $d) {
                $found = glob($d.'/tdz_'.$type.'*.php');
                foreach($found as $f) {
                    $k = basename($f, '.php');
                    $n = ucfirst(str_replace(['-', '_'], ' ', trim(substr($k, $shift), '-_')));
                    $r[$k] = $n;
                }
            }
        }

        return $r;

    }

    /**
     * Find current template file location, or false if none are found, accepts multiple arguments, processed in order.
     * example: $template = App_Studio::templateFile($mytemplate, 'tdz_entry');
     */
    public static function templateFile($tpl)
    {
        self::templateDir();
        return S::templateFile(func_get_args());
    }

    public static function translate($s, $table=null, $to=null, $from=null)
    {
        $In=self::$interfaceClass;
        if($to && !$In::$translate && $to==$from)  {
            return $s;
        } else {
            return self::t($s, null, $table);
        }
    }

    public static function t($s, $alt=null, $table='lang')
    {
        if(!$table) $table = 'lang';
        if(!($translated = Translate::message($s, $table)) || ($translated===$s && $alt)) {
            $translated = $alt;
        }
        return $translated;
    }


    public static function li($list)
    {
        $s = '';
        if($list instanceof Collection) $list = $list->getItems();
        if($list && count($list)>0) {
            foreach($list as $e) {
                $c = ($e->id==self::$page)?(' class="current"'):('');
                $s .= '<li'.$c.'>'
                    . (($e['link'])?('<a'.$c.' href="'.S::xml($e['link']).'">'.S::xml($e['title']).'</a>'):(S::xml($e['title'])))
                    .  (($e instanceof Entries)?(self::li($e->getChildren())):(''))
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
                if(!isset(S::$variables[$k])) {
                    S::$variables[$k] = (!is_array($a[$k]))?(array($a[$k])):($a[$k]);
                } else if(!is_array($a[$k])) {
                    if(!in_array($a[$k], S::$variables[$k])) S::$variables[$k][]=$a[$k];
                } else {
                    S::$variables[$k] = array_merge(S::$variables[$k], $a[$k]);
                }
            }
        }
        if(!isset(S::$variables['variables'])) S::$variables['variables']=array();
        S::$variables['variables'] += $a;
    }

    public static function interfaceId($M, $prefix=null)
    {
        $s = (is_string($M)) ?$M :implode('-', $M->getPk(true));
        if($prefix) $s = $prefix.'/preview/'.$s;

        return S::encrypt($s, null, 'uuid');
    }

    public static function interfaceAddress($s)
    {
        return S::decrypt($s, null, 'uuid');
    }


    public static function enabledModels($model=null)
    {
        static $models, $compatibility=[
            'Tecnodesign_Studio_Entry'=>'Studio\\Model\\Entries',
            'Tecnodesign_Studio_Content'=>'Studio\\Model\\Contents',
            'Tecnodesign_Studio_ContentDisplay'=>'Studio\\Model\\ContentsDisplay',
            'Tecnodesign_Studio_Relation'=>'Studio\\Model\\Relations',
            'Tecnodesign_Studio_Tag'=>'Studio\\Model\\Tags',
        ], $compatible=false;

        if(is_null($models)) {
            $models = [];
            $cfg = [
                'content'=>[
                    'Studio\\Model\\Entries',
                    'Studio\\Model\\Contents',
                    'Studio\\Model\\ContentsDisplay',
                    'Studio\\Model\\Relations',
                    'Studio\\Model\\Tags',
                    'Studio\\Model\\Permissions',
                ],
                'credential'=>[
                    'Studio\\Model\\Users',
                    'Studio\\Model\\Groups',
                    'Studio\\Model\\Credentials',
                ],
                'index'=>[
                    'Studio\\Model\\Interfaces',
                    'Studio\\Model\\Tokens',
                    'Studio\\Model\\Index',
                    'Studio\\Model\\IndexBlob',
                    'Studio\\Model\\IndexBool',
                    'Studio\\Model\\IndexDate',
                    'Studio\\Model\\IndexNumber',
                    'Studio\\Model\\IndexText',
                ],
                'schema'=>[
                    'Studio\\Model\\Schema',
                    'Studio\\Model\\SchemaProperties',
                    'Studio\\Model\\SchemaDisplay',
                ],
            ];
            if(($version=self::config('compatibility_level')) && $version < 2.5) {
                $compatible = true;
                $cfg['content'] = array_keys($compatibility);
            }
            foreach($cfg as $n=>$cns) {
                if(self::config('enable_interface_'.$n) || self::config('enable_api_'.$n)) {
                    $models = ($models) ?array_merge($models, $cns) :$cns;
                }
            }
        }
        if(!is_null($model)) {
            if(isset($compatibility[$model]) && !$compatible) $model = $compatibility[$model];
            return (in_array($model, $models)) ?$model :null;
        }

        return $models;
    }

    public static function fromFile($file, $attr=[])
    {
        if($R=Entries::fromFile($file, $attr)) {
            if($R->type==='page' && ($C = Contents::fromFile($file, $attr))) {
                return [$R, $C];
            }

            return $R;

        } else if($R=Contents::fromFile($file, $attr)) {
            $L = [];
            if($R->ContentDisplay) {
                $L = $R->ContentDisplay;
                if(is_object($L)) $L = $L->getItems();
                if(!$L) $L = [];

                array_unshift($L, $R);
                unset($R);

                return $L;
            } else {
                return $R;
            }
        } else if($R=Relations::fromFile($file, $attr)) {
            return $R;
        }
    }

    public static function sourceFile($src, $check=true)
    {
        if(!$src) return false;

        if($p=strpos($src, ':')) {
            $rs = Studio::config('web-repos');
            $rn = substr($src, 0, $p);
            if(!$rs || !isset($rs[$rn]) || !is_dir($d=S_REPO_ROOT.'/'.$rn)) return false;

            $repo = $rs[$rn];
            $mu = (isset($repo['mount-src'])) ?$repo['mount-src'] :'';
            if($mu) {
                if($mu==='.' || $mu==='./') $mu='';
                else if(substr($mu, -1)!='/') $mu .= '/';
            }
            $murl = substr($src, $p+1);
            $f = $d.'/'.$mu.$murl;
        } else {
            $f = Studio::documentRoot() . ((substr($src, 0, 1)!='/') ?'/' :'').$src;
        }

        if($check) {
            return (file_exists($f)) ?$f :null;
        }

        return $f;
    }
}