<?php
/**
 * Studio Framework shortcuts and multi-purpose utils
 *
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */
class tdz extends Studio
{
    public static
        $userClass='Tecnodesign_User'
        ;

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
                    $bp = ($a=='border')?(array($css[$i][$a])):(preg_split('/\s+/', $css[$i][$a], -1, PREG_SPLIT_NO_EMPTY));
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

    public static function og()
    {
    }

    public static function getRequestUri($arg=array())
    {
        return self::requestUri($arg);
    }

    public static function getUrlParams($url=false, $unescape=false)
    {
        return self::urlParams($url, $unescape);
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
        return self::$variables;
    }

    public static function getTitle()
    {
        self::set('title-replace', true);
        return '<h1>[[title]]</h1>';
    }

    public static function sqlEscape($str, $enclose=true) {return self::sql($str, $enclose);}
    public static function xmlEscape($s, $q=true) {return self::xml($s, $q);}
    public static function textToSlug($str, $accept=''){return self::slug($str, $accept);}
    public static function formatBytes($bytes, $precision=2) {return self::bytes($bytes, $precision);}
    public static function formatNumber($number, $decimals=2) {return self::number($number, $decimals);}
    public static function formatTable($arr, $arg=array()) {return self::table($arr, $arg);}
    /**
     * @param string $number
     * @param bool $uppercase
     * @return string
     *
     * @deprecated use numberToLetter() instead
     */
    public static function letter($number, $uppercase = false)
    {
        return self::numberToLetter($number, $uppercase);
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
        $d = self::database('countryNames', self::$lang);
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
        $d = Yaml::load($file);
        if($translate) {
            $d = self::t($d, $table, $language);
        }
        return $d;
    }

    public static function checkEmail($email, $checkDomain=true)
    {
        return parent::checkEmail($email, $checkDomain);
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

    /*
    public static function exec($a)
    {
        $script_name = false;
        if (!is_null(self::$script_name)) {
            $script_name = $_SERVER['SCRIPT_NAME'];
            $_SERVER['SCRIPT_NAME'] = self::$script_name;
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
            if(self::$noeval) {
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

        if(isset($a['shell']) && $a['shell']) {
            $output = [];
            $ret = 0;
            exec($a['shell'], $output, $ret);
            if($ret===0) $tdzres.=implode("\n", $output);
            else if(self::$log) self::log('[INFO] Error in command `'.$a['shell'].'`', implode("\n", $output));
            unset($output, $ret);
        }

        ob_end_clean();

        if ($script_name) {
            $_SERVER['SCRIPT_NAME'] = $script_name;
        }

        return $tdzres;
    }
    */
}

if(!defined('TDZ_CLI')) define('TDZ_CLI', S_CLI);
define('TDZ_TIME', S_TIME);
define('TDZ_TIMESTAMP', S_TIMESTAMP);
if (!defined('TDZ_ROOT')) define('TDZ_ROOT', S_ROOT);
if(!defined('TDZ_APP_ROOT')) define('TDZ_APP_ROOT', S_APP_ROOT);
if(!defined('TDZ_VAR')) define('TDZ_VAR', S_VAR);
if(!defined('TDZ_DOCUMENT_ROOT')) define('TDZ_DOCUMENT_ROOT', S_DOCUMENT_ROOT);
if(!defined('TDZ_PROJECT_ROOT')) define('TDZ_PROJECT_ROOT', S_PROJECT_ROOT);
tdz::autoloadParams('tdz');
