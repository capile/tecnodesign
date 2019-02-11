<?php
/**
 * Tecnodesign Framework shortcuts and multi-purpose utils
 *
 * PHP version 5.4
 *
 * @category  Core
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2018 Tecnodesign
 * @license   https://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      https://tecnodz.com/
 */
class tdz
{
    protected static
    $_app = null,
    $_env = null,
    $_connection = null,
    $values = false,
    $filters = array(),
    $script_name = null,
    $real_script_name = null,
    $encoder,
    $cp1252_map = array(
        "\xc2\x80" => "\xe2\x82\xac",
        "\xc2\x82" => "\xe2\x80\x9a",
        "\xc2\x83" => "\xc6\x92",
        "\xc2\x84" => "\xe2\x80\x9e",
        "\xc2\x85" => "\xe2\x80\xa6",
        "\xc2\x86" => "\xe2\x80\xa0",
        "\xc2\x87" => "\xe2\x80\xa1",
        "\xc2\x88" => "\xcb\x86",
        "\xc2\x89" => "\xe2\x80\xb0",
        "\xc2\x8a" => "\xc5\xa0",
        "\xc2\x8b" => "\xe2\x80\xb9",
        "\xc2\x8c" => "\xc5\x92",
        "\xc2\x8e" => "\xc5\xbd",
        "\xc2\x91" => "\xe2\x80\x98",
        "\xc2\x92" => "\xe2\x80\x99",
        "\xc2\x93" => "\xe2\x80\x9c",
        "\xc2\x94" => "\xe2\x80\x9d",
        "\xc2\x95" => "\xe2\x80\xa2",
        "\xc2\x96" => "\xe2\x80\x93",
        "\xc2\x97" => "\xe2\x80\x94",
        "\xc2\x98" => "\xcb\x9c",
        "\xc2\x99" => "\xe2\x84\xa2",
        "\xc2\x9a" => "\xc5\xa1",
        "\xc2\x9b" => "\xe2\x80\xba",
        "\xc2\x9c" => "\xc5\x93",
        "\xc2\x9e" => "\xc5\xbe",
        "\xc2\x9f" => "\xc5\xb8"
            );
    public static  
        $formats = array(
            'swf' => 'application/x-shockwave-flash',
            'pdf' => 'application/pdf',
            'exe' => 'application/octet-stream',
            'zip' => 'application/zip',
            'doc' => 'application/msword',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',
            'gif' => 'image/gif',
            'png' => 'image/png',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'rar' => 'application/rar',
            'ra' => 'audio/x-pn-realaudio',
            'ram' => 'audio/x-pn-realaudio',
            'ogg' => 'audio/x-pn-realaudio',
            'wav' => 'audio/x-wav',
            'wmv' => 'video/x-msvideo',
            'avi' => 'video/x-msvideo',
            'asf' => 'video/x-msvideo',
            'divx' => 'video/x-msvideo',
            'mp3' => 'audio/mpeg',
            'mp4' => 'video/mp4',
            'mpeg' => 'video/mpeg',
            'webm' => 'video/webm',
            'mpg' => 'video/mpeg',
            'mpe' => 'video/mpeg',
            'mov' => 'video/quicktime',
            'swf' => 'video/quicktime',
            '3gp' => 'video/quicktime',
            'm4a' => 'video/quicktime',
            'aac' => 'video/quicktime',
            'm3u' => 'video/quicktime',
            'js'  => 'application/javascript',
            'css' => 'text/css',
            'txt' => 'text/plain',
            'htc' => 'text/plain',
            'md'  => 'text/plain',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ttf'  => 'application/x-font-ttf',
            'woff' => 'application/x-font-woff',
            'woff2'=> 'application/x-font-woff2',
            'eot'  => 'application/vnd.ms-fontobject',
            'ttf'  => 'font/ttf',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'eot'  => 'font/vnd.ms-fontobject',
        ),
        $browsers = array(
            'webtv'=>'Web TV',
            'trident/7.0; rv:11.0'=>'Internet Explorer',
            'microsoft internet explorer'=>'Internet Explorer',
            'opera mini'=>'Opera Mini',
            'opera'=>'Opera',
            'msie'=>'Internet Explorer',
            'galeon'=>'Galeon',
            'firefox'=>'Firefox', // after safari?
            'chrome'=>'Google Chrome',
            'omniweb'=>'Omniweb',
            'android'=>'Android',
            'ipad'=>'iPad',
            'ipod'=>'iPod',
            'iphone'=>'iPhone',
            'blackberry'=>'BlackBerry',
            'nokia'=>'Nokia',
            'googlebot'=>'Googlebot',
            'msnbot'=>'MSN Bot',
            'bingbot'=>'Bing bot',
            'slurp'=>'Slurp',
            'facebookEexternalhit'=>'Facebook',
            'safari'=>'Safari',
            'netpositive'=>'NetPositive',
            'firebird'=>'Firebird',
            'konqueror'=>'Konqueror',
            'icab'=>'ICab',
            'phoenix'=>'Phoenix',
            'amaya'=>'Amaya',
            'lynx'=>'Lynx',
            'iceweasel'=>'Iceweasel',
            'w3c-checklink'=>'W3C',
            'w3c_validator'=>'W3C',
            'w3c-mobileok'=>'W3C',
            'mozilla'=>'Mozilla'
        ),
        $lib = null,
        $lang = 'en',
        $format = 'text/html',
        $timeout = 0,
        $assetsUrl = '/_assets',
        $async = true,
        $variables = array(),
        $minifier = array(),
        $paths=array(
            'cat'=>'/bin/cat',
            'java'=>'/usr/bin/java',
        ),
        $dateFormat='d/m/Y',
        $timeFormat='H:i',
        $decimalSeparator=',',
        $thousandSeparator='.',
        $connection,
        $connectRetries=3,
        $perfmon=0,
        $autoload,
        $tplDir,
        $translator='Tecnodesign_Translate::message',
        $markdown='Tecnodesign_Markdown',
        $database,
        $log,
        $logDir,
        $noeval
        ;
    
    /**
     * Application Startup, see Tecnodesign_App
     * 
     * @param mixed  $s          configuration file name or its contents parsed
     * @param string $siteMemKey name of the application, used to create a virtual space in memory
     * @param type $env          environment. used to retrieve configuration parameters
     * 
     * @return Tecnodesign_App 
     */
    public static function app($s, $siteMemKey=false, $env='prod')
    {
        if ($siteMemKey) {
            tdz::$_app = $siteMemKey;
            tdz::$_env = $env;
            Tecnodesign_Cache::siteKey($siteMemKey);
            if (!is_array($s) && file_exists($s)) {
                tdz::$timeout = filemtime($s);
                $cache = Tecnodesign_App::getInstance($siteMemKey, $env, tdz::$timeout);
                if ($cache) {
                    return $cache;
                }
            }
        }
        return new Tecnodesign_App($s, $siteMemKey, $env);
    }
    
    /**
     * Current application retrieval
     * 
     * @return Tecnodesign_App
     */
    public static function getApp()
    {
        return Tecnodesign_App::getInstance(tdz::$_app, tdz::$_env);
    }

    public static function appConfig()
    {
        return @tdz::objectCall(tdz::getApp(), 'config', func_get_args());
    }

    /**
     * Current user retrieval
     * 
     * @return Tecnodesign_User
     */
    public static function getUser()
    {
        static $cn;
        if(is_null($cn)) {
            $cn = tdz::getApp()->config('user', 'className');
            if(!$cn) $cn = 'Tecnodesign_User';
        }
        return $cn::getCurrent();
    }

    /**
     * User authentication and management shortcuts
     */
    public static function user($uid=null)
    {
        if(!is_null($uid)) {

        }
        return tdz::getUser();
    }

    /**
     * PDO Connection class. Uses $app configuration if $db connection parameters 
     * are not sent.
     * 
     * @param mixed           $db   PDO dsn, username and password arguments. Optionally
     *                              the $app database name can be provided.
     * @param Tecnodesign_App $app  failback Tecnodesign_Application to look at configuration
     * @return PDO 
     */
    public static function connect($db=false, $app=null, $throw=false)
    {
        if(is_null(tdz::$_connection)) {
            tdz::$_connection = array();
        }
        if(!is_array($db)) {
            if(is_object($db)) {
                if($db instanceof PDO) {
                    return $db;
                }
                $db='';
            }
            $name = (string)$db;
            if(isset(tdz::$_connection[$name])) {
                //tdz::$_connection[$name] = null;
                return tdz::$_connection[$name];
            }

            if(is_null(tdz::$database)) {
                if(is_null($app)) {
                    $app = tdz::getApp();
                }
                if($app && $app->database) {
                    tdz::$database = $app->database;
                }

                if(!tdz::$database) {
                    if($dbo=Tecnodesign_Cache::get('tdz/connect/'.$name, true)) {
                        $db = $dbo;
                        unset($dbo);
                    } else if(file_exists($f=TDZ_APP_ROOT.'/config/databases.yml')) {
                        $C = Tecnodesign_Yaml::load($f);
                        tdz::$database = array();
                        if(isset($C[tdz::$_env])) {
                            tdz::$database = $C[tdz::$_env]; 
                        }
                        if(isset($C['all'])) {
                            tdz::$database += $C['all']; 
                        }
                        unset($C);
                    }
                }
            }
            if(isset(tdz::$database[$name])) {
                $db = tdz::$database[$name];
                Tecnodesign_Cache::set('tdz/connect/'.$name, $db, true);
            }
            if(!is_array($db)) {
                if($db && isset(tdz::$database[$db])) {
                    $db = tdz::$database[$db];
                } else {
                    $db=array_values(tdz::$database);
                    if(!isset($db['dsn'])) {
                        $db = array_shift($db);
                    }
                }
            }
        } else {
            $name = md5(implode(':',$db));
            if(isset(tdz::$_connection[$name])) {
                //tdz::$_connection[$name] = null;
                return tdz::$_connection[$name];
            }
        }
        if(!is_array($db)) {
            return false;
        }
        $params = array('username'=>null, 'password'=>null, 'dsn'=>'', 'options'=>array());
        $mysql=false;
        $mssql=false;
        $pgsql=false;
        if(isset($db['dsn'])) {
            if(substr($db['dsn'], 0, 6)=='mysql:') {
                $mysql=true;
                $params['options'][PDO::MYSQL_ATTR_INIT_COMMAND] = 'SET NAMES utf8';
                $params['options'][PDO::ATTR_PERSISTENT] = true;
            } else if(substr($db['dsn'], 0, 7)=='sqlite:') {
                if(!isset($app)) {
                    $app = tdz::getApp();
                }
                $db['dsn'] = str_replace(array('$APPS_DIR', '$DATA_DIR'), array($app->tecnodesign['apps-dir'], $app->tecnodesign['data-dir']), $db['dsn']);
            } else if(substr($db['dsn'], 0, 6)=='dblib:') {
                $mssql=true;
            }
        }
        $db += $params;
        $db['options'][PDO::ATTR_ERRMODE]=PDO::ERRMODE_EXCEPTION;
        try
        {
            $conn=@new PDO($db['dsn'],$db['username'],$db['password'], $db['options']);
            self::$connection = $name;
            if($mysql && isset($db['options'][PDO::MYSQL_ATTR_INIT_COMMAND])) {
                $conn->exec('SET CHARACTER SET utf8');
            }
            if(isset($db['command'])) {
                if(!is_array($db['command'])) $db['command']=array($db['command']);
                foreach($db['command'] as $q) {
                    @$conn->exec($q);
                }
            }
            tdz::$_connection[$name]=$conn;
            if(!isset(tdz::$_connection[''])) {
                tdz::$_connection['']=$conn;
            }
            
        } catch(PDOException $e) {
            $msg = $e->getMessage();
            tdz::log('Error in '.__METHOD__.":\n  ".$msg.'('.$e->getLine().')');
            if(self::$connectRetries--) {
                tdz::log('retrying...');
                return tdz::connect($name, $app, $throw);
            }
            if($throw) {
                throw new Tecnodesign_Exception(array(tdz::t('Could not connect to database. Reasons are: %s', 'exception'), $msg));
            }
            return false;
        }
        return $conn;
    }

    public static function setConnection($name=false, $dbh=null)
    {
        if(is_null(tdz::$_connection)) {
            if(is_null($dbh)) {
                return $dbh;
            }
            tdz::$_connection = array();
        }
        $ret = null;
        if(isset(tdz::$_connection[$name])) {
            $ret = tdz::$_connection[$name];
        }
        tdz::$_connection[$name] = $dbh;
        return $ret;
    }

    
    /**
     * Translator shortcut
     * 
     * @param mixed  $message message or array of messages to be translated
     * @param string $table   translation file to be used
     * @param string $to      destination language, defaults to tdz::$lang
     * @param string $from    original language, defaults to 'en'
     */
    public static function t($message, $table=null, $to=null, $from=null)
    {
        list($cn, $m) = explode('::', tdz::$translator);
        return $cn::$m($message, $table, $to, $from);
    }
    
    /**
     * Shortcut for SQL Queries
     *
     * @param string $sql consluta a ser realizada
     *
     * @return array resultados com os valores associados
     */
    public static function query($sql)
    {
        $ret = array();
        $sqls = (is_array($sql))?($sql):(array($sql));
        $arg = func_get_args();
        try {
            foreach($sqls as $sql) {
                $conn=tdz::connect();
                if (!$conn) {
                    throw new Tecnodesign_Exception(tdz::t('Could not connect to database server.'));
                }
                $query = $conn->query($sql);
                $result=array();
                if ($query && count($arg)==1) {
                    if(preg_match('/^\s*(insert|update|delete|replace|set|begin|commit|create|alter|drop) /i', $sql)) $result = true;
                    else $result = @$query->fetchAll(PDO::FETCH_ASSOC);
                } else if($query) {
                    array_shift($arg);
                    $result = call_user_func_array(array($query, 'fetchAll'), $arg);
                }
                if(!isset($ret[0])) {
                    $ret = $result;
                } else if(isset($result[0])) {
                    $ret = array_merge($ret, $result);
                }
            }
        } catch(Exception $e) {
            tdz::log('Error in '.__METHOD__.":\n  ".$e->getMessage()."\n {$sql}");
            return false;
        }
        return $ret;
    }
    
    
    /**
     * Configuration loader
     * 
     * loads cascading configuration files.
     * 
     * Syntax: tdz::config($env='prod', $section=null, $cfg1, $cfg2...)
     * 
     * @return array Configuration
     */
    public static function config()
    {
        $a = func_get_args();
        $res = array();
        $env = 'prod';
        $envs = array('dev', 'prod', 'test', 'stage', 'maint');
        $section = false;
        foreach($a as $k=>$v)
        {
            if(is_object($v)) {
                $v = (array)$v;
            }
            if (is_array($v) || substr($v, -4)=='.yml') {
                continue;
            } else if (in_array($v, $envs)) {
                $env = $v;
            } else {
                $section = $v;
            }
            unset($a[$k]);
        }
        $configs = array();
        foreach ($a as $s) {
            if (!is_array($s)) {
                $s = Tecnodesign_Yaml::load($s);

                if (!is_array($s)) {
                    continue;
                }
                if ($section) {
                    if(isset($s[$env][$section])) {
                        $configs[] = $s[$env][$section];
                    }
                    if(isset($s['all'][$section])) {
                        $configs[] = $s['all'][$section];
                    }
                } else {
                    if(isset($s[$env])) {
                        $configs[] = $s[$env];
                    }
                    if(isset($s['all'])) {
                        $configs[] = $s['all'];
                    }
                }
            } else {
                $configs[] = $s;
            }
        }
        $res = call_user_func_array ('tdz::mergeRecursive', $configs);
        return $res;
    }

    public static function replace($s, $r, $r2=null)
    {
        if(is_array($s)) {
            foreach($s as $k=>$v) {
                $s[$k] = tdz::replace($v, $r);
            }
        } else if(is_null($r2)) {
            $s = strtr($s, $r);
        } else {
            $s = str_replace($r, $r2, $s);
        }
        return $s;
    }
    
    public static function mergeRecursive()
    {
        $a = func_get_args();
        $res = array_shift($a);
        foreach($a as $args) {
            if(!is_array($res)) {
                $res = $a;
            } else {
                foreach($args as $k=>$v) {
                    if(!isset($res[$k])) {
                        $res[$k] = $v;
                    } else if(is_array($res[$k]) && is_array($v)) {
                        $res[$k] = tdz::mergeRecursive($v, $res[$k]);
                    }
                }
            }
        }
        return $res;
    }
    
    
    /**
     * Request method to get current script name. May act as a setter if a string is
     * passed. Also returns absolute script name (according to $_SERVER[REQUEST_URI])
     * if true is passed.
     *
     * @return string current script name
     */
    public static function scriptName()
    {
        $a = func_get_args();
        if (isset($a[0])) {
            if($a[0]===false) {
                tdz::$real_script_name=null;
                $a[0]=true;
            }            
            if($a[0] === true) {
                if (is_null(tdz::$real_script_name)) {
                    if(isset($_SERVER['REDIRECT_STATUS']) && $_SERVER['REDIRECT_STATUS']=='200' && isset($_SERVER['REDIRECT_URL'])) {
                        tdz::$real_script_name = $_SERVER['REDIRECT_URL'];
                    } else if (isset($_SERVER['REQUEST_URI'])) {
                        tdz::$real_script_name = $_SERVER['REQUEST_URI'];
                    } else {
                        tdz::$real_script_name = '';
                    }
                    // remove extensions
                    if(!isset($a[1]) || $a[1]) {
                        tdz::$real_script_name = preg_replace('#\.(php|html?)(/.*)?$#i', '$2', tdz::$real_script_name);
                    }
                    $qspos = strpos(tdz::$real_script_name, '?');
                    if($qspos!==false) {
                        tdz::$real_script_name = substr(tdz::$real_script_name, 0, $qspos);
                    }
                    unset($qspos);
                }
                return tdz::$real_script_name;
            } else if(is_string($a[0]) && substr($a[0], 0, 1) == '/') {
                $qspos = strpos($a[0], '?');
                if($qspos!==false) {
                    $a[0] = substr($a[0], 0, $qspos);
                }
                unset($qspos);
                tdz::$script_name = $a[0];
                if(isset($a[2]) && $a[2]===true) 
                    tdz::$real_script_name = $a[0];
            }
        } else if (is_null(tdz::$script_name)) {
            tdz::$script_name = tdz::scriptName(true);
        }
        return tdz::$script_name;
    }


    /**
     * CSS Parser: for applying css rules everywhere!
     */
    public static function parseCss($s)
    {
        $css = array();
        if(is_string($s)) {
            preg_match_all('/(.+?)\s?\{\s?(.+?)\s?\}/', $s, $m);
            foreach($m[0] as $i=>$r) $css[$m[1][$i]] = $m[2][$i];
        } else $css = $s;
        foreach($css as $i=>$r) {
            if(!is_array($r)) {
                $css[$i]=array();
                foreach(explode(';', $r) as $attr) {
                    if (strlen(trim($attr)) > 0) {// for missing semicolon on last element, which is legal
                        list($name, $value) = explode(':', $attr);
                        $css[$i][trim($name)] = trim($value);
                    }
                }
            }
            // break margin, padding & border into each position
            $ba=array('margin', 'padding', 'border');
            foreach($ba as $a) {
                if(isset($css[$i][$a])) {
                    $bp = ($a=='border')?(array($css[$i][$a])):(preg_split('/\s+/', $css[$i][$a], null, PREG_SPLIT_NO_EMPTY));
                    $p=array();
                    if(count($bp)==1) {
                        $p[$a.'-top']=$p[$a.'-right']=$p[$a.'-bottom']=$p[$a.'-left']=$bp[0];
                    } else if(count($bp)==2) {
                        $p[$a.'-top']=$p[$a.'-bottom']=$bp[0];
                        $p[$a.'-right']=$p[$a.'-left']=$bp[1];
                    } else if(count($bp)==3) {
                        list($p[$a.'-top'],$p[$a.'-right'],$p[$a.'-bottom'])=$bp;
                    } else {
                        list($p[$a.'-top'],$p[$a.'-right'],$p[$a.'-bottom'],$p[$a.'-left'])=$bp;
                    }
                    unset($css[$i][$a]);
                    $css[$i]+=$p;
                }
            }
        }
        return $css;
    }

    
    /**
     * Compress Javascript & CSS
     */
    public static function minify($s, $root=false, $compress=true, $before=true, $raw=false, $output=false)
    {
        if($root===false) {
            $root = TDZ_DOCUMENT_ROOT;
        }
        // search for static files to compress
        $types = array(
          'js'=>array('pat'=>'#<script [^>]*src="([^"\?\:]+)"[^>]*>\s*</script>#', 'tpl'=>'<script type="text/javascript"'.((tdz::$async)?(' async="async"'):('')).' src="[[url]]"></script>'),
          'css'=>array('pat'=>'#<link [^>]*type="text/css"[^>]*href="([^"\?\:]+)"[^>]*>#', 'tpl'=>'<link rel="stylesheet" type="text/css" href="[[url]]" />'),
        );
        if(!is_array($s) && strpos($s, '<')===false) {
            $s = array($s);
        }
        $s0=$s;
        $sa = '';
        $components=array();
        if(is_array($s)) {
            $f=$s;
            $s='';
            foreach($f as $i=>$url){
                if(!$url) {
                    unset($f[$i]);
                    continue;
                }
                if($raw) {
                    continue;
                }
                if(strpos($url, '<')!==false) {
                    $sa .= $url;
                    continue;
                }
                $type = (substr($url, -5)=='.less' || substr($url, -4)=='.css' || strpos($url, '.css?')!==false)?('css'):('js');
                if(substr($url, 0, 1)!='/' && strpos($url, ':')===false) {
                    $url = tdz::$assetsUrl.'/'.$url;
                } else if($output && is_string($output) && file_exists($url)) {
                    $components[$type][$url]=filemtime($url);
                    $url = $output;
                    continue;
                }
                if($type=='css'){
                    $tpl = $types['css']['tpl'];
                } else {
                    $tpl = $types['js']['tpl'];
                }
                $s .= str_replace('[[url]]', tdz::xml($url), $tpl);
            }
        }
        if($compress && !file_exists(tdz::$paths['java'])) {
            $compress = false;
        }
        foreach($types as $type=>$o) {
            $files=(isset($components[$type]))?($components[$type]):(array());
            if($raw) {
                $ext = '.'.$type;
                foreach($f as $i=>$url){
                    if(substr($url, -1 * strlen($ext))==$ext) {
                        if(file_exists($url) && substr($url, 0, strlen($root))==$root) {
                            $files[substr($url, strlen($root))]=filemtime($url);
                        } else if(file_exists($root.$url)) {
                            $files[$url]=filemtime($root.$url);
                        }
                    }
                }
            } else {
                $lc=false;
                if(preg_match_all($o['pat'], $s, $m)) {
                    foreach($m[1] as $i=>$url) {
                        if(file_exists($root.$url)) {
                            $css=$root.$url;
                            $files[$url]=filemtime($css);
                            $s = str_replace($m[0][$i], '', $s);
                        }
                    }
                }
            }
            if(count($files)>0) {
                $fname = md5(implode(array_keys($files)));
                $url = self::$assetsUrl.'/'.$fname.'.'.$type;
                if($output) {
                    if(self::$assetsUrl && substr($output, 0, strlen(self::$assetsUrl))==self::$assetsUrl) {
                        $file = $root.$output;
                        $url = $output;
                    } else {
                        $file=$output;
                    }
                } else if(substr($root, 0, strlen(TDZ_ROOT))==TDZ_ROOT) {
                    $file = TDZ_VAR.'/cache/minify/'.basename($url);
                } else {
                    $file = $root.$url;
                }
                $time = max($files);
                $build = (!file_exists($file) || filemtime($file) < $time);
                $fs=array_keys($files);
                foreach($fs as $fk=>$fv) {
                    if(substr($fv, 0, strlen(self::$assetsUrl))==self::$assetsUrl || file_exists($root.$fv)) {
                        $fv = $fs[$fk]=$root.$fv;
                    }

                    if(substr($fv, -5)=='.less') {
                        $css = $fv.'.css';
                        if(!file_exists($css)) $css = TDZ_VAR.'/min-'.md5($fv).'-'.basename($fv);
                        if(!file_exists($css) || filemtime($css) < filemtime($fv)) {
                            // compile less
                            if(!$lc && class_exists('lessc')) {
                                $lc = new lessc();
                                $lc->setVariables(array('assets-url'=>'"'.self::$assetsUrl.'"'));
                                $imp = array(dirname($fv).'/',$root);
                                if($root!=TDZ_DOCUMENT_ROOT && is_dir($d=TDZ_DOCUMENT_ROOT.self::$assetsUrl.'/css/') && $imp[0]!=$d) array_unshift($imp, $d);
                                if(is_dir($d=$root.self::$assetsUrl.'/css/') && $imp[0]!=$d) array_unshift($imp, $d);
                                $lc->setImportDir($imp);
                                $lc->registerFunction('dechex', function($a){
                                    return dechex($a[1]);
                                });
                            }
                            $lc->checkedCompile($fv, $css);
                        }
                        if(file_exists($css)) {
                            $fs[$fk] = $css;
                        }
                    }
                }
                if($compress && $build){
                    // try yui compressor
                    $dir = dirname($file);
                    if(!is_dir($dir)) {
                        mkdir($dir, 0777, true);
                    }
                    $tempnam = tempnam(dirname($file), '._');
                    if(isset(tdz::$minifier[$type])) {
                        $cmd = sprintf(tdz::$minifier[$type], implode(' ',$fs), $tempnam);
                    } else {
                        $cmd = tdz::$paths['cat'].' '.implode(' ',$fs).' | '.tdz::$paths['java'].' -jar '.dirname(TDZ_ROOT).'/yuicompressor/yuicompressor.jar --nomunge --type '.$type.' -o '.$tempnam;
                    }
                    exec($cmd, $cmdoutput, $ret);
                    if(file_exists($tempnam) && filesize($tempnam)>0) {
                        $build = false;
                        rename($tempnam, $file);
                        chmod($file, 0666);
                        unset($tempnam);
                    } else {
                        tdz::log('[WARN] Minifying script failed: '.$cmd, $cmdoutput);
                    }
                    unset($cmdoutput, $ret);
                }
                if($output===true) {
                    return (file_exists($output) && filemtime($output) > $time);
                }
                if($build){
                    $js = '';
                    foreach($files as $fname => $ftime) {
                        $js .= file_get_contents($fname);
                    }
                }
                $url .= '?'.date('YmdHis', $time);
                if($raw) {
                    $s .= ($build)?($js):(file_get_contents($file));
                } else {
                    $s = ($before)?(str_replace('[[url]]', $url, $o['tpl']).$s):($s.str_replace('[[url]]', $url, $o['tpl']));
                }
            }
        }
        $s .= $sa;
        if(!$raw) {
            $s = preg_replace('/>\s+</', '><', trim($s));
        }
        if($output===true) {
            return file_exists($output);
        }
        return $s;
    }
    
    public static function og()
    {
    }

    /**
     * Camelizes strings as class names
     * 
     * @param string $s
     * @return string Camelized Class name
     */
    public static function camelize($s, $ucfirst=false)
    {
        $cn = str_replace(' ', '', ucwords(preg_replace('/[^a-z0-9A-Z]+/', ' ', $s)));
        if(!$ucfirst) {
            $cn = lcfirst($cn);
        }
        return $cn;
    }

    /**
     * Uncamelizes strings as underscore_separated_names
     * 
     * @param string $s
     * @return string Uncamelized function/table name
     */
    public static function uncamelize($s)
    {
        if(preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $s, $m)) {
            $ret = $m[0];
            unset($m);
            foreach ($ret as $k=>$m) {
                $ret[$k] = ($m == strtoupper($m))?(strtolower($m)):(lcfirst($m));
            }
            return implode('_', $ret);
        } else {
            return $s;
        }
    }

    public static function implode($v, $del=',')
    {
        if(is_array($v)) {
            $r = '';
            foreach($v as $a) {
                $r .= (($r)?($del):('')).tdz::implode($a, $del);
                unset($a);
            }
            return $r;
        } else {
            return (string) $v;
        }
    }

    public static function xmlImplode($v, $element='span', $printElement=true)
    {
        if(is_array($v)) {
            $r = '';
            foreach($v as $e=>$a) {
                if(!is_numeric($e) && $printElement) {
                    $r .= '<'.$element.'>'.tdz::xml($e).': ';
                } else {
                    $r .= '<'.$element.((!is_numeric($e))?(' data-element="'.tdz::xml($e).'"'):('')).'>';
                }
                $r .= tdz::xmlImplode($a, $element, $printElement).'</'.$element.'>';
                unset($a, $e);
            }
            return $r;
        } else {
            return tdz::xml((string)$v);
        }
    }
    
    public static function requestUri($arg=array())
    {
        $qs = '';
        if (!empty($arg)) {
            array_walk(
                $arg, create_function(
                    '&$v,$k', '$v = urlencode($k)."=".urlencode($v);'
                )
            );
            $qs = implode("&", $arg);
        }
        $uri = '';
        if(isset($_SERVER['REDIRECT_STATUS']) && $_SERVER['REDIRECT_STATUS']=='200' && isset($_SERVER['REDIRECT_URL'])) {
            $uri = $_SERVER['REDIRECT_URL'];
            if(isset($_SERVER['REDIRECT_QUERY_STRING'])) {
                $uri .= '?'.$_SERVER['REDIRECT_QUERY_STRING'];
            }
        } else if (isset($_SERVER['REQUEST_URI'])) {
            $uri = $_SERVER['REQUEST_URI'];
        } else {
            $uri = tdz::scriptName(true);
        }
        if ($qs!='') {
            if (strpos($uri, '?')!==false) {
                if (strpos($uri, $qs)===false) {
                    $uri .= '&' . $qs;
                }
            } else {
                $uri .= '?' . $qs;
            }
        }
        return $uri;
    }
    public static function getRequestUri($arg=array())
    {
        return tdz::requestUri($arg);
    }

    public static function getUrlParams($url=false, $unescape=false)
    {
        return tdz::urlParams($url, $unescape);
    }

    public static function urlParams($url=false, $unescape=false)
    {
        if($url===false || is_null($url)) $url = tdz::scriptName();
        if($url=='/') $url='';
        $fullurl = tdz::scriptName(true);
        $urlp = array();
        if ($fullurl!='/' && $url != $fullurl && substr($fullurl, 0, strlen($url) + 1) == $url . '/') {
            $urlp = explode('/', substr($fullurl, strlen($url) + 1));
        }
        if($unescape) {
            foreach($urlp as $i=>$v) {
                $urlp[$i] = urldecode($v);
                unset($i, $v);
            }
        }
        return $urlp;
    }

    public static function blendColors($c1, $c2, $i=0.1)
    {
        if(substr($c1, 0, 1)=='#')$c1=substr($c1,1);
        if(substr($c2, 0, 1)=='#')$c2=substr($c2,1);
        $c1a=array( hexdec(substr($c1,0,2)), hexdec(substr($c1,2,2)), hexdec(substr($c1,4,2)) );
        $c2a=array( hexdec(substr($c2,0,2)), hexdec(substr($c2,2,2)), hexdec(substr($c2,4,2)) );
        $c=array( 
            substr('0'.dechex(round($c1a[0]+(($c2a[0] - $c1a[0])*$i))),-2), 
            substr('0'.dechex(round($c1a[1]+(($c2a[1] - $c1a[1])*$i))),-2),
            substr('0'.dechex(round($c1a[2]+(($c2a[2] - $c1a[2])*$i))),-2),
        );
        return '#'.implode('', $c);
    }

    public static function getVariables()
    {
        return tdz::$variables;
    }

    public static function get($key)
    {
        if (isset(tdz::$variables[$key])) {
            return tdz::$variables[$key];
        } else {
            return false;
        }
    }
    public static function set($key, $value)
    {
        tdz::$variables[$key]=$value;
    }

    public static function getTitle()
    {
        tdz::set('title-replace', true);
        return '<h1>[[title]]</h1>';
    }

    public static function isMobile()
    {
        $useragent = (isset($_SERVER['HTTP_USER_AGENT'])) ? 
                        ($_SERVER['HTTP_USER_AGENT']) : ('');
        $ssearch1 = '/android|avantgo|blackberry|blazer|compal|elaine|fennec|'.
                    'hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|'.
                    'mmp|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|'.
                    'plucker|pocket|psp|symbian|treo|up\.(browser|link)|'.
                    'vodafone|wap|windows (ce|phone)|xda|xiino/i';
        $ssearch2 = '/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|'.
                    'ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|'.
                    'ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|'.
                    'bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|'.
                    'cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|'.
                    'dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|'.
                    'er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|'.
                    'gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|'.
                    'hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|'.
                    'hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|'.
                    'im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|'.
                    'kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|'.
                    '50|54|e\-|e\/|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|'.
                    'ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(di|rc|ri)|mi(o8|oa|ts)|'.
                    'mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|'.
                    'mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|'.
                    'ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|'.
                    'owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|'.
                    'pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|'.
                    'qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|'.
                    'ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|'.
                    'se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|'.
                    'sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|'.
                    'sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|'.
                    'tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|'.
                    'up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|'.
                    'vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|'.
                    'webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|xda(\-|2|g)|yas\-|your|'.
                    'zeto|zte\-/i';
        return (preg_match($ssearch1, $useragent)
                || preg_match($ssearch2, substr($useragent, 0, 4)));
    }

    public static function sql($str, $enclose=true)
    {
        if(is_array($str)) {
            foreach($str as $k=>$v){
                $str[$k]=tdz::sql($v, $enclose);
            }
            return $str;
        }
        $s = array('\\', "'");
        $r = array('\\\\', "''");
        $str = str_replace($s, $r, $str);
        $str = ($enclose) ? ("'{$str}'") : ($str);
        return $str;
    }
    public static function sqlEscape($str, $enclose=true) {return tdz::sql($str, $enclose);}

    /**
     * XML Escaping
     * 
     * Use this method to print content inside HTML/XML tags and attributes.
     * 
     * @param string $s text to be escaped
     * @param bool   $q escape quotes as well (defaults to true)
     * 
     * @return string escaped string
     */
    public static function xml($s, $q=true)
    {
        if (is_array($s)) {
            foreach ($s as $k => $v) {
                $s[$k] = tdz::xml($v, $q);
            }
            return $s;
        }
        $qs = ($q) ? (ENT_QUOTES) : (ENT_NOQUOTES);
        return htmlspecialchars(html_entity_decode($s, $qs, 'UTF-8'), $qs, 'UTF-8', false);
    }
    public static function xmlEscape($s, $q=true) {return tdz::xml($s, $q);}

    public static function browser($s=null)
    {
        if(is_null($s)) $s = $_SERVER['HTTP_USER_AGENT'];
        $s = strtolower($s);

        foreach(tdz::$browsers as $c=>$r) {
            if(strpos($s, $c)!==false) return $r;
        }
    }
    
    public static function render($d, $scope=null, $class='tdz-render', $translate=false, $xmlEscape=true)
    {
        $cn = false;
        if(is_object($d) && $d instanceof Tecnodesign_Model) {
            $o = $d;
            $cn = get_class($o);
            $d = (is_array($scope))?($scope):($cn::columns($scope));
            if(!$d) $d = array_keys($cn::$schema['columns']);
            $class .= ' caption';
        }
        $s = '<table'.(($class)?(' class="'.$class.'"'):('')).'>'
            . (($cn)?('<caption>'.(string) $o.'</caption>'):(''))
            . '<tbody>';
        foreach($d as $label=>$v) {
            if($cn) {
                if(is_integer($label)) {
                    $label = $cn::fieldLabel($fn, false);
                }
                $v = $o->renderField($v, null, $xmlEscape);
            } else {
                if($translate) {
                    $label = tdz::t(ucwords(str_replace(array('_', '-'), ' ', $label)));
                }
                if($xmlEscape) {
                    $v = str_replace(array('  ', "\n"), array('&#160; ', '<br />'), tdz::xmlEscape($v));
                }
            }
            $s .= '<tr><th scope="row">'.$label.'</th><td>'.$v.'</td></tr>';
        }
        $s .= '</tbody></table>';
        return $s;
    }

    public static function cleanCache($prefix='')
    {
        $cf = tdz::getApp()->tecnodesign['cache'] . '/' . $prefix;
        $cf.='.*';
        foreach (glob($cf) as $f) {
            @unlink($f);
        }
    }

    public static function meta($s='', $og=false)
    {
        if(is_array($s)) $s = implode('', $s);
        if (!isset(tdz::$variables['meta'])) {
            tdz::$variables['meta'] = $s;
        } else {
            if(is_array(tdz::$variables['meta'])) tdz::$variables['meta'] = implode('',tdz::$variables['meta']);
            if($s) {
                tdz::$variables['meta'].=$s;
            } else if(isset(tdz::$variables['variables']['meta'])) {
                if(is_array(tdz::$variables['variables']['meta'])) {
                    tdz::$variables['meta'].= implode('',tdz::$variables['variables']['meta']);
                } else {
                    tdz::$variables['meta'].= trim(tdz::$variables['variables']['meta']);
                }
                unset(tdz::$variables['variables']['meta']);
            }
        }
        if($og && !strpos(tdz::$variables['meta'], '<meta property="og:')) tdz::$variables['meta'] .= tdz::openGraph();
        return tdz::$variables['meta'];
    }

    public static function openGraph($args=array())
    {
        $exists = true;
        if(!isset(tdz::$variables['open-graph'])) {
            if(($app=tdz::getApp()) && isset($app->tecnodesign['open-graph'])) {
                $og = $app->tecnodesign['open-graph'];
            } else {
                $og = array();
            }
            if(isset(tdz::$variables['variables']['open-graph']) && is_array(tdz::$variables['variables']['open-graph'])) {
                $og = array_merge($og, tdz::$variables['variables']['open-graph']);
            }
            $e = tdz::get('entry');
            if($e && is_object($e)) {
                if($e->title)   $og['title'] = $e->title;
                if($e->link)    $og['url']   = $e->link;
                if($e->summary) $og['description'] = $e->summary;
            }
            tdz::$variables['open-graph'] = $og;
            $exists = false;
        } else {
            $og = tdz::$variables['open-graph'];
        }
        if(!is_array($args)) {
            return $og;
        }
        if ($args && is_array($args)) {
            if($exists) {
                foreach ($args as $k=>$v) {
                    if (!empty($v) && $k!='image') {
                        $og[$k]=$v;
                    } else if (!empty($v)) {
                        if (isset($og[$k]) && !is_array($og[$k])) {
                            $og[$k]=array($og[$k]);
                        }
                        if (is_array($v)) {
                            $og[$k] = array_merge($og[$k], $v);
                        } else {
                            $og[$k][]=$v;
                        }
                    }
                }
                $og += $args;
            } else {
                if(isset($args['image']) && $args['image']=='') {
                    unset($args['image']);
                }
                $args+=$og;
                $og = $args;
            }
            tdz::$variables['open-graph'] = $og;
        }
        $s = '';
        $gs='';
        $tw=array();
        $urls = array('image','video','url');
        $gplus=array('title'=>'name', 'description'=>'description', 'image'=>'image');
        //$twitter=array('image'=>'image');
        //$twitter=array('title'=>'title','description'=>'description','image'=>'image');
        foreach ($og as $k=>$v) {
            if (!is_array($v)) {
                $v=array($v);
            }
            if(substr($k, 0, 6)=='image:')continue;
            $tag = (strpos($k, ':')) ? ($k) : ('og:'.$k);
            foreach ($v as $i=>$m) {
                if (in_array($k, $urls) && substr($m, 0, 4)!='http') {
                    $m = tdz::buildUrl($m);
                }
                $m = tdz::xmlEscape($m);
                $s .= "\n<meta property=\"{$tag}\" content=\"{$m}\" />";
                if($k=='image' && isset($og['image:width'])) {
                    $s .= "\n<meta property=\"{$tag}:url\" content=\"{$m}\" />";
                    if(is_array($og['image:width'])) {
                        $s .= "\n<meta property=\"{$tag}:width\" content=\"{$og['image:width'][$i]}\" />";
                    } else {
                        $s .= "\n<meta property=\"{$tag}:width\" content=\"{$og['image:width']}\" />";
                    }
                    if(isset($og['image:height'])) {
                        if(is_array($og['image:height'])) {
                            $s .= "\n<meta property=\"{$tag}:height\" content=\"{$og['image:height'][$i]}\" />";
                        } else {
                            $s .= "\n<meta property=\"{$tag}:height\" content=\"{$og['image:height']}\" />";
                        }
                    }
                }
                /*
                if(isset($gplus[$k])) {
                    $gs .= "\n<meta itemprop=\"{$gplus[$k]}\" content=\"{$m}\" />";
                }
                */
                /*
                if(isset($twitter[$k]) && !isset($tw[$k])) {
                    $tw[$k] = "\n<meta itemprop=\"twitter:{$twitter[$k]}\" content=\"{$m}\" />";
                }
                */
            }
        }
        //if($tw) $s .= implode('', $tw);
        $s .= $gs;
        return $s;
    }
    public static function exec($a)
    {
        $script_name = false;
        if (!is_null(tdz::$script_name)) {
            $script_name = $_SERVER['SCRIPT_NAME'];
            $_SERVER['SCRIPT_NAME'] = tdz::$script_name;
        }
        if (isset($a['variables']) && is_object($a['variables'])) {
            $class = get_class($a['variables']);
            $$class = $a['variables'];
            if ($a['variables'] instanceof sfDoctrineRecord) {
                $a['variables'] = $a['variables']->getData();
            }
        }
        if (isset($a['variables']) && is_array($a['variables'])) {
            foreach ($a['variables'] as $var => $value) {
                $$var = $value;
            }
        }
        $tdzres = '';
        if(isset($a['callback'])) {
            if(isset($a['arguments'])) {
                $tdzres .= call_user_func_array($a['callback'], $a['arguments']); 
            } else {
                $tdzres .= call_user_func($a['callback']); 
            }
        }
        ob_start();
        if (isset($a['pi']) && $a['pi']) {
            if(tdz::$noeval) {
                $pi = tempnam(TDZ_VAR, 'tdzapp');
                file_put_contents($pi, '<?php '.$a['pi']);
                include $pi;
                $tdzres.=ob_get_contents();
                unlink($pi);
            } else {
                $tdzres.=eval($a['pi']);
                if(!$tdzres) $tdzres.=ob_get_contents();
            }
        }

        if (isset($a['script']) && substr($a['script'], -4) == '.php') {
            $script_name = str_replace('/../', '/', $a['script']);
            include $script_name;
            $tdzres.=ob_get_contents();
        };

        ob_end_clean();
        
        if ($script_name) {
            $_SERVER['SCRIPT_NAME'] = $script_name;
        }

        return $tdzres;
    }

    public static function isempty($a)
    {
        return is_null($a) || $a===false || $a==='' || $a===array();
    }

    public static function fixEncoding($s, $encoding='UTF-8')
    {
        if(tdz::$encoder===false) {
            return $s;
        } else if (is_array($s)) {
            foreach ($s as $k => $v) {
                $s[$k] = tdz::fixEncoding($v, $encoding);
            }
        } else if(is_string($s)) {
            $s0 = $s;
            if(tdz::$encoder) {
                $m = tdz::$encoder;
                $s = $m($s, $encoding);
                unset($m);
            } else {
                $s=iconv($encoding, "{$encoding}//IGNORE", $s);
            }
            if($s===false) {
                $s = $s0;
            }
            unset($s0);
        }
        return $s;
    }

    public static function encode($s)
    {
        $s = tdz::encodeUTF8($s);
        return $s;
    }

    public static function encodeUTF8($s)
    {
        if (is_array($s)) {
            foreach ($s as $k => $v) {
                $s[$k] = tdz::encodeUTF8($v);
            }
        } else {
            $s=strtr(utf8_encode($s), tdz::$cp1252_map);
        }
        return $s;
    }

    public static function decode($s)
    {
        return tdz::encodeLatin1($s);
    }

    public static function encodeLatin1($s)
    {
        if (is_array($s)) {
            foreach ($s as $k => $v) {
                $s[$k] = tdz::encodeLatin1($v);
            }
        } else {
            $s=utf8_decode(strtr($s, array_flip(tdz::$cp1252_map)));
        }
        return $s;
    }

    public static function getBrowserCache($etag, $lastModified, $expires=false)
    {
        @header(
            'last-modified: '.
            gmdate("D, d M Y H:i:s", $lastModified) . ' GMT'
        );
        $cacheControl = tdz::cacheControl(null, $expires);
        if ($expires && $cacheControl=='public') {
            @header(
                'expires: '.
                gmdate("D, d M Y H:i:s", time() + $expires) . ' GMT'
            );
        }
        @header('etag: "'.$etag.'"');

        $if_none_match = isset($_SERVER['HTTP_IF_NONE_MATCH']) ?
                stripslashes($_SERVER['HTTP_IF_NONE_MATCH']) :
                false;

        $if_modified_since = isset($_SERVER['HTTP_IF_MODIFIED_SINCE']) ?
                strtotime(stripslashes($_SERVER['HTTP_IF_MODIFIED_SINCE'])) :
                false;

        if (!$if_modified_since && !$if_none_match) {
            return;
        }
        if ($if_none_match && $if_none_match != $etag 
            && $if_none_match != '"' . $etag . '"'
        ) { 
            return; // etag is there but doesn't match
        }
        if ($if_modified_since && $if_modified_since != $lastModified) {
            return; // if-modified-since is there but doesn't match
        }
        /**
         * Nothing has changed since their last request - serve a 304 and exit
         */
        Tecnodesign_App::status(304);
        if(tdz::getApp()) {
            Tecnodesign_App::afterRun();
        }
        exit();    }

    public static function redirect($url='', $temporary=false)
    {
        $url = ($url == '') ? (tdz::scriptName()) : ($url);
        $status = ($temporary)?(302):(301);

        if (!preg_match('/\:\/\//', $url)) {
            $url = tdz::buildUrl($url);
        }
        $str = "<html><head><meta http-equiv=\"Refresh\" content=\"0;".
               "URL={$url}\"></head><body><body></html>";

        Tecnodesign_App::status($status);
        @header('location: '.$url, true, $status);
        @header('cache-control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
        @header('content-length: '.strlen($str));

        echo $str;
        tdz::flush();

        if(tdz::getApp()) {
            Tecnodesign_App::afterRun();
        }
        exit();
    }

    public static function flush()
    {
        @ob_end_flush();
        flush();
        if(function_exists('fastcgi_finish_request'))
            @fastcgi_finish_request();
    }

    public static function unflush()
    {
        $i=10;
        while(ob_get_level()>0 && $i--){
            ob_end_clean();
        }
    }

    public static function pages($pager, $uri=false, $maxpages=10, $tpl=array())
    {
        if ($uri === false) {
            $uri = tdz::scriptName(true);
        }
        if (!is_array($pager)) {
            $po = $pager;
            if($pager instanceof Tecnodesign_Collection) {
            } else {
                $pager = array(
                    'page' => $pager->getPage(),
                    'last-page' => $pager->getLastPage(),
                    'pages' => $pager->getLinks($maxpages),
                );
            }
        }
        if (!isset($pager['pages'])) {
            $firstp = ($pager['page'] > ceil($maxpages * .5)) ? 
                    ($pager['page'] - ceil($maxpages * .5)) : (1);
            if ($firstp + $maxpages > $pager['last-page']) {
                $firstp = $pager['last-page'] - $maxpages + 1;
            }
            if ($firstp < 1) {
                $firstp = 1;
            }
            $pager['pages'] = array();
            for ($i = 0; $i < $maxpages; $i++) {
                $page = $firstp + $i;
                $pager['pages'][] = $page;
                if ($page >= $pager['last-page']) {
                    break;
                }
            }
        }
        $html = '';
        $tpl+=array(
            'first' => '*first',
            'last' => '*last',
            'next' => '*next &#8594;',
            'previous' => '*&#8592; previous',
        );
        foreach($tpl as $k=>$v) {
            if(substr($v, 0, 1)=='*') {
                $tpl[$k]=tdz::t(substr($v,1));
            }
        }
        if ($pager['last-page'] > 1) {
            @list($uri,$qs) = explode('?', $uri, 2);
            if($qs) {
                $qs = preg_replace('#&?p=[0-9]*#', '', $qs);
                if(substr($qs, 0, 1)=='&') $qs = substr($qs,1);
            }
            $uri .= ($qs)?('?'.$qs.'&p='):('?p=');
            if ($pager['page'] != 1) {
                $html .= '<li class="previous"><a href="'.tdz::xmlEscape($uri).($pager['page'] - 1).
                        '">'.$tpl['previous'].'</a></li>';
                $html .= '<li class="first"><a href="'.tdz::xmlEscape($uri).'1">'.
                        $tpl['first'].'</a></li>';
            }
            foreach ($pager['pages'] as $page) {
                if ($page == $pager['page']) {
                    $html .= '<li><a href="'.tdz::xmlEscape($uri).$page.'"><strong>'.
                        $page.'</strong></a></li>';
                } else {
                    $html .= '<li><a href="'.tdz::xmlEscape($uri).$page.'">'.$page.'</a></li>';
                }
            }

            if ($pager['page'] != $pager['last-page']) {
                $html .= '<li class="last"><a href="'.tdz::xmlEscape($uri).$pager['last-page'].
                        '">' . $tpl['last'] . '</a></li>';
                $html .= '<li class="next"><a href="'.tdz::xmlEscape($uri).($pager['page'] + 1).
                        '">'.$tpl['next'].'</a></li>';
            }
            $html = '<ul class="pagination">'.$html.'</ul>';
        }
        return $html;
    }

    public static function fileFormat($file)
    {
        $ext = strtolower(
            preg_replace(
                '/.*\.([a-z0-9]{1,5})$/i', '$1',
                basename($file)
            )
        );
        if (isset(tdz::$formats[$ext])) {
            $format = tdz::$formats[$ext];
        } else if (is_file($file) && class_exists('finfo')) {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $format = $finfo->file($file);
        } else if (function_exists('mime_content_type')) {
            $format = @mime_content_type($file);
        } else {
            $format = false;
        }

        return $format;
    }

    public static function output($s, $format=null, $exit=true)
    {
        tdz::unflush();
        if($format=='json') {
            if(!is_string($s)) {
                $s = json_encode($s, JSON_FORCE_OBJECT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }
            $format = 'application/json; charset=UTF8';
        }
        
        if ($format != '') {
            @header('content-type: ' . $format);
        } else {
            @header('content-type: text/html; charset=UTF8');
        }
        @header('content-length: ' . strlen($s));
        echo $s;
        tdz::flush();
        if ($exit) {
            exit();
        }
    }
    
    public static function cacheControl($set=null, $expires=null)
    {
        if(!is_null($set) && is_string($set)) {
            tdz::set('cache-control', $set);
            $cacheControl = $set;
        } else {
            $cacheControl = tdz::get('cache-control');
        }
        if(!$cacheControl) {
            $cacheControl = 'private, must-revalidate';
            tdz::set('cache-control', $cacheControl);
        }
        if(!is_null($expires)) {
            $expires = (int)$expires;
            $cc = preg_replace('/\,.*/', '', $cacheControl);

            if (function_exists('header_remove')) {
                header_remove('cache-control');
                header_remove('pragma');
            }
            @header('cache-control: '.$cacheControl.', max-age='.$expires.', s-maxage='.$expires);
        }
        return $cacheControl;
    }

    public static function download($file, $format=null, $fname=null, $speed=0, $attachment=false, $nocache=false, $exit=true)
    {
        if (connection_status() != 0 || !$file)
            return(false);
        if(!$fname && $attachment) $fname = basename($file);
        $extension = strtolower(preg_replace('/.*\.([a-z0-9]{1,5})$/i', '$1',$fname));
        tdz::unflush();

        if(!file_exists($file)) {
            if($exit) exit();
            else return false;
        }
        $expires = (tdz::env()=='dev')?(false):(3600);
        if (isset($_GET['t']))
            $expires = (int)$_GET['t'];
        $lastmod = filemtime($file);
        if ($format != '')
            @header('content-type: ' . $format);
        else {
            if($fname) $format=tdz::fileFormat($fname);
            else $format=tdz::fileFormat($file);
            if ($format)
                @header('content-type: ' . $format);
        }
        $gzip = false;
        if (substr($format, 0, 5) == 'text/' && isset($_SERVER['HTTP_ACCEPT_ENCODING']) && substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip'))
            $gzip = true;
        if (substr($format, 0, 5) == 'text/')
            header('vary: Accept-Encoding', false);
        if ($nocache) {
            header('cache-control: no-cache, no-store, max-age=0, must-revalidate');
            header('expires: Thu, 11 Oct 2007 05:00:00 GMT'); // Date in the past
        } else {
            tdz::getBrowserCache(md5_file($file) . (($gzip) ? (';gzip') : ('')), $lastmod, $expires);
        }
        @header('content-transfer-encoding: binary');

        if ($attachment) {
            $contentDisposition = 'attachment';
            /* extensions to stream */
            $array_listen = array('mp3', 'm3u', 'm4a', 'mid', 'ogg', 'ra', 'ram', 'wm',
                'wav', 'wma', 'aac', '3gp', 'avi', 'mov', 'mp4', 'mpeg', 'mpg', 'swf', 'wmv', 'divx', 'asf');
            if (in_array($extension, $array_listen))
                $contentDisposition = 'inline';
            if (strstr($_SERVER['HTTP_USER_AGENT'], "MSIE")) {
                $fname = preg_replace('/\./', '%2e', $fname, substr_count($fname, '.') - 1);
                @header("content-disposition: $contentDisposition; filename=\"$fname\"");
            } else {
                @header("content-disposition: $contentDisposition; filename=\"$fname\"");
            }
        } else if($fname) {
            @header("content-disposition: filename=\"$fname\"");
        }
        if ($gzip) {
            $gzf=TDZ_VAR . '/cache/download/' . md5_file($file);
            if (!file_exists($gzf) || filemtime($gzf) > $lastmod) {                
                $s = file_get_contents($file);
                $gz = gzencode($s, 9);
                tdz::save($gzf, $gz, true);                
            }
            $gze = 'gzip';
            if (strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'x-gzip') !== false)
                $gze = 'x-gzip';
            header('content-encoding: ' . $gze);
            $file = $gzf;
        }
        $size = filesize($file);
        $range='';
        if(!isset($_SERVER['HTTP_X_REAL_IP'])) {
            //check if http_range is sent by browser (or download manager)
            if (isset($_SERVER['HTTP_RANGE'])) {
                list($size_unit, $range_orig) = explode('=', $_SERVER['HTTP_RANGE'], 2);
                if ($size_unit == 'bytes') {
                    //multiple ranges could be specified at the same time, but for simplicity only serve the first range
                    //http://tools.ietf.org/id/draft-ietf-http-range-retrieval-00.txt
                    $range = preg_replace('/\,*$/', '', $range_orig);
                    //list($range, $extra_ranges) = explode(',', $range_orig, 2);
                }
            }
            header('accept-ranges: bytes');
        }

        //figure out download piece from range (if set)
        if ($range)
            list($seek_start, $seek_end) = explode('-', $range, 2);

        //set start and end based on range (if set), else set defaults
        //also check for invalid ranges.
        $seek_end = (empty($seek_end)) ? ($size - 1) : min(abs(intval($seek_end)), ($size - 1));
        $seek_start = (empty($seek_start) || $seek_end < abs(intval($seek_start))) ? 0 : max(abs(intval($seek_start)), 0);

        //Only send partial content header if downloading a piece of the file (IE workaround)
        if ($seek_start > 0 || $seek_end < ($size - 1)) {
            Tecnodesign_App::status(206);
            header('content-range: bytes ' . $seek_start . '-' . $seek_end . '/' . $size);
        }
        header('content-length: ' . ($seek_end - $seek_start + 1));
        header('x-accel-buffering: no');


        //open the file
        $fp = fopen($file, 'rb');
        //seek to start of missing part
        fseek($fp, $seek_start);

        //start buffered download
        $left = $seek_end - $seek_start + 1;
        while ($left>0 && !feof($fp)) {
            //reset time limit for big files
            $chunk = 1024 * 8;
            if ($chunk > $left) {
                $chunk = $left;
                $left = 0;
            }
            $left -= $chunk;
            set_time_limit(0);
            print(fread($fp, $chunk));
            //print(fread($fp, $seek_end - $seek_start + 1));
        }
        tdz::flush();

        fclose($fp);
        if($exit) {
            Tecnodesign_App::afterRun();
            exit;
        }
    }

    public static function resize($img, &$o=array())
    {
        $img = new Tecnodesign_Image($img, $o);
        $imgd=$img->render();
        if(!$img || !$img->type) return false;
        $o['content-type'] = $img->mimeType();
        return $img->render();
    }

    public static function validUrl($s='')
    {
        $s = trim($s);
        if($s != '') {
            if (substr($s, 0, 1) != '/') $s = "/{$s}";
            $s = preg_replace('#\.\.+#', '.', $s);
            $s = preg_replace('#\.+([^a-z0-9-_])#i', '$1', $s);
            $s = tdz::slug($s, '_./', true);
            //$s = html_entity_decode(preg_replace('/&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);/i', '$1', htmlentities($s, ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8');
            //$s = preg_replace('/[^a-z0-9-_\.\/]+/i', '-', $s);
            $s = preg_replace('/-?\/-?/', '/', $s);
            $s = preg_replace('#//+#', '/', $s);
            $s = preg_replace('/-+/', '-', $s);
            $s = preg_replace('/^-|-$/', '', $s);
            $s = preg_replace('/([^\/])\/+$/', '$1', $s);
            return $s;
        }
        return $s;
    }

    public static function uploadDir($d=null)
    {
        if(!is_null($d) && $d) {
            tdz::$variables['upload-dir'] = $d;
        }
        if(!isset(tdz::$variables['upload-dir'])) {
            tdz::$variables['upload-dir'] = TDZ_VAR.'/upload';
            if($app=tdz::getApp() && isset($app->tecnodesign['upload-dir'])) {
                tdz::$variables['upload-dir'] = $app->tecnodesign['upload-dir'];
            }
        }
        return tdz::$variables['upload-dir'];
    }
    
    public static function postData($post=null)
    {
        $nf = (!is_null($post))?($post):($_POST);
        if(count($_FILES) >0) {
            $nf = array($nf);
            foreach($_FILES as $fn=>$fd) {
                foreach($fd as $up=>$f) {
                    $nf[][$fn] = tdz::setLastKey($f, $up, (isset($nf[0][$fn]) && $nf[0][$fn])?($nf[0][$fn]):(null));
                }
            }
            $nf = call_user_func_array('tdz::mergeRecursive', $nf);
        }
        return $nf;
    }

    protected static function setLastKey($a, $name, $post=null) {
        if(is_array($a)) {
            foreach($a as $k=>$v) {
                $a[$k]=tdz::setLastKey($v, $name);
                if($post && is_array($post) && isset($post[$k])) {
                    $a[$k]['_'] = $post[$k];
                }
            }
        } else {
            $a = array($name=>$a);
            if($post) $a[$name]['_'] = $post;
        }
        return $a;
    }
    

    /**
     * Debugging method
     *
     * Simple method to debug values - just outputs the value as text. The script
     * should end unless $end = FALSE is passed as param
     *
     * @param   mixed $var value to be displayed
     * @param   bool  $end should be FALSE to avoid the script termination
     *
     * @return  string text output of the $var definition
     */
    public static function debug()
    {
        $arg = func_get_args();
        if (!headers_sent())
            @header('content-type: text/plain;charset=UTF-8');
        foreach ($arg as $k => $v) {
            if ($v === false)
                return false;
            print_r(tdz::toString($v));
            echo "\n";
        }
        exit();
    }

    /**
     * Error messages logger
     *
     * Pretty print the objects to the PHP's error_log
     *
     * @param   mixed  $var  value to be displayed
     *
     * @return  void
     */
    public static function log()
    {
        static $trace;
        $logs = array();
        $d = (!is_array(tdz::$logDir))?(array(tdz::$logDir)):(tdz::$logDir);
        foreach($d as $l) {
            if($l=='syslog' && openlog('tdz', LOG_PID|LOG_NDELAY, LOG_LOCAL5)) {
                $logs['syslog'] = true;
            } else if($l=='error_log') {
                $logs[0] = true;
            } else if($l=='cli') {
                if(TDZ_CLI) $logs[2] = true;
            } else {
                if(!$l) {
                    if(tdz::$_app && tdz::$_env && ($app=tdz::getApp()) && isset($app->tecnodesign['log-dir'])) {
                        $l = $app->tecnodesign['log-dir'];
                        unset($app);
                    }
                    if(!$l) {
                        $l = TDZ_VAR . '/log';
                    }
                }
                if(substr($l, 0, 1)!='/') $l = realpath(TDZ_APP_ROOT.'/'.$l);
                $logs[3] = $l . '/tdz.log';
            }
            unset($l);
        }
        unset($d);

        foreach (func_get_args() as $k => $v) {
            if($v===true) {
                // enable trace
                $trace = true;
                continue;
            } else if($v===false) {
                $trace = false;
                continue;
            }
            if(!$trace) {
                $v = tdz::toString($v);
                if(isset($logs['syslog'])) {
                    $l = LOG_INFO;
                    if(substr($v, 0, 4)=='[ERR') $l = LOG_ERR;
                    else if(substr($v, 0, 5)=='[WARN') $l = LOG_WARNING;
                    else $l = LOG_INFO;
                    syslog($l, $v);
                }
                if(isset($logs[3])) {
                    error_log($v, 3, $logs[3]);
                }
                if(isset($logs[0])) {
                    error_log($v, 0);
                }
                if(isset($logs[2])) {
                    echo $v;
                }
            } else {
                try {
                    throw new Exception(tdz::toString($v));
                } catch(Exception $e) {
                    $v = (string)$e;
                    if(isset($logs['syslog'])) {
                        $l = LOG_INFO;
                        if(substr($v, 0, 4)=='[ERR') $l = LOG_ERR;
                        else if(substr($v, 0, 5)=='[WARN') $l = LOG_WARNING;
                        else $l = LOG_INFO;
                        syslog($l, $v);
                    }
                    if(isset($logs[3])) {
                        error_log($v, 3, $logs[3]);
                    }
                    if(isset($logs[0])) {
                        error_log($v, 0);
                    }
                    if(isset($logs[2])) {
                        echo $v;
                    }
                    unset($e);
                }
            }
            unset($v, $k);
        }

        if(isset($logs['syslog'])) {
            closelog();
        }
        unset($logs);
    }

    public static function toString($o, $i=0)
    {
        $s = '';
        $id = str_repeat(" ", $i++);
        if (is_object($o)) {
            $s .= $id . get_class($o) . ":\n";
            $id = str_repeat(" ", $i++);
            if (method_exists($o, 'getData'))
                $o = $o->getData();
        }
        if (is_array($o) || is_object($o)) {
            $proc = false;
            foreach ($o as $k => $v) {
                $proc = true;
                $s .= $id . $k . ": ";
                if (is_array($v) || is_object($v))
                    $s .= "\n" . tdz::toString($v, $i);
                else
                    $s .= $v . "\n";
            }
            if (!$proc && is_object($o))
                $s .= $id . (string) $o;
        }
        else
            $s .= $id . $o;
        return $s . "\n";
    }

    public static function serialize($a, $hint=null)
    {
        if(!is_null($hint)) {
            if($hint=='yaml') return Tecnodesign_Yaml::dump($a);
            else if($hint=='json') return json_encode($a,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
            else if($hint=='php') return serialize($a);
        }
        return (is_object($a))?(serialize($a)):(json_encode($a,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE));
    }

    public static function unserialize($a, $hint=null)
    {
        if(is_string($a)) {
            if(!is_null($hint)) {
                if($hint=='yaml') return Tecnodesign_Yaml::load($a);
                else if($hint=='json') return json_decode($a,true);
                else if($hint=='php') return unserialize($a);
            }
            return (substr($a,1,1)==':' && strpos('aOidsN', substr($a,0,1))!==false)?(unserialize($a)):(json_decode($a,true));
        }
    }

    /**
     * Text to Slug
     * 
     * @param string $str Text to convert to slug
     *
     * @return string slug
     */
    public static function slug($s, $accept='_', $anycase=null)
    {
        $s = html_entity_decode(preg_replace('/&([a-z]{1,2})(?:acute|cedil|circ|grave|lig|orn|ring|slash|th|tilde|uml);/i', '$1', htmlentities($s, ENT_QUOTES, 'UTF-8')), ENT_QUOTES, 'UTF-8');
        if($accept) {
            $accept = preg_replace('/([^a-z0-9])/i', '\\\$1', $accept);
        }
        $s = trim(preg_replace('/[^0-9a-z'.$accept.']+/i', '-', $s), '-');

        return ($anycase)?($s):(strtolower($s));
    }

    public static function textToSlug($str, $accept=''){return tdz::slug($str, $accept);}
    
    public static function timeToNumber($t)
    {
        $t = explode(':', $t);
        $i=1;
        $r=0;
        foreach($t as $p) {
            $r += ((int) $p)/$i;
            $i = $i*60;
        }
        return $r;
    }

    public static function numberToLetter($i) { return tdz::letter($i); }
    public static function letter($i)
    {
        $n='';
        if(!is_int($i)) $i=(int)$i;
        if($i<0) $i=0;
        while($i>25) {
            $ni=floor($i/26) -1;
            $i=$i%26;
            if($ni>25) {
                $i = $i + (26 * floor($ni/26));
                $ni = $ni%26;
            }
            $n.=chr(97+$ni);
        }
        $n .= chr(97+$i);
        return $n;
    }

    /**
     * Format bytes for humans
     *
     * @param float   $bytes     value to be formatted
     * @param integer $precision decimal units to use
     *
     * @return string formatted string
     */
    public static function formatBytes($bytes, $precision=2) {return tdz::bytes($bytes, $precision);}
    public static function bytes($bytes, $precision=2)
    {
        $units = array('B', 'Kb', 'Mb', 'Gb', 'Tb');

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
    
    public static function formatNumber($number, $decimals=2) {return tdz::number($number, $decimals);}
    public static function number($number, $decimals=2)
    {
        return number_format($number, $decimals, self::$decimalSeparator, self::$thousandSeparator);
    }

    public static function formatTable($arr, $arg=array()) {return tdz::table($arr, $arg);}
    public static function table($arr, $arg=array())
    {
        $class = (isset($arg['class'])) ? (" class=\"{$arg['class']}\"") : ('');
        $s = '<table cellpadding="0" cellspacing="0" border="0"' . $class . '><tbody>';
        $class = 'odd';
        $empty = (isset($arg['hide_empty'])) ? ($arg['hide_empty']) : (false);
        $ll = false;
        foreach ($arr as $label => $value) {
            if ($value === false) {
                if ($ll !== false)
                    $s = str_replace($ll, '', $s);

                $ll = '<tr><th colspan="2" class="legend">' . $label . '</th></tr>';
                $s .= $ll;
                $class = 'odd';
            }
            else if ($empty && trim(strip_tags($value)) == '') {
                
            } else {
                $ll = false;
                $s .= '<tr class="' . $class . '"><th>' . $label . '</th><td>' . $value . '</td></tr>';
                $class = ($class == 'even') ? ('odd') : ('even');
            }
        }
        if ($ll !== false)
            $s = str_replace($ll, '', $s);
        $s .= '</tbody></table>';
        return $s;
    }

    public static function markdown($s)
    {
        static $P;
        if(is_null($P)) {
            $cn = self::$markdown;
            if(!class_exists($cn)) {
                if(file_exists($f=TDZ_ROOT.'/src/'.$cn.'/'.$cn.'.php')) {
                    require_once $f;
                    unset($f);
                }
            }
            $P = new $cn();
        }
        return $P->text($s);
    }

    public static function text($s)
    {
        static $c;
        if(is_null($c)) {
            if(!class_exists('League\HTMLToMarkdown\HtmlConverter')) {
                $c = false;
            } else {
                $c = new League\HTMLToMarkdown\HtmlConverter();
            }
        }
        $r = strip_tags(($c)?($c->convert($s)):(str_replace(array('</p>','</div>'), "\n", $s)));
        if(!$r && $s) {
            $r = strip_tags(html_entity_decode($s));
        }
        return $r;
    }

    public static function safeHtml($s)
    {
        return preg_replace('#<(/?[a-z][a-z0-9\:\-]*)(\s|[a-z0-9\-\_]+\=("[^"]*"|\'[^\']*\')|[^>]*)*(/?)>#i', '<$1$2>', strip_tags($s, '<p><ul><li><ol><table><th><td><br><br/><div><strong><em><details><summary>'));
    }
    
    public static function buildUrl($url, $parts=array())
    {
        if (!is_array($url)) {
            $url = parse_url($url);
        }
        if(!isset($_SERVER['SERVER_PORT'])) {
            $_SERVER += array('SERVER_PORT'=>'80', 'HTTP_HOST'=>'localhost');
        }
        $url += $parts;
        $url += array(
            'scheme' => ($_SERVER['SERVER_PORT'] == '443' || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']) || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO']=='https')) ? ('https') : ('http'),
            'host' => (self::get('hostname')) ? (self::get('hostname')) : ($_SERVER['HTTP_HOST']),
            'path' => tdz::scriptName(true),
        );
        $s = '';
        $s = $url['scheme'] . '://';
        if (isset($url['user']) || isset($url['pass'])) {
            $s .= urlencode($url['user']);
            if (isset($url['pass'])) {
                $s .= ':' . urlencode($url['pass']);
            }
            $s .='@';
        }
        $s .= $url['host'];
        if (isset($url['port']) && !($url['port']=='80' && $url['scheme']=='http') && !($url['port']=='443' && $url['scheme']=='https')) {
            $s .= ':' . $url['port'];
        }
        $s .= $url['path'];
        if (isset($url['query'])) {
            $s .= '?' . $url['query'];
        }
        if (isset($url['fragment'])) {
            $s .= '#' . $url['fragment'];
        }
        return $s;
    }

    public static function formatUrl($url, $hostname='', $http='')
    {
        $s = '';
        if ($http == '') {
            $http = ($_SERVER['SERVER_PORT'] == '443') ? ('https://') : ('http://');
        }
        if ($hostname == '') {
            $hostname = $_SERVER['HTTP_HOST'];
        }
        $url = trim($url);
        if (preg_match('/[\,\n]/', $url)) {
            $urls = preg_split("/([\s\]]*[\,\n][\[\s]*)|[\[\]]/", $url, -1, PREG_SPLIT_NO_EMPTY);
            foreach ($urls as $k => $v) {
                $v = tdz::formatUrl($v);
                if ($v == ''
                    )unset($urls[$k]);
            }
            return implode(', ', $urls);
        }        
        if ($url == '') {
            $s = '';
        } elseif (preg_match('/^mailto\:\/*(.*)/', $url, $m)) {// email
            $s = '<a href="' . htmlentities($url) . '">' . $hostname . htmlentities($m[1]) . '</a>';
        } elseif (preg_match('/^[a-z0-9\.\-\_]+@/i', $url)) {// email
            $s = '<a href="mailto:' . htmlentities($url) . '">' . htmlentities($url) . '</a>';
        } elseif (!preg_match('/^[a-z]+\:\/\//', $url)) {// absolute
            //if (!preg_match('/^[^\.]+\.[^\.]+/', $url)) {// without host
            if (!preg_match('/^[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,3}/',$url)) { //without host
                $s = '<a href="' . htmlentities($url) . '">' . $http . $hostname . htmlentities($url) . '</a>';
            } else {
                $s = '<a href="' . $http . htmlentities($url) . '">' . $http . htmlentities($url) . '</a>';
            }
        } else {
            $s = '<a href="' . htmlentities($url) . '">' . htmlentities($url) . '</a>';
        }
        return $s;
    }

    /**
     * wordwrap for utf8 encoded strings
     *
     * @param string $str
     * @param integer $len
     * @param string $what
     * @return string
     * @author Milian Wolff <mail@milianw.de>
     */
    
    public static function wordwrap($str, $width, $break, $cut = false) {
        if (!$cut) {
            $regexp = '#^(?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){'.$width.',}\b#U';
        } else {
            $regexp = '#^(?:[\x00-\x7F]|[\xC0-\xFF][\x80-\xBF]+){'.$width.'}#';
        }
        if (function_exists('mb_strlen')) {
            $str_len = mb_strlen($str,'UTF-8');
        } else {
            $str_len = preg_match_all('/[\x00-\x7F\xC0-\xFD]/', $str, $var_empty);
        }
        $while_what = ceil($str_len / $width);
        $i = 1;
        $return = '';
        while ($i < $while_what) {
            preg_match($regexp, $str,$matches);
            $string = $matches[0];
            $return .= $string.$break;
            $str = substr($str, strlen($string));
            $i++;
        }
        return $return.$str;
    }

    /**
     * Authenticate a user against a password file generated by Apache's httpasswd
     * using PHP rather than Apache itself.
     *
     * @param string $user The submitted user name
     * @param string $pass The submitted password
     * @param string $pass_file='.htpasswd' The system path to the htpasswd file
     * @param string $crypt_type='DES' The crypt type used to create the htpasswd file
     * @return bool
     */
    public static function httpAuth($pass_file='.htpasswd', $crypt_type='DES', $user=null,$pass=null){
        // the stuff below is just an example useage that restricts0
        // user names and passwords to only alpha-numeric characters.
        if(is_null($user)) {
            $user = $_SERVER['PHP_AUTH_USER'];
        }
        if(is_null($pass)) {
            $pass = $_SERVER['PHP_AUTH_PW'];
        }
        // get the information from the htpasswd file
        if(ctype_alnum($user) && $pass && file_exists($pass_file) && is_readable($pass_file)){
            // the password file exists, open it
            if($fp=fopen($pass_file,'r')){
                while($line=fgets($fp)){
                    // for each line in the file remove line endings
                    $line=preg_replace('`[\r\n]$`','',$line);
                    list($fuser,$fpass)=explode(':',$line);
                    if($fuser==$user){
                        // the submitted user name matches this line
                        // in the file
                        switch($crypt_type){
                            case 'DES':
                                // the salt is the first 2
                                // characters for DES encryption
                                $salt=substr($fpass,0,2);
                                
                                // use the salt to encode the
                                // submitted password
                                $test_pw=crypt($pass,$salt);
                                break;
                            case 'PLAIN':
                                $test_pw=$pass;
                                break;
                            case 'SHA':
                            case 'MD5':
                            default:
                                // unsupported crypt type
                                break;
                        }
                        if($test_pw == $fpass){
                            // authentication success.
                            fclose($fp);
                            return TRUE;
                        }else{
                            break;
                        }
                    }
                }
                fclose($fp);
            }
        }
        Tecnodesign_App::status(401);
        @header('www-authenticate: Basic realm="Restricted access, please provide your credentials."');
        exit('<html><title>401 Unauthorized</title><body><h1>Forbidden</h1><p>Restricted access, please provide your credentials.</p></body></html>');
    }
    
    public static function env()
    {
        return tdz::$_env;
    }

    /**
     * Data encryption function
     *
     * Encrypts any data and returns a base64 encoded string with its information
     *
     * @param   mixed  $data      data to be encrypted
     * @param   string $salt      (optional) the salt to encrypt the data
     * @param   string $alg       (optional) the algorithm to use
     * @return  string            the encoded string
     */
    public static function encrypt($s, $salt=null, $alg=null)
    {
        if($alg) {
            // unique random ids per string
            // this is double-stored in file cache to prevent duplication
            if($alg==='uuid') {
                $sh = (strlen($s)>30 || preg_match('/[^a-z0-9-_]/i', $s))?(md5($s)):($s);
                if($r=Tecnodesign_Cache::get('uuid/'.$sh)) {
                    unset($sh);
                    return $r;
                } else {
                    // generate uniqid in base64: 10 char string
                    while(!$r) {
                        $r = rtrim(strtr(base64_encode((function_exists('openssl_random_pseudo_bytes'))?(openssl_random_pseudo_bytes(7)):(pack('H*',uniqid(true)))), '+/', '-_'), '=');
                        if(Tecnodesign_Cache::get('uuids/'.$r)) {
                            $r='';
                        }
                    }
                    Tecnodesign_Cache::set('uuid/'.$sh, $r);
                    Tecnodesign_Cache::set('uuids/'.$r, $s);
                }
                unset($sh);
                return $r;
            } else {
                if(is_null($salt)) {
                    if(!($salt=Tecnodesign_Cache::get('rnd', 0, true, true))) {
                        $salt = self::salt(32);
                        Tecnodesign_Cache::set('rnd', $salt, 0, true, true);
                    }
                }
                if(function_exists('openssl_encrypt')) {
                    if($alg===true || $alg===null) $alg = 'AES-256-CFB';
                    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($alg));
                    $s = $iv.openssl_encrypt($s, $alg, $salt, 0, $iv);
                } else if(function_exists('mcrypt_encrypt')) {
                    if($alg===true || $alg===null) $alg = '3DES';
                    # create a random IV to use with CBC encoding
                    $iv = mcrypt_create_iv(mcrypt_get_iv_size($alg, MCRYPT_MODE_CBC), MCRYPT_RAND);
                    $s = $iv.mcrypt_encrypt($alg, $salt, $s, MCRYPT_MODE_CBC, $iv);
                }
            }
        }
        return rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
    }

    public static function salt($length=40, $safe=true)
    {
        if(function_exists('openssl_random_pseudo_bytes')) {
            $rnd = openssl_random_pseudo_bytes($length);
        } else if(function_exists('random_bytes')) {
            $rnd = random_bytes($length);
        } else {
            $rnd = pack('H*',uniqid(true).uniqid(true).uniqid(true).uniqid(true).uniqid(true));
        }
        if($safe) {
            return substr(rtrim(strtr(base64_encode($rnd), '+/', '-_'), '='),0,$length);
        } else {
            return substr(base64_encode($rnd), 0, $length);

        }
    }

    /**
     * Data decryption function
     *
     * Decrypts data encrypted with encrypt
     *
     * @param mixed  $data   data to be decrypted
     * @param string $salt   (optional) the key to encrypt the data
     * @param string $alg    (optional) the algorithm to use
     * 
     * @return mixed the encoded information
     */
    public static function decrypt($r, $salt=null, $alg=null)
    {
        if($alg) {
            // unique random ids per string
            // this is double-stored in file cache to prevent duplication
            if($alg==='uuid') {
                return Tecnodesign_Cache::get('uuids/'.$r);
            } else {
                if(is_null($salt) && !($salt=Tecnodesign_Cache::get('rnd', 0, true, true))) {
                    return false;
                }
                $r = base64_decode(strtr($r, '-_', '+/'));
                if(function_exists('openssl_encrypt')) {
                    if($alg===true) $alg = 'AES-256-CFB';
                    $l = openssl_cipher_iv_length($alg);
                    $s = openssl_decrypt(substr($r, $l), $alg, $salt, 0, substr($r, 0, $l));
                } else if(function_exists('mcrypt_encrypt')) {
                    if($alg===true) $alg = '3DES';
                    # create a random IV to use with CBC encoding
                    $l  = mcrypt_get_iv_size($alg, MCRYPT_MODE_CBC);
                    $s = mcrypt_decrypt($alg, $salt, substr($r, $l), MCRYPT_MODE_CBC, substr($r, 0, $l));
                    unset($l);
                }
                unset($r, $alg, $salt);
                return $s;
            }
        }
        return base64_decode(strtr($r, '-_', '+/'));
    }

    /**
     * Dynamic hashing and checking 
     * 
     * Will return an hashed version of string using the MD5 method, instead of the 
     * common DES encryption algorithm. It's useful for cross-platforms encryptions, 
     * since the MD5 checksum can be found in many other environments (even not 
     * Unix/GNU).
     * 
     * The results are hashes and cannot be unencrypted. To check if a new text 
     * matches the encrypted version, provide this as the salt, and the result
     * should be the same as the encrypted text.
     * 
     * @param   string $str   the text to be encrypted
     * @param   string $salt  the encrypted text or a randomic salt
     * @param   string $type  hash type, can be either a hash_algos() or a string length
     *                        (from 40 to 80) for the hash size
     * 
     * @return  string        an encrypted version of $str
     */
    public static function hash($str, $salt=null, $type=40)
    {
        if($type=='uuid') {
            return self::encrypt($str, $salt, 'uuid');
        } else if(is_string($type)) {
            $t = strtoupper($type);
            if(substr($t, 0, 4)=='SSHA' || substr(strtolower($t), 0, 4)=='SMD5') {
                if(is_null($salt)) $salt = openssl_random_pseudo_bytes(20);
                else if(substr($salt, 0, strlen($type)+2)=="{{$t}}") {
                    $salt = substr(base64_decode(substr($salt, strlen($type)+2)), strlen(hash(strtolower(substr($t,1)), null, true)));
                }
                $h = "{{$t}}" . base64_encode(hash(strtolower(substr($t,1)), $str . $salt, true) . $salt);
            } else {
                $h = hash($type, $str);
                if ($salt != null && strcasecmp($h, $salt)==0) {
                    return $salt;
                } 
            }
            return $h;
        } else {
            $len = 8;
            $m='md5';
            if(is_int($type) && $type>32) {
                $len = $type - 32;
                if($type>64) {
                    if($type > 80) {
                        $type = 80;
                    }
                    $m = 'sha1';
                    $len = $type - 40;
                }
            }
            if(!$salt){
                $salt = $m(uniqid(rand(), 1));
            }
            $salt = substr($salt, 0, $len);
            return $salt . $m($str.$salt);
        }
    }
    
    
    public static function sameHost()
    {
        if(TDZ_CLI) {
            return true;
        }
        if(!isset($_SERVER['HTTP_REFERER']) || !($domain=preg_replace('/^https?\:\/\/([^\/]+).*/', '$1', $_SERVER['HTTP_REFERER'])) || $domain!=$_SERVER['HTTP_HOST']) {
            return false;
        }
        return true;
    }
    
    public static function countryNames($s=null)
    {
        $d = tdz::database('countryNames', tdz::$lang);
        if(!is_null($s)) {
            return $d[$s];
        }
        return $d;
    }
    
    
    public static function database($table, $language=null)
    {
        $file = TDZ_ROOT.'/src/Tecnodesign/Resources/database/'.$table.'.yml';
        if(!file_exists($file)) {
            return false;
        }
        $translate = false;
        if(!is_null($language)) {
            $tfile = TDZ_ROOT.'/src/Tecnodesign/Resources/database/'.$language.'/'.$table.'.yml';
            if(!file_exists($tfile)) {
                $translate = $language;
            } else {
                $file = $tfile;
            }
        }
        $d = Tecnodesign_Yaml::load($file);
        if($translate) {
            $d = tdz::t($d, $table, $language);
        }
        return $d;
    }

    /**
     * Date and time functions
     */
    public static function strtotime($date, $showtime = true)
    {
        $hour=$minute=$second=0;
        if(preg_match('/^([0-9]{4})(-([0-9]{2})(-([0-9]{2})(\T([0-9]{2})\:([0-9]{2})(\:([0-9]{2})(\.[0-9]+)?)?(Z|([-+])([0-9]{2})\:([0-9]{2}))?)?)?)?$/', trim($date), $m)){
            //           [year    ] -[month   ] -[day     ]  T[hour and minute     ]  :[seconds ][mseconds]   [timezone                  ]            
            $m[3] = ($m[3]=='')?(1):((int)$m[3]);
            $m[5] = ($m[5]=='')?(1):((int)$m[5]);            
            return @mktime((int)$m[7], (int)$m[8], (int)$m[10], $m[3], $m[5], (int)$m[1], -1);
        } elseif (strpos($date,"/") > 0 && (self::$dateFormat != '' && (self::$timeFormat != '' || !$showtime))){            
            $format = self::$dateFormat.(($showtime)?(' '.self::$timeFormat):(''));
            $dtcomp = preg_split('%[- /.:]%', $date);
            $frmtcomp = preg_split('%[- /.:]%', $format);
            if (is_array($dtcomp) && is_array($frmtcomp)) {
                foreach ($frmtcomp as $k=>$c) {
                    if ($c == "d"){
                        $day = @$dtcomp[$k];
                    } elseif ($c == "m"){
                        $month = @$dtcomp[$k];
                    } elseif ($c == "Y"){
                        $year = @$dtcomp[$k];
                    } elseif ($c == "H"){
                        $hour = @$dtcomp[$k];
                    } elseif ($c == "i"){
                        $minute = @$dtcomp[$k];
                    } elseif ($c == "s"){
                        $second = @$dtcomp[$k];
                    }
                }
            }
            return mktime((int)$hour, (int)$minute, (int)$second, (int)$month, (int)$day, (int)$year, -1);
        } else {            
            return @strtotime($date);
        }
    }
    public static function date($t, $showtime=true)
    {
        $s = self::$dateFormat.(($showtime)?(' '.self::$timeFormat):(''));
        if(!is_int($t)) {
            $t = strtotime($t);
        }
        return date($s, $t);
    }
    
    public static function dateDiff($start, $end='', $showtime=false)
    {
        $tstart = (is_int($start))?($start):(@strtotime($start));
        $tend = (is_int($end))?($end):(@strtotime($end));
        if($tend == false || $end == ''){
            $tend = $tstart;
        }
        $start = ($showtime)?(date('Y-m-d H:i:s', $tstart)):(date('Y-m-d', $tstart));
        $end   = ($showtime)?(date('Y-m-d H:i:s', $tend)):(date('Y-m-d', $tend));
        if ($start == $end){// same time
            $str = self::date($tstart, $showtime);
        } else if ($showtime && substr($start, 0, 10) == substr($end, 0, 10)){// same day
            $str = date(self::$dateFormat, $tstart);
            if($showtime) {
                $str .= ' '.self::t('from', 'date').' '.date(self::$timeFormat, $tstart)
                . ' '.self::t('up to', 'date').' '.date(self::$timeFormat, $tend);
            }
        } else if(substr($start, 0, 7) == substr($end, 0, 7) && tdz::$dateFormat=='d/m/Y'){// same month
            $str = date('d', $tstart)
                . ' '.self::t('to', 'date')
                . ' '.self::date($tend, $showtime);
        } else if (substr($start, 0, 5) == substr($end, 0, 5) && tdz::$dateFormat=='d/m/Y') { // same year
            $str = date(preg_replace('/[^a-z]*y[^a-z]*/i', '', self::$dateFormat), $tstart)
                . ' '.self::t('to', 'date')
                . ' '.self::date($tend, $showtime);
        } else {
            $str = self::date($tstart, $showtime)
                . ' '.self::t('to', 'date')
                . ' '.self::date($tend, $showtime);
        }
        return $str;
    }


    public static function timezoneOffset($tz, $d='now', $tz1=null)
    {
        $d = new DateTime($d, new DateTimeZone($tz));
        $o = $d->getOffset();
        if($tz1) {
            $d->setTimezone(new DateTimeZone($tz1));
            $o = $d->getOffset() - $o;
        }
        return $o;
    }


    public static function checkIp($ip=null, $cidrs=null)
    {
        $ipu = explode('.', $ip);
        foreach ($ipu as &$v)
            $v = str_pad(decbin($v), 8, '0', STR_PAD_LEFT);
        
        $ipu = join('', $ipu);
        $res = false;
        if($cidrs) {
            if(!is_array($cidrs)) $cidrs = array($cidrs);
            foreach ($cidrs as $cidr) {
                if(strpos($cidr, '/')===false) {
                    $cidr .= '/32';
                }
                $parts = explode('/', $cidr);
                $ipc = explode('.', $parts[0]);
                foreach ($ipc as &$v) $v = str_pad(decbin($v), 8, '0', STR_PAD_LEFT);
                $ipc = substr(join('', $ipc), 0, $parts[1]);
                $ipux = substr($ipu, 0, $parts[1]);
                $res = ($ipc === $ipux);
                if ($res) break;
            }
        }
        return $res;
    }    

    /**
     * Validate an email address.
     */
    public static function checkEmail($email, $checkDomain=true)
    {
        $isValid = true;
        $atIndex = strrpos($email, '@');
        if ($atIndex===false){
           $isValid = false;
        } else {
            $domain = substr($email, $atIndex+1);
            $local = substr($email, 0, $atIndex);
            $localLen = strlen($local);
            $domainLen = strlen($domain);
            if ($localLen < 1 || $localLen > 64) {
                // local part length exceeded
                $isValid = false;
            } else if ($domainLen < 1 || $domainLen > 255) {
                // domain part length exceeded
                $isValid = false;
            } else if ($local[0] == '.' || $local[$localLen-1] == '.') {
                // local part starts or ends with '.'
                $isValid = false;
            } else if (preg_match('/\\.\\./', $local)) {
                // local part has two consecutive dots
                $isValid = false;
            } else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
                // character not valid in domain part
                $isValid = false;
            } else if (preg_match('/\\.\\./', $domain)) {
                // domain part has two consecutive dots
                $isValid = false;
            } else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\","",$local))) {
                // character not valid in local part unless 
                // local part is quoted
                if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\","",$local))) {
                    $isValid = false;
                }
            }
            if ($checkDomain &&  $isValid && !tdz::checkDomain($domain, array('MX', 'A'))) {
                // domain not found in DNS
                $isValid = false;
            }
        }
        return $isValid;
    }

    public static function checkDomain($domain, $records=array('MX', 'A'), $cache=true)
    {
        if(!$cache || !($R=Tecnodesign_Cache::get('dnscheck/'.$domain, 600))) {
            $r = false;
            foreach($records as $k=>$v) {
                if(checkdnsrr($domain,$v)) {
                    $r = true;
                    unset($k, $v);
                    break;
                }
                unset($k, $v);
            }
            $R=($r)?('valid'):('invalid');
            Tecnodesign_Cache::set('dnscheck/'.$domain, $R, 600);
        }
        return ($R=='valid');
    }

    /**
     * Atomic file update
     *
     * Saves the $file with the $contents provided. If the file directory does not
     * exist, use $recursive=true to create it.
     *
     * @param string $file      the file to be saved
     * @param string $contents  the contents of the file to be saved
     * @param bool   $recursive whether the directory should be created if it doesn't
     *                          exist
     * @param binary $mask      octal mask to be applied to the file
     *
     * @return bool              true on success, false on error
     * @uses    xdb_pathtofile
     */
    public static function save($file, $contents, $recursive=false, $mask=0666) 
    {
        if ($file=='') {
            return false;
        }
        $dir = dirname($file);
        if (!is_dir($dir)) {
            if ($recursive) {
                $u=umask(0);
                mkdir($dir, $mask+0111, true);
                umask($u);
            } else {
                return false;
            }
        }
        $tmpfile = tempnam($dir, '.' . basename($file));
        
        try {
            $fd = fopen($tmpfile, 'wb');            
            fwrite($fd, $contents);            
            fclose($fd);
            
            if (!chmod($tmpfile, $mask)) {
                throw new Exception("File \"".$file."\" could not be saved -- permission denied");
            }
            
            if (!rename($tmpfile, $file)) {
                throw new Exception("File \"".$file."\" could not be saved -- permission denied");
            }
            return true;
        } catch(Exception $e) {
            tdz::log('[ERROR] Probably wrong filesystem permissions: '.$e);
            unlink($tmpfile);
            return false;
        }
    }

    public static function install($project=false, $module=null)
    {
        $app = new Tecnodesign_App_Install($project);
        if($module && method_exists($app, $fn=$module.'Install')) {
            $app->$fn();
        } else {
            $app->runInstall();
        }
    }
    
    
    public static function mail($to, $subject='', $message='', $headers=null, $attach=null)
    {
        try {
            $h = array(
              'To'=>$to,
              'Subject'=>$subject,
            );
            if($headers) {
                if(!is_array($headers)) {
                    $headers = Tecnodesign_Yaml::load($headers);
                }
                $h+=$headers;
            }
            if(!is_array($message)) {
            	$body = array(
            		'text/plain'=>$message,
            	);
            } else {
                $body = $message;
            }
        	if(isset(tdz::$variables['attachments']) && is_array(tdz::$variables['attachments'])) {
        	    $body += tdz::$variables['attachments'];
        	}
    		$mail = new Tecnodesign_Mail($h, $body);
            if(!$mail->send()) {
                throw new Tecnodesign_Exception($mail->getError());
            }
            return true;
        } catch(Exception $e) {
            tdz::log(__METHOD__, $e->getMessage());
            return false;
        }
    }
    
    
    
    public static function map()
    {
        $a = func_get_args();
        $fn = 'getLatLong';
        if (count($a)>0) {
            $fn = 'get'.ucfirst(array_shift($a));
        }
        return self::staticCall('Tecnodesign_Maps', $fn, $a);
    }

    public static function call($fn, $a=null)
    {
        if($a) {
            if(!is_array($fn)) return self::functionCall($fn, $a);
            else if(is_string($fn[0])) return self::staticCall($fn[0], $fn[1], $a);
            else return self::objectCall($fn[0], $fn[1], $a);
        } else {
            if(!is_array($fn)) return $fn();
            else {
                list($c, $m) = $fn;
                if(is_string($c)) return $c::$m();
                else return $c->$m();
            }
        }
    }

    public static function functionCall($fn, $a)
    {
        if(!function_exists($fn)) {
            return null;
        }
        switch(count($a))
        {
            case 0:
                return $fn();
            case 1:
                return $fn($a[0]);
            case 2:
                return $fn($a[0], $a[1]);
            case 3:
                return $fn($a[0], $a[1], $a[2]);
            case 4:
                return $fn($a[0], $a[1], $a[2], $a[3]);
            case 5:
                return $fn($a[0], $a[1], $a[2], $a[3], $a[4]);
            default:
                return call_user_func_array($fn, $a);
        }
    }

    public static function objectCall($c, $m, $a)
    {
        if(!method_exists($c, $m)) {
            return null;
        }
        switch(count($a))
        {
            case 0:
                return $c->$m();
            case 1:
                return $c->$m($a[0]);
            case 2:
                return $c->$m($a[0], $a[1]);
            case 3:
                return $c->$m($a[0], $a[1], $a[2]);
            case 4:
                return $c->$m($a[0], $a[1], $a[2], $a[3]);
            case 5:
                return $c->$m($a[0], $a[1], $a[2], $a[3], $a[4]);
            default:
                return call_user_func_array(array($c,$m), $a);
        }
    }

    public static function staticCall($c, $m, $a)
    {
        if(!method_exists($c, $m)) {
            return null;
        }
        switch(count($a))
        {
            case 0:
                return $c::$m();
            case 1:
                return $c::$m($a[0]);
            case 2:
                return $c::$m($a[0], $a[1]);
            case 3:
                return $c::$m($a[0], $a[1], $a[2]);
            case 4:
                return $c::$m($a[0], $a[1], $a[2], $a[3]);
            case 5:
                return $c::$m($a[0], $a[1], $a[2], $a[3], $a[4]);
            default:
                return call_user_func_array(array($c,$m), $a);
        }
    }

    protected static $chars='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-.';
    public static function compress64($s)
    {
    	if(strlen($s)>8) {
    		$ns = '';
    		while(strlen($s)>0) {
    			$ns .= tdz::compress64(substr($s, 0, 8));
				$s = substr($s, 8);
    		}
			return $ns;
    	}
        $num=hexdec($s);
        $b=64;
        $i=1;
        $ns='';
        while ($num>=1.0) {
            $r=$num%$b;
            $num = (($num - $r)/64);
            $ns = substr(tdz::$chars,$r,1).$ns;
        }
        return $ns;
    }
    public static function expand64($s)
    {
        $ns='';
        $re='/^['.str_replace(array('.','-'),array('\.','\-'), tdz::$chars).']+$/';
        if(!preg_match($re, $s))return false;
        $i=0;
        $num=0;
        while ($s!='') {
            $char=substr($s,-1);
            $s=substr($s,0,strlen($s) -1);
            $pos=strpos(tdz::$chars,$char);
            $num = $num + ($pos*pow(64, $i++));
        }
        $ns=dechex($num);
        return $ns;
      }
    
    /**
     * Make questions at command line
     */
    public static function ask($question, $default=null, $check=null, $err='One more time...', $retries=-1)
    {
        echo $question, ($check && is_array($check))?(' ('.implode(', ', $check).')'):(''), ($default)?("[{$default}]:\n"):("\n");
        $stdin = fopen('php://stdin', 'r');
        $r = trim(fgets($stdin));
        fclose($stdin);
        unset($stdin);
        if(!$r && $default) $r = $default;
        if($check) {
            if(is_array($check) && !in_array($r, $check)) {
                echo "{$err}\n";
                if($retries>0 || $retries <0) {
                    $retries--;
                    return self::ask($question, $default, $check, $err, $retries);
                }
                return false;
            }

        }
        return $r;
    }

    public static function relativePath($to, $from=null)
    {
        if(!$from) {
            $from = str_replace('\\', '/', getcwd());
        } else {
            $from = preg_replace('#[\\/]+|[\\/]\.[\\/]#', '/', $from);
            //if(substr($from, -1)=='/') $from = rtrim($from, '/');
        }
        $to = preg_replace('#[\\/]+|[\\/]\.[\\/]#', '/', $to);
        if(substr($to, 0, 1)!='/') return $to;
        //if(substr($to, -1)=='/') $to = rtrim($to, '/');

        $from     = explode('/', $from);
        $to       = explode('/', $to);
        $relPath  = $to;

        foreach($from as $depth => $dir) {
            // find first non-matching dir
            if(isset($to[$depth]) && $dir === $to[$depth]) {
                // ignore this directory
                array_shift($relPath);
            } else {
                // get number of remaining dirs to $from
                $remaining = count($from) - $depth;
                if($remaining > 1) {
                    // add traversals up to first matching dir
                    $padLength = (count($relPath) + $remaining - 1) * -1;
                    $relPath = array_pad($relPath, $padLength, '..');
                    unset($padLength, $remaining);
                    break;
                } else {
                    $relPath[0] = './' . $relPath[0];
                }
                unset($remaining);
            }
        }
        unset($from, $to);
        return implode('/', $relPath);
    }


    /**
     * Find current template file location, or false if none are found, accepts multiple arguments, processed in order.
     * example: $template = tdz::templateFile($mytemplate, 'tdz_entry');
     */
    public static function templateFile($tpl)
    {
        $app = tdz::getApp();
        if(is_null(tdz::$tplDir)) {
            self::$tplDir = array(
                $app->tecnodesign['templates-dir'],
            );
        }
        $apps = $app->tecnodesign['apps-dir'];
        unset($app);
        foreach(func_get_args() as $tpl) {
            if($tpl) {
                if(substr($tpl, 0, strlen($apps))==$apps && file_exists($tplf=$tpl.'.php')) {
                    return $tplf;
                }
                foreach(tdz::$tplDir as $d) {
                    if(strpos($tpl, '/')!==false && substr($tpl, 0, strlen($d))==$d && file_exists($tpl)) {
                        return $tpl;
                    } else if(file_exists($tplf=$d.'/'.$tpl.'.php')) {
                        return $tplf;
                    }
                }
            }
        }
        return false;
    }
    /**
     * Tecnodesign autoloader. Searches for classes under TDZ_ROOT
     * 
     * @param string $class class name to be loaded.
     *
     * @return void
     */
    public static function autoload($cn)
    {
        if($f=tdz::classFile($cn)) {
            require_once $f;
            tdz::autoloadParams($cn);
        } else if(tdz::$log>10) {
            tdz::log(true, '[ERROR] Class '.$cn.' was not found!');
        }
        return false;
    }

    public static function classFile($cn)
    {
        $c = str_replace(array('_', '\\'), '/', $cn);
        if (file_exists($f=TDZ_ROOT."/src/{$c}.php")) {
            return $f;
        } else {
            foreach(tdz::$lib as $libi=>$d) {
                if(substr($d, -1)=='/') tdz::$lib[$libi]=substr($d, 0, strlen($d)-1);
                if (file_exists($f=$d.'/'.$c.'.php') || 
                    file_exists($f=$d.'/'.$c.'/'.$c.'.php') || 
                    file_exists($f=$d.'/'.$c.'/'.$c.'.inc.php') || 
                    file_exists($f = $d.'/'.$c.'.class.php') || 
                    file_exists($f=$d.'/'.$c.'/'.strtolower($c).'.php')
                ) {
                    return $f;
                }
                unset($libi, $d, $f);
            }
        }
        unset($c);
    }

    public static function autoloadParams($cn)
    {
        if(is_null(self::$autoload)) {
            if(file_exists($c=TDZ_APP_ROOT.'/config/autoload.ini')) {
                self::$autoload = parse_ini_file($c, true);
            } else {
                self::$autoload = array();
            }
        }
        if(isset(self::$autoload[$cn])) {
            foreach(self::$autoload[$cn] as $k=>$v) {
                $cn::$$k = (!is_array($v) && substr($v, 0, 1)=='{')?(json_decode($v, true)):($v);
                unset($k, $v);
            }
            unset(self::$autoload[$cn]);
        }
        if(file_exists($c=TDZ_APP_ROOT.'/config/autoload.'.str_replace('\\', '_', $cn).'.ini')) {
            $c = parse_ini_file($c, true);
            if($c) {
                foreach($c as $k=>$v) {
                    $cn::$$k = tdz::rawValue($v);//(!is_array($v) && substr($v, 0, 1)=='{')?(json_decode($v, true)):($v);
                    unset($c[$k], $k, $v);
                }
            }
            unset($c);
        } else if(file_exists($c=TDZ_APP_ROOT.'/config/autoload.'.str_replace('\\', '_', $cn).'.yml')) {
            $c = Tecnodesign_Yaml::load($c);
            if($c) {
                foreach($c as $k=>$v) {
                    $cn::$$k = tdz::rawValue($v);
                    unset($c[$k], $k, $v);
                }
            }
            unset($c);
        }
    }

    public static function rawValue($v)
    {
        if(is_numeric($v) && preg_match('/^[0-9\.]+$/', $v)) {
            return ((string)((int)$v)===$v)?((int)$v):((double)$v);
        }
        return $v;
    }

    public static function raw(&$v)
    {
        if(is_string($v)) {
            if($v=='true') $v=true;
            else if($v=='false') $v=false;
            else if(is_numeric($v) && preg_match('/^-?(0?\.[0-9]*|[1-9][0-9]*\.[0-9]*|0|[1-9][0-9]*)$/', $v)) {
                $v = ((string)((int)$v)===$v)?((int)$v):($v+0.0);
            }
        } else if(is_array($v)) {
            array_walk($v, array(get_called_class(), 'raw'));
        }
        return $v;
    }
    
    /**
     * rmdir
     * 
     * Remove a directory recursively
     * 
     * @param string $dir directory name
     */
    public static function rmdirr($dir)
    {
        if (is_dir($dir)) { 
            try {
                $files = array_diff(scandir($dir), array('.','..')); 
                foreach ($files as $file) { 
                    (is_dir("$dir/$file")) ? tdz::rmdirr("$dir/$file") : unlink("$dir/$file"); 
                } 
                return rmdir($dir);
            } catch (Exception $e) {
                throw new Tecnodesign_Exception('Error '.$e.' ('.__METHOD__.' - '.__LINE.')');                
                return false;
            }        
        }
    }

    public static function tune($s=null,$m=20, $t=20)
    {
        if($m) {
            static $mem;
            if(is_null($mem)) $mem = (int) substr(ini_get('memory_limit'), 0, strlen(ini_get('memory_limit'))-1);
            $used = ceil(memory_get_peak_usage() * 0.000001);
            if($m===true) $m=$used;
            if($used + $m > $mem) {
                $mem = ($used + $m);
                ini_set('memory_limit', $mem.'M');
                if($s) {
                    $s .= "\tincreased memory limit to ".$mem.'M';
                    gc_collect_cycles();
                }
            }
            $mem = $used;
            unset($used);
        }
        if($t) {
            static $limit;
            if(is_null($limit)) $limit = ini_get('max_execution_time');
            $run = (int) (time() - TDZ_TIME);
            if($t===true) $t=$run;
            if($limit - $run < $t) {
                $limit = $run + $t;
                set_time_limit ($t);
                if($s) {
                    $s .= "\tincreased time limit to {$limit}s";
                }
            }
            if(tdz::$log) tdz::log($s." ({$mem}M {$run}s)");
            unset($run);
        } else {
            if(tdz::$log) tdz::log($s." ({$mem}M)");
        }
    }

}



if (!defined('TDZ_CLI')) {
    define('TDZ_CLI', (!isset($_SERVER['HTTP_HOST']) && basename($_SERVER['argv'][0], '.php')=='tdz'));
}
define('TDZ_TIME', microtime(true));
if (!defined('TDZ_ROOT')) {
    define('TDZ_ROOT', str_replace('\\', '/', dirname(__FILE__)));
    set_include_path(get_include_path().PATH_SEPARATOR.TDZ_ROOT.'/src');
}
if (!defined('TDZ_APP_ROOT')) {
    if(isset($_SERVER['tecnodz_dir']) && is_dir($_SERVER['tecnodz_dir'])) {
        define('TDZ_APP_ROOT', realpath($_SERVER['tecnodz_dir']));
    } else if(strrpos(TDZ_ROOT, '/lib/')!==false) {
        define('TDZ_APP_ROOT', substr(TDZ_ROOT, 0, strrpos(TDZ_ROOT, '/lib/')));
    } else {
        define('TDZ_APP_ROOT', TDZ_ROOT);
    }
}
if (!defined('TDZ_VAR')) {
    if(is_dir($d='./data/Tecnodesign') 
        || is_dir($d='./data') 
        || is_dir($d=TDZ_APP_ROOT.'/data/Tecnodesign')
        || is_dir($d=TDZ_APP_ROOT.'/data')
        ) {
        define('TDZ_VAR', realpath($d));
    } else {
        define('TDZ_VAR', TDZ_APP_ROOT.'/data/Tecnodesign');
    }
    unset($d);
}
if (!defined('TDZ_DOCUMENT_ROOT')) {
    if(is_dir($d=TDZ_APP_ROOT.'/../htdocs')
        || is_dir($d=TDZ_APP_ROOT.'/../www')
        || is_dir($d=TDZ_APP_ROOT.'/../web')
        || is_dir($d=TDZ_APP_ROOT.'/htdocs')
        || is_dir($d=TDZ_APP_ROOT.'/web')
        ) {
        define('TDZ_DOCUMENT_ROOT', realpath($d));
    } else {
        define('TDZ_DOCUMENT_ROOT', $d);
    }
    unset($d);
}

spl_autoload_register('tdz::autoload');
if(is_null(tdz::$lib)) {
    tdz::$lib = array();
    if(TDZ_ROOT!=TDZ_APP_ROOT) {
        tdz::$lib[]=TDZ_APP_ROOT.'/lib/';
    }
    tdz::$lib[] = dirname(TDZ_ROOT);
}
tdz::autoloadParams('tdz');
if (TDZ_CLI && !file_exists(TDZ_VAR.'/no-install') && isset($_SERVER['argv'][1]) && ($_SERVER['argv'][1]=='install' || (substr($_SERVER['argv'][1], 0, 8)=='install:') && isset(Tecnodesign_App_Install::$modules[substr($_SERVER['argv'][1], 8)]))) {
    $prjname = (isset($_SERVER['argv'][2]))?($_SERVER['argv'][2]):(false);
    $module = ($_SERVER['argv'][1]!='install')?(substr($_SERVER['argv'][1],8)):(null);
    tdz::install($prjname, $module);
    exit();
}
