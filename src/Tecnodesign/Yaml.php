<?php
/**
 * YAML loading and deploying using Spyc
 *
 * This package implements file caching and a interface to Spyc (www.yaml.org)
 *
 * PHP version 5.2
 *
 * @category  Yaml
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: Yaml.php 1229 2013-06-11 05:55:57Z capile $
 * @link      http://tecnodz.com/
 */

Tecnodesign_Yaml::parser();

/**
 * YAML loading and deploying using Spyc
 *
 * This package implements file caching and a interface to Spyc (www.yaml.org)
 *
 * @category  Yaml
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */
class Tecnodesign_Yaml
{
    public static $parser, $cache=true;
    /**
     * Defines/sets current Yaml parser
     */
    public static function parser($P=null)
    {
        if(!is_null($P)) {
            self::$parser = $P;
        }
        if(is_null(self::$parser)) {
            if(function_exists('yaml_parse')) self::$parser='php-yaml';
            else self::$parser = 'Spyc';
        }
        
        if(self::$parser=='Spyc' && !class_exists('Spyc')) {
            Tecnodesign_App_Install::dep('Spyc');
        }
        
        return self::$parser;
    }


    /**
     * Loads YAML text and converts to a PHP array
     *
     * @param string $s file name or YAML string to load
     * 
     * @return array contents of the YAML text
     */
    public static function load($s, $timeout=1800)
    {
        $readTimeout = $timeout;
        if(strlen($s)<255 && file_exists($s)) {
            if(self::$parser=='php-yaml') {
                $fn = 'yaml_parse_file';
            } else {
                $C = 'Spyc';
                $fn = 'YAMLLoad';
            }
            $file = true;
            $readTimeout = filemtime($s);
        } else {
            if(self::$parser=='php-yaml') {
                $fn = 'yaml_parse';
            } else {
                $C = 'Spyc';
                $fn = 'YAMLLoadString';
            }
            $file = false;
        }
        if(self::$cache && ($file || strlen($s)>4000)) {
            $ckey = 'yaml/'.md5($s);
            $cache = Tecnodesign_Cache::get($ckey, $readTimeout);
            if ($cache) return $cache;
            if(isset($C)) {
                $a = $C::$fn($s);
            } else {
                $a = $fn($s);
            }
            Tecnodesign_Cache::set($ckey, $a, $timeout);
            unset($ckey, $cache, $fn, $readTimeout);
            return $a;
        } else {
            if(isset($C)) {
                return $C::$fn($s);
            } else {
                return $fn($s);
            }
        }
    }

    /**
     * Loads YAML text and converts to a PHP array
     *
     * @param string $s YAML string to load
     * 
     * @return array contents of the YAML text
     */
    public static function loadString($s)
    {
        if(self::$parser=='php-yaml') {
            return yaml_parse($s);
        } else {
            return Spyc::YAMLLoadString($s);
        }
    }
    /**
     * Dumps YAML content from params
     *
     * @param mixed $a arguments to be converted to YAML
     * 
     * @return string YAML formatted string
     */
    public static function dump($a, $indent=2, $wordwrap=0)
    {
        if(self::$parser=='php-yaml') {
            ini_set('yaml.output_indent', (int)$indent);
            ini_set('yaml.output_width', $wordwrap);
            return yaml_emit($a, YAML_UTF8_ENCODING, YAML_LN_BREAK);
        } else {
            return Spyc::YAMLDump($a, $indent, $wordwrap);
        }
    }

    public static function save($s, $a, $timeout=1800)
    {
        $ckey = 'yaml/'.md5($s);
        if(self::$cache && $timeout) Tecnodesign_Cache::set($ckey, $a, $timeout);
        return tdz::save($s, self::dump($a), true);
    }

    /**
     * Appends YAML text to memory object and yml file
     *
     * @param string $s file name or YAML string to load
     * 
     * @return array contents of the YAML text
     */
    public static function append($s, $arg, $timeout=1800)
    {
        $text = $arg;
        if(is_array($arg)) {
            $text = "\n".preg_replace('/^---[^\n]*\n?/', '', self::dump($arg));
        } else {
            $arg = self::parse($arg);
        }
        $yaml = self::load($s);
        $a = tdz::mergeRecursive($yaml, $arg);
        if($a!=$yaml) {
            if(self::$cache) {
                $ckey = 'yaml/'.md5($s);
                Tecnodesign_Cache::set($ckey, $a, $timeout);
            }
            file_put_contents($s, $text, FILE_APPEND);
        }
        unset($arg, $yaml, $s, $text);
        return $a;
    }

}