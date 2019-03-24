<?php
/**
 * Studio and application configuration
 *
 * PHP version 5.4+
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2019 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Studio_Config extends Tecnodesign_Studio_Model
{
    public static $schema = array (
      'database' => '_studio-config',
      'tableName' => 'studio',
      'idPattern'=>'-*--%s',
      'className' => 'Tecnodesign_Studio_Config',
      'columns' => array (
        'id'=>array('type'=>'string','primary'=>true),
        'type'=>array('type'=>'string'),
        'data'=>array('type'=>'string','serialize'=>'yaml'),
        'modified'=>array('type'=>'datetime'),
      ),
      'relations' => array (
      ),
      'scope' => array (
      ),
      'order' => array(
      ),
      'events' => array (
      ),
      'form' => array (
      ),
    ), $allowNewProperties = true;

    protected $uid, $id, $type, $modified, $data;

    public function getId()
    {
        $r = '';
        if($this->uid) {
            $r = basename($this->uid, '.yml');
            if(static::$schema['tableName'] && substr($r, 0, strlen(static::$schema['tableName']))==static::$schema['tableName']) {
                $r = substr($r, strlen(static::$schema['tableName']));
                if(substr($r, 0, 1)=='-') $r = substr($r, 1);

                if(preg_match('/^([a-z]+)\--(.+)/', $r, $m)) {
                    if(file_exists(TDZ_ROOT.'/schema/'.$m[1].'.yml')) {
                        $this->type = $m[1];
                        $r = $m[2];
                    }
                }
            }
        }
        return $r;
    }

    public function getModified()
    {
        if($this->src && file_exists($this->src)) {
            return date('c', filemtime($this->src));
        }
    }

    public function __toString()
    {
        $id = $this->getId();
        return $this->previewType(false).': '.$id;
    }

    public function previewType($xml=true)
    {
        return ($xml)?(tdz::xml($this->type)):($this->type);
    }

}
