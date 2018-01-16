<?php
/**
 * Tecnodesign_Estudio_Tag table description
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
 * Tecnodesign_Estudio_Tag table description
 *
 * @category  Model
 * @package   Estudio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @link      http://tecnodz.com/
 */
class Tecnodesign_Estudio_Tag extends Tecnodesign_Model
{
    /**
     * Tecnodesign_Model schema
     *
     * Remove the comment below to disable automatic schema updates
     */
    //--tdz-schema-start--2014-12-27 18:32:23
    public static $schema = array (
      'database' => 'estudio',
      'tableName' => 'tdz_tags',
      'className' => 'tdzTag',
      'columns' => array (
        'id' => array ( 'type' => 'int', 'increment' => 'auto', 'null' => false, 'primary' => true, ),
        'entry' => array ( 'type' => 'int', 'null' => true, ),
        'tag' => array ( 'type' => 'string', 'size' => '100', 'null' => false, ),
        'slug' => array ( 'type' => 'string', 'size' => '100', 'null' => false, ),
        'version' => array ( 'type' => 'int', 'null' => true, ),
        'created' => array ( 'type' => 'datetime', 'null' => false, ),
        'updated' => array ( 'type' => 'datetime', 'null' => false, ),
        'expired' => array ( 'type' => 'datetime', 'null' => true, ),
      ),
      'relations' => array (
        'Entry' => array ( 'local' => 'entry', 'foreign' => 'id', 'type' => 'one', 'className' => 'Tecnodesign_Estudio_Entry', ),
        'Perfil' => array ( 'local' => 'perfil', 'foreign' => 'id', 'type' => 'one', ),
      ),
      'scope' => array (
        'link' => array ( 'tag', 'slug', ),
      ),
      'events' => array (
        'before-insert' => array ( 'actAs', ),
        'before-update' => array ( 'actAs', ),
        'before-delete' => array ( 'actAs', ),
        'active-records' => 'expired is null',
        'after-insert' => array ( 'actAs', ),
        'after-update' => array ( 'actAs', ),
        'after-delete' => array ( 'actAs', ),
      ),
      'form' => array (
        'perfil' => array ( 'bind' => 'perfil', 'type' => 'select', 'choices' => 'Perfil', ),
        'pais' => array ( 'bind' => 'pais', ),
        'area' => array ( 'bind' => 'area', ),
        'telefone' => array ( 'bind' => 'telefone', ),
        'complemento' => array ( 'bind' => 'complemento', ),
        'autor' => array ( 'bind' => 'autor', ),
        'removido_em' => array ( 'bind' => 'removido_em', ),
      ),
      'actAs' => array (
        'before-insert' => array ( 'auto-increment' => array ( 'id', ), 'timestampable' => array ( 'created', 'updated', ), 'sortable' => array ( 'ordem', ), ),
        'before-update' => array ( 'timestampable' => array ( 'updated', ), 'sortable' => array ( 'ordem', ), ),
        'before-delete' => array ( 'timestampable' => array ( 'updated', ), 'sortable' => array ( 'ordem', ), 'soft-delete' => array ( 'expired', ), ),
        'after-insert' => array ( 'versionable' => array ( 'version', ), ),
        'after-update' => array ( 'versionable' => array ( 'version', ), ),
        'after-delete' => array ( 'versionable' => array ( 'version', ), ),
      ),
    );
    protected $id, $entry, $tag, $slug, $version, $created, $updated, $expired, $Entry, $Perfil;
    //--tdz-schema-end--
}
