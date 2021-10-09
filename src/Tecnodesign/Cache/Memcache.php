<?php
/**
 * Variable Caching and retieving
 * 
 * This package implements a common interface for caching both in files or memory
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
class Tecnodesign_Cache_Memcache
{

    private static $_memcache;

    public static function memcache()
    {
        if(is_null(self::$_memcache) && function_exists('memcache_debug')) {
            self::$_memcache=new Memcache();
            $conn=false;
            foreach(Tecnodesign_Cache::$memcachedServers as $s) {
                if(preg_match('/^(.*)\:([0-9]+)$/', $s, $m)) {
                    if($conn=self::$_memcache->connect($m[1], (int)$m[2])) {
                        break;
                    }
                } else if($conn=self::$_memcache->connect($s, 11211)) {
                    break;
                }
                unset($s, $m);
            }
            if(!$conn) self::$_memcache=false;
            unset($conn);
        }
        return self::$_memcache;
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
        if(!self::memcache()) return Tecnodesign_Cache_File::get($key, $expires);

        $siteKey = Tecnodesign_Cache::siteKey();
        if($siteKey) {
            $key = $siteKey.'/'.$key;
        }
        unset($siteKey);
        if ($expires || $m) {
            if($expires && $expires<2592000) {
                $expired = time()-(int)$expires;
                $expires = time()+(int)$expires;
            } else {
                $expired = ($expires>time())?(0):($expires);
            }
            $meta = self::$_memcache->get($key.'.meta');
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
        return self::$_memcache->get($key);
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
        if(!self::memcache()) {
            return Tecnodesign_Cache_File::set($key, $value, $timeout);
        }

        $siteKey = Tecnodesign_Cache::siteKey();
        if(!is_array($key)) {
            $keys = array($key);
        } else $keys=$key;
        if($siteKey) {
            foreach($keys as $kk=>$kv) {
                $keys[$kk] = $siteKey.'/'.$kv;
                unset($kk,$kv);
            }
        }
        unset($siteKey);
        $ttl = (int)($timeout)?($timeout - time()):((int)$timeout);
        if($ttl<0) {// a timestamp should be supplied, not the seconds to expire?
            $ttl = (int)$timeout;
        }
        $ret = true;
        $meta = time().','.((is_object($value)||is_array($value))?(1):(strlen((string)$value)));
        foreach($keys as $key) {
            if(!self::$_memcache->set($key.'.meta', $meta, 0, $ttl) || !self::$_memcache->set($key, $value, 0, $ttl)) {
                $ret = false;
                break;
            }
            unset($key);
        }

        unset($keys,$key,$value,$timeout, $meta);
        return $ret;
    }
    public static function delete($key)
    {
        if(!self::memcache()) return Tecnodesign_Cache_File::delete($key);

        $siteKey = Tecnodesign_Cache::siteKey();
        if($siteKey) {
            $key = $siteKey.'/'.$key;
        }
        if(self::$_memcache->delete($key.'.meta') && self::$_memcache->delete($key)) {
            return true;
        } else {
            return false;
        }
    }}