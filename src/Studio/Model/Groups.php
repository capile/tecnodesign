<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
namespace Studio\Model;

class Groups extends \Tecnodesign_Studio_Group
{
    public static $schema;
    protected $id, $name, $priority, $created, $updated, $expired;
}