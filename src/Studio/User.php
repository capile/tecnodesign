<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
namespace Studio;

use Studio as S;

class User extends \Tecnodesign_User
{
    public static 
        $hashType='crypt:$6$rounds=5000$'; // hashing method

    public static function create($d)
    {
        $cn = S::getApp()->config('user', 'className');
        if(!$cn) $cn = 'Studio\\Model\\Users';

        return new $cn($d, true, true);
    }
    
}