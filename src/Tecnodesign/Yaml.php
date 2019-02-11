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

/**
 * @todo is it safe to remove?
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
    /**
     * Current parser
     * Leaving it public you allow user to use a parser not compatible with this class
     * @deprecated You should use setParser if not using native PHP YML
     * @var string
     */
    public static $parser;

    /**
     * Current parser
     * @var string
     */
    private static $currentParser;

    /**
     * @var boolean
     */
    public static $cache = true;

    private static $autoInstall = true;

    const PARSE_NATIVE = 'php-yaml';
    const PARSE_SPYC = 'Spyc';

    /**
     * Defines/sets current Yaml parser
     * @param string $parser
     * @return string|null
     */
    public static function parser($parser = null)
    {
        if ($parser !== null) {
            if (!in_array($parser, [self::PARSE_NATIVE, self::PARSE_SPYC], true)) {
                throw new \InvalidArgumentException("Invalid parser: $parser");
            }
            self::$currentParser = $parser;
        } elseif (self::$currentParser === null && extension_loaded('yaml')) {
            self::$currentParser = self::PARSE_NATIVE;
        } elseif (self::$currentParser === null) {
            self::$currentParser = self::PARSE_SPYC;
        }

        if (self::$currentParser === self::PARSE_SPYC && !class_exists('Spyc')) {
            if (self::$autoInstall) {
                Tecnodesign_App_Install::dep('Spyc');
            } else {
                throw new \RuntimeException('Spyc not installed');
            }
        }

        if (self::$currentParser === self::PARSE_NATIVE && !extension_loaded('yaml')) {
            throw new \RuntimeException('Yaml extension not installed.');
        }

        /**
         * Just to maintain compatibility with someone extended this class
         */
        self::$parser = self::$currentParser;

        return self::$currentParser;
    }

    /**
     * Loads YAML text and converts to a PHP array
     *
     * @param string $string file name or YAML string to load
     *
     * @param int $cacheTimeout
     * @return array contents of the YAML text
     */
    public static function load($string, $cacheTimeout = 1800)
    {
        // Initialize the default parser
        self::parser();

        $readTimeout = $cacheTimeout;

        $isFile = ($string && strlen($string) < 255 && file_exists($string));

        $cacheKey = 'yaml/' . md5($string);
        $useCache = self::$cache && ($isFile || strlen($string) > 4000);
        if ($useCache) {
            $cacheFound = Tecnodesign_Cache::get($cacheKey, $readTimeout);
            if ($cacheFound) {
                return $cacheFound;
            }
        }

        $className = (self::$currentParser === self::PARSE_NATIVE)? null : 'Spyc';
        if ($isFile) {
            $functionName = (self::$currentParser === self::PARSE_NATIVE)? 'yaml_parse_file' : 'YAMLLoad';
            $readTimeout = filemtime($string);
        } else {
            $functionName = (self::$currentParser === self::PARSE_NATIVE)? 'yaml_parse' : 'YAMLLoadString';
        }

        $yamlArray = $className ? $className::$functionName($string) : $functionName($string);

        if ($useCache) {
            Tecnodesign_Cache::set($cacheKey, $yamlArray, $cacheTimeout);
        }

        /**
         * @todo necessary for PHP < 7
         */
        unset($cacheKey, $useCache, $className, $functionName, $readTimeout);

        return $yamlArray;
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
        if (self::$currentParser === self::PARSE_NATIVE) {
            return yaml_parse($s);
        }

        return Spyc::YAMLLoadString($s);
    }

    /**
     * Dumps YAML content from params
     *
     * @param mixed $data arguments to be converted to YAML
     * @param int $indent
     * @param int $wordwrap
     * @return string YAML formatted string
     */
    public static function dump($data, $indent = 2, $wordwrap = 0)
    {
        if (self::$parser === self::PARSE_NATIVE) {
            ini_set('yaml.output_indent', (int)$indent);
            ini_set('yaml.output_width', $wordwrap);
            return yaml_emit($data, YAML_UTF8_ENCODING, YAML_LN_BREAK);
        }

        return Spyc::YAMLDump($data, $indent, $wordwrap);
    }

    /**
     * @param string $filename
     * @param mixed $data Arguments to be converted to YAML
     * @param int $timeout OPTIONAL Cache timeout
     * @return bool
     */
    public static function save($filename, $data, $timeout = 1800)
    {
        $cacheKey = 'yaml/' . md5($filename);
        if (self::$cache && $timeout) {
            Tecnodesign_Cache::set($cacheKey, $data, $timeout);
        }
        return tdz::save($filename, self::dump($data), true);
    }

    /**
     * Appends YAML text to memory object and yml file
     *
     * @param string $s file name or YAML string to load
     *
     * @return array contents of the YAML text
     */
    public static function append($s, $arg, $timeout = 1800)
    {
        $text = $arg;
        if (is_array($arg)) {
            $text = "\n" . preg_replace('/^---[^\n]*\n?/', '', self::dump($arg));
        } else {
            $arg = self::parse($arg);
        }
        $yaml = self::load($s);
        $a = tdz::mergeRecursive($yaml, $arg);
        if ($a != $yaml) {
            if (self::$cache) {
                $ckey = 'yaml/' . md5($s);
                Tecnodesign_Cache::set($ckey, $a, $timeout);
            }
            file_put_contents($s, $text, FILE_APPEND);
        }
        unset($arg, $yaml, $s, $text);
        return $a;
    }

    /**
     * @return bool
     */
    public static function isAutoInstall()
    {
        return static::$autoInstall;
    }

    /**
     * @param bool $autoInstall
     */
    public static function setAutoInstall($autoInstall)
    {
        static::$autoInstall = $autoInstall;
    }

}
