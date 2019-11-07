<?php
/**
 * Tecnodesign Automatic Interfaces
 * 
 * This is an action for managing interfaces for all available models
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Studio_Task
{

    static $taskDir = array('lib/task', '$TDZ_ROOT/src/Tecnodesign/Studio/Resources/task');

    public static function run($args=null, $vars=null)
    {
        if(!$args) $args = array();
        else if(!is_array($args)) $args = array($args);
        else if(!isset($args[0])) $args = array_values($args);

        $t = preg_replace('/[^a-z0-9\-]+/i', '', array_shift($args));
        if(!($f=static::file($t))) {
            return Tecnodesign_Studio::error(404);
        }
        $c = static::description($f);
        $map=array();
        $R = array('script'=>$f);
        unset($f);
        if(is_null($vars)) {
            foreach($c['arguments'] as $k=>$v) {
                $R['variables'][$k]=null;
                if(isset($v['short-version'])) $map[$v['short-version']]=$k;
                unset($k, $v);
            }
            $err = array();
            while(isset($args[0])) {
                $v = array_shift($args);
                if(substr($v, 0, 1)=='-') {
                    if(substr($v, 0, 2)=='--') {
                        $v = substr($v,2);
                    } else {
                        if(isset($map[substr($v, 1, 1)])) {
                            $v = $map[substr($v, 1, 1)].substr($v,2);
                        } else {
                            $err[] = "Unknown argument: '{$v}'";
                            continue;
                        }
                    }
                    if($p=strpos($v, '=')) {
                        $k = substr($v, 0, $p);
                        $v = substr($v, $p+1);
                    } else {
                        $k = $v;
                        $v = '';
                    }
                    if(!isset($c['arguments'][$k])) {
                        $err[] = "Unknown argument: '{$k}'";
                    }
                    $type = $c['arguments'][$k]['type'];
                    if($type=='bool') $R['variables'][$k] = true;
                    else if($type=='dir'){
                        if(is_dir($v)) {
                            $R['variables'][$k] = $v;
                        }
                    }
                }
            }
        } else {
            $R['variables'] = $vars;
            foreach($c['arguments'] as $k=>$v) {
                if(!isset($R['variables'][$k])) $R['variables'][$k]=null;
                unset($k, $v);
            }
        }
        return tdz::exec($R);
    }

    public static function file($t)
    {
        $ds = str_replace(array('$TDZ_ROOT', '$TDZ_APP_ROOT', '$TDZ_VAR'), array(TDZ_ROOT, TDZ_APP_ROOT, TDZ_VAR), static::$taskDir);
        foreach($ds as $k=>$d) {
            if(substr($d, 0, 1)!='/') $d = TDZ_APP_ROOT.'/'.$d;
            if(file_exists($f=$d.'/'.$t.'.php')) {
                unset($ds[$k], $d, $k, $ds);
                return $f;
                break;
            }
            unset($f, $ds[$k], $d, $k);
        }
        return false;
    }

    public function description($f)
    {
        $c = array();
        $fc = file_get_contents($f);
        if($p=strpos($fc, "\n---")) {
            $c = Tecnodesign_Yaml::load(substr($fc, $p+1, strpos($fc, "\n---", $p+1)-$p-1));
        }
        $c+=array('arguments'=>array());
        unset($fc, $p);
        return $c;
    }
}
