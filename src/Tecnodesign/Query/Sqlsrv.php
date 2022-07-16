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
 * @version   3.0
 */
class Tecnodesign_Query_Sqlsrv extends Tecnodesign_Query_Dblib
{
    const DRIVER='sqlsrv', PDO_AUTOCOMMIT=0, PDO_TRANSACTION=1;
    public static $tableAutoIncrement='identity';
}