<?php

namespace Studio\Model;

use Studio\Model as Model;
use Studio\Schema as Schema;
use tdz as S;

class Interfaces extends Model
{
    public static $schema;
    protected $id, $title, $model, $connection, $source, $schema_source, $schema_data, $credential, $index_interval, $indexed, $created, $updated;


    public function loadSchema()
    {
        $S = ['test'=>false];
        $this->refresh(['schema_data', 'schema_source', 'model']);
        if($this->model && method_exists($this->model, 'schema')) {
            $cn = $this->model;
            $sc = $cn::schema();
            $S = (array) $sc;
        } else if($this->schema_source) {
            $sc = Schema::import($this->schema_source);
            $S = $sc;//->properties;
        }

        if($this->schema_data) {
            $d = (is_string($this->schema_data)) ?S::unserialize($this->schema_data, 'yaml') :$this->schema_data;

            if($d) {
                $S = S::mergeRecursive($d, $S);
            }
        }

        return $S;
    }

    public function previewSchemaData()
    {
        return '<code style="white-space:pre">'.S::xml(preg_replace('#^---\n#', '', S::serialize($this->loadSchema(), 'yaml'))).'</code>';
    }

    public function cacheFile()
    {
        S::debug(__METHOD__, var_Export($this, true));
    }
}