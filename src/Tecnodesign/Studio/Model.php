<?php
/**
 * Studio and application configuration
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Studio_Model extends Tecnodesign_Model implements Tecnodesign_AutoloadInterface
{
    const SCHEMA_PROPERTY='schema';
    public static $allowNewProperties = true;

    public static function staticInitialize()
    {
        parent::staticInitialize();

        // check if database exists or needs to be overwriten by file
        if(static::$schema && ($db = static::$schema->database) && !isset(tdz::$database[$db])) {
            //tdz::$database[$db]=['dsn'=>tdz::expandVariables(Tecnodesign_Studio::$indexDatabase)];
            // @todo: need to create/alter tables for sqlite indexing
        } else if(isset($db) && $db) {
            Tecnodesign_Studio::$connection = $db;
        }
    }
}
