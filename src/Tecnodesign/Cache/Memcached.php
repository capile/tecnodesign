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
class Tecnodesign_Cache_Memcached
{

    private static $_memcached;

    public static function memcached()
    {
        if(is_null(self::$_memcached) && class_exists('Memcached')) {
            $skey = Tecnodesign_Cache::siteKey();
            self::$_memcached=new Memcached($skey);
            $conn=false;
            foreach(Tecnodesign_Cache::$memcachedServers as $s) {
                if(preg_match('/^(.*)\:([0-9]+)$/', $s, $m)) {
                    if(self::$_memcached->addServer($m[1], (int)$m[2])) $conn=true;
                } else if(self::$_memcached->addServer($s, 11211)) $conn=true;
                unset($s, $m);
            }
            if(!$conn) self::$_memcached=false;
            else {
                if($skey) {
                    self::$_memcached->setOption(Memcached::OPT_PREFIX_KEY, $skey.'/');
                }
                unset($skey);
            }
            unset($conn);

        }
        return self::$_memcached;
    } 

    public static function lastModified($key, $expires=0)
    {
        return self::get($key, $expires, 'modified');
    }

    public static function size($key, $expires=0)
    {
        return self::get($key, $expires, 'size');
    }
    
    /**
     * Gets currently stored key-pair value
     *
     * @param $key     mixed  key to be retrieved or array of keys to be tried (first available is returned)
     * @param $expires int    timestamp to be compared. If timestamp is newer than cached key, false is returned.
     * @param $method  mixed  Storage method to be used. Should be either a key or a value in self::$_methods
     */
    public static function get($key, $expires=0, $m=null)
    {
        if(!self::memcached()) return Tecnodesign_Cache_File::get($key, $expires);

        if ($expires || $m) {
            if($expires && $expires<2592000) {
                $expired = time()-(int)$expires;
                $expires = time()+(int)$expires;
            } else {
                $expired = ($expires>time())?(0):($expires);
            }
            $meta = self::$_memcached->get($key.'.meta');
            if($meta) list($lmod,$size)=explode(',',$meta);
            if($expires) {
                if(!$meta || !$lmod || $lmod < $expired) {
                    unset($meta, $lmod, $key, $expired, $expires, $size);
                    return false;
                }
            }
            if(!is_null($m)) {
                if($meta) {
                    unset($meta);
                    if($m=='size') return $size;
                    else if($m=='modified') return $lmod;
                }
                return false;
            }
            unset($meta);
        }

        return self::$_memcached->get($key);
    }

    /**
     * Sets currently stored key-pair value
     *
     * @param $key     mixed  key(s) to be stored
     * @param $value   mixed  value to be stored
     * @param $expires int    timestamp to be set as expiration date.
     * @param $method  mixed  Storage method to be used. Should be either a key or a value in self::$_methods
     */
    public static function set($key, $value, $expires=0)
    {
        if(!self::memcached()) return Tecnodesign_Cache_File::set($key, $value, $expires);
        if(!is_array($key)) {
            $key = array($key);
        }
        $keys = $key;
        $ttl = ($expires)?($expires - time()):($expires);
        if($ttl<0) {// a timestamp should be supplied, not the seconds to expire?
            $ttl = $expires;
        }
        $meta = time().','.((is_object($value))?(1):(strlen((string)$value)));
        foreach($keys as $key) {
            if(!self::$_memcached->set($key.'.meta', $meta, (int)$expires) || !self::$_memcached->set($key, $value, (int)$expires)) {
                unset($keys, $key, $ttl, $meta);
                return false;
            }
            unset($key);
        }
        unset($keys, $ttl, $meta);
        return true;
    }

    public static function delete($key)
    {
        if(!self::memcached()) return Tecnodesign_Cache_File::delete($key, $expires);
        if(self::$_memcached->deleteMulti($key.'.meta', $key)) {
            return true;
        } else {
            return false;
        }
    }
}