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
class Tecnodesign_Cache_Apc
{

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
        if(!function_exists($fn='apc_fetch') && !function_exists($fn='apcu_fetch')) {
            tdz::log('[ERROR] No APC installed! It shouldn\'t be selected.');
            return Tecnodesign_Cache_File::get($key, $expires);
        }
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
            $meta = $fn($key.'.meta');
            if($meta) list($lmod,$size)=explode(',',$meta);
            if($expired) {
                if(!$meta || !$lmod || $lmod < (int) $expired) {
                    unset($meta, $lmod, $key, $expires, $size);
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
        return $fn($key);
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
        if(!function_exists($fn='apc_store') && !function_exists($fn='apcu_store')) {
            tdz::log('[ERROR] No APC installed! It shouldn\'t be selected.');
            return Tecnodesign_Cache_File::set($key, $value, $expires);
        }
        $ttl = (int)($expires)?($expires - time()):($expires);
        if($ttl<0) {// a timestamp should be supplied, not the seconds to expire?
            $ttl = $expires;
        }
        $siteKey = Tecnodesign_Cache::siteKey();
        $meta = time().','.((is_object($value)||is_array($value))?(1):(strlen((string)$value)));
        if(!is_array($key)) $key = array($key);
        foreach($key as $k) {
            if($siteKey) {
                $k = $siteKey.'/'.$k;
            }
            if(!@$fn($k.'.meta', $meta, $ttl) || !@$fn($k, $value, $ttl)) {
                unset($ttl, $siteKey, $k, $meta);
                return false;
            }
            unset($k);
        }
        unset($ttl, $siteKey, $key, $meta);
        return true;
    }

    public static function delete($key)
    {
        if(!function_exists($fn='apc_delete') && !function_exists($fn='apcu_delete')) {
            tdz::log('[ERROR] No APC installed! It shouldn\'t be selected.');
            return Tecnodesign_Cache_File::delete($key);
        }
        $siteKey = Tecnodesign_Cache::siteKey();
        if($siteKey) {
            $key = $siteKey.'/'.$key;
        }
        if(@$fn($key.'.meta') && @$fn($key)) {
            return true;
        } else {
            return false;
        }
    }
}