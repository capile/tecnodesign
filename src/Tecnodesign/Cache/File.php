<?php
/**
 * Variable Caching and retieving
 * 
 * This package implements a common interface for caching both in files or memory
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Cache_File
{
    private static $_cacheDir=null;
    public static $serialize=true;

    public static function lastModified($key, $expires=0)
    {
        $lmod = @filemtime(self::filename($key));
        if ($lmod && (!$expires || $lmod > $expires)) {
            return $lmod;
        }
        return false;
    }

    public static function size($key, $expires=0)
    {
        return @filesize(self::filename($key));
    }

    public static function filename($key)
    {
        return self::cacheDir().'/'.tdz::slug($key, '/_-', true).'.cache';
    }

    public static function cacheDir($s=null)
    {
        if (!is_null($s)) {
            self::$_cacheDir = $s;
        } else if (is_null(self::$_cacheDir)) {
            self::$_cacheDir = S_VAR.'/cache';
        }
        return self::$_cacheDir;
    }

    /**
     * Gets currently stored key-pair value
     *
     * @param $key     mixed  key to be retrieved or array of keys to be tried (first available is returned)
     * @param $expires int    timestamp to be compared. If timestamp is newer than cached key, false is returned.
     * @param $method  mixed  Storage method to be used. Should be either a key or a value in self::$_methods
     */
    public static function get($key, $expires=0)
    {
        $cfile = self::filename($key);
        @clearstatcache(true, $cfile);
        if($expires) {
            if($expires<2592000) {
                $expired = time()-(int)$expires;
                $expires = time()+(int)$expires;
            } else {
                $expired = ($expires>time())?(0):($expires);
            }
        }
        if (file_exists($cfile) && (!$expires || filemtime($cfile) > $expired)) {
            list($toexpire, $ret) = explode("\n", file_get_contents($cfile), 2);
            if($toexpire && $toexpire<microtime(true)) {
                @unlink($cfile);
                $ret = false;
            } else if (self::$serialize) {
                $ret = tdz::unserialize($ret);
            } else $ret=false;
        } else $ret=false;

        unset($cfile, $key, $expires, $expired);

        return $ret;
    }
    
    /**
     * Sets currently stored key-pair value
     *
     * @param $key     mixed  key(s) to be stored
     * @param $value   mixed  value to be stored
     * @param $expires int    timestamp to be set as expiration date.
     * @param $method  mixed  Storage method to be used. Should be either a key or a value in self::$_methods
     */
    public static function set($key, $value, $timeout=0)
    {
        if(self::$serialize) {
            $value = tdz::serialize($value);
        }
        if($timeout && $timeout<2592000) $timeout = microtime(true)+(float)$timeout;
        $ret = tdz::save(self::filename($key), ((float) $timeout)."\n".$value, true);
        unset($key,$value,$timeout);
        return $ret;
    }

    public static function delete($key)
    {
        $cfile = self::filename($key);
        @unlink($cfile);
        unset($cfile, $key);
        return true;
    }
}