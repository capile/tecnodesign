<?php
/**
 * Optional user database for authentication
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
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
