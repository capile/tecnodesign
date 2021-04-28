<?php

namespace Studio\Model;

class Credentials extends \Tecnodesign_Studio_Credential
{
    public static $schema;
    protected $userid, $groupid, $created, $updated, $expired, $Users, $Groups;
}