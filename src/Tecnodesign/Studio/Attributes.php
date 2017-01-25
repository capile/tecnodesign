<?php
/**
 * Tecnodesign_Studio_Content table description
 *
 * PHP version 5.3
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @link      http://tecnodz.com/
 * @version   SVN: $Id$
 */

/**
 * Tecnodesign_Studio_Content table description
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @link      http://tecnodz.com/
 */
class Tecnodesign_Studio_Attributes extends Tecnodesign_Model
{
    /**
     * Tecnodesign_Model schema
     *
     * Remove the comment below to disable automatic schema updates
     */
    //--tdz-schema-start--2012-02-29 19:44:01
    public static $schema = array (
      'database' => 'studio',
      'tableName' => 'tdz_attributes',
      'className' => 'tdzAttributes',
      'columns' => array (
        'content' => array ( 'type' => 'string', 'null' => false, 'primary' => true, ),
        'name' => array ( 'type' => 'string', 'size'=>200, 'null' => false, 'primary' => true, ),
        'value' => array ( 'type' => 'string', 'null' => true, ),
        'created' => array ( 'type' => 'datetime', 'null' => false, ),
        'updated' => array ( 'type' => 'datetime', 'null' => false, ),
        'expired' => array ( 'type' => 'datetime', 'null' => true, ),
      ),
      'relations' => array (
        'Content' => array ( 'local' => 'content', 'foreign' => 'id', 'type' => 'one', 'className' => 'tdzContent', ),
      ),
      'scope' => array (
        'content'=>array('name','value'),
      ),
      'order' => array(
      ),
      'events' => array (
        'before-insert' => array ( 'actAs', ),
        'before-update' => array ( 'actAs', ),
        'before-delete' => array ( 'actAs', ),
        'active-records' => '`expired` is null',
      ),
      'form' => array (
        'name'=>array('bind'=>'name', 'type'=>'text', 'class'=>'i1s2'),
        'value'=>array('bind'=>'value', 'type'=>'text', 'class'=>'i1s2'),
      ),
      'actAs' => array (
        'before-insert' => array ( 'timestampable' => array ( 'created', 'updated' ), ),
        'before-update' => array ( 'timestampable' => array ( 'updated', ), ),
        'before-delete' => array ( 'timestampable' => array ( 'updated', ), 'soft-delete' => array ( 'expired', ), ),
      ),
    );
    protected $content, $name, $value, $created, $updated, $expired, $Content;
    //--tdz-schema-end--
    
}
