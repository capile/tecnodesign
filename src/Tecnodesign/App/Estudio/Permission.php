<?php
/**
 * Tecnodesign_App_Estudio_Permission table description
 *
 * PHP version 5.3
 *
 * @category  Model
 * @package   Estudio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @link      http://tecnodz.com/
 * @version   SVN: $Id$
 */

/**
 * Tecnodesign_App_Estudio_Permission table description
 *
 * @category  Model
 * @package   Estudio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @link      http://tecnodz.com/
 */
class Tecnodesign_App_Estudio_Permission extends Tecnodesign_Model
{
    /**
     * Tecnodesign_Model schema
     *
     * Remove the comment below to disable automatic schema updates
     */
    //--tdz-schema-start--2012-02-29 19:44:01
    public static $schema = array (
      'database' => 'estudio',
      'tableName' => 'tdz_permissions_version',
      'className' => 'tdzPermission',
      'columns' => array (
        'id' => array ( 'type' => 'int', 'null' => false, 'primary' => true, ),
        'created' => array ( 'type' => 'datetime', 'null' => false, 'primary' => true, ),
        'entry' => array ( 'type' => 'int', 'null' => true, ),
        'role' => array ( 'type' => 'string', 'size' => '100', 'null' => false, ),
        'credentials' => array ( 'type' => 'string', 'size' => '', 'null' => true, ),
        'expired' => array ( 'type' => 'datetime', 'null' => true, ),
      ),
      'relations' => array (
      /*
        'Entry' => array ( 'local' => 'entry', 'foreign' => 'id', 'type' => 'one', 'className' => 'Tecnodesign_App_Estudio_Entry', ),
      */
      ),
      'scope' => array (
      ),
      'order' => array(
        'created'=>'desc',
      ),
      'group' => array(
        'id'
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
    protected $id, $created, $entry, $role, $credentials, $version, $expired, $Entry;
    //--tdz-schema-end--
}
