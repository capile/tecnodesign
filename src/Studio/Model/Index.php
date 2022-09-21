<?php
/**
 * Studio Index
 * 
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
use Tecnodesign_App as App;
use Tecnodesign_Studio as Studio;
use Tecnodesign_Studio_Interface as Api;
use Tecnodesign_Cache as Cache;
use Tecnodesign_Query as Query;
use Tecnodesign_Database as Database;
use tdz as S;

class Index extends Model
{
    public static 
        $schema,
        $interfaces,
        $pathSeparator='/';
    protected $interface, $id, $summary, $indexed, $created, $updated, $IndexText, $IndexDate, $IndexBool, $IndexNumber, $IndexBlob, $IndexInterfaces;

    /**
     * Verifies if the Tecnodesign_Model $M, from $interface is indexed and newer than the index
     * If not, schedule a reindex
     */
    public static function check($M, $interface, $updated='updated')
    {
        if(!($id = implode('-', $M->getPk(true)))) return;
        try {
            $lmod = null;
            if($updated && ($lmod = $M->$updated)) $lmod = strtotime($lmod);
            if(!static::checkConnection()) return false;
            if(!($I=static::find(['interface'=>$interface, 'id'=>$id],1,['indexed'])) || ($lmod && strtotime($I->updated)<$lmod)) {
                App::afterRun(array(
                    'callback'=>array(get_called_class(), 'reindex'),
                ));
            }
        } catch(Exception $e) {
            \tdz::log('[ERROR] '.$e->getMessage()."\n$e");
        }
    }

    public static function reindex()
    {
        // studio indexing
        if(!static::checkConnection()) return;

        $index = [];
        if(App::request('shell') && ($a = App::request('argv'))) {
            if(static::$interfaces) {
                $index=array_intersect(static::$interfaces, $a);
                if(!$index) $index = [];
                else $a = array_diff($a, $index);
            }

            if($a) {
                $L = Interfaces::find(['id'=>$a],null,null,false);
                if($L) $index = array_merge($index, $L);
                unset($L);
            }
        } else {
            $index = static::$interfaces;
            $L = Interfaces::find(['index_interval>'=>0],null,null,false);
            if($L) $index = array_merge($index, $L);
            unset($L);
        }

        //if(Cache::get('studio/indexing')) return;
        //Cache::set('studio/indexing', $t=TDZ_TIME, 20);
        $q=null;
        foreach($index as $a) {
            $ref = null;
            if(is_string($a)) {
                $ref = $a;
                $a = Api::find($a, false);
                if(!$a) continue;
            }
            static::indexInterface($a, $ref);
        }
        Cache::delete('studio/indexing');
        return true;
    }

    public static function indexInterface($a, $icn=null, $scope='preview', $keyFormat=false, $valueFormat=false, $serialize=false)
    {
        $q = null;
        if(isset($a['search']) && $a['search']) $q = $a['search'];

        $II = null;
        if(is_object($a)) {
            $II = $a;
            $id = $a->id;
            $cn = $a->model();
        } else if(isset($a['model'])) {
            $id = $a['interface'];
            $cn = $a['model'];
        } else {
            $cn = null;
            $id = null;
        }

        if(!$cn || !$id) return;
        $t0 = microtime(true);

        if(S::$log>0) S::log('[INFO] Indexing: '.$id.' (time: '.S::formatNumber($t0-TDZ_TIME, 5).', mem: '.S::formatBytes(memory_get_peak_usage(true)).')');

        if(!$II) $II = Interfaces::find(['id'=>$id],1);
        $lmod = null;

        if(!$II) {
            $II = Interfaces::replace([
                'id'=>$id,
                'label'=>(isset($a['label'])) ?$a['label'] :$cn::label(),
                'model'=>$cn,
                'credential'=>(isset($a['auth'])) ?tdz::serialize($a['auth'], 'json') :S::serialize(Api::$authDefault),
                'indexed'=>TDZ_TIMESTAMP,
            ], null, null, true);
            if(!$II) return;
        } else {
            $lmod = strtotime($II->indexed);
            $II->indexed = TDZ_TIMESTAMP;
            $II->save();
        }

        $q = ($q) ?['where'=>$q] :[];
        $scope = null;
        if(isset($a['options']['scope'])) {
            $cn::$schema->scope += $a['options']['scope'];
        }

        if(isset($cn::$schema->scope['index'])) $scope = 'index';
        else if(isset($cn::$schema->scope['preview'])) $scope = 'preview';

        if($scope) $q['scope'] = $scope;

        if(isset($a['options']['group-by'])) {
            $q['groupBy'] = $a['options']['group-by'];
        }

        if($scope && is_string($scope)) {
            $pscope = $cn::columns($scope, null, 3, true);
        } else {
            $pscope = $scope;
        }

        $count = null;
        $R = $cn::query($q);
        if($R) {
            $countable = (method_exists($R, 'config')) ?$R->config('countable') :true;
            $count = ($countable) ?$R->count() :10000;
            $limit = $cn::$queryBatchLimit;
            $offset = 0;
            $fn_created = $fn_updated = null;
            if(isset($cn::$schema->actAs['before-update']['timestampable'])) $fn_updated=$cn::$schema->actAs['before-update']['timestampable'];
            else if(isset($a['options']['last-modified'])) $fn_updated = [$a['options']['last-modified']];
            if(isset($cn::$schema->actAs['before-insert']['timestampable'])) {
                $fn_created = ($fn_updated) ?array_diff($cn::$schema->actAs['before-insert']['timestampable'], $fn_updated) :$cn::$schema->actAs['before-insert']['timestampable'];
            }

            if(!$limit) $limit = 100;
            $pkid = $cn::pk();
            $ppk = ['id', 'uid', 'uuid'];
            while($count > $offset) {
                $L = $R->fetch($offset, $limit);
                if(!$L) break;

                foreach($L as $i=>$o) {
                    $offset++;
                    try {
                        if(!$pkid) {
                            foreach($ppk as $pkid) {
                                if($pk=$o->$pkid) {
                                    break;
                                }
                                $pkk = strtoupper($pkid);
                                if($pk=$o->$pkid) {
                                    break;
                                }
                                $pkid = null;
                            }
                        } else if($pkid && is_string($pkid)) {
                            $pk = $o->$pkid;
                        } else {
                            $pk = $o->getPk();
                        }
                        if(is_null($pk) || $pk==='' || $pk===false) {
                            throw new \Exception('No primary key to index.');
                        }
                        $b = ['interface'=>$id,'id'=>$pk];
                        $d = [
                            'interface'=>$id,
                            'id'=>$pk,
                            'summary'=>(string) $o,
                            'indexed'=>TDZ_TIMESTAMP,
                            'IndexBlob'=>[],
                            'IndexBool'=>[],
                            'IndexDate'=>[],
                            'IndexNumber'=>[],
                            'IndexText'=>[],
                        ];
                        if($fn_updated) {
                            $o->refresh($fn_updated);
                            $cmod = null;
                            foreach($fn_updated as $fn) {
                                if(($dt=$o->$fn) && ($cmod=strtotime($dt))) {
                                    $d['updated'] = $dt;
                                    $d['__skip_timestamp_updated'] = true;
                                    break;
                                }
                            }
                            if($cmod && $lmod && $cmod<$lmod && ($I=static::find($b,1))) {
                                $I->__skip_timestamp_updated = true;
                                $I['indexed'] = TDZ_TIMESTAMP;
                                $I->save();
                                unset($I);
                                continue;
                            }
                        }
                        if($fn_created) {
                            $o->refresh($fn_created);
                            foreach($fn_created as $fn) {
                                if(($dt=$o->$fn) && strtotime($dt)) {
                                    $d['created'] = $dt;
                                    $d['__skip_timestamp_created'] = true;
                                    break;
                                }
                            }
                        }
                        $P = [];
                        if($preview=$o->asArray($pscope, $keyFormat, $valueFormat, $serialize)) {
                            foreach($preview as $n=>$v) {
                                if(!S::isempty($n)) {
                                    self::propToRel($v, $n, (isset($o::$schema->properties[$n])) ?$o::$schema->properties[$n] :null, $d, $b);
                                }
                            }
                        }
                        static::replace($d);
                        unset($P);
                    } catch (\Exception $e) {
                        S::log('[ERROR] There were a few problems while indexing '.$cn.': '.$e->getMessage());
                    }
                }
                if(S::$log > 0 && $count > $offset) S::log('[INFO] Ongoing index offset for '.$cn.': '.$offset);
            }
            unset($R, $L);
        }

        if(method_exists($cn, 'studioIndex')) {
            $cn::studioIndex($a, $icn, $pscope, $keyFormat, $valueFormat, $serialize);
        }

        $total = $offset;

        if($total && $lmod && ($R=static::find(['interface'=>$id, 'indexed<'=>preg_replace('/\.[0-9]+$/', '', TDZ_TIMESTAMP)])) && $R->count()>0) {
            $count = $R->count();
            $total += $count;
            if(!isset($limit)) $limit = $cn::$queryBatchLimit;
            if(!$limit) $limit = 500;
            $offset = 0;
            while($count > $offset) {
                $L = $R->getItem($offset, $limit);
                if(!$L) break;
                foreach($L as $i=>$o) {
                    $offset++;
                    $o->delete(true);
                }
            }
        }
        if(S::$log>0) S::log('[INFO] Indexed '.$total.' '.$cn.' in '.S::formatNumber(microtime(true)-$t0, 5).'s (mem: '.S::formatBytes(memory_get_peak_usage(true)).')');
    }

    public function getSource($format='array', $sort=null)
    {
        $r = [];
        foreach(self::$schema->relations as $rn=>$rd) {
            if(substr($rn, 0, 5)!=='Index') continue;
            $R = null;
            if(isset($this->$rn)) $R = $this->$rn;
            else $R = $this->getRelation($rn, null, null, false);
            if($R && is_object($R)) $R = $R->getItems();
            if($R) {
                foreach($R as $i=>$o) {
                    if(!$o->isDeleted()) {
                        self::expandValues($o->name, $o->value, $r);
                    }
                    unset($i, $o);
                }
            }
            unset($R, $rn, $rd);
        }

        if($sort) ksort($r);

        return ($format=='json') ?S::serialize($r, 'json') :$r;
    }

    public static function expandValues($name, $value, &$r=[])
    {
        if(strpos($name, self::$pathSeparator)!==false) {
            list($pre, $name) = explode(self::$pathSeparator, $name, 2);
            if(is_numeric($pre) && (int)$pre==$pre) $pre = (int) $pre;
            if(!isset($r[$pre])) $r[$pre] = [];
            return self::expandValues($name, $value, $r[$pre]);
        } else {
            $r[$name] = $value;
        }

        return $r;
    }

    public static function propToRel($value, $name, $schema, &$output=[], $base=[])
    {
        static $map = ['string'=>'text', 'int64'=>'number', 'int'=>'number', 'float'=>'number', 'decimal'=>'number', 'char'=>'text', 'varchar'=>'text', 'nvarchar'=>'text', 'bit'=>'bool', 'boolean'=>'bool'];
        static $skip = ['_new', '_original', '_update', '_delete', '_relation', '_query', '_connected', '_p', '_forms'];

        if(in_array($name, $skip)) return $output;
        if($schema && isset($schema['format'])) {
            $type = $schema['format'];
        } else if($schema && isset($schema['type'])) {
            $type = $schema['type'];
        } else {
            $type = 'text';
        }
        if(is_array($value)) {
            $subs = ($schema && $type=='object' && isset($schema['properties'])) ?$schema['properties'] :null;
            $fd = (!$subs && $schema && $type=='array' && isset($schema['items'])) ?$schema['items'] :null;
            foreach($value as $k=>$v) {
                if($subs) {
                    $fd = (isset($subs[$k])) ?$subs[$k] :null;
                }
                self::propToRel($v, $name.self::$pathSeparator.$k, $fd, $output, $base);
            }
            return $output;
        }

        if(isset($map[$type])) $type = $map[$type];
        else if($type=='text' && is_string($value) && strlen($value)>2000) $type='blob';
        else if(substr($type, 0, 4)=='date') $type='date';
        else if(is_int($value) || is_float($value)) $type = 'number';

        $rel = 'Index'.ucwords($type);
        if(!isset($output[$rel])) $rel = 'IndexText';
        $output[$rel][] = $base + ['name'=>(string)$name, 'value'=>$value];

        return $output;
    }

    public function expandProperties($arr, $prefix=null)
    {
        if(is_array($arr)) {
            $r = [];
            foreach($arr as $k=>$v) {
                $n = ($prefix) ?$prefix.self::$pathSeparator.$k :$k;
                if(is_array($v)) $r += self::expandProperties($v, $n);
                else $r[$n] = $v;
            }

            return $r;
        } else {
            return $arr;
        }
    }


    public static function checkConnection($conn=null)
    {
        if(!$conn) {
            $conn = static::$schema->database;
        }
        if(!($db=Query::database($conn))) {
            if(is_string(Studio::$index) && ($db=Query::database(Studio::$index))) {
                S::$database[$conn] = $db;
            } else {
                S::log('[WARNING] Index database is not available. '.$conn);
                return false;
            }
        }
        // check studio and index database, and create tables if required
        $check = Studio::enabledModels();
        $H = [];
        $T = [];
        foreach($check as $cn) {
            $dbn = $cn::$schema->database;
            if(!($cdb=Query::database($dbn))) continue;
            if(!isset($H[$dbn])) $H[$dbn] = $cn::queryHandler();
            if(!isset($T[$dbn])) {
                $T[$dbn] = [];
                foreach(Database::getTables($dbn) as $t) {
                    if(is_array($t)) $t = $t['table_name'];
                    $T[$dbn][$t] = $t;
                }
            }
            if(!isset($T[$dbn][$cn::$schema->tableName])) {
                if(S::$log>0) S::log('[INFO] Creating table '.$dbn.'.'.$cn::$schema->tableName);
                try {
                    $H[$dbn]->create($cn::$schema);
                } catch(\Exception $e) {
                    S::log('[WARNING] Error while creating table: '.$e->getMessage(), $H[$dbn]->lastQuery());
                }
            }
            if(isset($cn::$schema->actAs['after-insert']['versionable']) && !isset($T[$dbn][$cn::$schema->tableName.'_version'])) {
                $cn::$schema->tableName .= '_version';
                if(S::$log>0) S::log('[INFO] Creating table '.$dbn.'.'.$cn::$schema->tableName);
                $cn::$schema->properties['version']->primary = true;
                $idx = [];
                foreach($cn::$schema->properties as $fn=>$fd) {
                    if($fd->index) {
                        $idx[$fn] = $fd->index;
                        $fd->index = null;
                    }
                    unset($fn, $fd);
                }
                try {
                    $H[$dbn]->create($cn::$schema);
                } catch(\Exception $e) {
                    S::log('[WARNING] Error while creating table: '.$e->getMessage(), $H[$dbn]->lastQuery());
                }
                $cn::$schema->tableName = substr($cn::$schema->tableName, 0, strlen($cn::$schema->tableName) - 8);
                $cn::$schema->properties['version']->primary = null;
                foreach($idx as $fn=>$fd) {
                    $cn::$schema->properties[$fn]->index = $fd;
                    unset($idx[$fn], $fn, $fd);
                }
            }
        }

        return $db;
    }
}
