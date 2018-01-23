<?php
/**
 * Tecnodesign_Studio_Credential table description
 *
 * PHP version 5.3
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Studio_Credential extends Tecnodesign_Model
{
    /**
     * Tecnodesign_Model schema
     */
    //--tdz-schema-start--2014-12-27 18:32:23
    public static $schema = array (
      'database' => 'studio',
      'tableName' => 'tdz_credentials',
      'className' => 'Tecnodesign_Studio_Credential',
      'columns' => array (
        'user' => array ( 'type' => 'int', 'min' => 0, 'null' => false, 'primary' => true, ),
        'groupid' => array ( 'type' => 'int', 'min' => 0, 'null' => false, 'primary' => true, ),
        'created' => array ( 'type' => 'datetime', 'null' => false, ),
        'updated' => array ( 'type' => 'datetime', 'null' => false, ),
        'expired' => array ( 'type' => 'datetime', 'null' => true, ),
      ),
      'relations' => array (
        'Group' => array ( 'local' => 'groupid', 'foreign' => 'id', 'type' => 'one', 'className' => 'Tecnodesign_Studio_Group', ),
        'User' => array ( 'local' => 'user', 'foreign' => 'id', 'type' => 'one', 'className' => 'Tecnodesign_Studio_User', ),
      ),
      'scope' => array (
      ),
      'events' => array (
        'before-insert' => array ( 'actAs', ),
        'before-update' => array ( 'actAs', ),
        'before-delete' => array ( 'actAs', ),
        'active-records' => '`expired` is null',
      ),
      'form' => array (
        'user' => array ( 'bind' => 'user', 'type' => 'select', 'choices' => 'User', ),
        'groupid' => array ( 'bind' => 'groupid', 'type' => 'select', 'choices' => 'Group', ),
      ),
      'actAs' => array (
        'before-insert' => array ( 'timestampable' => array ( 'created', 'updated', ), ),
        'before-update' => array ( 'timestampable' => array ( 'updated', ), ),
        'before-delete' => array ( 'timestampable' => array ( 'updated', ), 'soft-delete' => array ( 'expired', ), ),
      ),
    );
    protected $user, $groupid, $created, $updated, $expired, $Group, $User;
    //--tdz-schema-end--
}
