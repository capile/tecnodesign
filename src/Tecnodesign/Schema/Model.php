<?php
/**
 * Model Meta-Schema
 *
 * This is the meta-schema for Tecnodesign_Model, to validate all model schemas
 *
 * PHP version 5.4
 *
 * @category  Model
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2019 Tecnodesign
 * @license   https://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Schema_Model extends Tecnodesign_Schema
{
    public static $meta;

    public 
        $title, // { type: string }
        $database, // { type: string }
        $className, // { type: string }
        $tableName, // { type: string }
        $view, // { type: string }
        $properties, // { type: object }
        $patternProperties, // { type: array }
        $overlay, // { type: object }
        $scope, // { type: object }
        $relations, // { type: object }
        $events, // { type: object }
        $orderBy, // { type: object }
        $groupBy; // { type: object }
        //$columns, // { alias: properties }
        //$form; // { alias: overlay }


}