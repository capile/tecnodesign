<?php

namespace Studio\Model;

class Users extends \Tecnodesign_Studio_User
{
    public static $schema;
    protected $id, $username, $name, $password, $email, $details, $created, $updated, $expired;
}