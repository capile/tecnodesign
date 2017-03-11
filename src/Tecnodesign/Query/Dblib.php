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
class Tecnodesign_Query_Dblib extends Tecnodesign_Query_Sqlite
{

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

}