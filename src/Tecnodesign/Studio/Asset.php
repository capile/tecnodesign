<?php
/**
 * Static file rendering
 *
 * PHP version 5.3
 *
 * @category  Asset
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Studio_Asset
{
    /**
     * Configurable behavior
     * This is only available for customizing Studio, please use the tdzAsset class
     * within your lib folder (not TDZ_ROOT!) or .ini files
     */
    const 
        OPTIMIZE='/_',  // URL patter prefix for checking URLs eligible for optimization, use @ to disable
        URL='/_';       // Where (relative to the document root), the optimized files should be stored 

    public static 
        $optimizeActions=array(
          'min'=>array(
            'method'=>'minify',
            'extensions'=>array('css', 'js'),
            'combine'=>true,
            'alt-extension'=>array('css'=>'less'),
          ),
          'icon'=>array(
            'method'=>'resize',
            'extensions'=>array('', 'jpg', 'jpeg', 'png', 'gif'),
            'arguments'=>array('width'=>100,'height'=>25,'crop'=>true),
            'combine'=>false,
          ),
        );

    public function file($url, $root=null)
    {
        $p = Tecnodesign_Studio::page($url);
        if($p) {
            $d = TDZ_VAR.'/'.Tecnodesign_Studio::$uploadDir;
            if(file_exists($file=$d.'/'.$p->source)) {
                unset($p, $d);
                return $file;
            }
        }
        unset($p);
        if(is_null($root)) $root = Tecnodesign_Studio::$app->tecnodesign['document-root'];
        if(file_exists($file=$root.$url)) {
            unset($root);
            return $file;
        }
        return false;
    }

    public static function run($url=null, $root=null, $optimize=null)
    {
        if(Tecnodesign_Studio::$cacheTimeout) tdz::cacheControl('public', Tecnodesign_Studio::$staticCache);
        if(is_null($url)) $url = tdz::scriptName();
        if(is_null($root)) $root = Tecnodesign_Studio::$app->tecnodesign['document-root'];
        if(is_file($root.$url)) {
            tdz::download($root.$url, tdz::fileFormat($url), null, 0, false, false, false);
            Tecnodesign_Studio::$app->end();
        }
        if(is_null($optimize)) $optimize = strncmp($url, tdzAsset::OPTIMIZE, strlen(tdzAsset::OPTIMIZE))===0;

        if(!$optimize 
            || !preg_match('/^(.*\.)([^\.\/]+)\.([^\.\/]+)$/', $url, $m) 
            || !isset(tdzAsset::$optimizeActions[$m[2]]) 
            || !(in_array($m[3], tdzAsset::$optimizeActions[$m[2]]['extensions']) || in_array('*', tdzAsset::$optimizeActions[$m[2]]['extensions']))
        ) {
            return false;
        }
        $u = $m[1].$m[3];
        if(!($file=tdzAsset::file($u, $root))) {
            if(isset(tdzAsset::$optimizeActions[$m[2]]['alt-extension'][$m[3]])) {
                $u = $m[1].tdzAsset::$optimizeActions[$m[2]]['alt-extension'][$m[3]];
                $file=tdzAsset::file($u, $root);
            }
            if(!$file) return false;
        }
        unset($u);
        $method = tdzAsset::$optimizeActions[$m[2]];
        $ext = strtolower($m[3]);
        $result=null;
        if($method['method']=='resize') {
            $result = tdz::resize($file, $method['params']);
        } else if($method['method']=='minify') {
            $opt = array('/'.basename($file));
            $d = dirname($file);
            $T = filemtime($file);
            if($qs=Tecnodesign_App::request('query-string')) {
                foreach(explode(',', $qs) as $l) {
                    $o = '/'.basename($l).'.'.$ext;
                    if(file_exists($d.$o) || ($ext=='css' && file_exists($d.($o='/'.basename($l).'.less')))) {
                        $opt[]=$o;
                        $t = filemtime($d.$o);
                        if($t>$T)$T=$t;
                    }
                    unset($o, $l, $t);
                }
            }
            $cache = Tecnodesign_Cache::cacheDir().'/assets/'.md5($d.':'.implode(',', $opt)).'.'.$ext;
            if(!file_exists($cache) || filemtime($cache)<$T) {
                tdz::minify($opt, $d, true, true, false, $cache);
            }
            unset($opt, $d, $h);
            if(file_exists($cache) && filemtime($cache)>filemtime($file)) {
                $R = $cache;
                //$result = file_get_contents($cache);
            } else {
                $R = $file;
                //$result = file_get_contents($file);
            }
            unset($cache, $file);
        } else {
            $args=array($file);
            if(isset($method['params'])) {
                $args[] = $method['params'];
            } else if(isset($method['arguments'])) {
                $args = array_merge($args, $method['arguments']);
            }
            $result = call_user_func_array(array('tdz', $method['method']), $args);
            unset($args);
        }
        if($result) {
            tdz::output($result, tdz::fileFormat($url), false);
        } else if(isset($R)) {
            tdz::download($R, null, null, 0, false, false, false);
            unset($R);
        }
        unset($result, $file, $method, $ext);
        Tecnodesign_Studio::$app->end();
    }
}
