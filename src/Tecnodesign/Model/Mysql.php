<?php
/**
 * Tecnodesign MySQL Model specific methods and functions
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
 * @version   SVN: $Id: Mysql.php 1268 2013-08-06 18:18:16Z capile $
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
class Tecnodesign_Model_Mysql
{
    
    public static $behaviors=array(
        'uid'=>array('before-insert', 'before-update', 'before-delete'),
        'timestampable'=>array('before-insert', 'before-update', 'before-delete'),
        'insertable'=>array('before-update', 'before-delete'),
        'sortable'=>array('before-insert', 'before-update', 'before-delete'),
        'versionable'=>array('after-insert', 'after-update', 'after-delete'),
        'soft-delete'=>array('active-records', 'before-delete'),
        'auto-increment'=>array('before-insert'),
    );
    public static $properties=array('serialize','alias');
    public static function updateSchema($schema, $o=false)
    {
        $tn = $schema['tableName'];
        $db = $schema['database'];
        $cn = (isset($schema['className']))?($schema['className']):(tdz::camelize(ucfirst($tn)));
        tdz::setConnection('', tdz::connect($db));
        $tdesc = tdz::query('show full columns from '.$tn);
        $schema['columns'] = array();
        if(!$tdesc) {
            exit("No tables to update!\n");
        }
        if($o && $o instanceof Tecnodesign_Model) $o=$o->asArray();
        foreach($tdesc as $fd) {
            $base = array();
            $fn = array_values($fd);
            if(isset($schema['columns'][$fn[0]])) {
                $base = $schema['columns'][$fn[0]];
            }
            $schema['columns'][$fn[0]]=self::parseColumn($fd, $base, $o);
            if($o && isset($fd['Default']) && $fd['Default']!='') {
                $o[$fn[0]]=$fd['Default'];
            }
        }
        $schema = Tecnodesign_Model_Mysql::parseRelations($schema);
        if (!isset($schema['scope'])) {
            $schema['scope']=array();
        }
        if (!isset($schema['events'])) {
            $schema['events']=array();
        }
        $cn = (isset($schema['className']))?($schema['className']):(tdz::camelize(ucfirst($tn)));
        if (!isset($schema['form']) && method_exists($cn, 'formFields')) {
            $cn::$schema =& $schema;
            $cn::formFields();
        }
        // extra: search for keywords in field comments
        // see static $behaviors
        $events=array();
        $se=array();
        $reprop = '/('.implode('|',self::$properties).'):\s*([^,\s\;]+)([,\s\;]+)?/';
        foreach ($tdesc as $fd) {
            if($fd['Comment']!='') {
                $fn = array_values($fd);
                $fn = array_shift($fn);
                if(preg_match_all($reprop, $fd['Comment'], $m)) {
                    foreach($m[1] as $k=>$v) {
                        $schema['columns'][$fn][$v] = $m[2][$k];
                        unset($k, $v);
                    }
                    str_replace($m[0], '', $fd['Comment']);
                    unset($m);
                    if(trim($fd['Comment'])=='') continue;
                }

                foreach(self::$behaviors as $bn=>$e) {
                    if(strpos($fd['Comment'], $bn)!==false) {
                        if(isset($schema['form'][$fn])) {
                            unset($schema['form'][$fn]);
                        }
                        $found=array();
                        foreach($e as $en) {
                            if(strpos($fd['Comment'], $en)!==false) {
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
        if(count($se)>0) {
            if(isset($se['active-records'])) {
                $se['active-records'] = '`'.implode('` is null and `', $events['active-records']['soft-delete']).'` is null';
                unset($events['active-records']);
            }
            $add = array('events'=>$se, 'actAs'=>$events);
            $schema = tdz::mergeRecursive($schema, $add);
        }
        if($o===false) {
            return $schema;
        }
        $fcode = array();
        foreach ($schema['columns'] as $fn=>$fd) {
            if(isset($o[$fn])){
                $fcode[] = '$'.$fn.'='.var_export($o[$fn], true);
            } else {
                $fcode[] = '$'.$fn;
            }
        }
        foreach ($schema['relations'] as $fn=>$fd) {
            $fcode[] = '$'.$fn;
        }
        $indent = 2;
        $code = "//--tdz-schema-start--".date('Y-m-d H:i:s')."\npublic static \$schema = "
            . str_replace(array("\n  array (",',),','array ( '),array('array(',' ),','array( '),preg_replace('/\n\r?    (a|\)| ) */', '$1', var_export($schema, true)))
            //. preg_replace('/\n'.str_repeat('  ', $indent).'( |\))+/e', '" ".trim("$1")', preg_replace('/(=>)\s+/', '=> ', var_export($schema, true)))
            ;
        $code .= ";\nprotected ".implode(', ', $fcode).";\n";
        
        if(preg_match_all('/ array\( (([0-9]+) \=\> \'[^\']*\',? )+\)/', $code, $m)) {
            $r=array();
            foreach($m[0] as $l) {
                if(preg_match_all('/([0-9]+) \=\> (\'[^\']*\',? )/', $l, $n)) {
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
                        $r[$l] = 'array( '.$v.')';
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
        $type = trim(strtolower($fd['Type']));
        $desc = '';
        $unsigned = false;
        if(preg_match('/\s*\(([0-9\,]+)\)\s*(signed|unsigned)?.*/', $type, $m)) {
            $desc = trim($m[1]);
            $type = substr($type, 0, strlen($type) - strlen($m[0]));
            if(isset($m[2]) && $m[2]=='unsigned') {
                $unsigned = true;
            }
        }
        if ($type=='datetime' || $type=='date') {
            $f['type'] = $type;
        //} else if($type=='tinyint' && $desc=='1') {
        //    $f['type'] = 'bool';
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
            if($fd['Extra']=='auto_increment') {
                $f['increment']='auto';
            }
        } else if($type=='float') {
            $f['type'] = 'float';
            $f['size'] = (int) 10;
            $f['decimal'] = (int) 2;
        } else if($type=='decimal') {
            $f['type'] = 'float';
            $desc = explode(',',$desc);
            $f['size'] = (int)$desc[0];
            $f['decimal'] = (int)$desc[1];
        } else if($type=='double') {
            $f['type'] = 'float';
            $f['size'] = (int) 10;
            $f['decimal'] = (int) 2;
        } else if(substr($type, -4)=='text') {
            $f['type'] = 'string';
            $f['size'] = $desc;
        } else if($type=='varchar') {
            $f['type'] = 'string';
            $f['size'] = $desc;
        } else if($type=='char') {
            $f['type'] = 'string';
            $f['size'] = $desc;
            $f['min-size'] = $desc;
        } else if(substr($type, 0, 4)=='enum') {
            $f['type'] = 'string';
            $choices = array();
            preg_match_all('/\'([^\']+)\'/', $type, $m);
            foreach($m[1] as $v) {
                $choices[$v]=$v;
            }
            $f['choices']=$choices;
        } else {
            tdz::debug($fd, $type, $desc, $m);
        }
        $f['null'] = ($fd['Null']=='YES');
        if($fd['Key']=='PRI') {
            $f['primary']=true;
        }
        $f += $base;
        
        return $f;
    }

    
    
    public static function parseRelations($s=array())
    {
        $tn = $s['tableName'];
        $dbname = $db = $s['database'];
        if(tdz::$database) {
            $dbo = tdz::$database[$db];
            if(preg_match('/\;dbname=([^\;]+)/', $dbo['dsn'], $m)){
                $dbname = $m[1];
            }
        } else {
            $dbo = Tecnodesign_Database::$dbo;
        }
        $sql = "select constraint_name as fk, ordinal_position as pos, table_name as tn, column_name as f, referenced_table_name as ref, referenced_column_name as ref_f from information_schema.key_column_usage where table_schema='{$dbname}' and referenced_table_name is not null and (table_name='{$tn}' or referenced_table_name='{$tn}') order by 2, 1";
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
            $alias = $class = Tecnodesign_Database::className($ref, $dbo);
            if(!class_exists($class)) {
                tdz::debug($class." does not exist!\n", false);
                continue;
            }
            if(preg_match('/^__([a-z0-9]+)__$/i', $rel['fk'], $m)) {
                $alias = $m[1];
            } else if(strpos($alias, '_')!==false || strpos($alias, '\\')!==false) {
                $alias = preg_replace('/.*[_\\\\]([^_\\\\]+)$/', '$1', $alias);
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