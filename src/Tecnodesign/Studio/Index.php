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
        $interfaces=['Tecnodesign_Studio_Interface'],
        $indexBatchLimit=500;
    protected $interface, $id, $summary, $indexed, $created, $updated, $expired, $IndexProperties, $IndexInterfaces;

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
                // move to afterRun
                self::reindex();
                /*
                Tecnodesign_App::afterRun(array(
                    'callback'=>array('Tecnodesign_Studio_Index', 'reindex'),
                    'arguments'=>array('sync'),
                ));
                */
            }
        } catch(Exception $e) {
            \tdz::log(__METHOD__.'[ERROR] '.$e->getMessage()."\n$e");
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
    }

    public static function indexInterface($a, $icn=null, $scope='preview', $keyFormat=true, $valueFormat=true, $serialize=true)
    {
        $q = null;
        if(isset($a['search'])) $q = $a['search'];
        $cn = $a['model'];

        $II = Tecnodesign_Studio_IndexInterfaces::replace([
            'interface'=>$a['interface'],
            'label'=>(isset($a['label'])) ?$a['label'] :$cn::label(),
            'model'=>$cn,
            'credential'=>(isset($a['auth'])) ?tdz::serialize($a['auth'], 'json') :tdz::serialize($icn::$authDefault),
            'indexed'=>TDZ_TIMESTAMP,
        ], null, null, false);
        if(!$II) return;

        if($lmod=$II->getOriginal('indexed')) {
            // add last modified to $q
        }

        $url = $a['interface'];
        if($icn && $icn!='Tecnodesign_Studio_Interface') {
            $url = $icn::base().'/'.$i;
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
            $limit = (property_exists($cn, 'indexBatchLimit')) ?$cn::$indexBatchLimit :static::$indexBatchLimit;
            $offset = 0;
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
                    try {
                        static::replace([
                            'interface'=>$a['interface'],
                            'id'=>$pk,
                            'summary'=>(string) $o,
                            'indexed'=>TDZ_TIMESTAMP,
                            'IndexProperties'=>$P,
                        ]);
                    } catch (\Exception $e) {
                        tdz::debug(__METHDO__.' '.$e->getMessage()."\n{$e}", $P, var_export($preview, true), var_export($o, true));
                    }
                }
            }
            unset($R, $L);
        }

        if($lmod && ($R=static::find(['interface'=>$a['interface'], 'indexed<'=>TDZ_TIMESTAMP])) && $R->count()>0) {
            $count = $R->count();
            if(!isset($limit)) $limit = (property_exists($cn, 'indexBatchLimit')) ?$cn::$indexBatchLimit :static::$indexBatchLimit;
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

        $II->save();
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
                if(!file_exists($dbf)) {
                    $H = static::queryHandler();
                    $H->create(Tecnodesign_Studio_IndexInterfaces::$schema);
                    $H->create(Tecnodesign_Studio_Index::$schema);
                    $H->create(Tecnodesign_Studio_IndexLog::$schema);
                    $H->create(Tecnodesign_Studio_IndexProperties::$schema);
                }
            }
        }
        return $db;
    }
}
