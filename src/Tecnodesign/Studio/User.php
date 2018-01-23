<?php
/**
 * Optional user database for authentication
 *
 * PHP version 5.3
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Studio_User extends Tecnodesign_Model
{
    /**
     * Tecnodesign_Model schema
     */
    //--tdz-schema-start--2014-12-27 18:32:23
    public static $schema = array (
      'database' => 'studio',
      'tableName' => 'tdz_users',
      'label' => '*Users',
      'className' => 'Tecnodesign_Studio_User',
      'columns' => array (
        'id' => array ( 'type' => 'int', 'min' => 0, 'increment' => 'auto', 'null' => false, 'primary' => true, ),
        'login' => array ( 'type' => 'string', 'size' => '100', 'null' => false, ),
        'name' => array ( 'type' => 'string', 'size' => '200', 'null' => true, ),
        'password' => array ( 'type' => 'string', 'size' => '100', 'null' => true, ),
        'email' => array ( 'type' => 'string', 'size' => '100', 'null' => true, ),
        'details' => array ( 'type' => 'string', 'size' => '', 'null' => true, ),
        'accessed' => array ( 'type' => 'datetime', 'null' => true, ),
        'created' => array ( 'type' => 'datetime', 'null' => false, ),
        'updated' => array ( 'type' => 'datetime', 'null' => false, ),
        'expired' => array ( 'type' => 'datetime', 'null' => true, ),
      ),
      'relations' => array (
        'Credential' => array ( 'local' => 'id', 'foreign' => 'user', 'type' => 'one', 'className' => 'Tecnodesign_Studio_Credential', ),
      ),
      'scope' => array (
        'string'=>array('name'),
      ),
      'events' => array (
        'before-insert' => array ( 'actAs', ),
        'before-update' => array ( 'actAs', ),
        'before-delete' => array ( 'actAs', ),
        'active-records' => '`expired` is null',
      ),
      'form' => array (
        'login' => array ( 'bind' => 'login', ),
        'name' => array ( 'bind' => 'name', ),
        'password' => array ( 'bind' => 'password', ),
        'email' => array ( 'bind' => 'email', ),
        'details' => array ( 'bind' => 'details', ),
      ),
      'actAs' => array (
        'before-insert' => array ( 'auto-increment' => array ( 'id', ), 'timestampable' => array ( 'created', 'updated', ), ),
        'before-update' => array ( 'timestampable' => array ( 'updated', ), ),
        'before-delete' => array ( 'timestampable' => array ( 'updated', ), 'soft-delete' => array ( 'expired', ), ),
      ),
    );
    protected $id, $login, $name, $password, $email, $details, $created, $updated, $expired, $Credential;
    //--tdz-schema-end--
    protected $lastAccess, $credentials;

    public function setPassword($s)
    {
        if($s!==null) {
            $this->password = tdz::hash($s, null, Tecnodesign_User::$hashType);
        }
    }

    public function getLastAccess()
    {
        if(is_null($this->lastAccess)) {
            if($this->accessed) {
                $this->lastAccess = strtotime($this->accessed);
            } else {
                $this->lastAccess = false;
            }
        }
        return $this->lastAccess;
    }

    public function setLastAccess($t)
    {
        if(is_numeric($t) && $t>$this->getLastAccess()+1) {
            $this->accessed = date('Y-m-d\TH:i:s', $t);
            if(!is_null($this->credentials)) $this->credentials=null;
            $this->save();
        }
    }

    public function getCredentials()
    {
        if(is_null($this->credentials)) {
            $cs = tdzGroup::find(array('Credential.user'=>$this->id,'Credential.expired'=>''),0,array('name'),false);
            $this->credentials=array();
            if($cs) {
                foreach($cs as $C) {
                    $this->credentials[(int)$C->id]=tdz::slug($C->name);
                }
            }
        }
        return $this->credentials;
    }
}
