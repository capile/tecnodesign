<?php
/**
 * Entries relationships
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Studio_Relation extends Tecnodesign_Studio_Model
{
    public static $schema;
    protected $id, $parent, $entry, $position, $version, $created, $updated, $expired, $Child, $Parent, $Perfil, $Turma;
}
