<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
 */
namespace Studio\Model;

use Studio\Model as Model;
use Studio as S;

class SchemaDisplay extends Model
{
    public static $schema;

    protected $schema_id, $id, $bind, $type, $content, $condition, $created, $updated, $Schema, $Properties;

    public function choicesBind($check=null, $count=null)
    {
        $r = [];
        $q = [];
        if($this->schema_id) {
            $q['schema_id']=$this->schema_id;
        }
        if($check) {
            $q['bind'] = $check;
        }
        if($q && ($L = SchemaProperties::find($q,$count,'string',false))) {
            if($count==1) $L = [$L];
            foreach($L as $i=>$o) {
                $r[$o->bind] = (string) $o;
                unset($L[$i], $i, $o);
            }
            unset($L);
        }

        return $r;
    }

    public function choicesType()
    {
        $o = [];
        static $o;
        if(!$o) {
            $o = S::t([
                'label' => 'Label',
                'before' => 'Content Before (Markdown)',
                'after' => 'Content After (Markdown)',
                'choices' => 'Available Options (YAML or CSV)',
                'hidden' => 'Hidden',
                'disabled' => 'Disabled',
                'unavailable' => 'Not Available',
            ], 'model-studio_schema_display');
        }
        return $o;
    }

    public function choicesCondition()
    {
        $o = [];
        static $o;
        if(!$o) {
            $o = S::t([
                '=' => 'Equal to',
                '!=' => 'Not Equal to',
                '>' => 'Greaten than',
                '>=' => 'Greater than or Equal to',
                '<' => 'Lower than',
                '<=' => 'Lower than or Equal to',
                '' => 'Empty',
                '*' => 'Not Empty',
            ], 'model-studio_schema_display');
        }
        return $o;
    }
}