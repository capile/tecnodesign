<?php
/**
 * Optional user database for authentication
 *
 * PHP version 5.6
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Studio_User extends Tecnodesign_Studio_Model
{
    public static $schema;
    protected $id, $login, $name, $password, $email, $details, $created, $updated, $expired, $Credential;
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
