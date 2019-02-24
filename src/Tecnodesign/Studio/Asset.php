<?php
/**
 * Static file rendering and optimization
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
        ),
        $optimizePatterns=array(
            '#<script [^>]*src="([^"\?\:]+)"[^>]*>\s*</script>#' => 'js',
            '#<link [^>]*type="text/css"[^>]*href="([^"\?\:]+)"[^>]*>#' => 'css',
        ),
        $optimizeTemplates=array(
            'js'  => '<script type="text/javascript" async="async" src="%s"></script>',
            'css' => '<link rel="stylesheet" type="text/css" href="%s" />',
        ),
        $optimizeExtensions=array(
            'less'=>'css',
            'scss'=>'css',
        );


    protected $source, $output, $root, $format, $optimize=true;

    public function __construct($options=array())
    {
        if($options) {
            foreach($options as $n=>$o) {
                if(property_exists($this, $n)) $this->$n = $o;
                unset($n, $o);
            }
        }
    }

    public function render($output=null, $exit=true)
    {
        if($this->format && method_exists($this, $m='render'.ucfirst($this->format))) {
            $r = $this->$m($output, $exit);
        } else if($this->source && $this->output) {
            $r = $this->build();
        } else if($output) {
            $r = '';
            if(is_array($this->source)) {
                foreach($this->source as $i=>$o) {
                    $r .= file_get_contents($o);
                }
            } else {
                $r = file_get_contents($this->source);
            }
            return tdz::output($r, $this->getFormat(), $exit);
        }
        unset($m);

        if($output && file_exists($this->output)) {
            tdz::download($this->output, $this->getFormat(), null, 0, false, false, $exit);
        }

        return $r;
    }

    public function getFormat()
    {
        if(isset(tdz::$formats[$this->extension])) {
            return tdz::$formats[$this->extension];
        } else if($this->output) {
            return tdz::fileformat($this->output);
        }
    }

    public function build($files=null, $outputFile=null)
    {
        $optimize = $this->optimize;
        if($optimize && (!isset(tdz::$minifier[$this->format]) || !file_exists($jar=dirname(TDZ_ROOT).'/yuicompressor/yuicompressor.jar'))) $optimize = false;

        if(!$outputFile) $outputFile = $this->output;

        $tempnam = tempnam(dirname($outputFile), '._'.basename($outputFile));

        if(!$files) {
            $files = $this->source;
        }
        if(!is_array($files)) {
            $files = array($files);
        }
        if($optimize) {
            // try yui compressor
            $cacheDir = ($app=tdz::getApp()) ?$app->tecnodesign['cache-dir'] :null;
            if(!$cacheDir) $cacheDir = TDZ_VAR.'/cache/minify';
            if(!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            if(isset(tdz::$minifier[$this->format])) {
                $cmd = sprintf(tdz::$minifier[$this->format], implode(' ',$files), $tempnam);
            } else {
                $cmd = tdz::$paths['cat'].' '.implode(' ',$files).' | '.tdz::$paths['java'].' -jar '.escapeshellarg($jar).' --nomunge --type '.$this->format.' -o '.$tempnam;
            }
            exec($cmd, $cmdoutput, $ret);
            if(file_exists($tempnam) && filesize($tempnam)>0) {
                rename($tempnam, $outputFile);
                chmod($outputFile, 0666);
                unset($tempnam, $cacheDir);
                return true;
            } else {
                tdz::log('[WARN] Minifying script failed: '.$cmd, $cmdoutput);
            }
            unset($cmdoutput, $ret, $cacheDir);
        }

        foreach($files as $i=>$o) {
            file_put_contents($tempnam, file_get_contents($o), FILE_APPEND);
        }
        rename($tempnam, $outputFile);
        chmod($outputFile, 0666);
        unset($tempnam);
        return file_exists($outputFile);
    }

    public function renderCss($output=null, $exit=true)
    {
        $r = array();
        $f = is_array($this->source) ?$this->source :[$this->source];

        foreach($f as $i=>$o) {
            if(!file_exists($o)) {

            } else if(substr($o, -5)==='.less') {
                if(!isset($r['less'])) $r['less']=array();
                $r['less'][$o]=filemtime($o);
            } else if(substr($o, -5)==='.scss') {
                if(!isset($r['scss'])) $r['scss']=array();
                $r['scss'][$o]=filemtime($o);
            } else {
                $r[] = $o;
            }
        }

        $cacheDir = ($app=tdz::getApp()) ?$app->tecnodesign['cache-dir'] :null;
        if(!$cacheDir) $cacheDir = TDZ_VAR.'/cache/minify';
        if(isset($r['less'])) {
            $tmpCss = $cacheDir.'/less-'.md5(implode(':',array_keys($r['less']))).'.css';
            if(!file_exists($tmpCss) || filemtime($tmpCss)<max($r['less'])) {
                $this->parseLess(array_keys($r['less']), $tmpCss);
            }
            $r['less'] = $tmpCss;
        }

        return $this->build($r);
    }

    public function parseLess($fs, $outputFile)
    {
        static $parser;
        if(!$parser && class_exists('lessc')) {
            $parser = new lessc();
            $parser->setVariables(array('assets-url'=>escapeshellarg(tdz::$assetsUrl), 'studio-url'=>escapeshellarg(Tecnodesign_Studio::$home)));
            $parser->registerFunction('dechex', function($a){
                return dechex($a[1]);
            });
        }

        if(!$parser) return $this->build($fs, $outputFile);

        if(is_array($fs) && count($fs)>1) {
            $importDir = array();
            $s = '';
            foreach($fs as $i=>$o) {
                if(!in_array($d=dirname($o), $importDir)) $importDir[] = $d;
                unset($d);
                $s .= '@import '.escapeshellarg(basename($o)).";\n";
                unset($fs[$i], $i, $o);
            }
            $fs = $s;
            $save = true;
            unset($s);
        } else {
            if(is_array($fs)) $fs = array_shift($fs);
            $importDir = array(dirname($fs));
            $save = false;
        }

        if($this->root && !in_array($this->root, $importDir)) array_unshift($importDir, $this->root);
        if(is_dir($d=TDZ_DOCUMENT_ROOT.tdz::$assetsUrl.'/css/') && !in_array($d, $importDir)) array_unshift($importDir, $d);
        $parser->setImportDir($importDir);
        unset($importDir);

        if($save) {
            tdz::save($outputFile, $parser->compile($fs));
        } else {
            $parser->checkedCompile($fs, $outputFile);
        }

    }

    public function parseScss($fs, $outputFile)
    {
        static $parser;
        if(!$parser && class_exists('scssc')) {
            $parser = new scssc();
            //$parser->setVariables(array('assets-url'=>'"'.tdz::$assetsUrl.'"'));
            $parser->registerFunction('dechex', function($a){
                return dechex($a[1]);
            });
        }

        if(!$parser) return $this->build($fs, $outputFile);

        if(is_array($fs) && count($fs)>1) {
            $importDir = array();
            $s = '';
            foreach($fs as $i=>$o) {
                if(!in_array($d=dirname($o), $importDir)) $importDir[] = $d;
                unset($d);
                $s .= '@import '.escapeshellarg($o).";\n";
                unset($fs[$i], $i, $o);
            }
            $fs = $s;
            unset($s);
        } else {
            if(is_array($fs)) $fs = array_shift($fs);
            $importDir = array(dirname($fs));
            $fs = '@import '.escapeshellarg($fs);
        }

        if($this->root!=TDZ_DOCUMENT_ROOT && is_dir($d=TDZ_DOCUMENT_ROOT.tdz::$assetsUrl.'/css/') && !in_array($s, $importDir)) $importDir[] = $d;
        if($this->root && !in_array($this->root, $importDir)) $importDir[] = $this->root;
        $parser->setImportPaths($importDir);
        unset($importDir);

        tdz::save($outputFile, $parser->compile($fs));
    }


    /**
     * Compress Javascript & CSS
     */
    public static function minify($s, $root=false, $compress=true, $before=true, $raw=false, $output=false)
    {
        if($root===false) {
            $root = TDZ_DOCUMENT_ROOT;
        }

        $assets = array(); // assets to optimize
        $r = ''; // other metadata not to messed with (unparseable code)
        $f = (!is_array($s))?(array($s)):($s);

        foreach($f as $i=>$url) {
            if(strpos($url, '<')!==false) {
                // html code, must match a pattern
                foreach(static::$optimizePatterns as $re=>$ext) {
                    if(preg_match_all($re, $url, $m) && $m[0]) {
                        foreach($m[1] as $i=>$o) {
                            if(file_exists($f=$root.$o)) {
                                if(!isset($assets[$ext])) $assets[$ext]=array();
                                $assets[$ext][$f] = filemtime($f);
                                if($url===$m[0][$i]) $url = '';
                                else $url=str_replace($m[0][$i], '', $url);
                            }
                            unset($i, $o, $f);
                        }
                    }
                    unset($m, $re, $ext);
                    if(!$url) break;
                }
                if($url) $r .= $url;
            } else if (preg_match('/\.([a-z0-9]+)(\?|\#|$)/i', $url, $m)) {
                if (isset(static::$optimizeExtensions[$m[1]])) $ext = static::$optimizeExtensions[$m[1]];
                else if (isset(static::$optimizeTemplates[$m[1]])) $ext = $m[1];
                else continue;

                if((isset($m[2]) && $m[2]) || preg_match('#^(http:)?//#', $url) || !(file_exists($f=$root.$url) || (file_exists($f=$url) && (substr($url, 0, strlen($root))==$root || substr($url, 0, strlen(TDZ_ROOT))==TDZ_ROOT )) )) {
                    // not to be compressed, just add to output
                    $r .= sprintf(static::$optimizeTemplates[$ext], tdz::xml($url));
                } else {
                    if(!isset($assets[$ext])) $assets[$ext]=array();
                    $assets[$ext][$f] = filemtime($f);
                }
                unset($f, $m);
            }
        }
        $s = '';
        $updated = true;
        foreach($assets as $ext=>$fs) {
            if(is_string($output)) {
                $outputUrl = $output;
                if(substr($output, -1*(strlen($ext) + 1))!=='.'.$ext) {
                    $outputUrl .= '.'.$ext;
                }
            } else {
                $outputUrl = md5(implode(':',array_keys($fs))).'.'.$ext;
            }

            if(strpos($outputUrl, '/')===false) {
                $outputUrl = tdz::$assetsUrl.'/'.$outputUrl;
                $outputFile = $root.$outputUrl;
            } else if($output && substr($outputUrl, 0, strlen($root))==$root) {
                $outputFile = $outputUrl;
            } else {
                $outputFile = $root.'/'.$outputUrl;
            }

            if(!file_exists($outputFile) || filemtime($outputFile)<max($fs)) {
                $A = new Tecnodesign_Studio_Asset(array(
                    'source'=>array_keys($fs),
                    'output'=>$outputFile,
                    'optimize'=>$compress,
                    'format'=>$ext,
                    'root'=>$root,
                ));

                if(!is_dir($d=dirname($outputFile))) mkdir($d, 0777, true);
                unset($d);
                $add = $A->render(false);
                unset($A);
            } else {
                $add = true;
                $updated = false;
            }

            if($raw) {
                $s .= file_get_contents($outputFile);
            } else if($add) {
                $s .= sprintf(static::$optimizeTemplates[$ext], $outputUrl.'?'.date('YmdHis', filemtime($outputFile)));
            }


            unset($assets[$ext], $add, $outputUrl, $outputFile, $fs, $ext);
        }

        if($before) $r = $s.$r;
        else $r .= $s;

        if($raw) {
            return $s;
        } else if($output===true) {
            return $updated;
        }
        unset($s);

        return $r;
    }
 

    public static function file($url, $root=null)
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
