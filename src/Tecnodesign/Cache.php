<?php

/**
 * Variable Caching and retieving
 *
 * This package implements a common interface for caching both in files or memory
 *
 * PHP version 5.3
 *
 * @category  Cache
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: Cache.php 1198 2013-03-13 19:18:55Z capile $
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Cache
{
    public static $expires = 0, $memcachedServers = array(), $storage;//array('localhost:11211');
    /**
     * Cache key used for storing this site information in memory, must be a
     * unique string.
     *
     * @var string
     */
    private static $_siteKey = null;

    public static function getLastModified($key, $expires = 0, $method = true)
    {
        return self::lastModified($key, $expires, $method);
    }

    public static function lastModified($key, $expires = 0, $method = null)
    {
        $cn = 'Tecnodesign_Cache_' . ucfirst(self::storage($method));
        if (is_array($key) && $key) {
            foreach ($key as $ckey) {
                $ret2 = $cn::lastModified($ckey, $expires);
                if ($ret2 > $ret) {
                    $ret = $ret2;
                }
                unset($ckey, $ret2);
            }
        } else {
            $ret = $cn::lastModified($key, $expires);
        }
        unset($fn, $key, $expires, $method);
        if ($ret) {
            $ret = (int)$ret;
        }
        return $ret;
    }

    public static function storage($method = null)
    {
        if ($method !== null && is_string($method)
            && in_array($method, array('file', 'apc', 'memcache', 'memcached'))) {
            return $method;
        }

        if (self::$storage === null) {
            if (self::$memcachedServers && ini_get('memcached.serializer') && Tecnodesign_Cache_Memcached::memcached()) {
                self::$storage = 'memcached';
            } elseif (self::$memcachedServers && function_exists('memcache_debug') && Tecnodesign_Cache_Memcache::memcache()) {
                self::$storage = 'memcache';
            } elseif (function_exists('apc_fetch')) {
                self::$storage = 'apc';
            } else {
                self::$storage = 'file';
            }
        }
        return self::$storage;
    }

    /**
     * Gets currently stored key-pair value
     *
     * @param $key     mixed  key to be retrieved or array of keys to be tried (first available is returned)
     * @param $expires int    timestamp to be compared. If timestamp is newer than cached key, false is returned.
     * @param $method  mixed  Storage method to be used. Should be either a key or a value in self::$_methods
     */
    public static function get($key, $expires = 0, $method = null, $fileFallback = false)
    {
        $cn = 'Tecnodesign_Cache_' . ucfirst($method = self::storage($method));
        if ($expires && $expires < 2592000) {
            $expires = microtime(true) - (float)$expires;
        }
        if (is_array($key)) {
            foreach ($key as $ckey) {
                $ret = $cn::get($ckey, $expires);
                if ($ret) {
                    unset($ckey);
                    break;
                }
                unset($ckey, $ret);
            }

            if (!isset($ret)) {
                $ret = false;
            }

        } else {
            $ret = $cn::get($key, $expires);
        }

        if ($fileFallback && $ret === false && $method !== 'file' && !$expires) {
            $ret = Tecnodesign_Cache_File::get($key);
            if ($ret) {
                self::set($key, $ret);
            }
        }
        return $ret;
    }

    /**
     * Sets currently stored key-pair value
     *
     * @param mixed $key key(s) to be stored
     * @param mixed $value value to be stored
     * @param int|float $expires timestamp to be set as expiration date.
     * @param mixed $method Storage method to be used. Should be either a key or a value in self::$_methods
     *
     * @return bool
     */
    public static function set($key, $value, $expires = 0, $method = null, $fileFallback = false)
    {
        $className = 'Tecnodesign_Cache_' . ucfirst($method = self::storage($method));
        if ($expires && $expires < 2592000) {
            $expires = microtime(true) + (float)$expires;
        }
        $ret = $className::set($key, $value, $expires);
        if ($fileFallback && $method != 'file' && !$expires) {
            $ret = Tecnodesign_Cache_File::set($key, $value);
        }
        return $ret;
    }

    public static function delete($key, $method = null, $fileFallback = false)
    {
        $className = 'Tecnodesign_Cache_' . ucfirst($method = self::storage($method));
        if ($fileFallback && $method !== 'file') {
            Tecnodesign_Cache_File::delete($key);
        }
        return $className::delete($key);
    }

    public static function size($key, $expires = 0, $method = null, $fileFallback = false)
    {
        $className = 'Tecnodesign_Cache_' . ucfirst($method = self::storage($method));
        if ($expires && $expires < 2592000) {
            $expires = microtime(true) + (float)$expires;
        }
        $ret = $className::size($key, $expires = 0);
        if ($fileFallback && $ret === false && $method !== 'file') {
            $ret = Tecnodesign_Cache_File::size($key);
        }
        return $ret;
    }

    /**
     * Defines a scope for this server cache space
     * @param string $siteKey
     * @return bool|string|null
     */
    public static function siteKey($siteKey = null)
    {
        if ($siteKey !== null) {
            self::$_siteKey = $siteKey;
        } elseif (self::$_siteKey === null) {
            self::$_siteKey = false;
        }

        return self::$_siteKey;
    }

    public static function filename($key)
    {
        return Tecnodesign_Cache_File::filename($key);
    }

    public static function cacheDir($s = null)
    {
        return Tecnodesign_Cache_File::cacheDir($s);
    }
}

stream_wrapper_register('cache', 'Tecnodesign_Cache_Wrapper');
