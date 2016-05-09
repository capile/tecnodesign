<?php
/**
 * Entries relationships
 *
 * PHP version 5.3
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Studio_Relation extends Tecnodesign_Model
{
    /**
     * Tecnodesign_Model schema
     */
    //--tdz-schema-start--2014-12-27 18:32:23
    public static $schema = array (
      'database' => 'studio',
      'tableName' => 'tdz_relations',
      'className' => 'tdzRelation',
      'columns' => array (
        'id' => array ( 'type' => 'int', 'increment' => 'auto', 'null' => false, 'primary' => true, ),
        'parent' => array ( 'type' => 'int', 'null' => true, ),
        'entry' => array ( 'type' => 'int', 'null' => false, ),
        'position' => array ( 'type' => 'int', 'null' => true, ),
        'version' => array ( 'type' => 'int', 'null' => true, ),
        'created' => array ( 'type' => 'datetime', 'null' => false, ),
        'updated' => array ( 'type' => 'datetime', 'null' => false, ),
        'expired' => array ( 'type' => 'datetime', 'null' => true, ),
      ),
      'relations' => array (
        'Child' => array ( 'local' => 'entry', 'foreign' => 'id', 'type' => 'one', 'className' => 'Tecnodesign_Studio_Entry', ),
        'Parent' => array ( 'local' => 'parent', 'foreign' => 'id', 'type' => 'one', 'className' => 'Tecnodesign_Studio_Entry', ),
        'Perfil' => array ( 'local' => 'perfil', 'foreign' => 'id', 'type' => 'one', ),
        'Turma' => array ( 'local' => 'ingresso', 'foreign' => 'id', 'type' => 'one', ),
      ),
      'scope' => array (
      ),
      'order' => array (
        'position' => 'asc',
      ),
      'events' => array (
        'before-insert' => array ( 'actAs', ),
        'before-update' => array ( 'actAs', ),
        'before-delete' => array ( 'actAs', ),
        'active-records' => 'expired is null',
      ),
      'form' => array (
        'perfil' => array ( 'bind' => 'perfil', 'type' => 'select', 'choices' => 'Perfil', ),
        'ingresso' => array ( 'bind' => 'ingresso', 'type' => 'select', 'choices' => 'Turma', ),
        'conclusao' => array ( 'bind' => 'conclusao', ),
        'ativo' => array ( 'bind' => 'ativo', ),
      ),
      'actAs' => array (
        'before-insert' => array ( 'auto-increment' => array ( 'id', ), 'timestampable' => array ( 'created', 'updated', ), 'sortable' => array ( 'position', ), ),
        'before-update' => array ( 'timestampable' => array ( 'updated', ), 'sortable' => array ( 'position', ), ),
        'before-delete' => array ( 'timestampable' => array ( 'updated', ), 'soft-delete' => array ( 'expired', ), 'sortable' => array ( 'position', ), ),
      ),
    );
    protected $id, $parent, $entry, $position, $version, $created, $updated, $expired, $Child, $Parent, $Perfil, $Turma;
    //--tdz-schema-end--
}
