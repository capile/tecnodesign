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
use tdz as S;

class Crypto
{
    public static 
        $defaultHashType='crypt:$6$rounds=5000$'    // hashing method
        ;

    /**
     * Dynamic hashing and checking
     *
     * Will return an hashed version of string using the MD5 method, instead of the
     * common DES encryption algorithm. It's useful for cross-platforms encryptions,
     * since the MD5 checksum can be found in many other environments (even not
     * Unix/GNU).
     *
     * The results are hashes and cannot be unencrypted. To check if a new text
     * matches the encrypted version, provide this as the salt, and the result
     * should be the same as the encrypted text.
     *
     * @param   string $str   the text to be encrypted
     * @param   string $salt  the encrypted text or a randomic salt
     * @param   string $type  hash type, can be either a hash_algos() or a string length
     *                        (from 40 to 80) for the hash size
     *
     * @return  string        an encrypted version of $str
     */
    public static function hash($str, $salt=null, $type=null)
    {
        if(is_null($type) || $type===true) { // guess based on $salt
            if($salt) {
                if(preg_match('/^\{([^\}]+)\}/', $salt, $m)) {
                    $type = $m[1];
                } else if(preg_match('/^\$(2[axy]|5|6)\$/', $salt, $m)) {
                    $type = 'crypt';
                } else {
                    // this should be deprecated
                    $type = 40;
                }
            } else {
                $type = self::$defaultHashType;
            }
        }
        if($type=='uuid') {
            return S::encrypt($str, $salt, 'uuid');
        } else if($type==='crypt' || substr($type, 0, 6)==='crypt:') {
            if(is_null($salt)) {
                $opts = (substr($type, 0, 6)==='crypt:') ?substr($type, 6) :'$6$';
                if(substr($opts, -1)!='$') $opts .= '$';
                $salt = $opts.((substr($opts, 0, 2)=='$2') ?self::salt(22,['+'=>'.']): self::salt(16,['+'=>'.']));
            }
            return crypt($str, $salt);
        } else if(is_string($type)) {
            $t = strtoupper($type);
            if(substr($t, 0, 4)=='SSHA' || substr(strtolower($t), 0, 4)=='SMD5') {
                if(is_null($salt)) $salt = self::salt(20, false);
                else if(substr($salt, 0, strlen($type)+2)=="{{$t}}") {
                    $salt = substr(base64_decode(substr($salt, strlen($type)+2)), strlen(hash(strtolower(substr($t,1)), null, true)));
                }
                $h = "{{$t}}" . base64_encode(hash(strtolower(substr($t,1)), $str . $salt, true) . $salt);
            } else {
                $h = hash($type, $str);
                if ($salt != null && strcasecmp($h, $salt)==0) {
                    return $salt;
                }
            }
            return $h;
        } else {
            $len = 8;
            $m='md5';
            if(is_int($type) && $type>32) {
                $len = $type - 32;
                if($type>64) {
                    if($type > 80) {
                        $type = 80;
                    }
                    $m = 'sha1';
                    $len = $type - 40;
                }
            }
            if(!$salt){
                $salt = $m(uniqid(rand(), 1));
            }
            $salt = substr($salt, 0, $len);
            return $salt . $m($str.$salt);
        }
    }


    public static function salt($length=40, $safe=true)
    {
        if(function_exists('openssl_random_pseudo_bytes')) {
            $rnd = openssl_random_pseudo_bytes($length);
        } else if(function_exists('random_bytes')) {
            $rnd = random_bytes($length);
        } else {
            $rnd = substr(pack('H*',uniqid(true).uniqid(true).uniqid(true).uniqid(true).uniqid(true)), 0, $length);
        }
        if($safe) {
            $r = (is_array($safe)) ?$safe :['+'=>'-','/'=>'_'];
            return substr(strtr(rtrim(base64_encode($rnd), '='), $r), 0, $length);
        } else if($safe!==false) {
            return substr(base64_encode($rnd), 0, $length);
        } else {
            return $rnd;
        }
    }
}