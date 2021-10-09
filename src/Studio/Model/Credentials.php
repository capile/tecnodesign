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

class Credentials extends \Tecnodesign_Studio_Credential
{
    public static $schema;
    protected $userid, $groupid, $created, $updated, $expired, $Users, $Groups;
}