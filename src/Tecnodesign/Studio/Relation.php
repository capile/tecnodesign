<?php
/**
 * Entries relationships
 *
 * PHP version 5.6
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Studio_Relation extends Tecnodesign_Studio_Model
{
    public static $schema;
    protected $id, $parent, $entry, $position, $version, $created, $updated, $expired, $Child, $Parent, $Perfil, $Turma;
}
