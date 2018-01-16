<?php
/**
 * Resources (pages/contents) required credentials
 *
 * PHP version 5.3
 *
 * @category  Model
 * @package   Estudio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Estudio_Permission extends Tecnodesign_Model
{
    /**
     * Tecnodesign_Model schema
     */
    //--tdz-schema-start--2014-12-27 18:32:23
    public static $schema = array (
      'database' => 'estudio',
      'label' => '*Permissions',
      'tableName' => 'tdz_permissions',
      'className' => 'tdzPermission',
      'columns' => array (
        'id' => array ( 'type' => 'int', 'null' => false, 'primary' => true, ),
        'entry' => array ( 'type' => 'int', 'null' => true, ),
        'role' => array ( 'type' => 'string', 'size' => '100', 'null' => false, ),
        'credentials' => array ( 'type' => 'string', 'size' => '', 'null' => true, ),
        'version' => array ( 'type' => 'int', 'null' => false, 'primary' => true, ),
        'created' => array ( 'type' => 'datetime', 'null' => false, ),
        'updated' => array ( 'type' => 'datetime', 'null' => false, ),
        'expired' => array ( 'type' => 'datetime', 'null' => true, ),
      ),
      'relations' => array (
        'Permission' => array ( 'local' => 'id', 'foreign' => 'id', 'type' => 'one', 'className' => 'Tecnodesign_Estudio_Permission', ),
      ),
      'scope' => array (
      ),
      'order' => array (
        'created' => 'desc',
      ),
      'group' => array (
        0 => 'id',
      ),
      'events' => array (
        'before-insert' => array ( 'nextId', 'actAs', ),
        'before-update' => array ( 'actAs', ),
        'before-delete' => array ( 'actAs', ),
        'active-records' => 'expired is null',
      ),
      'form' => array (
      ),
      'actAs' => array (
        'before-insert' => array ( 'timestampable' => array ( 'created', ), ),
        'before-update' => array ( 'timestampable' => array ( 'created', ), ),
        'before-delete' => array ( 'timestampable' => array ( 'created', ), 'soft-delete' => array ( 'expired', ), ),
      ),
    );
    protected $id, $entry, $role, $credentials, $version, $created, $updated, $expired, $Permission;
    //--tdz-schema-end--
}
