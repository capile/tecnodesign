<?php
/**
 * Tecnodesign MSSQL Model specific methods and functions
 *
 * Basic and simple ORM based on PDO methods only
 *
 * PHP version 5.3
 *
 * @category  Model
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: Mysql.php 1053 2012-03-09 14:56:20Z capile $
 * @link      http://tecnodz.com/
 */

/**
 * Tecnodesign Model
 *
 * Basic and simple ORM based on PDO methods only
 *
 * @category  Model
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */
class Tecnodesign_Model_Dblib
{

    public static $behaviors=array(
        'uid'=>array('before-insert', 'before-update', 'before-delete'),
        'timestampable'=>array('before-insert', 'before-update', 'before-delete'),
        'sortable'=>array('before-insert', 'before-update', 'before-delete'),
        'versionable'=>array('after-insert', 'after-update', 'after-delete'),
        'soft-delete'=>array('active-records', 'before-delete'),
        'auto-increment'=>array('before-insert'),
    );
    public static function updateSchema($schema, $o)
    {
        $app = tdz::getApp();
        if(!$app) {
            return false;
        }
        $tn = $schema['tableName'];
        $db = $schema['database'];
        $dbs = tdz::$database;
        $conn = tdz::connect($db);
        tdz::setConnection('', $conn);
        if(preg_match('/\;dbname=([^\;]+)/', $dbs[$db]['dsn'], $m)){
            $dbname = $m[1];
        }
        $tdesc = tdz::query("select distinct c.column_name as name, c.data_type as type, null as 'default', c.is_nullable as 'null', isnull(c.character_maximum_length, c.numeric_precision) as size, c.numeric_scale as decimal, columnproperty(object_id(c.table_name),c.column_name,'IsIdentity') as 'autoincrement', pkc.column_name as 'primary', c.ordinal_position"
            . " from information_schema.columns as c"
            . " inner join information_schema.tables as t on t.table_catalog='{$dbname}' and t.table_name=c.table_name"
            . " left outer join information_schema.table_constraints as pk on pk.table_name=c.table_name and pk.constraint_type='PRIMARY KEY'"
            . " left outer join information_schema.key_column_usage as pkc on pkc.constraint_name=pk.constraint_name and pkc.column_name=c.column_name"
            . " where c.table_name='{$tn}' order by c.ordinal_position");
        $schema['columns'] = array();
        if(!$tdesc) {
            exit("No tables to update!\n");
        }
        foreach($tdesc as $fd) {
            $base = array();
            $fn = $fd['name'];
            if(isset($schema['columns'][$fn])) {
                $base = $schema['columns'][$fn];
            }
            $schema['columns'][$fn]=self::parseColumn($fd, $base, $o);
            if(isset($fd['default']) && $fd['default']!='') {
                $o[$fn]=$fd['default'];
            }
        }
        $schema = self::parseRelations($schema);
        if (!isset($schema['scope'])) {
            $schema['scope']=array();
        }
        if (!isset($schema['events'])) {
            $schema['events']=array();
        }
        if (!isset($schema['form'])) {
            $cn = (isset($schema['className']))?($schema['className']):(tdz::camelize(ucfirst($tn)));
            $cn::$schema =& $schema;
            $cn::formFields();
        }
        // extra: search for keywords in field comments
        // see static $behaviors
        $events=array();
        $se=array();
        if(is_array(Tecnodesign_Database::$actAsAlias)) {
            foreach (Tecnodesign_Database::$actAsAlias as $fn=>$comments) {
                if(isset($schema['columns'][$fn])) {
                    foreach(self::$behaviors as $bn=>$e) {
                        if(strpos($comments, $bn)!==false) {
                            if(isset($schema['form'][$fn])) {
                                unset($schema['form'][$fn]);
                            }
                            $found=array();
                            foreach($e as $en) {
                                if(strpos($comments, $en)!==false) {
                                    $found[]=$en;
                                }
                            }
                            if(count($found)>0) {
                                $e = $found;
                            }
                            foreach($e as $en) {
                                if($en=='active-record') {
                                    $events[$en][]=$fn;
                                } else {
                                    $events[$en][$bn][]=$fn;
                                    $se[$en][0]='actAs';
                                }
                            }
                        }
                    }
                }
            }
        }
        if(count($se)>0) {
            if(isset($se['active-records'])) {
                $se['active-records'] = implode('is null and ', $events['active-records']['soft-delete']).' is null';
                unset($events['active-records']);
            }
            $add = array('events'=>$se, 'actAs'=>$events);
            $schema = tdz::mergeRecursive($schema, $add);
        }
        $fcode = array();
        foreach ($schema['columns'] as $fn=>$fd) {
            if(isset($o->$fn)){
                $fcode[] = '$'.$fn.'='.var_export($o->$fn, true);
            } else {
                $fcode[] = '$'.$fn;
            }
        }
        foreach ($schema['relations'] as $fn=>$fd) {
            $fcode[] = '$'.$fn;
        }
        $indent = 2;
        $code = "//--tdz-schema-start--".date('Y-m-d H:i:s')."\npublic static \$schema = ".preg_replace('/\n'.str_repeat('  ', $indent).'( |\))+/', '" ".trim("$1")', preg_replace('/(=>)\s+/', '=> ', var_export($schema, true)));
        $code .= ";\nprotected ".implode(', ', $fcode).";\n";

        if(preg_match_all('/array \( (([0-9]+) \=\> \'[^\']*\', )+\)/', $code, $m)) {
            $r=array();
            foreach($m[0] as $l) {
                if(preg_match_all('/([0-9]+) \=\> (\'[^\']*\', )/', $l, $n)) {
                    $numeric = true;
                    $i=0;
                    $v='';
                    foreach($n[1] as $kk=>$k) {
                        $v .= $n[2][$kk];
                        if($k!=$i++) {
                            $numeric = false;
                            break;
                        }
                    }
                    if($numeric) {
                        $r[$l] = 'array ( '.$v.')';
                    }
                }
            }
            if(count($r)>0) {
                $code = str_replace(array_keys($r), array_values($r), $code);
            }
        }
        return $code;
    }


    public static function parseColumn($fd, $base=array(), $o)
    {
        $protected = array('type', 'min', 'max', 'length', 'null');// , 'increment'
        if(is_array($base) && count($base)>0) {
            foreach ($protected as $remove) {
                if(isset($base[$remove])) {
                    unset($base[$remove]);
                }
            }
        } else {
            $base = array();
        }
        $f=array();
        // find type and limits
        // date and datetime
        $type = $fd['type'];
        $desc = '';
        $unsigned = false;
        if(preg_match('/\s*\(([0-9\,]+)\)\s*(signed|unsigned)?$/', $type, $m)) {
            $desc = trim($m[1]);
            $type = substr($type, 0, strlen($type) - strlen($m[0]));
            if(isset($m[2]) && $m[2]=='unsigned') {
                $unsigned = true;
            }
        }
        if ($type=='datetime' || $type=='date') {
            $f['type'] = $type;
        } else if($type=='bit') {
            $f['type'] = 'bool';
        } else if(substr($type, -3)=='int') {
            $f['type'] = 'int';
            if($unsigned) {
                $f['min'] = 0;
            }
            if ($type=='tinyint') {
                $f['max'] = ($unsigned)?(255):(128);
            }
            if($fd['autoincrement']=='1') {
                $f['increment']='auto';
            }
            $f['size'] = (int)$fd['size'];
        } else if($type=='decimal') {
            $f['type'] = 'float';
            $f['size'] = (int)$fd['size'];
            $f['decimal'] = (int)$fd['decimal'];
        } else if($type=='money') {
            $f['type'] = 'float';
            $f['size'] = (int)$fd['size'];
            $f['decimal'] = 2;
        } else if($type=='float' || $type=='real') {
            $f['type'] = 'float';
            $f['size'] = (int)$fd['size'];
            $f['decimal'] = 4;
        } else if(substr($type, -4)=='text' || $type=='image' || $type=='varchar' || $type=='nvarchar') {
            $f['type'] = 'string';
            $f['size'] = (int)$fd['size'];
        } else if($type=='char') {
            $f['type'] = 'string';
            $f['size'] = (int)$fd['size'];
            $f['min-size'] = $fd['size'];
        } else if(substr($type, 0, 4)=='enum') {
            $f['type'] = 'string';
            $choices = array();
            preg_match_all('/\'([^\']+)\'/', $type, $m);
            foreach($m[1] as $v) {
                $choices[$v]=$v;
            }
            $f['choices']=$choices;
        } else {
            tdz::debug(__METHOD__.', '.__LINE__, $fd, $type, $desc, $m);
        }
        $f['null'] = (strtolower($fd['null'])=='yes');
        if($fd['primary']!='') {
            $f['primary']=true;
        }
        $f += $base;

        return $f;
    }



    public static function parseRelations($s=array())
    {
        $tn = $s['tableName'];
        $dbname = $db = $s['database'];
        $dbs = tdz::$database;
        if(preg_match('/\;dbname=([^\;]+)/', $dbs[$db]['dsn'], $m)){
            $dbname = $m[1];
        }
        $sql = "select t.constraint_name as fk, t.table_name as tn, t.column_name as f, ref.table_name as ref, ref.column_name as ref_f, t.ordinal_position as pos"
            . " from information_schema.key_column_usage as t"
            . " inner join information_schema.referential_constraints as r on r.constraint_name=t.constraint_name"
            . " inner join information_schema.key_column_usage as ref on ref.constraint_name=r.unique_constraint_name and ref.ordinal_position=t.ordinal_position"
            . " where t.table_name='{$tn}' or ref.table_name='{$tn}' order by t.ordinal_position, t.constraint_name";
        $rels = tdz::query($sql);
        $r = array();
        foreach ($rels as $rel) {
            if ($rel['tn']==$tn) {
                $ref = $rel['ref'];
                $local = $rel['f'];
                $type = 'one';
                $foreign = $rel['ref_f'];
            } else {
                $ref = $rel['tn'];
                $local = $rel['ref_f'];
                $type = 'many';
                $foreign = $rel['f'];
            }
            $alias = $class = Tecnodesign_Database::className($ref, $db);
            if(!class_exists($class)) {
                continue;
            }
            if(preg_match('/^__([a-z0-9]+)__$/i', $rel['fk'], $m)) {
                $alias = $m[1];
            } else if(strpos($alias, '_')!==false || strpos($alias, '\\')!==false) {
                $alias = preg_replace('/.*[_\\\\]([^_\\\\]+)$/', '$1', $alias);
            } else if(Tecnodesign_Database::$classPrefix && substr($alias, 0, strlen(Tecnodesign_Database::$classPrefix))==Tecnodesign_Database::$classPrefix) {
                $alias = substr($alias, strlen(Tecnodesign_Database::$classPrefix));
            }
            if (isset($r[$alias])) {
                if(!is_array($r[$alias]['local'])) {
                    $r[$alias]['local']=array($r[$alias]['local']);
                    $r[$alias]['foreign']=array($r[$alias]['foreign']);
                }
                $r[$alias]['local'][]=$local;
                $r[$alias]['foreign'][]=$foreign;
            } else {
                $r[$alias]['local']=$local;
                $r[$alias]['foreign']=$foreign;
            }
            $r[$alias]['type']=$type;
            if($alias!=$class){
                //$r[$class]=$r[$alias];
                $r[$alias]['className']=$class;
            }
        }
        if(!isset($s['relations']) || !is_array($s['relations'])) {
            $s['relations']=$r;
        } else {
            $s['relations']=$r + $s['relations'];
        }
        return $s;
    }
}
