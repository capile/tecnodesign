<?php

namespace Studio\Model;

class Users extends \Tecnodesign_Studio_User
{
    public static $schema;
    protected $id, $username, $name, $password, $email, $details, $created, $updated, $expired;

    public function __toString()
    {
        return ($this->name && $this->username) ?(string)"{$this->name} ({$this->username})" :(string)$this->username; 
    }

    public function getCredentials()
    {
        if(is_null($this->credentials)) {
            $cs = Groups::find(['Credentials.userid'=>$this->id],null,['name'],false);
            $this->credentials=[];
            if($cs) {
                foreach($cs as $C) {
                    $this->credentials[(int)$C->id]=$C->name;
                }
            }
        }
        return $this->credentials;
    }
}