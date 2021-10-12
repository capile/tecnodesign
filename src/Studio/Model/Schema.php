<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
namespace Studio\Model;

use Studio\Model as Model;
use Studio as S;

class Schema extends Model
{
    public static $schema;

    protected $id, $title, $type, $description, $class_name, $database, $table_name, $view, $order_by, $group_by, $pattern_properties, $scope, $created, $updated, $SchemaProperties;
}