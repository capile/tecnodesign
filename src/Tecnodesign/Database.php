<?php
/**
 * Tecnodesign Database
 *
 * Basic and simple ORM based on PDO methods only
 *
 * PHP version 5.3
 *
 * @category  Database
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: Database.php 1296 2013-12-04 23:26:15Z capile $
 * @link      http://tecnodz.com/
 */

/**
 * Tecnodesign Database
 *
 * Basic and simple ORM based on PDO methods only
 *
 * @category  Database
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
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
        if (is_null($db) || !is_array($db)) {
            $app = tdz::getApp();
            if(!$app) {
                return false;
            }
            $dbs = tdz::$database;
            if(is_null($db)) {
                $dbnames = array_keys($dbs);
                $dbn = $dbnames[0];
            } else {
                $dbn = $db;
            }
            $db = $dbs[$dbn];
        }
        $tables = array();
        $conn = tdz::connect($db);
        if(preg_match('/\;dbname=([^\;]+)/', $db['dsn'], $m)){
            $dbname = $m[1];
            $driver = @$conn->getAttribute(PDO::ATTR_DRIVER_NAME);
            if($driver=='dblib') {
                $sql = "select table_name from information_schema.tables where table_catalog='{$dbname}'".((!self::$enableViews)?(" and table_type='BASE TABLE'"):(''));
                $tables = $conn->query($sql)->fetchAll(PDO::FETCH_COLUMN);
            } else {
                // mysql detailed query
                $tables = $conn->query("select table_name, table_comment, create_time, update_time from information_schema.tables where table_schema='{$dbname}'")->fetchAll();
            }
        }
        if(!$tables || count($tables)==0){
            if(!isset($driver)) {
                $driver = (isset($db['dsn']))?(preg_replace('/\:.*/', '', $db['dsn'])):(null);
            }
            $q = ($driver=='sqlite')?('select name from sqlite_master where type=\'table\''):('show tables');
            $tables = $conn->query($q)->fetchAll(PDO::FETCH_COLUMN);
        }
        return $tables;
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
        $models = self::getModels($db);
        if(isset($models[$tn])) {
            return $models[$tn];
        } else {
            $p = self::$classPrefix;
            if(!is_null(self::$ns)) {
                $p = self::$ns.'\\'.$p;
            }
            return $p.ucfirst(tdz::camelize($tn));
        }
    }
    
    
    public static function import($data)
    {
        if(!is_array($data)) {
            if(strpos($data, "\n")===false && file_exists($data)) {
                $data = file_get_contents($data);
            }
            $data = Tecnodesign_Yaml::load($data);
        }
        if(!is_array($data)) {
            return false;
        }
        $next = array();
        try {
            foreach($data as $cn=>$records) {
                if(!class_exists($cn)) continue;
                $sc = $cn::$schema;
                foreach($records as $k=>$r) {
                    $o=false;
                    if(substr($k, 0, 1)=='$') {
                        // fetch existing record by primary key, otherwise, create record
                        $o=$cn::find(substr($k,1));
                        if($o) {
                            $o->isNew(false);
                        }
                    }
                    if(!$o) {
                        $o = new $cn;
                        $o->isNew(true);
                    }
                    $rel = array();
                    foreach($r as $fn=>$fv) {
                        if (isset($sc['columns'][$fn])) {
                            if(trim($fv)=='') {
                                $fv = false;
                            }
                            $o->$fn = $fv;
                        } else if(isset($sc['relations'][$fn])) {
                            $rel[$fn]=$fv;
                        }
                    }
                    $o->save();
                    foreach($rel as $rn=>$rv) {
                        $rcn = $rn;
                        $rd = $sc['relations'][$rn];
                        if(isset($rv['className'])) {
                            $rcn = $rv['className'];
                        }
                        $base = array();
                        if(!is_array($rd['local'])) {
                            $base[$rd['foreign']]=$o->{$rd['local']};
                        } else {
                            foreach($rd['local'] as $i=>$fn) {
                                $base[$rd['foreign'][$i]]=$o->$fn;
                            }
                        }
                        $erv = $o->getRelation($rn);
                        if($erv) {
                            if($rd['type']=='one') {
                                $next[$rcn]['$'.$erv->getPk()]=$base + $rv + (array)$erv->asArray();
                                $rv = array();
                            } else {
                                foreach($rv as $i=>$rvv) {
                                    $val = $erv[$i];
                                    if(!$val) {
                                        break;
                                    }
                                    $next[$rcn]['$'.$val->getPk()]=$base + $rvv + (array)$val->asArray();
                                    unset($rv[$i]);
                                }
                            }
                        }
                        foreach($rv as $rvv) {
                            $next[$rcn][]=$base + $rvv;
                        }
                    }
                }
                if(count($next)>0) {
                    self::import($next);
                }
            }
        } catch(Exception $e) {
            tdz::debug($e->getMessage(), (array)$o);
        }
    }
    
    /**
     * Syncronizes the application models with the database
     */
    public static function syncronizeModels($tns=array(), $lib=false, $metadata=false, $dbs=null)
    {
        if(is_null($dbs) || !is_array($dbs)) {
            $app = tdz::getApp();
            if(!$app) {
                tdz::debug('no app');
                return false;
            }
            $dbs = tdz::$database;
        }
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
            tdz::setConnection('', tdz::connect($dbo));
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
            
            if(!$meta && ($metadata || $app)){
                if(!$metadata || !is_array($metadata)) {
                    $metadata = $app->metadata;
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
                . " * PHP version 5.3\n"
                . " *\n" . $meta
                . " * @version   SVN: \$Id\$\n"
                . " */\n\n"
                . "/**\n"
                . " * {$ocn} table description\n"
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