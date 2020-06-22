<?php
/**
 * Database abstraction
 *
 * PHP version 5.6+
 *
 * @category  Database
 * @package   Model
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2020 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Query_Sqlsrv extends Tecnodesign_Query_Dblib
{
    const PDO_AUTOCOMMIT=0, PDO_TRANSACTION=1;
}