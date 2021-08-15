<?php
/**
 * Tecnodesign Studio
 * 
 * Stand-alone Content Management System.
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */

use Tecnodesign_Studio_Entry as Entry;

class Tecnodesign_Studio
{
    /**
     * These are the basic configuration settings. Update them by creating a 
     * configuration file named: config/autoload.Tecnodesign_Studio.yml
     */
    public static 
        $app,               // updated at runtime, this is the main application alias, used internally (also by other classes)
        $automatedInstall,  // deprecated
        $internal,
        $webInterface,
        $webButton,
        $webInteractive,
        $cliInterface=true, // enable command-line interface
        $resetInterfaceStyle=true,
        $resetInterfaceScript=true,
        $checkOrigin=1,     // prevents sending user details to external origins, use 2 to prevent even to unknown origins
        $allowOrigin=[],
        $private=[],        // updated at runtime, indicates when a cache-control: private,nocache should be sent
        $page,              // updated at runtime, actual entry id rendered
        $connection,        // connection to use, set to false to disable database
        $params=array(),    // updated at runtime, general params
        $cacheTimeout=false,// configurable, cache timeout, use false to disable caching, 0 means forever
        $staticCache=false, // configurable, store static previews of entries
        $home='/_studio',   // configurable, where to load Studio interface
        $uid='/_me',        // configurable, where to load a json to check if a user is authenticated
        $uploadDir,         // deprecated, use tdz::uploadDir
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
        $interfaceClass='Tecnodesign_Studio_Interface',
        $interfaces = [
            'interfaces'=>'interfaces',
        ],
        $cliApps=[
            'config'=>['Tecnodesign_App_Install','config'],
            'check'=>['Tecnodesign_Studio_Index', 'checkConnection'],
            'index'=>['Tecnodesign_Studio_Index','reindex'],
            'import'=>['Tecnodesign_Database','import'],
        ];
    const VERSION = 2.5;    // should match the development branch 

    /**
     * This is a Tecnodesign_App, the constructor is loaded once and then cached (until configuration changes)
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
            $root = preg_replace('#/+$#', '', TDZ_VAR.'/'.Entry::$pageDir);
        }
        return $root;
    }

    public static function app()
    {
        if(!self::$app) {
            self::$app = tdz::getApp();
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
        self::app();
        $req = Tecnodesign_App::request();
        $sn = $req['script-name'];

        if($req['shell']) {
            while(isset(self::$cliSkipScriptName[0]) && $sn==self::$cliSkipScriptName[0] && $req['argv']) {
                array_shift(self::$cliSkipScriptName);
                $sn = array_shift($req['argv']);
            }
            tdz::scriptName($sn);
            self::$cacheTimeout = false;
            self::$staticCache  = false;
            chdir(TDZ_APP_ROOT);
        } else if(static::$webInterface) {
            Tecnodesign_App::$assets[] = '!Z.Studio';
            Tecnodesign_App::$assets[] = '!Z.Interface';
            Tecnodesign_App::$assets[] = '!'.Tecnodesign_Form::$assets;
        }

        if(!self::$languages && isset(self::$app->tecnodesign['languages'])) self::$languages=self::$app->tecnodesign['languages'];

        if(self::$languages) tdz::$lang=self::language(self::$languages);

        if(is_null(self::$connection)) {
            if(isset(self::$app->studio['connection'])) {
                self::$connection = self::$app->studio['connection'];
            }
            if(self::$connection && self::$connection!='studio' && !Tecnodesign_Query::database('studio')) tdz::$database['studio'] = tdz::$database[self::$connection];
        }
        if(self::$connection==='studio' && !Tecnodesign_Query::database('studio')) {
            self::$connection = null;
        }
        if(isset($_GET['!rev'])) {
            self::$params['!rev'] = $_GET['!rev'];
            self::$staticCache = 0;
        } else if(self::$cacheTimeout && self::$staticCache) {
            self::getStaticCache();
        }
        tdz::$translator = 'Tecnodesign_Studio::translate';

        if(!isset(static::$assetsOptimizeUrl)) static::$assetsOptimizeUrl = tdz::$assetsUrl;

        // try the interface
        if(static::$webInterface && ($sn==self::$home || strncmp($sn, self::$home, strlen(self::$home))===0)) {
            static::$internal = true;
            tdz::scriptName($sn);
            tdz::cacheControl('private,must-revalidate,no-cache', 0);
            return self::_runInterface();
        } else if(static::$cliInterface && self::$cli && TDZ_CLI && substr($sn, 0, 1)==':' && isset(self::$cliApps[$sn=substr($sn,1)])) {
            // cli apps
            tdz::$variables['template'] = 'cli';
            Tecnodesign_App::response('layout', 'cli');
            if(isset(self::$cliApps[$sn])) {
                list($cn, $m) = self::$cliApps[$sn];
                return $cn::$m();
            }
            self::error(404);
        } else if(Tecnodesign_App::request('headers', 'tdz-slots') || $sn==self::$uid) {
            tdz::cacheControl('private', static::$cacheTimeout);
            tdz::output(tdz::serialize(self::uid(), 'json'), 'json');
        } else if(self::ignore($sn)) {
            self::error(404);
        } else if($E=self::page($sn)) {
            $E->render();
            unset($E);
            if(!self::$private && !tdz::get('cache-control') && static::$cacheTimeout) {
                tdz::cacheControl('public', static::$cacheTimeout);
            }
            return true;
        } else if(substr($sn, 0, strlen(static::$assetsOptimizeUrl))==static::$assetsOptimizeUrl && Tecnodesign_Studio_Asset::run($sn)) {
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
            $req = Tecnodesign_App::request();
            if(substr($req['query-string'],0,1)=='!' && in_array($lang=substr($req['query-string'],1), $l)) {
                setcookie('lang',$lang,0,'/',false,false);
                tdz::redirect($req['script-name'].'?'.$lang);
            }
            unset($lang);
            if(!(isset($_COOKIE['lang']) && ($lang=$_COOKIE['lang']) && (in_array($lang, $l) || (strlen($lang)>2 && in_array($lang=substr($lang,0,2), $l))))) {
                unset($lang);
            }
            if (!isset($lang) && ($langs=Tecnodesign_App::request('headers', 'accept-language'))) {
                $accept = preg_split('/(;q=[0-9\.]+|\,)\s*/', $langs, null, PREG_SPLIT_NO_EMPTY);
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
        } else if($lang!=tdz::$lang) {
            tdz::$lang = $lang;
        }
        return $lang;
    }

    private static function _runInterface($url=null)
    {
        if(!$url) $url = substr(tdz::scriptName(), strlen(self::$home));
        if(strpos($url, '.')!==false && !strpos($url, '/')) {
            if(substr($url, 0, 1)=='.') $url = '/studio'.$url;
            else if(substr($url, 0, 1)!='/') $url = '/'.$url;
            if(!Tecnodesign_Studio_Asset::run($url, TDZ_ROOT.'/src/Z', true) 
                && (substr($url, -4) == '.css' || substr($url, -3) == '.js') 
                && !Tecnodesign_Studio_Asset::run($url, TDZ_VAR.'/cache/minify', true)) {
                self::error(404);
            }
        } else {
            static $methods = array(
                '/s'=>'listInterfaces',
                //'/q'=>'listInterfaces',
            );
            if(!($U=tdz::getUser()) || !$U->isAuthenticated() || !($U->isSuperAdmin() || ($c=self::credential(array('studio','edit','previewUnpublished'))) && $U->hasCredential($c, false))) {
                return self::error(403);
            }

            $In=self::$interfaceClass;
            if(Tecnodesign_App::request('headers', 'z-action')=='Interface') {
                tdz::scriptName(self::$home);
                tdz::$translator = 'Tecnodesign_Studio::translate';
                Tecnodesign_App::response('layout', 'layout');
                tdz::$variables['document-root'] = dirname(__FILE__).'/Resources/assets';
                //Tecnodesign_App::response('script', array('/z.js','/studio.js','/interface.js'));
                //Tecnodesign_App::response('style', array('/studio.less'));
                return $In::run();
            } else if(isset($methods[$url])) {
                $m = $methods[$url];
                return self::$m();
            } else {
                tdz::scriptName(self::$home);
                tdz::$translator = 'Tecnodesign_Studio::translate';
                Tecnodesign_App::$assets[] = 'Z.Studio';
                Tecnodesign_App::$assets[] = 'Z.Interface';
                Tecnodesign_App::$assets[] = 'Z.Form';
                Tecnodesign_App::response('layout', 'studio');
                if(self::config('reset_interface_style')) Tecnodesign_App::response('style', []);
                if(self::config('reset_interface_script')) Tecnodesign_App::response('script', []);
                //tdz::$variables['document-root'] = dirname(__FILE__).'/Resources/assets';
                //tdz::$assetsUrl = self::$home;
                //Tecnodesign_App::response('script', array('/z.js','/studio.js','/interface.js'));
                //Tecnodesign_App::response('style', array('/studio.less'));
                return $In::run();
            }
        }
        self::error(404);
    }

    public static function listInterfaces()
    {
        $R = array();
        $p = Tecnodesign_App::request('post');

        if($p && is_array($p)) {
            foreach($p as $i=>$o) {
                if($r=static::interfaceAddress($o)) {
                    $R[] = $r;
                }
            }
        }

        $In=self::$interfaceClass;
        $In::headers();
        Tecnodesign_App::end($In::toJson($R));
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
                if($o->source && Entry::file($o->source)) $R[$k]['id'] = $o->source;
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

    public static function content($page, $checkLang=true, $checkTemplates=true, $addResponse=true)
    {
        static $root;
        if(is_null($root)) $root = Tecnodesign_Studio::documentRoot();
        if((substr($page, 0, strlen($root))!==$root && substr($page, 0, strlen(static::$templateRoot))!==static::$templateRoot) || !file_exists($page)) return;
        $slotname = Entry::$slot;
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
            if(!preg_match('/^[a-z]{2}$/', $lang) || (self::$languages && !in_array($lang, self::$languages))) {
                $pp[]=$lang;
            } else {
                if($checkLang && $lang!=tdz::$lang) {
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
        if(!isset(tdzContent::$contentType[$ext])) return false;
        else if(is_array(tdzContent::$disableExtensions) && in_array($ext, tdzContent::$disableExtensions)) return false;
        $p = file_get_contents($page);
        if(!$p) return false;
        $meta = null;
        if($m = Entry::meta($p)) {
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
        $id = substr($page, strlen(Tecnodesign_Studio::documentRoot()));
        $lmod = date('Y-m-d\TH:i:s', filemtime($page));
        $C = new tdzContent(array(
            'id'=>tdz::hash($id, null, 'uuid'),
            //'entry'=>tdzContent::entry($id),
            'slot'=>$slotname,
            'content'=>$p,
            'content_type'=>$ext,
            'source'=>$id,
            'attributes'=>$meta,
            'content'=>$p,
            'position'=>$pos,
            'updated'=>$lmod,
            'published'=>$lmod,
            //'_position'=>$pos,
        ));
        if(!is_null($pos)) $C->_position = $slotname.$pos;

        if($addResponse && isset($meta) && $meta) {
            if(isset($meta['attributes'])) unset($meta['attributes']);
            if(isset($meta['credential'])) unset($meta['credential']);
            if($meta) static::addResponse($meta);
            foreach(tdzContent::$schema->properties as $fn=>$fd) {
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
            if(is_array(Tecnodesign_Studio::$allowOrigin) && !in_array($referer=tdz::buildUrl(''), Tecnodesign_Studio::$allowOrigin)) {
                Tecnodesign_Studio::$allowOrigin[] = $referer;
            }
            if(!($from=Tecnodesign_App::request('headers', 'origin')) && !($from=Tecnodesign_App::request('headers', 'referer')) && Tecnodesign_Studio::$checkOrigin>1) {
                return false;
            }

            if($from) {
                $valid = false;
                foreach(Tecnodesign_Studio::$allowOrigin as $allow) {
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

        $U = tdz::getUser();

        if($U) {
            $r = $U->asArray();
            if(self::$webInterface && (($U->isAuthenticated() && ($U->isSuperAdmin()) || (($ec= self::credential('studio')) && $U->hasCredential($ec, false))))) {
                $r['plugins']=array(
                    'studio'=>array(
                        'home'=>self::$home,
                        'options'=>[],
                        'load'=>['z-studio','z-interface'],
                    ),
                );
                if(static::$webButton) $r['plugins']['studio']['options']['button'] = true;
                if(static::$webInteractive) $r['plugins']['studio']['options']['interactive'] = true;
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

        if(static::$userMessage && ($m=$U->getMessage(null, true))) {
            $mp = (is_string(static::$userMessage)) ?static::$userMessage :'message';
            $r[$mp] = $m;
        }

        if(tdz::scriptName(true)===self::$uid) {
            tdz::cacheControl('private,must-revalidate', static::$cacheTimeout);
            tdz::output(tdz::serialize($r, 'json'), 'json');
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
            'type'=>Entry::$previewEntryType,
            'expired'=>'',
        );
        static $scope = array('id','title','type','link','source','master','format','updated','published','version');
        self::$private = array();
        if(static::$response) static::addResponse(static::$response);
        $connEnabled = (self::$connection && self::config('enable_interface_entry'));
        if(is_null($published)) {
            // get information from user credentials
            if($connEnabled && ($U=tdz::getUser()) && $U->hasCredential($c=self::credential('previewUnpublished'), false)) {
                $published = false;
                self::$private = (is_array($c))?($c):(array($c));
                // replace Entry by tdzEntryVersion and probe for latest version (?)
                if(isset(self::$params['!rev'])) {
                    $f['version'] = self::$params['!rev'];
                    if(substr(Entry::$schema['table_name'], -8)!='_version') Entry::$schema['table_name'] .= '_version';
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
        if($connEnabled && ($E=Entry::find($f, 1, $scope,false,array('type'=>'desc','published'=>'desc','version'=>'desc')))) {
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
        if(!$E && ($E=Entry::findPage($url, false, true))) {
            unset($f, $published);
        } else if(preg_match('/('.str_replace('.', '\.', implode('|',self::$allowedExtensions)).')$/', $url, $m) && ($E=Entry::findPage(substr($url,0,strlen($url)-strlen($m[1])), false, true))) {
            $url = substr($url,0,strlen($url)-strlen($m[1]));
            unset($f, $published);
        }
        if(!$E && !$exact && substr($url, 0, 1)=='/' && strlen($url)>1) {
            $f['Contents.content_type']=tdzContent::$multiviewContentType;
            $u = $url;
            while(strlen($u)>1) {
                $u = preg_replace('#/[^/]+$#', '', $u);
                $f['link'] = $u;
                if($connEnabled && ($E=Entry::find($f,1,$scope,false,array('type'=>'desc')))) {
                    unset($f, $published);
                    break;
                } else if($u && ($E=Entry::findPage($u, true))) {
                    unset($f, $published);
                    break;
                }
            }
            if($E) {
                //tdz::scriptName($u);
            }
        }

        if($E) {
            self::checkIndex($E, 'e');
            //if(self::$cacheTimeout) Tecnodesign_Cache::set('e-url'.$url, $E, self::$cacheTimeout);
            return $E;
        }
        unset($f, $url, $published, $E);
        return false;
    }

    public static function template($url=null)
    {
        $E = new Entry(array('link'=>$url),false, false);
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
        self::templateDir();
        return $tpl;
    }

    public static function templateDir()
    {
        static $d = TDZ_ROOT.'/src/Tecnodesign/Resources/templates/';
        if(is_null(tdz::$tplDir)) {
            tdz::templateDir();
        }
        if(!in_array($d, tdz::$tplDir)) {
            tdz::$tplDir[] = $d;
        }
        return tdz::$tplDir;
    }

    public static function error($code=500)
    {
        if(!Tecnodesign_Studio::$app) Tecnodesign_Studio::$app = tdz::getApp();
        if(isset(tdz::$variables['route']['layout'])  && tdz::$variables['route']['layout']) {
            $layout = tdz::$variables['route']['layout'];
        } else {
            $layout = self::templateFile(Entry::$layout, 'layout');
        }
        static::$status = $code;
        self::template('/error'.$code);
        Entry::loadMeta('/error'.$code);

        return Tecnodesign_Studio::$app->runError($code, $layout);
    }

    public static function checkIndex($M, $interface=null)
    {
        if(self::$index) {
            // check if the studio connection should be set or changed to self::$index, if it's a string
            Tecnodesign_Studio_Index::check($M, $interface);
        }
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
                $connEnabled = (self::$connection && self::config('enable_interface_credential'));
                if($connEnabled) {
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
            $cn = 'Tecnodesign_Studio_Entry';
            $p = substr($p, 5);
            if(!property_exists($cn, $p)) $cn=null;
        }
        if($cn) {
            // translate
            return $cn::$p;
        } else if(self::app()) {
            if(isset(self::$app->studio[$p])) {
                return self::$app->studio[$p];
            } else if(property_exists(get_called_class(), $p=tdz::camelize($p))) {
                return self::$$p;
            }
        }
        return false;
    }

    public static function templateFiles($type)
    {
        static $r;

        if(is_null($r)) {
            if(!in_array($d=TDZ_ROOT.'/src/Tecnodesign/Resources/templates', tdz::templateDir())) {
                tdz::$tplDir[] = $d;
            }
            $r = [];
            $shift = strlen('tdz_'.$type);
            foreach(tdz::$tplDir as $d) {
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
     * example: $template = Tecnodesign_App_Studio::templateFile($mytemplate, 'tdz_entry');
     */
    public static function templateFile($tpl)
    {
        if(!in_array($d=TDZ_ROOT.'/src/Tecnodesign/Resources/templates', tdz::templateDir())) {
            tdz::$tplDir[] = $d;
        }
        unset($d);
        return tdz::templateFile(func_get_args());
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
        if(!($translated = Tecnodesign_Translate::message($s, $table)) || ($translated===$s && $alt)) {
            $translated = $alt;
        }
        return $translated;
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
                    .  (($e instanceof Entry)?(self::li($e->getChildren())):(''))
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
            }
        }
        if(!isset(tdz::$variables['variables'])) tdz::$variables['variables']=array();
        tdz::$variables['variables'] += $a;
    }

    public static function interfaceId($M, $prefix=null)
    {
        $s = (is_string($M)) ?$M :implode('-', $M->getPk(true));
        if($prefix) $s = $prefix.'/v/'.$s;

        return tdz::encrypt($s, null, 'uuid');
    }

    public static function interfaceAddress($s)
    {
        return tdz::decrypt($s, null, 'uuid');
    }
}

if(!class_exists('tdzEntry')) {
    if(!in_array($libdir = dirname(__FILE__).'/Studio/Resources/model', tdz::$lib)) tdz::$lib[]=$libdir;
    unset($libdir);
}

if(!defined('TDZ_ESTUDIO')) define('TDZ_ESTUDIO', Tecnodesign_Studio::VERSION);
