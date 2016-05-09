<?php
/**
 * Database abstraction for MySQL
 *
 * PHP version 5.3
 *
 * @category  Data
 * @package   Birds
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id$
 * @link      http://tecnodz.com/
 */
namespace Birds\Data;
class MysqlSchema
{
    public static function load($n, $tn, $schema=array())
    {
        try {
            $tdesc = \Birds\Data\Sql::runStatic($n, 'show full columns from '.$tn)->fetchAll(\PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            return false;
        }
        if(!$tdesc || count($tdesc)==0) return false;

        if(!isset($schema['class'])) $schema['class']='';
        $schema['table'] = $tn;
        $schema['columns'] = array();
        $schema['relations'] = array();
        $schema['scope'] = array();
        foreach($tdesc as $fd) {
            $schema['columns'][$fd['Field']]=self::parseColumn($fd);
            if(isset($schema['columns'][$fd['Field']]['primary']) && $schema['columns'][$fd['Field']]['primary'])
                $schema['scope']['primary'][] = $fd['Field'];
            unset($fd);
        }
        $schema['class'] = \Birds\Schema\Builder::className($tn, $schema);
        $schema = self::parseRelations($n, $schema);
        return $schema;
    }

    public static function parseColumn($fd)
    {
        $f=array();
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
        }
        $f['null'] = ($fd['Null']=='YES');
        if($fd['Key']=='PRI') {
            $f['primary']=true;
        }
        return $f;
    }

    
    
    public static function parseRelations($n, $s=array())
    {
        $tn = $s['table'];
        try {
            $rels = \Birds\Data\Sql::runStatic($n, "select constraint_name as fk, ordinal_position as pos, table_name as tn, column_name as f, referenced_table_name as ref, referenced_column_name as ref_f from information_schema.key_column_usage where table_schema=database() and referenced_table_name is not null and (table_name='{$tn}' or referenced_table_name='{$tn}') order by 2, 1")->fetchAll(\PDO::FETCH_ASSOC);
        } catch(Exception $e) {
            return $s;
        }
        if(!$rels) return $s;
        if(!isset($s['relations'])) $s['relations'] = array();
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
            $alias = $class = \Birds\Schema\Builder::className($ref);
            if(!$alias) continue;
            if(preg_match('/^__([a-z0-9]+)__$/i', $rel['fk'], $m)) {
                $alias = $m[1];
            } else if(strpos($alias, '_')!==false || strpos($alias, '\\')!==false) {
                $alias = preg_replace('/.*[_\\\\]([^_\\\\]+)$/', '$1', $alias);
            }
            if (isset($r[$alias])) {
                if(!is_array($r[$alias]['local'])) {
                    $s['relations'][$alias]['local']=array($s['relations'][$alias]['local']);
                    $s['relations'][$alias]['foreign']=array($s['relations'][$alias]['foreign']);
                }
                $s['relations'][$alias]['local'][]=$local;
                $s['relations'][$alias]['foreign'][]=$foreign;
            } else {
                $s['relations'][$alias]['local']=$local;
                $s['relations'][$alias]['foreign']=$foreign;
            }
            $s['relations'][$alias]['type']=$type;
            if($alias!=$class){
                $s['relations'][$alias]['className']=$class;
            }
        }
        return $s;
    }
}