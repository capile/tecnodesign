<?php

namespace Studio\Model;

use Studio\Model as Model;
use Studio\Schema as Schema;
use Studio\OAuth2\Storage as Storage;
use Studio\OAuth2\Client as Client;
use Tecnodesign_Schema_Model as ModelSchema;
use tdz as S;

class Interfaces extends Model
{
    public static $schema, $pkids=['id', 'uid', 'Id', 'ID', 'pkId', 'UUID', 'uuid'];
    protected $id, $title, $model, $connection, $source, $schema_source, $schema_data, $credential, $index_interval, $indexed, $created, $updated;

    public function model()
    {
        $S = $this->loadSchema(false);
        if(!$this->model) {
            $cn = 'Studio_Interfaces_'.S::camelize($this->id, true);
            $d = S::getApp()->config('tecnodesign', 'cache-dir').'/interface';
            if($d) {
                $f = $d.'/'.$cn.'.php';
                if(!file_exists($f)) {
                    $fns = ($S->properties) ?array_keys($S->properties) :[];
                    if($S->relations) $fns = array_merge($fns, array_keys($S->relations));
                    $pf = ($fns) ?'protected $'.implode(', $', $fns).';' :'';
                    S::save($f, '<?'.'php class '.$cn.' extends Studio\\Model { public static $schema, $allowNewProperties=true; };');
                }
                require_once $f;
            }
            $S->className = $cn;
            $S->patternProperties = '/.*/';
            $cn::$schema = $S;
        } else {
            $cn = $this->model;
        }
        $S = null;

        return $cn;
    }

    public function loadSchema($asArray=true)
    {
        $S = ['test'=>false];
        $this->refresh(['schema_data', 'schema_source', 'model']);
        if($this->model && method_exists($this->model, 'schema')) {
            $cn = $this->model;
            $sc = $cn::schema();
            $S = ($asArray) ?(array) $sc :$sc;
        } else if($this->schema_source) {
            $sc = Schema::import($this->schema_source);
            $S = $sc;//->properties;

            if(!$asArray) {
                $S = new ModelSchema($S);
            }
        }

        if($this->schema_data) {
            $d = (is_string($this->schema_data)) ?S::unserialize($this->schema_data, 'yaml') :$this->schema_data;

            if($d && $asArray) {
                $S = S::mergeRecursive($d, $S);
            }
        }

        if(!$asArray) {
            $pk = [];
            foreach($S->properties as $fn=>$fd) {
                if($fd['primary']) $pk[] = $fn;
            }
            if(!$pk) {
                foreach(static::$pkids as $k) {
                    if(isset($S->properties[$k])) {
                        $S->properties[$k]->primary = true;
                        $pk[] = $k;
                        break;
                    }
                }
            }

            if($pk) {
                $scope = ($S->scope) ?$S->scope :[];
                $scope['uid'] = $pk;
                $S->scope = $scope;
                unset($scope);
            }

            if($this->connection) {
                $S->database = $this->connection();
            }
            if($this->source) $S->tableName = $this->source;
        }

        return $S;
    }

    public function connection()
    {
        if(!$this->connection) return;

        if(!isset(S::$database[$this->connection])) {
            if(preg_match('/^server:/', $this->connection)) {
                list($type, $sid) = explode(':', $this->connection);
                $Server = new Client(Storage::fetch($type, $sid));
                if($a=$Server->api_endpoint) {
                    $o = $Server->api_options;
                    if(!is_array($o) && $o) $o = S::unserialize($o, 'json');
                    if(!$o) $o=[];
                    $o['connectionCallback'] = [$Server, 'connectApi'];
                    S::$database[$this->connection] = [
                        'dsn' => $a,
                        'options'=>$o,
                    ];
                }
            }
        }

        return $this->connection;
    }

    public function previewSchemaData()
    {
        return '<code style="white-space:pre">'.S::xml(preg_replace('#^---\n#', '', S::serialize($this->loadSchema(), 'yaml'))).'</code>';
    }

    public function cacheFile()
    {
        $d = S::getApp()->config('tecnodesign', 'cache-dir').'/interface';
        $f =  $d.'/'.S::slug($this->id).'.yml';
        if(!file_exists($f)) {
            $a = $this->asArray('interface');
            if(!isset($a['model'])) {
                $a['model'] = 'Studio\\Model\\Index';
                $a['search'] = ['interface'=>$this->id];
            }

            if(!S::save($f, S::serialize(['all'=>$a], 'yaml'), true)) {
                $f = null;
            }
        }

        return $f;
    }
}