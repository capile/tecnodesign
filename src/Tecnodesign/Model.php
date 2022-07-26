<?php
/**
 * Tecnodesign Model
 * 
 * Full database abstraction ORM.
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */
class Tecnodesign_Model extends Studio\Model
{
    /**
     * Model schema auto-updates
     *
     * This method updates the schema definitions ot the Models indicated by $f
     *
     * @param string $f file name to update
     *
     * @return bool  whether the class was updated.
     */
    public function updateSchema($f=null)
    {
        if(is_null($f) || !file_exists($f)) {
            return false;
        }
        $schema = $this->schema();

        $classCode = file_get_contents($f);
        $start = strpos($classCode, '//--tdz-schema-start--');
        $end = strpos($classCode, '//--tdz-schema-end--');
        if ($start===false || $end===false) {
            return false;
        }
        if(!isset($schema['tableName']) || $schema['tableName']=='') {
            $schema['tableName'] = tdz::uncamelize(get_class($this));
        }
        $app = tdz::getApp();
        if($app) {
            $dbold = tdz::$database;
            if(!isset($schema['database']) || $schema['database']=='') {
                $db = array_keys($dbold);
                $db=$db[0];
                $schema['database']=$db;
            } else {
                $db = $schema['database'];
            }
            // set default database for tdz::query
            $databases = array($db=>$dbold[$db]);
            $databases += $dbold;
            tdz::$database = $databases;
            $dbo = $databases[$db];
            unset($databases);
        } else {
            $schema['database'] = Tecnodesign_Database::$database;
            $dbo = Tecnodesign_Database::$dbo;
        }
        $tn = $schema['tableName'];
        $dbtype = preg_replace('/\:.*/', '', $dbo['dsn']);
        $scn = 'Tecnodesign_Model_'.ucfirst($dbtype);
        if(!class_exists($scn)) {
            throw new Tecnodesign_Exception('Don\'t know how to update this schema, sorry...');
        }
        $code = $scn::updateSchema($schema, $this);
        $code = str_replace("\n", "\n    ", $code);
        $code = substr($classCode, 0, $start).$code.substr($classCode, $end);
        return tdz::save($f, $code);
    }
}
