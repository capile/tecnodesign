<?php
/**
 * Tecnodesign_App_Estudio_Relation table description
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
 * Tecnodesign_App_Estudio_Relation table description
 *
 * @category  Model
 * @package   Estudio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @link      http://tecnodz.com/
 */
class Tecnodesign_App_Estudio_Relation extends Tecnodesign_Model
{
    /**
     * Tecnodesign_Model schema
     *
     * Remove the comment below to disable automatic schema updates
     */
    //--tdz-schema-start--2012-02-29 19:40:02
    public static $schema = array (
      'database' => 'estudio',
      'tableName' => 'tdz_relations_version',
      'className' => 'tdzRelation',
      'columns' => array (
      ),
      'relations' => array (
        'Perfil' => array ( 'local' => 'perfil', 'foreign' => 'id', 'type' => 'one', ),
        'Turma' => array ( 'local' => 'ingresso', 'foreign' => 'id', 'type' => 'one', ),
      ),
      'scope' => array (
      ),
      'events' => array (
        'before-insert' => array ( 'actAs', ),
        'before-update' => array ( 'actAs', ),
        'before-delete' => array ( 'actAs', ),
        'active-records' => 'removido_em is null',
      ),
      'form' => array (
        'perfil' => array ( 'bind' => 'perfil', 'type' => 'select', 'choices' => 'Perfil', ),
        'ingresso' => array ( 'bind' => 'ingresso', 'type' => 'select', 'choices' => 'Turma', ),
        'conclusao' => array ( 'bind' => 'conclusao', ),
        'ativo' => array ( 'bind' => 'ativo', ),
      ),
      'actAs' => array (
        'before-insert' => array ( 'auto-increment' => array ( 'id', ), 'timestampable' => array ( 'criado_em', 'modificado_em', ), ),
        'before-update' => array ( 'timestampable' => array ( 'modificado_em', ), ),
        'before-delete' => array ( 'timestampable' => array ( 'modificado_em', ), 'soft-delete' => array ( 'removido_em', ), ),
      ),
    );
    protected $Perfil, $Turma;
    //--tdz-schema-end--
}
