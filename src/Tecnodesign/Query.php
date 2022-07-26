<?php
/**
 * Database abstraction
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
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

            if(!tdz::$database) {
                $cfgDir = (isset($app->tecnodesign['config-dir'])) ?$app->tecnodesign['config-dir'] :TDZ_APP_ROOT.'/config';
                if(file_exists($f=$cfgDir.'/databases.yml')) {
                    $C = Tecnodesign_Yaml::load($f);
                    tdz::$database = array();
                    if(isset($C[tdz::env()])) {
                        tdz::$database = $C[tdz::env()]; 
                    }
                    if(isset($C['all'])) {
                        tdz::$database += $C['all']; 
                    }
                    unset($C);
                }
            }

            if(tdz::$database) {
                foreach(tdz::$database as $name=>$def) {
                    if(isset($def['dsn']) && strpos($def['dsn'], '$')!==false) {
                        if(!isset($s)) {
                            $s = array('$APPS_DIR', '$DATA_DIR');
                            $r = array($app->tecnodesign['apps-dir'], $app->tecnodesign['data-dir']);
                            unset($app);
                        }
                        tdz::$database[$name]['dsn'] = str_replace($s, $r, $def['dsn']);
                    }
                }
            }
            unset($app);
        }
        if(!is_null($db)) {
            if(isset(tdz::$database[$db])) return tdz::$database[$db];
            if(strpos($db, ':')) {
                $options = null;
                if(strpos($db, ';')) {
                    list($dsn, $params) = explode(';', $db, 2);
                    if($params) parse_str($params, $options);
                } else {
                    $dsn = $db;
                }
                list($h, $u) = explode(':', $dsn, 2);
                if(class_exists($cn='Tecnodesign_Query_'.tdz::camelize($h, true))) {
                    tdz::$database[$db] = [
                        'dsn'=>$dsn,
                        'className' => $cn,
                    ];
                    if($options) {
                        tdz::$database[$db]['options'] = $options;
                    }
                }
                return tdz::$database[$db];
            }

            return null;
        }

        return tdz::$database;
    }

    public static function handler($s=null)
    {
        static $H = array();
        $n = '';
        if(is_string($s) && static::database($s)) {
            $n = $s;
        } else if((is_string($s) && $s && property_exists($s, 'schema')) || $s instanceof Tecnodesign_Model) {
            $n = $s::$schema->database;
            if(is_object($s)) {
                $s = (isset($s::$schema->className))?($s::$schema->className):(get_class($s));
            }
        }
        if(!isset($H[$n])) {
            $H[$n] = self::databaseHandler($n);
        }
        $cn = $H[$n];

        return new $cn($s);
    }

    public static function databaseHandler($n)
    {
        $dbs = self::database();
        if(isset($dbs[$n])) {
            $db = $dbs[$n];
        } else if((is_string($n) && $n && property_exists($n, 'schema')) || $n instanceof Tecnodesign_Model) {
            $n = $n::$schema->database;
            if(isset($dbs[$n])) {
                $db = $dbs[$n];
            }
        }
        if(!isset($db)) {
            throw new Tecnodesign_Exception(['There\'s no %s database configured', $n]);
        }
        $cn = (isset($db['class']))?($db['class']):('Tecnodesign_Query_'.ucfirst(substr($db['dsn'], 0, strpos($db['dsn'], ':'))));
        unset($dbs, $db);
        return $cn;
    }

    public function offsetSet($offset, $value): void
    {
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
    public function offsetExists($offset): bool
    {
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
    public function offsetUnset($offset): void
    {
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
            }
        }
        unset($this->{$offset});
    }

    #[\ReturnTypeWillChange]
    public function offsetGet($offset)
    {
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