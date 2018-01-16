<?php
/**
 * Optional group database for authorization
 *
 * PHP version 5.3
 *
 * @category  Model
 * @package   Estudio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Estudio_Group extends Tecnodesign_Model
{
    /**
     * Tecnodesign_Model schema
     */
    //--tdz-schema-start--2014-12-27 18:32:23
    public static $schema = array (
      'database' => 'estudio',
      'tableName' => 'tdz_groups',
      'label' => '*Groups',
      'className' => 'Tecnodesign_Estudio_Group',
      'columns' => array (
        'id' => array ( 'type' => 'int', 'min' => 0, 'increment' => 'auto', 'null' => false, 'primary' => true, ),
        'name' => array ( 'type' => 'string', 'size' => '100', 'null' => false, ),
        'priority' => array ( 'type' => 'int', 'null' => false, ),
        'created' => array ( 'type' => 'datetime', 'null' => false, ),
        'updated' => array ( 'type' => 'datetime', 'null' => false, ),
        'expired' => array ( 'type' => 'datetime', 'null' => true, ),
      ),
      'relations' => array (
        'Credential' => array ( 'local' => 'id', 'foreign' => 'groupid', 'type' => 'one', 'className' => 'Tecnodesign_Estudio_Credential', ),
      ),
      'scope' => array (
      ),
      'events' => array (
        'before-insert' => array ( 'actAs', ),
        'before-update' => array ( 'actAs', ),
        'before-delete' => array ( 'actAs', ),
        'active-records' => 'expired is null',
      ),
      'form' => array (
        'name' => array ( 'bind' => 'name', ),
        'priority' => array ( 'bind' => 'priority', ),
      ),
      'actAs' => array (
        'before-insert' => array ( 'auto-increment' => array ( 'id', ), 'timestampable' => array ( 'created', 'updated', ), ),
        'before-update' => array ( 'timestampable' => array ( 'updated', ), ),
        'before-delete' => array ( 'timestampable' => array ( 'updated', ), 'soft-delete' => array ( 'expired', ), ),
      ),
    );
    protected $id, $name, $priority, $created, $updated, $expired, $Credential;
    //--tdz-schema-end--
}
