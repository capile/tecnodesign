<?php
/**
 * Tecnodesign Database
 * 
 * Basic and simple ORM based on PDO methods only
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Database
{
    
    public static $classPrefix='', $ns=null, $actAsAlias=null, $enableViews=true, $database='tdz', $dbo=array('/mysql');
    
    public function updateSchema()
    {
        $app = tdz::getApp();
        $req = $app->request();
        self::syncronizeModels($req['argv']);
        echo("\n");
        return 'cli';
    }
    
    /**
     * Fetches all table names from given database
     * 
     * @param string $db connection name 
     */
    public static function getTables($db=null)
    {
        if (is_null($db)) {
            $db = array_keys(Tecnodesign_Query::database())[0];
        }
        $H = Tecnodesign_Query::handler($db);
        if(!method_exists($H, 'getTables')) return [];
        return $H->getTables($db);
    }

    /**
     * Fetches all table names from given database
     * 
     * @param string $db connection name 
     */
    protected static $models=null;
    public static function getModels($db=null)
    {
        if(is_null(self::$models)){
            $tables = self::getTables($db);
            $m = array();
            foreach ($tables as $tn) {
                $cn=false;
                if(is_array($tn)) {
                    $a=$tn;
                    if(isset($a[0]) && $a[0]) {
                        $tn = $a[0];
                    } else {
                        $tn = array_shift($a);
                    }
                    if(isset($a[1]) && $a[1]) {
                        // parse comments
                        $opt = Tecnodesign_Yaml::loadString($a[1]);
                        if(is_array($opt) && isset($opt['className'])) {
                            $cn = $opt['className'];
                            if(!$cn) continue;
                        }
                    }
                }
                if(!$cn){
                    $cn = tdz::camelize(ucfirst($tn));
                }
                $cn = self::$classPrefix.$cn;
                if(!is_null(self::$ns)) {
                    $cn = self::$ns.'\\'.$cn;
                }
                if (class_exists($cn)) {
                    $m[$tn]=$cn;
                }
            }
            self::$models=$m;
        }
        return self::$models;
    }
    
    public static function className($tn, $db=null)
    {
        if($db && ($models = self::getModels($db)) && isset($models[$tn])) {
            return $models[$tn];
        } else {
            $p = self::$classPrefix;
            if(!is_null(self::$ns) && self::$ns) {
                $p = self::$ns.'\\'.$p;
            }

            return (class_exists($cn=$p.tdz::camelize($tn))) ?$cn :$p.tdz::camelize($tn, true);
        }
    }
    
    
    public static function import($data=null)
    {
        if(!$data && TDZ_CLI && ($data=Tecnodesign_App::request('argv'))) {
            if(count($data)==1) $data = array_shift($data);
            else {
                foreach($data as $f) {
                    self::import($data);
                }
                return;
            }
        }
        if(!is_array($data)) {
            if(strpos($data, "\n")===false && file_exists($data)) {
                $data = file_get_contents($data);
            }
            $data = Tecnodesign_Yaml::load($data);
        }
        if(!is_array($data)) {
            return false;
        }
        try {
            $replace = array();
            foreach($data as $cn=>$records) {
                if(!class_exists($cn)) continue;
                $sc = $cn::$schema;
                foreach($records as $k=>$r) {
                    $o=null;
                    $q=null;
                    $set = null;
                    $r = tdz::expandVariables($r);
                    if(isset($r['__key'])) {
                        $pks = $r['__key'];
                        unset($r['__key']);
                    } else {
                        $pks = $cn::pk($sc, true);
                    }
                    if(isset($r['__set'])) {
                        $set = $r['__set'];
                        unset($r['__set']);
                    }
                    if($pks) {
                        $q = array();
                        if(!is_array($pks)) $pks = array($pks);
                        foreach($pks as $pk) {
                            if(isset($r[$pk])) $q[$pk] = $r[$pk];
                        }
                    }
                    if($q) {
                        $o=$cn::find($q,1);
                    }
                    if(!$o) {
                        $o = new $cn;
                    }
                    $rel = array();
                    foreach($r as $fn=>$fv) {
                        if (isset($sc->properties[$fn]) || substr($fn, 0, 1)=='_') {
                            if(!$fv) {
                                $fv = false;
                            }
                            $o->$fn = $fv;
                        } else if(isset($sc['relations'][$fn])) {
                            $o->setRelation($fn, $fv);
                        }
                    }
                    $o->save();

                    if($set) {
                        foreach($set as $sk=>$sv) {
                            if(!defined($sk)) define($sk, $o->$sv);
                        }
                    }
                }
            }
        } catch(Exception $e) {
            tdz::debug($e->getMessage(), $r, (string)$e);
        }
    }
    

    public static function exportSchema($tns=array(), $dbs=null)
    {
        if(TDZ_CLI && !$tns && ($r=Tecnodesign_App::response('route')) && $r['class']===get_called_class() && $r['method']==='exportSchema') {
            // parse cmdline args -- expect table names
            $tns = Tecnodesign_App::request('argv');
            if(!is_array($tns)) $tns = [];
            $output = true;
            if(!class_exists('tdzEntry')) {
                if(!in_array($libdir = dirname(__FILE__).'/Studio/Resources/model', tdz::$lib)) tdz::$lib[]=$libdir;
                unset($libdir);
            }
        }

        if(is_null($dbs) || !is_array($dbs)) {
            $dbs = Tecnodesign_Query::database();
        }
        if(!$dbs) return false;
        $res = [];
        foreach($dbs as $db=>$dbo) {
            if(isset($dbo['sync']) && !$dbo['sync']) continue;
            self::$database = $db;
            self::$dbo = $dbo;
            $H = Tecnodesign_Query::handler($db);
            if(!method_exists($H, 'getTables') || !method_exists($H, 'getTableSchema')) continue;
            $tables = $H->getTables($db);
            if(!$tables) continue;
            if($tns) $tables = array_intersect($tables, $tns);
            if(!$tables) continue;

            foreach($tables as $tn) {
                $res[] = $H->getTableSchema($tn);
            }
        }

        if(isset($output)) {
            echo json_encode($res, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            return Tecnodesign_App::end();
        }

        return $res;
    }

    /**
     * Syncronizes the application models with the database
     */
    public static function syncronizeModels($tns=array(), $lib=false, $metadata=false, $dbs=null)
    {
        if(is_null($dbs) || !is_array($dbs)) {
            $dbs = tdz::$database;
        }
        if(!$dbs) return false;
        $ctns = count($tns);
        if(!$lib) {
            $libs = tdz::$lib;
            $lib = array_shift($libs);
        }
        $libs = tdz::$lib;
        $tables = array();
        foreach($dbs as $db=>$dbo) {
            if(isset($dbo['sync']) && !$dbo['sync']) continue;
            self::$database = $db;
            self::$dbo = $dbo;
            tdz::setConnection('', tdz::connect($db));
            $tbls = Tecnodesign_Database::getTables($dbo);
            if(!$tbls) continue;
            foreach($tbls as $td) {
                $tn = (is_array($td))?($td['table_name']):($td);
                if(substr($tn,0,1)=='_') continue;
                $cn = self::className($tn, $dbo);//ucfirst(tdz::camelize("$tn"));
                $opts = array('table'=>$tn, 'database'=>$db, 'className'=>$cn);
                if(is_array($td)){
                    $a=$td;
                    if(isset($a[0]) && $a[0]) {
                        $tn=$opts['table'] = $a[0];
                    } else {
                        $tn=$opts['table'] = array_shift($a);
                    }
                    if(isset($a[1]) && $a[1]) {
                        // parse comments
                        $add = Tecnodesign_Yaml::load($a[1]);
                        if(is_array($add)) {
                            $opts=$add+$opts;
                        }
                    }
                }
                if(!$opts['className']){
                    continue;
                }
                $tables[$db.'.'.$tn]=$opts;
            }
        }
        $meta='';
        // search for unavailable models
        foreach($tables as $tid=>$t) {
            $tn = $t['table'];
            $db = $t['database'];
            $cn = $t['className'];
            $cf = str_replace(array('_', '\\'), '/', $cn);
            if($ctns && !in_array($tn, $tns) && !in_array($cn, $tns)) {
                unset($tables[$tid]);
                continue;
            }
            $cfile = tdz::autoload($cn);
            if(!$cfile) {
                $cfile = $lib.'/'.$cf.'.php';
            }
            if (file_exists($cfile)) {
                $tables[$tid]['file']=$cfile;
                continue;
            }
            if (count($libs)>0) {
                foreach($libs as $lib2) {
                    $cfile2 = $lib2.'/'.$cf.'.php';
                    if (file_exists($cfile2)) {
                        $cfile = $cfile2;
                        break;
                    }
                }
                if (file_exists($cfile)) {
                    $tables[$tid]['file']=$cfile;
                    continue;
                }
            }
            $tables[$tid]['file']=$cfile;
            
            if(!$meta && $metadata){
                if(!$metadata || !is_array($metadata)) {
                    $metadata = tdz::getApp()->metadata;
                }
                if($metadata){
                    $ml = 0;
                    foreach ($metadata as $prop=>$value) {
                        if(strlen($prop)>$ml) {
                            $ml = strlen($prop);
                        }
                    }
                    foreach ($metadata as $prop=>$value) {
                        $meta .= " * @{$prop}".str_repeat(' ', $ml - strlen($prop))." {$value}\n";
                    }
                }
            }
            $ns=false;
            $ocn = str_replace('\\', '\\\\', $cn);
            if(strpos($cn, '\\')>0) {
                $ns = explode('\\', $cn);
                $cn = array_pop($ns);
                $ns = implode('\\', $ns);
            }
            $s = '<'."?php\n"
                . "/**\n"
                . " * {$ocn} table description\n"
                . " *\n"
                . " * PHP version 5.4+\n"
                . " *\n" . $meta
                . " */\n"
                . (($ns)?("namespace {$ns};\n"):(''))
                . "class {$cn} extends ".(($ns)?('\\'):(''))."Tecnodesign_Model\n"
                . "{\n"
                . "    /**\n"
                . "     * Tecnodesign_Model schema\n"
                . "     *\n"
                . "     * Remove the comment below to disable automatic schema updates\n"
                . "     */\n"
                . "    //--tdz-schema-start--\n"
                . "    public static \$schema = array(\n"
                . "      'database'=>'{$db}',\n"
                . "      'tableName'=>'{$tn}',\n"
                . "      'className'=>'{$ocn}',\n"
                . "    );\n"
                . "    //--tdz-schema-end--\n"
                . "}\n";
            tdz::save($cfile, $s, true);
        }
        foreach($tables as $tid=>$t) {
            $tn = $t['table'];
            $cn = $t['className'];
            $cfile = tdz::autoload($cn); // $t['file'];
            if(!$cfile) {
                $cfile = $t['file'];
            }
            require_once $cfile;
            $o = new $cn();
            $o->updateSchema($cfile);
        }
    }
}