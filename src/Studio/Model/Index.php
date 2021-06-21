<?php
/**
 * Studio Index
 * 
 * PHP version 7.2
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
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
        $interfaces;
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

    public static function indexInterface($a, $icn=null, $scope='preview', $keyFormat=true, $valueFormat=true, $serialize=true)
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
            while($count > $offset) {
                $L = $R->fetch($offset, $limit);
                if(!$L) break;

                foreach($L as $i=>$o) {
                    $offset++;
                    try {
                        $pk = $o->getPk();
                        $b = ['interface'=>$id,'id'=>$pk];
                        $d = [
                            'interface'=>$id,
                            'id'=>$pk,
                            'summary'=>(string) $o,
                            'indexed'=>TDZ_TIMESTAMP,
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
                                    $type = (isset($o::$schema->properties[$n]->type)) ?$o::$schema->properties[$n]->type :'text';
                                    if(is_array($v)) $v = S::serialize($v, 'json');
                                    if($type=='text' && strlen($v)>2000) $type='blob';
                                    else if($type=='int' || $type=='float' || $type=='decimal' || ($type=='text' && (is_int($v) || is_float($v)))) $type='number';
                                    else if(substr($type, 0, 4)=='date') $type='date';
                                    $rel = 'Index'.ucwords($type);

                                    if(!isset($d[$rel])) $d[$rel] = [];
                                    $d[$rel][] = ['interface'=>$id, 'id'=>$pk, 'name'=>(string)$n, 'value'=>$v];
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
