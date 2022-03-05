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

use Studio as S;
use Studio\Api;
use Studio\App;
use Studio\Model;
use Studio\Schema;
use Studio\OAuth2\Storage;
use Studio\OAuth2\Client;
use Studio\Studio;
use Tecnodesign_Schema_Model as ModelSchema;
use Tecnodesign_Query_Api as QueryApi;
use Tecnodesign_Yaml as Yaml;

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
        $S = [];
        $this->refresh(['schema_data', 'schema_source', 'model']);
        if($this->model && method_exists($this->model, 'schema')) {
            $cn = $this->model;
            $sc = $cn::schema();
            $S = ($asArray) ?(array) $sc :$sc;
        } else if($this->schema_source) {
            $sc = Schema::import($this->schema_source);
            $S = $sc;//->properties;
        }

        if($this->schema_data) {
            $d = (is_string($this->schema_data)) ?S::unserialize($this->schema_data) :$this->schema_data;

            if($d) {
                $S = S::mergeRecursive($d, $S);
            }
        }
        if(!$asArray) {
            $S = new ModelSchema($S);
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
        static $n;
        static $d;
        static $i=1;

        if(is_null($n)) $n = (isset(Studio::$interfaces['interfaces'])) ?Studio::$interfaces['interfaces'] :'interfaces';
        if(is_null($d)) $d = S::getApp()->config('tecnodesign', 'cache-dir').'/interface';

        $id = S::slug($this->id, '_', true);
        $f =  $d.'/'.$id.'.yml';
        $f0 = Api::configFile($id, [$f]);
        if(!file_exists($f) || !$this->updated || !($t=strtotime($this->updated)) || $t>filemtime($f)) {
            $a = ['all'=>['interface'=>$id]];
            if($f0 && $f0!==$f) {
                //$a['all']['base'] = $id;
                if(($a0 = Yaml::load($f0)) && isset($a['all']['interface']) && $a['all']['interface']==$id) {
                    $a = $a0;
                }
                unset($a0);
            }
            $a['all'] += $this->asArray('interface');
            if(!isset($a['all']['model'])) {
                $a['all']['model'] = 'Studio\\Model\\Index';
                $a['all']['search'] = ['interface'=>$this->id];
                $a['all']['key'] = 'id';
                $a['all']['options']['scope']['uid'] = ['id'];
            } else {
                $a['all']['model'] = str_replace('\\\\', '\\', $a['all']['model']);
            }

            if(!isset($a['all']['options'])) $a['all']['options'] = [];
            $a['all']['options'] += ['list-parent'=>$n, 'priority'=>$i++, 'index'=>($this->index_interval > 0)];

            if(!S::save($f, S::serialize($a, 'yaml'), true)) {
                $f = null;
            }
        }

        return $f;
    }

    public static function findCacheFile($file)
    {
        $d = S::getApp()->config('tecnodesign', 'cache-dir').'/interface';
        $f =  $d.'/'.S::slug($file, '_', true).'.yml';
        if(file_exists($f)) return $f;
    }

    public function executeImport($Interface=null)
    {
        if(!($p=S::urlParams()) && ($route = App::response('route'))) {
            S::scriptName($route['url']);
            $p = S::urlParams();
        }

        self::$boxTemplate     = $Interface::$boxTemplate;
        self::$headingTemplate = $Interface::$headingTemplate;
        self::$previewTemplate = $Interface::$previewTemplate;
        S::$variables['form-field-template'] = self::$previewTemplate;

        $S = new Interfaces();
        $F = $S->getForm('import');
        $s = '';
        if(($post=App::request('post')) && $F->validate($post)) {
            $d = $F->getData();
            try {
                $m = 'import'.S::camelize($d['_schema_source_type'], true);
                $msg = '';
                if($R = QueryApi::runStatic($d['schema_source'])) {
                    $S->$m($R, $msg);
                    $s .= $msg;
                }
            } catch(\Exception $e) {
                S::log('[ERROR] Could not import '.S::serialize($d, 'json').': '.$e->getMessage()."\n{$e}");
                $msg = '<div class="z-i-msg z-i-error">'.S::t(Api::$importError).'<br />'.S::xml($e->getMessage()).'</div>';
            }
        }

        $s .= (string) $F;

        $r = $Interface['text'];
        $r['preview'] = $s;

        $Interface['text'] = $r;
    }

    public function importSwagger($d, &$msg='')
    {
        $url = $this->schema_source;
        if(isset($d['basePath'])) {
            $surl = parse_url($url);
            if(isset($d['host'])) $surl['host'] = $d['host'];
            $url = S::buildUrl($d['basePath'], $surl);
        }

        // api options
        $api = [];
        if(isset($d['parameters']['perPage'])) {
            $api['limit'] = $d['parameters']['perPage']['name'];
            if(isset($d['parameters']['perPage']['default'])) $api['limitCount'] = (int)$d['parameters']['perPage']['default'];
        }
        if(isset($d['parameters']['page'])) {
            $api['pageOffset'] = $d['parameters']['page']['name'];
            $api['startPage'] = (isset($d['parameters']['perPage']['default'])) ?(int)$d['parameters']['perPage']['default'] :1;
        }
        $api['schema'] = $this->schema_source;

        // import connections
        $cid = null;
        if(isset($d['securityDefinitions'])) {
            foreach($d['securityDefinitions'] as $i=>$o) {
                if(isset($o['type']) && $o['type']!='oauth2') continue;
                $b = ['id'=>$i, 'type'=>'server'];
                $cid = 'server:'.$i;
                if(!($T=Tokens::find($b,1))) {
                    $T = new Tokens($b, true, false);
                }

                $options = $T->options;
                if($options && is_string($options)) $options = S::unserialize($options);
                $options['api_endpoint'] = $url;
                if(isset($o['authorizationUrl'])) $options['authorization_endpoint'] = $o['authorizationUrl'];
                if(isset($o['tokenUrl'])) $options['token_endpoint'] = $o['tokenUrl'];
                if(isset($o['userinfoUrl'])) $options['userinfo_endpoint'] = $o['userinfoUrl'];
                if(isset($o['scopes'])) $api['scopes'] = $o['scopes'];
                $options['api_options'] = $api;

                $T->options = $options;
                $T->save();
                $msg .= '<div class="z-i-msg z-i-success">'.sprintf(S::t(Api::$importSuccess), $T::label(), (string)$T).'</div>';
            }
        }
        // loop through paths and import APIs
        if(isset($d['paths'])) {
            foreach($d['paths'] as $i=>$o) {
                foreach($o as $m=>$ad) {
                    $aid = (isset($ad['operationId'])) ?$ad['operationId'] :$m.':'.$i;
                    $aid = $this->id.':'.$aid;
                    $sc = [];
                    if(isset($ad['responses'][200]['schema'])) $sc = Schema::import($ad['responses'][200]['schema']);
                    if(!isset($sc['properties']) && isset($sc['items']['$ref']['properties'])) {
                        $sc['properties'] = $sc['items']['$ref']['properties'];
                        unset($sc['items']);
                    }
                    if(!is_array($sc)) $sc = [];
                    $sc = ['_options' => ['methods' => [$m] ] ] + $sc;
                    $a = [
                        'id' => $aid,
                        'connection'=>$cid,
                        'source'=>$i,
                    ];
                    if(isset($ad['summary'])) $a['title'] = $ad['summary'];
                    if(isset($ad['parameters'])) $sc['_options']['args'] = $ad['parameters'];
                    $a['schema_data'] = S::serialize($sc, 'json');

                    $A = self::replace($a);
                    $msg .= '<div class="z-i-msg z-i-success">'.sprintf(S::t(Api::$importSuccess), $A::label(), (string)$A).'</div>';
                }
            }
        }

        // combine models?
        return true;
    }
}