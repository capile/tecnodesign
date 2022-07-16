<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */
namespace FootballData;

class Api extends \Tecnodesign_Query_Api
{
    public static
        $dataAttribute='{$tableName}',
        $countAttribute='count',
        $enableOffset=false;
}