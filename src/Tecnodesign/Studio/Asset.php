<?php
/**
 * Static file rendering and optimization
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Studio_Asset
{
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
        ),
        $outputToRoot = true,
        $assetVariables = [
            'icon-font' => 'fontawesome',
            'icon-font-name' => 'FontAwesome',
            'icon-font-size' => '1em',
            /*
            'icon-font' => 'material-icons',
            'icon-font-name' => 'Material Icons',
            'icon-font-size' => '1.6em',
            */
        ],
        $importDir = [];


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
        $shell = $optimize = $this->optimize;
        if($optimize && (!isset(tdz::$minifier[$this->format]) || !file_exists($jar=dirname(TDZ_ROOT).'/yuicompressor/yuicompressor.jar'))) $shell = false;

        if(!$outputFile) $outputFile = $this->output;

        $tempnam = tempnam(dirname($outputFile), '._'.basename($outputFile));

        if(!$files) {
            $files = $this->source;
        }
        if(!is_array($files)) {
            $files = array($files);
        }
        if($optimize) {
            $cmdoutput=null;
            $cacheDir = ($app=tdz::getApp()) ?$app->tecnodesign['cache-dir'] :null;
            if(!$cacheDir) $cacheDir = TDZ_VAR.'/cache/minify';
            if(!is_dir($cacheDir)) {
                mkdir($cacheDir, 0777, true);
            }
            if($shell) {
                if(isset(tdz::$minifier[$this->format])) {
                    $cmd = sprintf(tdz::$minifier[$this->format], implode(' ',$files), $tempnam);
                } else {
                    $cmd = tdz::$paths['cat'].' '.implode(' ',$files).' | '.tdz::$paths['java'].' -jar '.escapeshellarg($jar).' --nomunge --type '.$this->format.' -o '.$tempnam;
                }
                exec($cmd, $cmdoutput, $ret);
            } else {
                $Min = null;
                $add = '';
                foreach($files as $f) {
                    if(strpos($f, '.min.'.strtolower($this->format))) {
                        $add .= "\n".file_get_contents($f);
                    } else if($Min===null) {
                        $cmd = 'MatthiasMullie\\Minify\\'.strtoupper($this->format);
                        $Min = new $cmd($f);
                    } else {
                        $Min->add($f);
                    }
                }
                if($Min) {
                    tdz::save($tempnam, $Min->minify(null, [dirname($outputFile), TDZ_DOCUMENT_ROOT]).$add);
                    unset($Min);
                } else if($add) {
                    tdz::save($tempnam, $add);
                }
            }
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
        if(!is_dir($cacheDir)) {
            mkdir($cacheDir, 0777, true);
        }
        if(isset($r['less'])) {
            $tmpCss = $cacheDir.'/less-'.md5(tdz::$assetsUrl.'/'.implode(':',array_keys($r['less']))).'.css';
            if(!file_exists($tmpCss) || filemtime($tmpCss)<max($r['less'])) {
                $this->parseLess(array_keys($r['less']), $tmpCss);
            }
            $r['less'] = $tmpCss;
        }

        if(isset($r['scss'])) {
            $tmpCss = $cacheDir.'/scss-'.md5(tdz::$assetsUrl.'/'.implode(':',array_keys($r['scss']))).'.css';
            if(!file_exists($tmpCss) || filemtime($tmpCss)<max($r['scss'])) {
                $this->parseScss(array_keys($r['scss']), $tmpCss);
            }
            $r['scss'] = $tmpCss;
        }

        return $this->build($r);
    }

    public function parseLess($fs, $outputFile)
    {
        static $compiler='lessc';
        // inspect memory usage by this component
        tdz::tune(null, 32, 10);
        if(!class_exists($compiler)) {
            return $this->build($fs, $outputFile);
        }

        $parser = new $compiler();
        $parser->registerFunction('dechex', function($a) {
            return dechex($a[1]);
        });
        $parser->setVariables(array('assets-url'=>escapeshellarg(tdz::$assetsUrl), 'studio-url'=>escapeshellarg(Tecnodesign_Studio::$home))+static::$assetVariables);
        $importDir = (is_array(self::$importDir)) ?self::$importDir :[self::$importDir];
        if(is_dir($d=TDZ_DOCUMENT_ROOT.tdz::$assetsUrl.'/css/') && !in_array($d, $importDir)) $importDir[] = $d;
        if($this->root && !in_array($this->root, $importDir)) $importDir[] = $this->root.'/';

        if(is_array($fs) && count($fs)>1) {
            $s = '';
            foreach($fs as $i=>$o) {
                if(!in_array($d=dirname($o), $importDir)) $importDir[] = $d.'/';
                unset($d);
                $s .= '@import '.escapeshellarg(basename($o)).";\n";
                unset($fs[$i], $i, $o);
            }
            $fs = $s;
            $save = true;
            unset($s);
        } else {
            if(is_array($fs)) $fs = array_shift($fs);
            $importDir[] = dirname($fs).'/';
            $save = false;
        }

        $parser->setImportDir($importDir);
        unset($importDir);

        if($save) {
            tdz::save($outputFile, $parser->compile($fs));
        } else {
            $parser->checkedCompile($fs, $outputFile);
        }

        $parser = null;

    }

    public function parseScss($fs, $outputFile)
    {
        static $compiler='ScssPhp\\ScssPhp\\Compiler';
        if(!class_exists($compiler)) {
            return $this->build($fs, $outputFile);
        }

        $parser = new $compiler();
        $parser->setVariables(array('assets-url'=>escapeshellarg(tdz::$assetsUrl), 'studio-url'=>escapeshellarg(Tecnodesign_Studio::$home))+static::$assetVariables);
        $parser->registerFunction('dechex', function($a){
            return dechex($a[1]);
        });
        $importDir = (is_array(self::$importDir)) ?self::$importDir :[self::$importDir];
        if(is_dir($d=TDZ_DOCUMENT_ROOT.tdz::$assetsUrl.'/css/') && !in_array($d, $importDir)) $importDir[] = $d;
        if($this->root && !in_array($this->root, $importDir)) $importDir[] = $this->root.'/';

        if(is_array($fs) && count($fs)>1) {
            $s = '';
            foreach($fs as $i=>$o) {
                if(!in_array($d=dirname($o), $importDir)) $importDir[] = $d;
                unset($d);
                $s .= '@import '.escapeshellarg(basename($o)).";\n";
                unset($fs[$i], $i, $o);
            }
            $fs = $s;
            unset($s);
        } else {
            if(is_array($fs)) $fs = array_shift($fs);
            $importDir[] = dirname($fs);
            $fs = '@import '.escapeshellarg(basename($fs));
        }

        if($this->root!=TDZ_DOCUMENT_ROOT && is_dir($d=TDZ_DOCUMENT_ROOT.tdz::$assetsUrl.'/css/') && !in_array($s, $importDir)) $importDir[] = $d;
        if($this->root && !in_array($this->root, $importDir)) $importDir[] = $this->root;
        $parser->setImportPaths($importDir);
        unset($importDir);

        tdz::save($outputFile, $parser->compile($fs));

        $parser = null;
    }


    /**
     * Compress Javascript & CSS
     */
    public static function minify($src, $root=false, $compress=true, $before=true, $raw=false, $output=false)
    {
        if($root===false) {
            $root = TDZ_DOCUMENT_ROOT;
        }

        $assets = array(); // assets to optimize
        $r = ''; // other metadata not to messed with (unparseable code)
        $f = (!is_array($src))?(array($src)):($src);
        $s = '';

        foreach($f as $i=>$url) {
            if(is_array($url)) {
                $r .= static::minify($url, $root, $compress, $before, $raw, (!is_numeric($i)) ?$i :false);
            } else if(strpos($url, '<')!==false) {
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

                if((isset($m[2]) && $m[2]) || preg_match('#^(http:)?//#', $url) || !(file_exists($f=$root.$url) || (file_exists($f=$url) && (substr($url, 0, strlen($root))==$root || substr($url, 0, strlen(TDZ_PROJECT_ROOT))==TDZ_PROJECT_ROOT )) )) {
                    // not to be compressed, just add to output
                    $r .= sprintf(static::$optimizeTemplates[$ext], tdz::xml($url));
                } else {
                    if(!isset($assets[$ext])) $assets[$ext]=array();
                    $assets[$ext][$f] = filemtime($f);
                }
                unset($f, $m);
            }
        }
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
            if($file=$p->getFile()) {
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

    public static function run($url=null, $root=null, $optimize=null, $outputToRoot=null)
    {
        if(Tecnodesign_Studio::$cacheTimeout) tdz::cacheControl('public', Tecnodesign_Studio::$staticCache);
        if(is_null($url)) $url = tdz::scriptName();
        if(is_null($root)) $root = Tecnodesign_Studio::$app->tecnodesign['document-root'];
        if(is_file($root.$url)) {
            tdz::download($root.$url, tdz::fileFormat($url), null, 0, false, false, false);
            Tecnodesign_Studio::$app->end();
        }
        if(is_null($optimize)) $optimize = strncmp($url, Tecnodesign_Studio::$assetsOptimizeUrl, strlen(Tecnodesign_Studio::$assetsOptimizeUrl))===0;


        if(!$optimize 
            || !preg_match('/^(.*\.)([^\.\/]+)\.([^\.\/]+)$/', $url, $m) 
            || !isset(Tecnodesign_Studio_Asset::$optimizeActions[$m[2]]) 
            || !(in_array(strtolower($m[3]), Tecnodesign_Studio_Asset::$optimizeActions[$m[2]]['extensions']) || in_array('*', Tecnodesign_Studio_Asset::$optimizeActions[$m[2]]['extensions']))
        ) {
            return false;
        }

        $u = $m[1].$m[3];
        if(!($file=Tecnodesign_Studio_Asset::file($u, $root))) {
            if(isset(Tecnodesign_Studio_Asset::$optimizeActions[$m[2]]['alt-extension'][strtolower($m[3])])) {
                $u = $m[1].Tecnodesign_Studio_Asset::$optimizeActions[$m[2]]['alt-extension'][strtolower($m[3])];
                $file=Tecnodesign_Studio_Asset::file($u, $root);
            }
            if(!$file) return false;
        }
        unset($u);
        $method = static::$optimizeActions[$m[2]];
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
                static::minify($opt, $d, true, true, false, $cache);
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
        if(is_null($outputToRoot)) $outputToRoot = self::$outputToRoot;
        if($result) {
            if($outputToRoot && tdz::save($root.$url, $result, true)) {
                tdz::download($root.$url, null, null, 0, false, false, false);
            } else {
                tdz::output($result, tdz::fileFormat($url), false);
            }
        } else if(isset($R)) {
            tdz::download($R, null, null, 0, false, false, false);
            unset($R);
        }
        unset($result, $file, $method, $ext);
        Tecnodesign_Studio::$app->end();
    }

}
