<?php
/**
 * Database abstraction
 *
 * PHP version 5.4
 *
 * @category  Database
 * @package   Model
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2017 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Query_Dblib extends Tecnodesign_Query_Sql
{

    public static $textToVarchar=2147483647, $microseconds=3;
    /**
     * Returns the last inserted ID from a insert call
     * returns true if successful
     */
    public function lastInsertId($M=null, $conn=null)
    {
        if(!$conn) {
            $conn = self::connect($this->schema('database'));
        }
        $q = $conn->query('SELECT CAST(COALESCE(SCOPE_IDENTITY(), @@IDENTITY) AS int) as id');
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
            if($this->_groupBy && !$pk) {
                $cc = trim($this->_groupBy);
            } else if($pk && $this->_from && strpos($this->_from, ' left outer join ')) {
                $cc = static::concat($pk,'a.');
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
            . ' from '.$this->_from
            . (($this->_where)?(' where '.$this->_where):(''))
            . ((!$count && $this->_groupBy)?(' group by'.$this->_groupBy):(''))
            . ((!$count && $this->_orderBy)?(' order by'.$this->_orderBy):(''))
            . ((!$count && $this->_offset)?(' offset '.$this->_offset.' rows'):(''))
            . ((!$count && $this->_limit && $this->_offset)?(' fetch next '.$this->_limit.' rows only'):(''))
        ;
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

    public static function concat($a, $p='a.', $sep='-')
    {
        if(is_array($a)) {
            $r = $p.implode('+'.tdz::sql($sep).'+'.$p, $a);
            return $r;
        } else {
            return $p.$a;
        }
    }

    /**
     * Enables transactions for this connector
     * returns the transaction $id
     */
    public function transaction($id=null, $conn=null)
    {
        if(is_null($this->_transaction)) $this->_transaction = array();
        if(!$id) {
            $id = uniqid('tdzt');
        }
        if(!isset($this->_transaction[$id])) {
            if(!$conn) {
                $conn = self::connect($this->schema('database'));
            }
            $this->exec('begin transaction '.$id, $conn);
            $this->_transaction[$id] = $conn;
        }
        return $id;
    }

    /**
     * Commits transactions opened by ::transaction
     * returns true if successful
     */
    public function commit($id=null, $conn=null)
    {
        if(!$this->_transaction) return false;
        if(!$id) {
            $id = array_shift(array_keys($this->_transaction));
        }
        if(isset($this->_transaction[$id])) {
            if(!$conn) $conn = $this->_transaction[$id];
            unset($this->_transaction[$id]);
            if($conn) {
                $this->exec('commit transaction '.$id, $conn);
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * Commits transactions opened by ::transaction
     * returns true if successful
     */
    public function rollback($id=null, $conn=null)
    {
        if(!$this->_transaction) return false;
        if(!$id) {
            $id = array_shift(array_keys($this->_transaction));
        }
        if(isset($this->_transaction[$id])) {
            if(!$conn) $conn = $this->_transaction[$id];
            unset($this->_transaction[$id]);
            if($conn) {
                $this->exec('rollback transaction '.$id, $conn);
                return true;
            } else {
                return false;
            }
        }
    }

}