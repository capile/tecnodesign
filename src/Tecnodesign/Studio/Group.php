<?php
/**
 * Optional group database for authorization
 *
 * PHP version 5.6
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Studio_Group extends Tecnodesign_Studio_Model
{
    public static $schema;
    protected $id, $name, $priority, $created, $updated, $expired, $Credential;
}
