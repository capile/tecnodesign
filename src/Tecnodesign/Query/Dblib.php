<?php
/**
 * Database abstraction
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Query_Dblib extends Tecnodesign_Query_Sql
{
    const DRIVER='dblib', QUOTE='[]', PDO_AUTOCOMMIT=0, PDO_TRANSACTION=0;

    public static 
        $textToVarchar=2147483647,
        $microseconds=3,
        $datetimeSize=null,
        $typeMap=['float'=>'decimal', 'number'=>'decimal','bool'=>'bit'],
        $options=array(
            'command'=>'SET CONCAT_NULL_YIELDS_NULL ON;SET QUOTED_IDENTIFIER ON;'
        );
    /**
     * Returns the last inserted ID from a insert call
     * returns true if successful
     */
    public function lastInsertId($M=null, $conn=null)
    {
        if(!$conn) {
            $conn = self::connect($this->schema('database'));
        }
        $q = $conn->query('select cast(coalesce(scope_identity(), @@identity) AS int) as id');
        if($q) {
            list($insertId) = $q->fetch(PDO::FETCH_NUM);
            return $insertId;
        }
    }

    public function buildQuery($count=false)
    {
        if(is_null($this->_where)) {
            $this->_where = $this->getWhere(array());
        }
        if($count) {
            $s = ' count(*)';
            $cc = '';
            $pk = $this->scope('uid');
            if($this->_groupBy) {
                if(strpos($this->_groupBy, ',')) $cc = 'checksum('.trim($this->_groupBy).')';
                else $cc = trim($this->_groupBy);
            } else if($pk && $this->_from && strpos($this->_from, ' left outer join ')) {
                $cc = $this->concat($pk);
            }
            if($cc) {
                $s = ' count(distinct '.$cc.')';
            }
        } else {
            if($this->_distinct && $this->_selectDistinct) {
                $this->_select = $this->_selectDistinct + $this->_select;
            }
            $s = $this->_distinct;
            if(!$this->_offset && $this->_limit) {
                $s .= ' top '.$this->_limit;
            }
            $s .= ($this->_select)?(' '.implode(', ', $this->_select)):(' a.*');
        }

        $q = 'select'
            . $s
            . ' from '.$this->getFrom()
            . (($this->_where)?(' where '.$this->_where):(''))
            . ((!$count && $this->_groupBy)?(' group by'.$this->_groupBy):(''))
            . ((!$count && $this->_orderBy)?(' order by'.$this->_orderBy):(''))
        ;

        if(!$count && ($this->_offset||$this->_limit)) {
            if(!$this->_orderBy) {
                $q .= ' order by 1';
            }
            if($this->_offset) {
                $q .= ' offset '.$this->_offset.' rows';
            }
            if($this->_limit && $this->_offset) {
                $q .= ' fetch next '.$this->_limit.' rows only';
            }
        }

        return $q;
    }

    public function addOrderBy($o, $sort='asc')
    {
        parent::addOrderBy($o, $sort);
        if($o && $this->_distinct) {
            if(is_array($o)) {
                $fns = array();
                foreach($o as $k=>$v) {
                    if(is_int($v)) continue;
                    else if(is_int($k)) $fns[] = preg_replace('/\s+(asc|desc)/', '', $v);
                    else $fns[] = $k;
                }
            } else if(!is_int($o)) {
                $fns = array(preg_replace('/\s+(asc|desc)/', '', $o));
            } else {
                $fns = null;
            }
            if($fns) {
                foreach($fns as $i=>$o) {
                    if(strpos($o, '.')!==false && strpos($o, ' ')===false) {
                        $fns[$i] .= ' __orderby'.$i;
                    }
                }
                $this->addSelect($fns);
            }
        }
        return $this;
    }

    public function concat($a, $sep='-', $getAlias=true)
    {
        if(is_array($a) && count($a)>1) {
            $r = '';
            foreach($a as $fn) {
                if($getAlias) $fn=$this->getAlias($fn, null, true);
                $r .= (($r) ?'+'.tdz::sql($sep).'+' :'')
                    . 'coalesce('.$fn.',\'\')';
            }
            return $r;
        } else {
            if(is_array($a)) $a = array_shift($a);
            return ($getAlias) ?$this->getAlias($a, null, true) :$a;
        }
    }

    public function getTablesQuery($database=null, $enableViews=null)
    {
        $dbname = $this->getDatabaseName($database);

        return 'select table_name from INFORMATION_SCHEMA.TABLES where TABLE_CATALOG='.tdz::sql($dbname)
            . ((!$enableViews) ?' and TABLE_TYPE=\'BASE TABLE\'' :'');
    }

    public function getTableSchemaQuery($table, $database=null, $enableViews=null)
    {
        $dbname = $this->getDatabaseName($database);
        $schema = null;
        if(strpos($table, '.')!==false) {
            $td = explode('.', $table);
            $table = array_pop($td);
            if($td) $schema = array_pop($td);
            if($td) $dbname = array_pop($td);
        }

        if(($db=$this->query('select distinct TABLE_CATALOG from INFORMATION_SCHEMA.TABLES')) && $db[0]['TABLE_CATALOG']!=$dbname) {
            // force using the correct database
            $this->exec('use '.tdz::sql($dbname, false));
        }
        unset($db);

        $dbname = tdz::sql($dbname);
        $tn = tdz::sql($table);

        return "select COLUMN_NAME as 'bind', DATA_TYPE as 'type', CHARACTER_MAXIMUM_LENGTH as 'size', COLUMN_DEFAULT as 'default', case when IS_NULLABLE='NO' then 1 else 0 end as 'required' from INFORMATION_SCHEMA.COLUMNS where TABLE_CATALOG={$dbname} and TABLE_NAME={$tn} order by ORDINAL_POSITION asc";
    }

    public function getRelationSchemaQuery($table, $database=null, $enableViews=null)
    {
        $dbname = $this->getDatabaseName($database);
        $schema = null;
        if(strpos($table, '.')!==false) {
            $td = explode('.', $table);
            $table = array_pop($td);
            if($td) $schema = array_pop($td);
            if($td) $dbname = array_pop($td);
        }
        if(($db=$this->query('select distinct TABLE_CATALOG from INFORMATION_SCHEMA.TABLES')) && $db[0]['TABLE_CATALOG']!=$dbname) {
            // force using the correct database
            $this->exec('use '.tdz::sql($dbname, false));
        }
        unset($db);

        $tn = tdz::sql($table);
        return "select f.name as fk,OBJECT_NAME(f.parent_object_id) as tn, COL_NAME(fc.parent_object_id, fc.parent_column_id) as f,OBJECT_NAME (f.referenced_object_id) as ref,COL_NAME(fc.referenced_object_id, fc.referenced_column_id) as ref_f from sys.foreign_keys as f inner join sys.foreign_key_columns as fc on f.object_id=fc.constraint_object_id where f.is_disabled=0 and (object_name(f.parent_object_id)={$tn} or OBJECT_NAME(f.referenced_object_id)={$tn})";
    }
}