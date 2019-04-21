<?php
/**
 * Studio and application configuration
 *
 * PHP version 5.6
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2019 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Studio_Model extends Tecnodesign_Model implements Tecnodesign_AutoloadInterface
{
    const SCHEMA_PROPERTY='schema';
    public static $schema, $allowNewProperties = true;

    public static function staticInitialize()
    {
        static $dbs = array(
            '_studio' => 'file:web/*',
            '_studio-config' => 'file:config/*.yml',
        );
        static::$schema = Tecnodesign_Schema_Model::loadSchema(get_called_class());

        // check if database exists or needs to be overwriten by file
        if(is_null(tdz::$database)) Tecnodesign_Query::database();
        if(static::$schema && ($db = static::$schema->database) && !isset(tdz::$database[$db])) {
            if(substr($db, 0, 1)==='_' && isset(tdz::$database[substr($db,1)])) {
                static::$schema->database = substr($db,1);
            } else if(isset($dbs[$db])) {
                tdz::$database[$db] = array('dsn'=>$dbs[$db]);
            }
        }
        parent::staticInitialize();
    }
}
