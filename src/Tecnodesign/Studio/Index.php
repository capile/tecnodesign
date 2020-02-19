<?php
/**
 * Studio Index
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Studio_Index extends Tecnodesign_Model
{
    public static 
        $schema,
        $interfaces=['Tecnodesign_Studio_Interface'];
    protected $interface, $id, $summary, $indexed, $created, $updated, $IndexProperties, $IndexInterfaces;

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
                Tecnodesign_App::afterRun(array(
                    'callback'=>array('Tecnodesign_Studio_Index', 'reindex'),
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
        if(Tecnodesign_Cache::get('studio/indexing')) return;
        Tecnodesign_Cache::set('studio/indexing', $t=TDZ_TIME, 20);
        foreach(static::$interfaces as $cn) {
            if($is = $cn::find(null, false)) {
                foreach($is as $i=>$a) {
                    static::indexInterface($a, $cn);
                }
            }
        }
        Tecnodesign_Cache::delete('studio/indexing');
        return true;
    }

    public static function indexInterface($a, $icn=null, $scope='preview', $keyFormat=true, $valueFormat=true, $serialize=true)
    {
        $q = null;
        if(isset($a['search'])) $q = $a['search'];
        $cn = $a['model'];
        if($cn=='Tecnodesign_Studio_Index' || (isset($a['index']) && !$a['index']) || !($dbn=$cn::$schema->database) || !($cdb=Tecnodesign_Query::database($dbn)) || (isset($cdb['sync']) && !$cdb['sync']) || (isset($cdb['index']) && !$cdb['index'])) {
            \tdz::log('[INFO] Not indexing '.$cn);
            return;
        }
        $t0 = microtime(true);
        if(tdz::$log>0) tdz::log('[INFO] Indexing: '.$cn.' (time: '.tdz::formatNumber($t0-TDZ_TIME, 5).', mem: '.tdz::formatBytes(memory_get_peak_usage(true)).')');

        $II = Tecnodesign_Studio_IndexInterfaces::replace([
            'interface'=>$a['interface'],
            'label'=>(isset($a['label'])) ?$a['label'] :$cn::label(),
            'model'=>$cn,
            'credential'=>(isset($a['auth'])) ?tdz::serialize($a['auth'], 'json') :tdz::serialize($icn::$authDefault),
            'indexed'=>TDZ_TIMESTAMP,
        ], null, null, true);
        if(!$II) return;

        if($lmod=$II->getOriginal('indexed')) {
            // add last modified to $q
        }

        $url = $a['interface'];
        if($icn && $icn!='Tecnodesign_Studio_Interface') {
            $url = $icn::base().'/'.$url;
        } else if(!$icn) {
            $icn = 'Tecnodesign_Studio_Interface';
        }

        $scope = null;
        if(isset($a['options']['scope']['preview'])) {
            $cn::$schema['scope'] += $a['options']['scope'];
            $scope = 'preview';
        }

        if($scope && is_string($scope)) {
            $pscope = $cn::columns($scope, null, 3, true);
        } else {
            $pscope = $scope;
        }
        if(($R=$cn::find($q, null, $scope)) && $R->count()>0) {
            $count = $R->count();
            $limit = $cn::$queryBatchLimit;
            $offset = 0;
            $fn_created = $fn_updated = null;
            if(isset($cn::$schema->actAs['before-update']['timestampable'])) $fn_updated=$cn::$schema->actAs['before-update']['timestampable'];
            if(isset($cn::$schema->actAs['before-insert']['timestampable'])) {
                $fn_created = ($fn_updated) ?array_diff($cn::$schema->actAs['before-insert']['timestampable'], $fn_updated) :$cn::$schema->actAs['before-insert']['timestampable'];
            }


            if(!$limit) $limit = 500;
            while($count > $offset) {
                $L = $R->getItem($offset, $limit);
                if(!$L) break;

                foreach($L as $i=>$o) {
                    $offset++;

                    $pk = $o->getPk();
                    $P = [];
                    if($preview=$o->asArray($pscope, $keyFormat, $valueFormat, $serialize)) {
                        foreach($preview as $n=>$v) {
                            $P[] = ['interface'=>$a['interface'], 'id'=>$pk, 'name'=>$n, 'value'=>$v];
                        }
                    }
                    $d = [];
                    if($fn_updated) {
                        $o->refresh($fn_updated);
                        foreach($fn_updated as $fn) {
                            if($dt=$o->$fn) {
                                $d['updated'] = $dt;
                                $d['__skip_timestamp_updated'] = true;
                                break;
                            }
                        }
                    }
                    if($fn_created) {
                        $o->refresh($fn_created);
                        foreach($fn_created as $fn) {
                            if($dt=$o->$fn) {
                                $d['created'] = $dt;
                                $d['__skip_timestamp_created'] = true;
                                break;
                            }
                        }
                    }
                    try {
                        static::replace([
                            'interface'=>$a['interface'],
                            'id'=>$pk,
                            'summary'=>(string) $o,
                            'indexed'=>TDZ_TIMESTAMP,
                            'IndexProperties'=>$P,
                        ] +$d);
                        unset($P);
                    } catch (\Exception $e) {
                        tdz::log('[ERROR] There were a few problems while indexing '.$cn.': '.$e->getMessage());
                    }
                }
                if(tdz::$log > 0 && $count > $offset) tdz::log('[INFO] Ongoing index offset for '.$cn.': '.$offset);
            }
            unset($R, $L);
        }

        if(method_exists($cn, 'studioIndex')) {
            $cn::studioIndex($a, $icn, $pscope, $keyFormat, $valueFormat, $serialize);
        }

        $total = $count;

        if($lmod && ($R=static::find(['interface'=>$a['interface'], 'indexed<'=>preg_replace('/\.[0-9]+$/', '', TDZ_TIMESTAMP)])) && $R->count()>0) {
            $count = $R->count();
            $total += $count;
            if(!isset($limit)) $limit = $cn::$queryBatchLimit;
            $offset = 0;
            if(!$limit) $limit = 500;
            while($count > $offset) {
                $L = $R->getItem($offset, $limit);
                if(!$L) break;
                foreach($L as $i=>$o) {
                    $offset++;
                    $o->delete(true);
                }
            }
        }
        if(tdz::$log>0) tdz::log('[INFO] Indexed '.$total.' '.$cn.' in '.tdz::formatNumber(microtime(true)-$t0, 5).'s (mem: '.tdz::formatBytes(memory_get_peak_usage(true)).')');
    }


    public static function checkConnection($conn=null)
    {
        if(!$conn) {
            $conn = static::$schema->database;
        }
        if(!($db=Tecnodesign_Query::database($conn))) {
            if(is_string(Tecnodesign_Studio::$index) && ($db=Tecnodesign_Query::database(Tecnodesign_Studio::$index))) {
                tdz::$database[$conn] = $db;
            } else {
                $dbf=TDZ_VAR.'/studio-index.db';
                tdz::$database[$conn] = $db = ['dsn'=>'sqlite:'.$dbf];
            }
            // check studio and index database, and create tables if required
            $check = [
                'Tecnodesign_Studio_IndexInterfaces',
                'Tecnodesign_Studio_Index',
                'Tecnodesign_Studio_IndexLog',
                'Tecnodesign_Studio_IndexProperties',
                'Tecnodesign_Studio_Config',
                'Tecnodesign_Studio_Entry',
                'Tecnodesign_Studio_Content',
                'Tecnodesign_Studio_ContentDisplay',
                'Tecnodesign_Studio_Permission',
                'Tecnodesign_Studio_Relation',
                'Tecnodesign_Studio_Tag',
                'Tecnodesign_Studio_Group',
                'Tecnodesign_Studio_User',
                'Tecnodesign_Studio_Credential',
            ];

            $H = [];
            $T = [];
            foreach($check as $cn) {
                $dbn = $cn::$schema->database;
                if(!($cdb=Tecnodesign_Query::database($dbn))) continue;
                if(!isset($H[$dbn])) $H[$dbn] = $cn::queryHandler();
                if(!isset($T[$dbn])) {
                    $T[$dbn] = [];
                    foreach(Tecnodesign_Database::getTables($dbn) as $t) {
                        if(is_array($t)) $t = $t['table_name'];
                        $T[$dbn][$t] = $t;
                    }
                }
                if(!isset($T[$dbn][$cn::$schema->tableName])) {
                    if(tdz::$log>0) tdz::log('[INFO] Creating table '.$dbn.'.'.$cn::$schema->tableName);
                    $H[$dbn]->create($cn::$schema);
                }
            }
        }
        return $db;
    }
}
