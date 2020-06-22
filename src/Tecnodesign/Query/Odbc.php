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
class Tecnodesign_Query_Odbc extends Tecnodesign_Query_Dblib
{
    const PDO_AUTOCOMMIT=0, PDO_TRANSACTION=1;
}