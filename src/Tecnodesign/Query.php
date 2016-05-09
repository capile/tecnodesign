<?php
/**
 * Database abstraction
 *
 * PHP version 5.3
 *
 * @category  Database
 * @package   Model
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2016 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Query implements \ArrayAccess
{
    public function __construct($o=array())
    {
        foreach($o as $k=>&$v) {
            $this->$k = $v;
            unset($k, $v);
        }
    }

    public static function database($db=null)
    {
        if(is_null(tdz::$database)) {
            $app = tdz::getApp();
            if($app && $app->database) {
                tdz::$database = $app->database;
            }
            unset($app);

            if(!tdz::$database) {
                if(file_exists($f=TDZ_APP_ROOT.'/config/databases.yml')) {
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
        if(!is_null($db)) {
            if(isset(tdz::$database[$db])) return tdz::$database[$db];
            return null;
        }
        return tdz::$database;
    }

    public static function handler($s=null)
    {
        static $H = array();
        $n = '';
        if((is_string($s) && $s && property_exists($s, 'schema')) || $s instanceof Tecnodesign_Model) {
            $n = $s::$schema['database'];
            if(is_object($s)) {
                $s = (isset($s::$schema['className']))?($s::$schema['className']):(get_class($s));
            }
        }
        if(!isset($H[$n])) {
            $dbs = self::database();
            $db = (!isset($dbs[$n]))?(array_shift($dbs)):($dbs[$n]);
            $cn = (isset($db['class']))?($db['class']):('Tecnodesign_Query_'.ucfirst(substr($db['dsn'], 0, strpos($db['dsn'], ':'))));
            $H[$n] = $cn;
            unset($dbs, $db);
        } else {
            $cn = $H[$n];
        }
        return new $cn($s);
    }

    public function offsetSet($offset, $value) {
        if (is_null($offset)) {
            $i=0;
            while(isset($this->{$i})) {
                $i++;
            }
            $this->{$i} = $value;
        } else if($p=strpos($offset, '/')) {
            $b = substr($offset, 0, $p);
            $offset = substr($offset, $p+1);
            if(!isset($this->{$b})) {
                $this->{$b} = array();
            }
            $a = $this->{$b};
            if(strpos($offset, '/')) {
                @eval('$this->{$b}[\''.str_replace('/', '\'][\'', $offset).'\']=$value;');
            } else {
                $this->{$b}[$offset]=$value;
            }
        } else {
            $this->{$offset} = $value;
        }
    }
    public function offsetExists($offset) {
        if($p=strpos($offset, '/')) {
            $b = substr($offset, 0, $p);
            $offset = substr($offset, $p+1);
            if(isset($this->{$b}) && isset($this->{$b}[$offset])) {
                if(strpos($offset, '/')) {
                    $a = $this->{$b}[$offset];
                    while($p=strpos($offset, '/')) {
                        if(is_array($a) && isset($a[substr($offset, 0, $p)])) {
                            $a = $a[substr($offset, 0, $p)];
                        } else {
                            return false;
                        }
                    }
                    return true;
                }
                return true;
            }
            return false;
        }
        return isset($this->{$offset});
    }
    public function offsetUnset($offset) {
        if($p=strpos($offset, '/')) {
            $b = substr($offset, 0, $p);
            $offset = substr($offset, $p+1);
            if(isset($this->{$b})) {
                $a = $this->{$b};
                if(strpos($offset, '/')) {
                    @eval('unset($this->{$b}[\''.str_replace('/', '\'][\'', $offset).'\']);');
                } else {
                    unset($this->{$b}[$offset]);
                }
                return $a;
            }
            return false;
        }
        unset($this->{$offset});
    }

    public function offsetGet($offset) {
        if($p=strpos($offset, '/')) {
            $b = substr($offset, 0, $p);
            $offset = substr($offset, $p+1);
            if(isset($this->{$b}) && isset($this->{$b}[$offset])) {
                if(strpos($offset, '/')) {
                    $a = $this->{$b}[$offset];
                    while($p=strpos($offset, '/')) {
                        if(is_array($a) && isset($a[substr($offset, 0, $p)])) {
                            $a = $a[substr($offset, 0, $p)];
                        } else {
                            return false;
                        }
                    }
                    return $a;
                }
                return $this->{$b}[$offset];
            }
            return false;
        }
        return isset($this->{$offset}) ? $this->{$offset} : null;
    }
}