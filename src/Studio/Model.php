<?php
/**
 * Model
 * 
 * Object definition and logic
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
namespace Studio;

use Tecnodesign_Studio as Studio;
use Tecnodesign_Query as Query;
use tdz as S;

class Model extends \Tecnodesign_Model
{
    const SCHEMA_PROPERTY='schema';
    const AUTOLOAD_CALLBACK='staticInitialize';
    public static $allowNewProperties = true, $schemaClass='Studio\\Schema\\Model';

    public static function staticInitialize()
    {
        parent::staticInitialize();
        // check if database exists or needs to be overwriten by file
        if(static::$schema && ($db = static::$schema->database) && !Query::database($db)) {
            $conn = Studio::config('connection');
            if($conn && $conn!=$db && isset(S::$database[$conn])) {
                S::$database[$db] = S::$database[$conn];
            }
        }
    }
}