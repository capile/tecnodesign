<?php

namespace Studio;
use tdz as S;

class User extends \Tecnodesign_User
{
    public static function create($d)
    {
        $cn = S::getApp()->config('user', 'className');
        if(!$cn) $cn = 'Studio\\Model\\Users';

        return new $cn($d, true, true);
    }
    
}