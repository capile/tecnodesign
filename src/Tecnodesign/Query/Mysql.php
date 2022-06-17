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
 * @version   2.6
 */
class Tecnodesign_Query_Mysql extends Tecnodesign_Query_Sql
{
    const DRIVER='mysql';
    public static $options=array(
        PDO::ATTR_PERSISTENT => false,
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ),
    $tableAutoIncrement='auto_increment',
    $tableDefault='ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';

    public function getTablesQuery($database=null, $enableViews=null)
    {
        if(is_null($database)) $database = $this->schema('database');
        return 'select table_name, table_comment, create_time, update_time from information_schema.tables where table_schema='.tdz::sql($this->getDatabaseName($database));
    }
}