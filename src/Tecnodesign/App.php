<?php
/**
 * Tecnodesign Application Server
 *
 * This package enable Tecnodesign application management.
 *
 * PHP version 5.2
 *
 * @category  App
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: App.php 1298 2013-12-12 02:33:05Z capile $
 * @link      http://tecnodz.com/
 */

/**
 * Tecnodesign Application Server
 *
 * This package enable Tecnodesign application management.
 *
 * @category  App
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
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
        $result,
        $http2push=false,
        $link;
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
        $base = $this->_vars['tecnodesign']['apps-dir'];
        if (!$base || $base == '.') {
            $base = substr(TDZ_ROOT, 0, strpos(TDZ_ROOT, '/lib/'));
            $this->_vars['tecnodesign']['apps-dir'] = $base;
        }
        if(!isset($this->_vars['tecnodesign']['controller-options'])) {
            $this->_vars['tecnodesign']['controller-options']=self::$defaultController;
        } else {
            $this->_vars['tecnodesign']['controller-options']+=self::$defaultController;
        }
        if(!isset($this->_vars['tecnodesign']['routes'])) {
            $this->_vars['tecnodesign']['routes']=array();
        }
        foreach ($this->_vars['tecnodesign']['routes'] as $url=>$route) {
            $this->_vars['tecnodesign']['routes'][$url]=$this->getRouteConfig($route);
        }
        foreach ($this->_vars['tecnodesign'] as $name=>$value) {
            if ((substr($name, -4)== 'root' || substr($name, -4)=='-dir') && (is_array($value) || (substr($value, 0, 1)!='/' && substr($value, 1, 1)!=':'))) {
                if(is_array($value)) {
                    foreach($this->_vars['tecnodesign'][$name] as $i=>$value) {
                        if(substr($value, 0, 1)!='/' && substr($value, 1, 1)!=':') {
                            $save = true;
                            $this->_vars['tecnodesign'][$name][$i]=str_replace('\\', '/', realpath($base.'/'.$value));
                        }
                    }
                } else {
                    $save = true;
                    $this->_vars['tecnodesign'][$name]=str_replace('\\', '/', realpath($base.'/'.$value));
                }
            }
        }
        $this->cache();
        $this->start();
        $save = false;
        if(isset($this->_vars['tecnodesign']['addons']) && is_array($this->_vars['tecnodesign']['addons'])) {
            foreach ($this->_vars['tecnodesign']['addons'] as $addon=>$load) {
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
        if(isset($this->_vars['tecnodesign']['lib-dir'])) {
            $sep = (isset($_SERVER['WINDIR']))?(';'):(':');
            if(!is_array($this->_vars['tecnodesign']['lib-dir'])) {
                $this->_vars['tecnodesign']['lib-dir'] = explode($sep, $this->_vars['tecnodesign']['lib-dir']);
            }
            foreach ($this->_vars['tecnodesign']['lib-dir'] as $dir) {
                if(substr($dir, 0, 1)!='/' && substr($dir, 1, 1)!=':') {
                    $dir = $this->_vars['tecnodesign']['apps-dir'].'/'.$dir;
                }
                if(!in_array($dir, tdz::$lib)) {
                    tdz::$lib[]=$dir;
                }
            }
            $libdir = ini_get('include_path').$sep.implode($sep, tdz::$lib);
            @ini_set('include_path', $libdir);
        }
        if(isset($this->_vars['tecnodesign']['languages'])) {
            tdz::set('languages', $this->_vars['tecnodesign']['languages']);
        }
        if(isset($this->_vars['tecnodesign']['language'])) {
            tdz::$lang = $this->_vars['tecnodesign']['language'];
        }
        if(isset($this->_vars['tecnodesign']['document-root'])) {
            $_SERVER['DOCUMENT_ROOT'] = $this->_vars['tecnodesign']['document-root'];
        }
        if(isset($this->_vars['database']) && !tdz::$database) {
            tdz::$database = $this->_vars['database'];
        }
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
        if(isset($this->_vars['tecnodesign']['export'])) {
            foreach($this->_vars['tecnodesign']['export'] as $cn=>$toExport) {
                if(!tdz::classFile($cn)) {
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
            $routes = $this->_vars['tecnodesign']['routes'];
            $defaults = $this->_vars['tecnodesign']['controller-options'];
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
                self::$result = $this->runTemplate(self::$_response['layout'], self::$_response);
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
                if(!isset(self::$_response['headers']['content-length'])) {
                    if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip')) {
                        self::$result = gzencode(self::$result, 9);
                        self::$_response['headers']['content-encoding'] = (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip'))?('x-gzip'):('gzip');
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
            if(isset($this->_vars['tecnodesign']['controller-options']['layout'])) {
                $layout = $this->_vars['tecnodesign']['controller-options']['layout'];
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

    public function runTemplate($tpl, $variables, $cache=false)
    {
        if($tpl && strpos($tpl, '<')!==false) return $tpl;
        if(static::$assets) {
            foreach(static::$assets as $i=>$n) {
                static::asset($n);
                unset(static::$assets[$i], $i, $n);
            }
        }
        $result = false;
        $exec = array('variables'=>$variables, 'script'=>tdz::templateFile($tpl));
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
    public function asset($component)
    {
        if(is_null(tdz::$assetsUrl)) return;

        if(substr($component, 0, 1)=='!') {
            $component = substr($component, 1);
            $output = false;
        } else {
            $output = true;
        }

        static $types=array('js'=>'js','less'=>'css');
        static $destination=array('js'=>'script','css'=>'style');

        foreach($types as $from=>$to) {
            // first look for assets
            if(!isset(tdz::$variables['variables'][$destination[$to]])) tdz::$variables['variables'][$destination[$to]]=array();

            $t = null;
            $src=preg_split('/\s*\,\s*/', $component, null, PREG_SPLIT_NO_EMPTY);
            $fmod = 0;
            foreach($src as $i=>$n) {
                $n0 = preg_replace('#[\.\/].*#', '', $n);
                if(file_exists($f=TDZ_DOCUMENT_ROOT.tdz::$assetsUrl.'/'.$to.'/'.str_replace('.', '/', $n).'.'.$from)
                   || file_exists($f=TDZ_ROOT.'/src/Tecnodesign/Resources/assets/'.$n.'.'.$from)
                   || file_exists($f=TDZ_ROOT.'/src/'.$n.'/'.$n.'.'.$from)
                   || file_exists($f=TDZ_ROOT.'/src/'.str_replace('.', '/', $n).'.'.$from)
                   || file_exists($f=dirname(TDZ_ROOT).'/'.$n0.'/'.str_replace('.', '/', $n).'.'.$from)
                   || file_exists($f=dirname(TDZ_ROOT).'/'.$n0.'/src/'.str_replace('.', '/', $n).'.'.$from)
                   || file_exists($f=dirname(TDZ_ROOT).'/'.$n0.'/dist/'.str_replace('.', '/', $n).'.'.$from)
                ) {
                    $src[$i]=$f;
                    if($t===null) {
                        $t =  tdz::$assetsUrl.'/'.tdz::slug($n).'.'.$to;
                        $tf =  TDZ_DOCUMENT_ROOT.$t;
                        if(in_array($t, tdz::$variables['variables'][$destination[$to]])) {
                            $t = null;
                            break;
                        }
                    }
                    if(($mod=filemtime($f)) && $mod > $fmod) $fmod = $mod;
                    unset($mod);
                } else {
                    unset($src[$i]);
                }
                unset($f);
            }

            if($t) { // check and build
                if(file_exists($tf) && filemtime($tf)>$fmod) $src = null;
                if($src) {
                    tdz::minify($src, TDZ_DOCUMENT_ROOT, true, true, false, $t);
                    if(!file_exists($tf)) {// && !copy($f, $tf)
                        tdz::log('[ERROR] Could not build component '.$component.': '.$tf.' from ', $src);
                    }
                }

                if($output) {
                    if($tf) $t .= '?'.date('YmdHis', filemtime($tf));

                    if(isset(tdz::$variables['variables'][$destination[$to]][700])) {
                        tdz::$variables['variables'][$destination[$to]][] = $t;
                    } else {
                        tdz::$variables['variables'][$destination[$to]][700] = $t;
                    }
                }
            }
            unset($t, $tf, $from, $to);
        }
    }


    public function runRoute($url)
    {
        if(is_array($url) && isset($url['url'])) {
            $options = $url;
            $url = $options['url'];
        } else if(isset($this->_vars['tecnodesign']['routes'][$url])) {
            $options = $this->_vars['tecnodesign']['routes'][$url];
        } else {
            return false;
        }

        if(isset($options['url']) && $options['url']!='') {
            $url = $options['url'];
        } else {
            $options['url'] = $url;
        }
        $pat=(isset($options['additional-params']) && $options['additional-params'])?("@^{$url}@i"):("@^{$url}\$@i");
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
                        $this->_vars['tecnodesign']['routes'][$url]['params'][$pi]['choices']=$po['choices'];
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
        $route += $this->_vars['tecnodesign']['controller-options'];
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
            $app = tdz::getApp();
            if(isset($app->tecnodesign['response'])) {
                self::$_response += $app->tecnodesign['response'];
            }
            self::$_request=array('started'=>microtime(true));
            self::$_request['shell']=TDZ_CLI;
            self::$_request['method']=(!self::$_request['shell'])?(strtolower($_SERVER['REQUEST_METHOD'])):('get');
            self::$_request['ajax']=(isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']=='XMLHttpRequest');
            if (!self::$_request['shell']) {
                self::$_request['ip'] = $_SERVER['REMOTE_ADDR'];
                self::$_request['hostname']=preg_replace('/[\s\n\;]+/', '', $_SERVER['HTTP_HOST']);
                self::$_request['https']=(isset($_SERVER['HTTPS']));
                self::$_request['host']=((self::$_request['https'])?('https://'):('http://')).self::$_request['hostname'];
                $ui=@parse_url($_SERVER['REQUEST_URI']);
                if(!$ui) {
                    $ui=array();
                    if(strpos($_SERVER['REQUEST_URI'], '?')!==false) {
                        $ui['path']=substr($_SERVER['REQUEST_URI'], 0, strpos($_SERVER['REQUEST_URI'], '?'));
                        $ui['query']=substr($_SERVER['REQUEST_URI'], strpos($_SERVER['REQUEST_URI'], '?')+1);
                    } else {
                        $ui['path']=$_SERVER['REQUEST_URI'];
                    }
                }
            } else {
                $arg = $_SERVER['argv'];
                self::$_request['shell'] = array_shift($arg);
                $ui = array_shift($arg);
                $ui=parse_url($ui);
                if(isset($ui['query'])) {
                    parse_str($ui['query'], $_GET);
                }
                self::$_request['argv']=$arg;
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
        }
        if($q=='headers' && !isset(self::$_request[$q])) {
            self::$_request[$q]=array();
            foreach($_SERVER as $k=>$v) {
                if(substr($k, 0, 5)=='HTTP_') {
                    self::$_request[$q][str_replace('_','-',strtolower(substr($k,5)))] = $v;
                }
                unset($k, $v);
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
        while($v=array_shift($a)) {
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