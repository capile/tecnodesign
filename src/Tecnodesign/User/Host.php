<?php
/**
 * Tecnodesign Host-based Authentication
 *
 * This package enables authentication & authorization for apps.
 *
 * PHP version 5.4
 *
 * @category  User
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2015 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      https://tecnodz.com/
 */
class Tecnodesign_User_Host
{

    public static $hosts=array();
    protected $id, $address;

    public static function authenticate($o=null)
    {
        if(is_array($o) && isset($o['hosts'])) {
            static::$hosts += $o['hosts'];
        }

        $h = self::remoteAddr();
        if(isset(self::$hosts[$h])) {
            return self::$hosts[$h];
        }
    }

    public static function remoteAddr()
    {
        if(isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else if(isset($_SERVER['REMOTE_ADDR'])) {
            return $_SERVER['REMOTE_ADDR'];
        }
        return false;
    }

    public function isAuthenticated()
    {
        return self::authenticate();
    }

    public function find($id)
    {
        if($r=self::authenticate()) {
            if(isset(Tecnodesign_User::$cfg['ns']['host']['className'])) {
                $cn = Tecnodesign_User::$cfg['ns']['host']['className'];
                $H = $cn::find($r,1,'minimal');
            } else {
                $H = new Tecnodesign_Collection();
                $H->id = $id;
            }
            return $H;
        }
    }
}