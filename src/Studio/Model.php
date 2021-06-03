<?php

namespace Studio;

use Tecnodesign_Studio as Studio;
use Tecnodesign_Query as Query;
use tdz as S;

class Model extends \Tecnodesign_Model implements \Tecnodesign_AutoloadInterface
{
    const SCHEMA_PROPERTY='schema';
    public static $allowNewProperties = true;

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