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
 * @version   3.0
 */
class Tecnodesign_Yaml extends Studio\Yaml
{
    /**
     * Current parser
     * Leaving it public you allow user to use a parser not compatible with this class
     * @deprecated You should use setParser if not using native PHP YML
     * @var string
     */
    public static $parser;

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
}
