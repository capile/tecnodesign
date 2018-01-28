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
class Tecnodesign_Query_Sqlite extends Tecnodesign_Query_Sql
{
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
                return $this->exec('commit transaction '.$id, $conn);
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
                return $this->exec('rollback transaction '.$id, $conn);
            } else {
                return false;
            }
        }
    }
}