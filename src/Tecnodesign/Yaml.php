<?php
/**
 * YAML loading and deploying using Spyc
 * 
 * This package implements file caching and a interface to Spyc (www.yaml.org)
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
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

    private static $autoInstall = false;

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
            if(($parser===self::PARSE_SPYC && !class_exists('Spyc')) || ($parser===self::PARSE_NATIVE && !extension_loaded('yaml'))) {
                return false;
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
     * @param int $cacheTimeout
     *
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
            if ($isFile && ($lastModified = filemtime($string)) > time() - $readTimeout) {
                $readTimeout = $lastModified;
                unset($lastModified);
            }

            $cacheFound = Tecnodesign_Cache::get($cacheKey, $readTimeout);
            if ($cacheFound) {
                return $cacheFound;
            }
        }

        $className = (self::$currentParser === self::PARSE_NATIVE) ? null : 'Spyc';
        if ($isFile) {
            $functionName = (self::$currentParser === self::PARSE_NATIVE) ? 'yaml_parse_file' : 'YAMLLoad';
            $readTimeout = filemtime($string);
        } else {
            $functionName = (self::$currentParser === self::PARSE_NATIVE) ? 'yaml_parse' : 'YAMLLoadString';
        }

        $yamlArray = $className ? $className::$functionName($string) : $functionName($string);
        if($yamlArray===false) {
            tdz::log('[INFO] Could not parse Yaml: '.$string);
        }

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
        // Initialize the default parser
        self::parser();

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
        // Initialize the default parser
        self::parser();

        if (self::$currentParser === self::PARSE_NATIVE) {
            ini_set('yaml.output_indent', (int)$indent);
            ini_set('yaml.output_width', (int)$wordwrap);
            return yaml_emit($data, YAML_UTF8_ENCODING, YAML_LN_BREAK);
        }

        // BUGFIX: Spyc does not escape keys starting with *
        return preg_replace('/^( +)(\*[^\:]+)\:( |$)/m', '$1\'$2\':$3', Spyc::YAMLDump($data, $indent, $wordwrap));
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
        if ($timeout && self::$cache) {
            Tecnodesign_Cache::set($cacheKey, $data, $timeout);
        }

        /**
         * @todo there's no way to know if $filename is string or a file to be updated or create
         */
        return tdz::save($filename, self::dump($data), true);
    }

    /**
     * Appends YAML text to memory object and yml file
     *
     * This is used with tdz::t(). It should be used with caution since it will not
     * merge correctly all files. Interfaces configurations for example
     *
     * @param string $yaml file name or YAML string to load
     * @param array $append
     * @param int $timeout
     *
     * @return array contents of the YAML text
     */
    public static function append($yaml, $append, $timeout = 1800)
    {
        if (!is_array($append)) {
            throw new \InvalidArgumentException('$append must be an array');
        }

        $yamlArray = self::load($yaml);
        $yamlMerged = array_replace_recursive($yamlArray, $append);
        if ($yamlMerged !== $yamlArray) {
            self::save($yaml, $yamlMerged, $timeout);
        }

        unset($yaml, $append, $timeout, $yamlArray);

        return $yamlMerged;
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
