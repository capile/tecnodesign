<?php
/**
 * Tecnodesign Application Server
 *
 * This package enable Tecnodesign application management.
 *
 * PHP version 5.6+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_App
{
    protected static $_instances = null, $_current=null, $_request=null, $_response=array();
    protected $_name=null;
    protected $_env=null;
    protected $_timeout=3600;
    protected $_vars=array();
    public $addons=array();
    public $start=null;
    public static
        $defaultScheme='https',
        $beforeRun=array(),
        $afterRun=array(),
        $defaultController = array(
            'class'=>false,
            'cache'=>false,
            'method'=>false,
            'additional-params'=>false,
            'params'=>false,
            'format'=>false,
            'template'=>false,
            'layout'=>false,
            'credentials'=>false,
        ),
        $assets=array('Z'),
        $assetRequirements=[
            'Z.Form'=>'moment,pikaday-time/pikaday,pikaday-time/css/pikaday,pell/dist/pell.min',
            'Z.Graph'=>'d3/dist/d3.min,c3/c3.min',
        ],
        $assetsOptional=[
            'Z.Form'=>[
                'quill'=>'quill/dist/quill.min,quill/dist/quill.snow',
                'choices.js'=>'choices.js/public/assets/scripts/choices.min,choices.js',
            ],
        ],
        $copyNodeAssets=[
            'Z.Interface'=>'@fortawesome/fontawesome-free/webfonts/fa-solid-900.*',
            //'Z.Interface'=>'material-design-icons/iconfont/MaterialIcons-Regular.*',
            'Z.Form'=>'quill/dist/quill.min.js.map',
        ],
        $result,
        $http2push=false,
        $link;
    protected static $configMap = ['tecnodesign'=>'app'];
    protected $_o=null;

    public function __construct($s, $siteMemKey=false, $env='prod')
    {
        if ($siteMemKey) {
            Tecnodesign_Cache::siteKey($siteMemKey);
            $this->_name = $siteMemKey;
        }
        $this->start=time();
        if (!defined('TDZ_ENV')) {
            define('TDZ_ENV', $env);
        } else {
            $env = TDZ_ENV;
        }
        $this->_env = $env;
        if(is_array($s)) {
            array_unshift($s, $env);
            $this->_vars = tdz::staticCall('tdz', 'config', $s);
        } else {
            $this->_vars = tdz::config($s, $env);
        }
        unset($s);
        foreach(self::$configMap as $from=>$to) {
            if(isset($this->_vars[$from])) {
                if(!isset($this->_vars[$to])) $this->_vars[$to] = $this->_vars[$from];
                else $this->_vars[$to] += $this->_vars[$from];
                unset($this->_vars[$from]);
            }
            unset($from, $to);
        }
        $base = (isset($this->_vars['app']['apps-dir'])) ?$this->_vars['app']['apps-dir'] :null;
        if (!$base || $base === '.') {
            $base = TDZ_APP_ROOT;
            $this->_vars['app']['apps-dir'] = $base;
        }
        if(!isset($this->_vars['app']['controller-options'])) {
            $this->_vars['app']['controller-options']=self::$defaultController;
        } else {
            $this->_vars['app']['controller-options']+=self::$defaultController;
        }
        if(!isset($this->_vars['app']['routes'])) {
            $this->_vars['app']['routes']=array();
        }
        foreach ($this->_vars['app']['routes'] as $url=>$route) {
            $this->_vars['app']['routes'][$url]=$this->getRouteConfig($route);
        }
        if(isset($this->_vars['app']['default-route'])) {
            $this->_vars['app']['routes']['.*']=$this->getRouteConfig($this->_vars['app']['default-route']);
        }
        foreach ($this->_vars['app'] as $name=>$value) {
            if ((substr($name, -4)== 'root' || substr($name, -4)=='-dir') && (is_array($value) || (substr($value, 0, 1)!='/' && substr($value, 1, 1)!=':'))) {
                if(is_array($value)) {
                    foreach($value as $i=>$dvalue) {
                        if(substr($dvalue, 0, 1)!='/' && substr($dvalue, 1, 1)!=':') {
                            $save = true;
                            $this->_vars['app'][$name][$i]=str_replace('\\', '/', realpath($base.'/'.$dvalue));
                        }
                    }
                } else {
                    $save = true;
                    $this->_vars['app'][$name]=str_replace('\\', '/', realpath($base.'/'.$value));
                }
            }
            unset($name, $value);
        }
        $this->cache();
        $this->start();
        /*
        $save = false;
        if(isset($this->_vars['app']['addons']) && is_array($this->_vars['app']['addons'])) {
            foreach ($this->_vars['app']['addons'] as $addon=>$load) {
                if ($load) {
                    $save = true;
                    if (is_array($load) && isset($load['class'])) {
                        $class = $load['class'];
                        $config = $load['params'];
                    } else {
                        $class = 'Tecnodesign_App_'.ucfirst($addon);
                        $config = $load;
                    }
                    tdz::autoload($class);
                    if (class_exists($class)) {
                        if(!is_array($config) && isset($this->_vars[$config])) {
                            $config = $this->_vars[$config];
                        } else if(!is_array($config) && isset($this->_vars[$addon])) {
                            $config = $this->_vars[$addon];
                        }
                        $this->addons[$addon] = $class;
                        $this->_o[$class] = new $class($config, $this->_env);
                    }
                }
            }
        }
        if($save){
            $this->cache();
        }
        */
    }

    public static function getInstance($name=false, $env='prod', $expires=0)
    {
        $instance="{$name}/{$env}";
        $ckey="app/{$instance}";
        $app = false;
        if (!defined('TDZ_ENV')) {
            define('TDZ_ENV', $env);
        } else {
            $env = TDZ_ENV;
        }
        if (!$name) {
            if(is_null(Tecnodesign_App::$_instances)) {
                Tecnodesign_App::$_instances = new ArrayObject();
            }
            $instances = Tecnodesign_App::$_instances;
            $siteKey = Tecnodesign_Cache::siteKey();
            if ($siteKey) {
                $siteKey .= '/';
                foreach ($instances as $key=>$instance) {
                    if (substr($key, 0, strlen($siteKey))==$siteKey) {
                        return $instance;
                    }
                }
            } else {
                if(!is_array($instances)) {
                    $instances = (array)$instances;
                }
                return array_shift($instances);
            }
        }
        if(isset(Tecnodesign_App::$_instances[$instance])) {
            return Tecnodesign_App::$_instances[$instance];
        } else if(Tecnodesign_Cache::siteKey()) {
            $app = Tecnodesign_Cache::get($ckey, $expires);
            if($app) {
                Tecnodesign_App::$_instances[$instance] = $app;
            }
        }
        return $app;
    }

    public function __wakeup()
    {
        $this->start();
    }

    /**
     * Class initialization
     */
    public function start()
    {
        if(isset($this->_vars['app']['lib-dir'])) {
            $sep = (isset($_SERVER['WINDIR']))?(';'):(':');
            if(!is_array($this->_vars['app']['lib-dir'])) {
                $this->_vars['app']['lib-dir'] = explode($sep, $this->_vars['app']['lib-dir']);
            }
            foreach ($this->_vars['app']['lib-dir'] as $dir) {
                if(substr($dir, 0, 1)!='/' && substr($dir, 1, 1)!=':') {
                    $dir = $this->_vars['app']['apps-dir'].'/'.$dir;
                }
                if(!in_array($dir, tdz::$lib)) {
                    tdz::$lib[]=$dir;
                }
            }
            $libdir = ini_get('include_path').$sep.implode($sep, tdz::$lib);
            @ini_set('include_path', $libdir);
        }
        if(isset($this->_vars['app']['languages'])) {
            tdz::set('languages', $this->_vars['app']['languages']);
        }
        if(isset($this->_vars['app']['language'])) {
            tdz::$lang = $this->_vars['app']['language'];
        }
        if(isset($this->_vars['app']['document-root'])) {
            $_SERVER['DOCUMENT_ROOT'] = $this->_vars['app']['document-root'];
        }
        /*
        if(isset($this->_vars['database']) && !tdz::$database) {
            tdz::$database = $this->_vars['database'];
        }
        */
    }

    public static function end($output='', $status=200)
    {
        throw new Tecnodesign_App_End($output, $status);
    }

    /**
     * Restores a cached instance to current request
     */
    public function renew()
    {
        self::$_request=null;
        self::request();
        if(isset($this->_vars['app']['export'])) {
            foreach($this->_vars['app']['export'] as $cn=>$toExport) {
                if($cn!=='tdz' && !tdz::classFile($cn)) {
                    $cn = 'Tecnodesign_'.tdz::camelize($cn, true);
                    if(!tdz::classFile($cn)) {
                        return false;
                    }
                }
                foreach($toExport as $k=>$v) {
                    $cn::$$k=$v;
                }
            }
        }
    }


    /**
     * Stores current application config in memory
     *
     * @return bool true on success, false on error
     */
    public function cache()
    {
        if (is_null($this->_name) || !$this->_timeout) {
            return false;
        }
        $instance="{$this->_name}/{$this->_env}";
        $ckey="app/{$instance}";
        if(is_null(Tecnodesign_App::$_instances)) {
            Tecnodesign_App::$_instances = array();
        }
        Tecnodesign_App::$_instances[$instance]=$this;
        return Tecnodesign_Cache::set($ckey, $this, $this->_timeout);
    }

    public function run()
    {
        // run internals first...
        $this->renew();
        foreach(self::$beforeRun as $exec) {
            tdz::exec($exec);
        }
        try {
            // then check addons, like Symfony
            foreach ($this->addons as $addon=>$class) {
                $addonObject = $this->getObject($class);
                $m = 'run';
                if (method_exists($addonObject, $m)) {
                    $addonObject->$m();
                }
            }
            $routes = $this->_vars['app']['routes'];
            $defaults = $this->_vars['app']['controller-options'];
            $request = self::request();
            $valid = false;
            if (isset($routes[$request['script-name']])) {
                $valid = $this->runRoute($request['script-name'], $request);
            }
            if(!$valid) {
                foreach ($routes as $url=>$options) {
                    $valid = $this->runRoute($url, $request);
                    if ($valid) {
                        break;
                    }
                }
            }
            if(!$valid || !self::$_response['found']) {
                $this->runError(404, $defaults['layout']);
            }
            if (isset(self::$_response['template']) && self::$_response['template']) {
                if(!isset(self::$_response['variables'])) self::$_response['variables']=array();
                self::$_response['data']=$this->runTemplate(self::$_response['template'], self::$_response['variables'], self::$_response['cache']);
            }
            if(!isset(self::$_response['data'])) {
                self::$_response['data']=false;
            }
            self::$result=self::$_response['data'];
            if(isset(self::$_response['layout']) && self::$_response['layout']) {
                self::$result = $this->runTemplate(self::$_response['layout']);
            }
        } catch(Tecnodesign_App_End $e) {
            self::status($e->getCode());
            self::$result = $e->getMessage();
        } catch(Tecnodesign_Exception $e) {
            if($e->error) {
                tdz::log('Error in action stack: '.$e->getMessage());
                $this->runError(500, $defaults['layout']);
            } else {
                self::$result = $e->getMessage();
            }
        } catch(Exception $e) {
            tdz::log('Error in action stack: '.$e->getMessage());
            $this->runError(500, $defaults['layout']);
        }
        if(isset(tdz::$variables['exit']) && !tdz::$variables['exit']) return self::$result;
        if(!self::$_request['shell']) {
            if(!headers_sent()) {
                tdz::unflush();
                if(!isset(self::$_response['headers']['content-length'])) {
                    if (PHP_SAPI !== 'cli-server'
                        && ($enc=Tecnodesign_App::request('headers', 'accept-encoding')) && substr_count($enc, 'gzip')) {
                        @ini_set('zlib.output_compression','Off');
                        self::$result = gzencode(self::$result, 6);
                        self::$_response['headers']['content-encoding'] = (strpos($enc, 'x-gzip')!==false) ?'x-gzip' :'gzip';
                        if(!isset(self::$_response['headers']['vary'])) {
                            self::$_response['headers']['vary'] = 'accept-encoding';
                        } else {
                            self::$_response['headers']['vary'] .= ', accept-encoding';
                        }
                    }
                    self::$_response['headers']['content-length'] = strlen(self::$result);
                }
                foreach(self::$_response['headers'] as $hn=>$h) {
                    if(!is_int($hn)) {
                        header($hn.': '.$h);
                    } else {
                        header($h);
                    }
                }
                if (self::$_response['cache']) {
                    $timeout = (self::$_response['cache']>0)?(self::$_response['cache']):(3600);
                    tdz::cacheControl('public', $timeout);
                } else if(!tdz::get('cache-control')) {
                    tdz::cacheControl('no-cache, private, must-revalidate', false);
                }
            }
            if(self::$http2push && self::$link) {
                header('link: '.static::$link);
            }
            echo self::$result;
            tdz::flush();
        } else {
            echo self::$result;
        }
        // post-processing, like garbage collection, freeing memory, saving to update records, etc.
        Tecnodesign_App::afterRun();
        //exit();
    }

    public static function afterRun($exec=null, $next=false)
    {
        if($exec && $next) {
            $t=microtime(true);
            Tecnodesign_App::$afterRun[$t]=$exec;
            $nrun = Tecnodesign_Cache::get('nextRun', 0, null, true);
            if(!$nrun || !is_array($nrun)) {
                $nrun =array();
            }
            $nrun[$t] = $exec;
            Tecnodesign_Cache::set('nextRun', $nrun, 0, null, true);
        } else if($exec) {
            Tecnodesign_App::$afterRun[]=$exec;
        } else {
            $run = Tecnodesign_App::$afterRun;
            $nrun = Tecnodesign_Cache::get('nextRun', 0, null, true);
            if($nrun) {
                if(is_array($nrun)) {
                    $run = array_merge($run, $nrun);
                }
                Tecnodesign_Cache::delete('nextRun', null, true);
            }
            Tecnodesign_App::$afterRun=array();
            foreach($run as $exec) {
                tdz::exec($exec);
            }
        }
    }

    public static function status($code=200, $header=true)
    {
        // http://www.iana.org/assignments/http-status-codes/http-status-codes.xhtml
        static $status = array(
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',
            206 => 'Partial Content',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            412 => 'Precondition Failed',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
        );
        if(!isset($status[$code])) $code = 500;
        $proto = (isset($_SERVER['SERVER_PROTOCOL']))?($_SERVER['SERVER_PROTOCOL']):('HTTP/1.1');
        @header($proto.' '.$code.' '.$status[$code], true);
        return $code.' '.$status[$code];
    }

    public function runError($error, $layout=null)
    {
        @ob_clean();
        if(!self::status($error)) {
            $error = 500;
            self::status($error);
        }
        if(is_null($layout)) {
            if(isset($this->_vars['app']['controller-options']['layout'])) {
                $layout = $this->_vars['app']['controller-options']['layout'];
            }
        }
        if(tdz::templateFile('error'.$error)) self::$_response['template']='error'.$error;
        else self::$_response['template']='error';
        self::$_response['cache']=false;
        self::$_response['layout']=$layout;
        if(!isset(tdz::$variables['variables'])) tdz::$variables['variables']=array();
        if(!isset(self::$_response['variables'])) self::$_response['variables']=tdz::$variables['variables'];
        self::$_response['variables']['error'] = $error;
        self::$_response['data']=$this->runTemplate(self::$_response['template'], self::$_response['variables'], self::$_response['cache']);
        $result=self::$_response['data'];
        if(self::$_response['layout']) {
            self::$_response += tdz::$variables;
            $result = $this->runTemplate(self::$_response['layout'], self::$_response);
        }
        //@header('content-type: text/html; charset=utf-8');
        @header('content-length: '.strlen($result));
        tdz::cacheControl('no-cache, private, must-revalidate', false);
        echo $result;
        tdz::flush();
        exit();
    }

    public function runTemplate($tpl, $variables=null, $cache=false)
    {
        if($tpl && is_string($tpl) && strpos($tpl, '<')!==false) return $tpl;
        if(static::$assets) {
            static::$assets = array_unique(static::$assets);
            foreach(static::$assets as $i=>$n) {
                static::asset($n);
                unset(static::$assets[$i], $i, $n);
            }
        }
        $result = false;
        $exec = array(
            'variables' => is_array($variables) ?tdz::mergeRecursive($variables, self::$_response) :self::$_response,
            'script' => tdz::templateFile($tpl)
        );
        if($exec['script']) {
            $result=tdz::exec($exec);
        }
        return $result;
    }

    /**
     * All loaded assets should be built into TDZ_DOCUMENT_ROOT.tdz::$assetsUrl (if assetUrl is set)
     *
     * Currently they are loaded from TDZ_ROOT/src/Tecnodesign/Resources/assets but this should be evolved to a modular structure directly under src
     * and external components should also be loaded (example: font-awesome, d3 etc)
     */
    public static function asset($component)
    {
        static $loaded=array();
        if(is_null(tdz::$assetsUrl) || isset($loaded[$component])) return;
        $loaded[$component] = true;

        if(substr($component, 0, 1)=='!') {
            $component = substr($component, 1);
            $output = false;
        } else {
            $output = true;
        }

        $c0 = $component;

        if(isset(static::$assetRequirements[$c0])) {
            $component .= ','.static::$assetRequirements[$c0];
        } else if(strpos($c0, '/') && isset(static::$assetRequirements[$c1 = str_replace('/', '.', $c0)])) {
            $component .= ','.static::$assetRequirements[$c1];
            unset($c1);
        }

        if(isset(static::$assetsOptional[$c0])) {
            foreach(static::$assetsOptional[$c0] as $n=>$c1) {
                if(file_exists(TDZ_PROJECT_ROOT.'/node_modules/'.$n)) {
                    $component .= ','.$c1;
                }
                unset($n, $c1);
            }
        }

        static $types=array('js'=>'js','less'=>'css');
        static $destination=array('js'=>'script','css'=>'style');
        static $copyExt='{eot,ttf,svg,woff,png,jpg,gif}';
        $build = false;

        $projectRoot = file_exists(TDZ_APP_ROOT.'/composer.json') ?TDZ_APP_ROOT :dirname(TDZ_APP_ROOT);
        foreach($types as $from=>$to) {
            // first look for assets
            if(!isset(self::$_response[$destination[$to]])) self::$_response[$destination[$to]]=array();

            $t = null;
            $src=preg_split('/\s*\,\s*/', $component, null, PREG_SPLIT_NO_EMPTY);
            $fmod = 0;
            foreach($src as $i=>$n) {
                $n0 = preg_replace('#[\.\/].*#', '', $n);
                if(file_exists($f=TDZ_DOCUMENT_ROOT.tdz::$assetsUrl.'/'.$to.'/'.str_replace('.', '/', $n).'.'.$from)
                   || file_exists($f=TDZ_PROJECT_ROOT.'/node_modules/'.$n.'.'.$from)
                   || file_exists($f=TDZ_PROJECT_ROOT.'/node_modules/'.$n.'.'.$to)
                   || file_exists($f=TDZ_PROJECT_ROOT.'/node_modules/'.$n.'/'.$n.'.'.$from)
                   || file_exists($f=TDZ_PROJECT_ROOT.'/node_modules/'.$n.'/'.$n.'.'.$to)
                   || file_exists($f=TDZ_PROJECT_ROOT.'/node_modules/'.$n.'/'.$from.'/'.$n.'.'.$from)
                   || file_exists($f=TDZ_PROJECT_ROOT.'/node_modules/'.$n.'/'.$to.'/'.$n.'.'.$to)
                   || file_exists($f=TDZ_ROOT.'/src/Tecnodesign/Resources/assets/'.$n.'.'.$from)
                   || file_exists($f=TDZ_ROOT.'/src/'.$n.'/'.$n.'.'.$from)
                   || file_exists($f=TDZ_ROOT.'/src/'.str_replace('.', '/', $n).'.'.$from)
                   || file_exists($f=dirname(TDZ_ROOT).'/'.$n0.'/'.str_replace('.', '/', $n).'.'.$from)
                   || file_exists($f=dirname(TDZ_ROOT).'/'.$n0.'/src/'.str_replace('.', '/', $n).'.'.$from)
                   || file_exists($f=dirname(TDZ_ROOT).'/'.$n0.'/dist/'.str_replace('.', '/', $n).'.'.$from)
                   //|| file_exists($f=TDZ_PROJECT_ROOT.'/node_modules/'.$n.'/package.json')
                ) {
                    /*
                    if(substr($f, -13)=='/package.json') {
                        if(($pkg = json_decode(file_get_contents($f), true)) && isset($pkg['main']) && substr($pkg['main'], -1*strlen($to))==$to && file_exists($f2=TDZ_PROJECT_ROOT.'/node_modules/'.$n.'/'.$pkg['main'])) {
                            $f = $f2;
                        } else {
                            unset($src[$i], $f);
                            continue;
                        }
                        unset($f2, $pkg);
                    }
                    */

                    $src[$i]=$f;
                    if($t===null) {
                        $t =  tdz::$assetsUrl.'/'.tdz::slug($n).'.'.$to;
                        $tf =  TDZ_DOCUMENT_ROOT.$t;
                        if(in_array($t, self::$_response[$destination[$to]])) {
                            $t = null;
                            break;
                        }
                    }
                    if(($mod=filemtime($f)) && $mod > $fmod) $fmod = $mod;
                    unset($mod);
                } else {
                    if(tdz::$log>3) tdz::log('[DEBUG] Component '.$src[$i].' not found.');
                    unset($src[$i]);
                }
                unset($f);
            }
            if($t) { // check and build
                if(file_exists($tf) && filemtime($tf)>$fmod) {
                    $src = null;
                } else {
                    $build = true;
                }
                if($src) {
                    Tecnodesign_Studio_Asset::minify($src, TDZ_DOCUMENT_ROOT, true, true, false, $t);
                    if(!file_exists($tf)) {// && !copy($f, $tf)
                        tdz::log('[ERROR] Could not build component '.$component.': '.$tf.' from ', $src);
                    }
                }

                if($output) {
                    if($tf) $t .= '?'.date('Ymd-His', filemtime($tf));

                    if(isset(self::$_response[$destination[$to]][700])) {
                        self::$_response[$destination[$to]][] = $t;
                    } else {
                        self::$_response[$destination[$to]][700] = $t;
                    }
                }
            }
            unset($t, $tf, $from, $to);
        }

        if($build && ($files = glob(TDZ_ROOT.'/src/{'.str_replace('.', '/', $component).'}{-*,}.'.$copyExt, GLOB_BRACE))) {
            $p = strlen(TDZ_ROOT.'/src/');
            foreach($files as $source) {
                $dest = TDZ_DOCUMENT_ROOT.tdz::$assetsUrl.'/'.tdz::slug(substr($source, $p),'.');
                if(!file_exists($dest) || filemtime($dest)<filemtime($source)) {
                    copy($source, $dest);
                }
            }
            unset($files);
        }
        if($build && isset(static::$copyNodeAssets[$c0]) && ($files = glob($projectRoot.'/node_modules/'.static::$copyNodeAssets[$c0], GLOB_BRACE))) {
            foreach($files as $source) {
                $dest = TDZ_DOCUMENT_ROOT.tdz::$assetsUrl.'/'.basename($source);
                if(!file_exists($dest) || filemtime($dest)<filemtime($source)) {
                    copy($source, $dest);
                }
            }
        }
    }

    public function runRoute($url)
    {
        if(is_array($url) && isset($url['url'])) {
            $options = $url;
            $url = $options['url'];
        } else if(isset($this->_vars['app']['routes'][$url])) {
            $options = $this->_vars['app']['routes'][$url];
        } else {
            return false;
        }

        if(isset($options['url']) && $options['url']!='') {
            $url = $options['url'];
        } else {
            $options['url'] = $url;
        }

        $purl = str_replace('@', '\\@', $url);
        if(substr($url, 0, 1)==='~') {
            $pat = "@{$purl}@";
        } else if(preg_match('/[\^\$]/', $url)) {
            $pat = "@^{$purl}@";
        } else if(isset($options['additional-params']) && $options['additional-params']) {
            $pat = "@^{$purl}(/|\$)@";
        } else {
            $pat = "@^{$purl}\$@";
        }
        $purl = null;
        if (!preg_match($pat, self::$_request['script-name'], $m)) {
            return false;
        }
        if(self::$_request['shell']) {
            $m = array_merge($m, self::$_request['argv']);
        }
        $class=$options['class'];
        $method=$options['method'];
        $method=tdz::camelize($method);
        $params=array();
        // param verification
        $valid=true;
        if (isset($options['params']) && is_array($options['params'])) {
            $ps=$m;
            $pi=-1;
            $base=array_shift($ps);
            if ($options['additional-params']) {
                $ap=substr(self::$_request['self'],strlen($base));
                if(substr($ap, 0, 1)=='/') $ap = substr($ap,1);
                $ap=preg_split('#/#', $ap, null);
                $ps=array_merge($ps, $ap);
            }
            foreach ($ps as $pi=>$pv) {
                $pv = urldecode($pv);
                if (isset($options['params'][$pi])) {
                    $po=$options['params'][$pi];
                    if (!is_array($po)) {
                        $po=array('name'=>$po);
                    }
                    if (isset($po['choices']) && !is_array($po['choices'])) {
                        // expand method in $po['choices'] to an array and cache it
                        $po['choices'] = @eval('return '.$po['choices'].';');
                        if(!is_array($po['choices'])) {
                            $po['choices'] = array();
                        }
                        $this->_vars['app']['routes'][$url]['params'][$pi]['choices']=$po['choices'];
                        $this->cache();
                    }
                    if ($pv && isset($po['choices']) && !in_array($pv, $po['choices'])) {
                        // invalid param
                        $valid=false;
                        return false;
                    }
                    $params[$po['name']]=$pv;
                } else if(!$options['additional-params']) {
                    // invalid param
                    $valid=false;
                    return false;
                }
                if(!$valid) {
                    continue;
                }
                if(isset($po['append'])) {
                    if($po['append']=='method') {
                        $method.=ucfirst($pv);
                    } else if($po['append']=='class') {
                        $class.=ucfirst($pv);
                    }
                }
                if (isset($po['prepend'])) {
                    if($po['prepend']=='method') {
                        $method=$pv.ucfirst($method);
                    } else if ($po['prepend']=='class') {
                        $class=$pv.ucfirst($class);
                    }
                }
            }
            $pi++;
            while (isset($options['params'][$pi])) {
                $po=$options['params'][$pi++];
                if(!is_array($po)) {
                    $po=array('name'=>$po);
                }
                if(isset($po['required']) && $po['required']) {
                    $valid=false;
                    return false;
                    break;
                } else {
                    $params[$po['name']]=null;
                }
            }
            if (!$valid) {
                return false;
                //continue;
            }
        }
        if(isset($options['credentials']) && $options['credentials']) {
            $user = tdz::getUser();
            $forbidden = false;
            if(!$user) {
                $forbidden = true;
            } else if(is_array($options['credentials']) && !$user->hasCredential($options['credentials'])) {
                $forbidden = true;
            }
            if($forbidden) {
                $this->runError(403, $options['layout']);
                return false;
            }
        }

        self::$_request['action-name']="$class::$method";
        self::$_response['found']=true;
        self::$_response['route']=$options;
        if(isset($options['layout'])) {
            self::$_response['layout']=$options['layout'];
        }
        self::$_response['cache']=(isset($options['cache']))?($options['cache']):(false);
        if(isset($options['params']) && is_array($options['params'])) {
            self::$_response['variables']=$options['params'];
        }
        if(isset($options['static']) && $options['static']) {
            $static = true;
            $o = $class;
        } else {
            $static = false;
            $o=$this->getObject($class);
        }
        $template=false;

        if(isset($options['arguments']) && (!$params || (!isset($params[0]) || !$params[0]))) $params = $options['arguments'];
        if(method_exists($o,'preExecute')) {
            if($static) $o::preExecute($this, $params);
            else $o->preExecute($this, $params);
        }
        if(method_exists($o,$method)) {
            if($static) $template=$o::$method($params);
            else $template=$o->$method($params);
        } else {
            return false;
        }
        if(method_exists($o,'postExecute')) {
            if($static) $o::postExecute($this, $params);
            else $o->postExecute($this, $params);
        }
        if($template && is_string($template)) {
            self::$_response['template']=$template;
        } else if($template!==false) {
            self::$_response['template']=$class.'_'.$method;
        } else {
            self::$_response['template']=false;
        }
        if(isset(self::$_response['cache']) && self::$_response['cache']) {
            $this->_o[$class] = $o;
            $this->cache();
        }

        return true;
    }

    /**
     * Object loader
     *
     * Loads controller classes and stores them in memory.
     *
     * @param type $class
     * @return object
     */
    public function getObject($class)
    {
        $cache = false;
        if(is_null($this->_o)) {
            $this->_o=new ArrayObject();
        }
        if(!isset($this->_o[$class])) {
            $cache=true;
            $this->_o[$class]=new $class("{$this->_name}/{$this->_env}");
        }
        if($cache) {
            $this->cache();
        }
        return $this->_o[$class];
    }

    public function getRouteConfig($route)
    {
        if(!is_array($route)) {
            $route = array('method'=>$route);
        }
        $route += $this->_vars['app']['controller-options'];
        return $route;
    }

    /**
     * Request builder
     *
     * Might be replaced afterwards for a proper Tecnodesign_Request object
     *
     * @return array request directives
     */
    public static function request($q=null, $sub=null)
    {
        $removeExtensions=array('html', 'htm', 'php');
        if(is_null(self::$_request)) {
            self::$_response=&tdz::$variables;
            self::$_response+=array('headers'=>array(),'variables'=>array());
            if($r=tdz::getApp()->config('app', 'response')) {
                self::$_response += $r;
            }
            unset($r);
            self::$_request=array('started'=>microtime(true));
            self::$_request['shell']=TDZ_CLI;
            self::$_request['method']=(!self::$_request['shell'])?(strtolower($_SERVER['REQUEST_METHOD'])):('get');
            self::$_request['ajax']=(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']=='XMLHttpRequest');
            if (!self::$_request['shell']) {
                self::$_request['ip'] = $_SERVER['REMOTE_ADDR'];
                self::$_request['hostname']=preg_replace('/([\s\n\;]+|\:[0-9]+$)/', '', $_SERVER['HTTP_HOST']);
                self::$_request['https']=(isset($_SERVER['HTTPS']));
                if(isset($_SERVER['REQUEST_SCHEME'])) {
                    self::$_request['scheme']=$_SERVER['REQUEST_SCHEME'];
                } else {
                    self::$_request['scheme']=(self::$_request['https']) ?'https' :'http';
                }
                self::$_request['host']=self::$_request['scheme'].'://'.self::$_request['hostname'];
                if(isset($_SERVER['SERVER_PORT'])) {
                    self::$_request['port']=$_SERVER['SERVER_PORT'];
                }
                $uri = tdz::requestUri();
                $ui=@parse_url($uri);
                if(!$ui) {
                    $ui=array();
                    if(strpos($uri, '?')!==false) {
                        $ui['path']=substr($uri, 0, strpos($uri, '?'));
                        $ui['query']=substr($uri, strpos($uri, '?')+1);
                    } else {
                        $ui['path']=$uri;
                    }
                }
            } else {
                $arg = $_SERVER['argv'];
                self::$_request['shell'] = array_shift($arg);
                $uri = array_shift($arg);
                $ui=parse_url($uri);
                if(!$ui || !isset($ui['path'])) $ui = ['path'=>$uri];
                self::$_request['scheme'] = (isset($ui['scheme'])) ?$ui['scheme'] :self::$defaultScheme;
                if(!isset($ui['host'])) {
                    $ui['host'] = tdz::get('hostname');
                    if(!$ui['host']) $ui['host'] = 'localhost';
                }
                self::$_request['hostname'] = $ui['host'];
                self::$_request['host']=self::$_request['scheme'].'://'.self::$_request['hostname'];

                if(isset($ui['port'])) self::$_request['port'] = $ui['port'];
                if(isset($ui['query'])) {
                    parse_str($ui['query'], $_GET);
                }
                self::$_request['argv']=$arg;
            }
            if(isset(self::$_request['host']) && isset(self::$_request['port']) && !((self::$_request['port']=='80' && self::$_request['https']) || (self::$_request['port']=='443' && self::$_request['https'])) && substr(self::$_request['host'], -1*(strlen(self::$_request['port'])+1))!=':'.self::$_request['port']) {
                self::$_request['host'] .= ':'.self::$_request['port'];
            }

            self::$_request['query-string']=(isset($ui['query']))?($ui['query']):('');
            self::$_request['script-name']=$ui['path'];
            if (preg_match('/\.('.implode('|', $removeExtensions).')$/i', $ui['path'], $m)) {
                self::$_request['self']=substr($ui['path'],0,strlen($ui['path'])-strlen($m[0]));
                self::$_request['extension']=substr($m[0],1);
            } else {
                self::$_request['self']=$ui['path'];
            }
            self::$_request['get']=$_GET;
            // fix: apache fills up CONTENT_TYPE rather than HTTP_CONTENT_TYPE
            if(self::$_request['method']!='get' && !isset($_SERVER['HTTP_CONTENT_TYPE']) && isset($_SERVER['CONTENT_TYPE'])) {
                $_SERVER['HTTP_CONTENT_TYPE'] = $_SERVER['CONTENT_TYPE'];
            }
            if(self::$_request['method']!='get' && isset($_SERVER['HTTP_CONTENT_TYPE'])) {
                if(substr($_SERVER['HTTP_CONTENT_TYPE'],0,16)=='application/json') {
                    if($d=file_get_contents('php://input')) {
                        self::$_request['post']=json_decode($d, true);
                        if(is_null(self::$_request['post'])) {
                            self::$_request['error']['post'] = 'Invalid request body.';
                        }
                        unset($d);
                    }
                } else if(substr($_SERVER['HTTP_CONTENT_TYPE'],0,15)=='application/xml' || substr($_SERVER['HTTP_CONTENT_TYPE'],0,8)=='text/xml') {
                    if($d=file_get_contents('php://input')) {
                        $xml = simplexml_load_string($d, null, LIBXML_NOCDATA);
                        if($xml) {
                            self::$_request['post'] = (array) $xml;
                        } else {
                            self::$_request['error']['post'] = 'Invalid request body.';
                        }
                        unset($d, $xml);
                    }
                }
            }
            if(!isset(self::$_request['post'])) {
                self::$_request['post']=tdz::postData($_POST);
            }
            self::$_request = tdz::fixEncoding(self::$_request, 'UTF-8');
        }
        if($q==='headers' && !isset(self::$_request[$q])) {
            self::$_request[$q]=array();
            foreach($_SERVER as $k=>$v) {
                if(substr($k, 0, 5)=='HTTP_') {
                    self::$_request[$q][str_replace('_','-',strtolower(substr($k,5)))] = $v;
                }
                unset($k, $v);
            }
            self::$_request['headers'] = tdz::fixEncoding(self::$_request['headers'], 'UTF-8');
        }

        if($q==='cookie' && !isset(self::$_request[$q])) {
            self::$_request[$q] = [];
            if (isset($_SERVER['HTTP_COOKIE'])) {
                $rawcookies=preg_split('/\;\s*/', $_SERVER['HTTP_COOKIE'], null, PREG_SPLIT_NO_EMPTY);
                foreach ($rawcookies as $cookie) {
                    if (strpos($cookie, '=')===false) {
                        self::$_request[$q][$cookie] = true;
                        continue;
                    }
                    list($cname, $cvalue)=explode('=', $cookie, 2);
                    self::$_request[$q][trim($cname)][] = $cvalue;
                }
            }
            if(isset($_COOKIE) && $_COOKIE) {
                foreach($_COOKIE as $cname=>$cvalue) {
                    if(!isset(self::$_request[$q][trim($cname)])) {
                        self::$_request[$q][trim($cname)][] = $_COOKIE[$cname];
                    }
                }
            }
        }

        if($q) {
            if(!isset(self::$_request[$q])) return null;
            $r = self::$_request[$q];
            if($sub) {
                $args = func_get_args();
                array_shift($args);
                while(isset($args[0])) {
                    $p = array_shift($args);
                    if(!isset($r[$p])) {
                        $r = null;
                        unset($p);
                        break;
                    } else {
                        $r = $r[$p];
                    }
                    unset($p);
                }
            }
            return $r;
        }
        return self::$_request;
    }

    /**
     * Response updater
     *
     * Retrieves/Updates the response object.
     *
     * @return bool
     */
    public static function response()
    {
        $a = func_get_args();
        $an = count($a);
        if ($an==2 && !is_array($a[0])) {
            self::$_response[$a[0]]=$a[1];
        } else if($an==1 && is_array($a[0])) {
            self::$_response = tdz::mergeRecursive($a[0], self::$_response);
        } else if($an==1) {
            if(isset(self::$_response[$a[0]])) return self::$_response[$a[0]];
            else return;
        }
        return self::$_response;
    }

    public function config()
    {
        $a = func_get_args();
        $o = $this->_vars;
        $first = true;
        while($v=array_shift($a)) {
            if($first) {
                if(isset(self::$configMap[$v])) $v = self::$configMap[$v];
                $first = false;
            }
            if(isset($o[$v])) {
                $o=$o[$v];
            } else {
                $o = null;
                break;
            }
        }
        return $o;
    }

    /**
     * Magic terminator. Returns the page contents, ready for output.
     *
     * @return string page output
     */
    function __toString()
    {
        return false;
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
        if(isset(self::$configMap[$name])) $name = self::$configMap[$name];
        $m='set'.ucfirst($name);
        if (method_exists($this, $m)) {
            $this->$m($value);
        }
        $this->_vars[$name]=$value;
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
        if(isset(self::$configMap[$name])) $name = self::$configMap[$name];
        $m='get'.ucfirst($name);
        $ret = false;
        if (method_exists($this, $m)) {
            $ret = $this->$m();
        } else if (isset($this->_vars[$name])) {
            $ret = $this->_vars[$name];
        }
        return $ret;
    }
}
