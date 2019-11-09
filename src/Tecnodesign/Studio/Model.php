<?php
/**
 * Studio and application configuration
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
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
        if(static::$schema && ($db = static::$schema->database) && !Tecnodesign_Query::database($db)) {
            $cfg = tdz::getApp()->studio;
            if($cfg && isset($cfg['connection']) && $cfg['connection']!=$db && isset(tdz::$database[$cfg['connection']])) {
                static::$schema->database = $cfg['connection'];
            }
        }
        if(isset($db) && $db) {
            Tecnodesign_Studio::$connection = $db;
        }
    }
}
