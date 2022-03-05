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
use Studio\Model\Contents;
use Studio\Model\Permissions;
use Studio\Model\Tags;
use Studio\Studio;
use Tecnodesign_Cache as Cache;
use Tecnodesign_Collection as Collection;
use Tecnodesign_Yaml as Yaml;
use Tecnodesign_Exception as Exception;

class Entries extends Model
{
    public static 
        $schema,
        $schemaClass = 'Studio\\Schema\\Model',
        $layout='layout',                               // default layout
        $slots=array(                                   // default slots
            'header'=>null,
            'body'=>null,
            'footer'=>null,
        ),
        $slot='body',
        $slotElements = array('header', 'nav', 'footer'),
        $types = array(
            'page'=>'*Page',
            'feed'=>'*Newsfeed',
            'entry'=>'*Article',
            'file'=>'*Uploaded file',
        ),
        $pageDir='web',                                // where pages are stored (relative to TDZ_VAR)
        $uploadDir,                                    // deprecated, use S::uploadDir
        $indexFile='index',                            // filename to use for directory reads
        $previewEntryType=array('feed','file','page'), // which entry types can be previewed
        $hostnames=[],                                 // list of hostnames that should be skipped in the link validation
        $s = 1;

    protected $id, $title, $summary, $link, $source, $format, $published, $language, $type, $master, $version=false, $created, $updated=false, $expired, $Tag, $Contents, $Permission, $Child, $Parent, $Related, $Children;
    
    protected $dynamic=false, $wrapper, $modified, $credential;

    public function __toString()
    {
        return $this->title
            . ' ('.(($this->type) ?$this->choicesTypes($this->type) :'').'#'.$this->id.')';
    }

    public function studioId($prefix=null)
    {
        if(!$prefix) {
            $prefix = ($this->type) ?$this->type :'site';
        }

        return Studio::interfaceId($this, $prefix);
    }

    public function render()
    {
        if(Studio::$private) {
            $cch = 'private';
            if(!Studio::$staticCache) $cch .= ', no-cache';
            S::cacheControl($cch, Studio::$staticCache);
        } else if(Studio::$staticCache) {
            App::$afterRun['staticCache']=array('callback'=>array('Tecnodesign_Studio','setStaticCache'));
            S::cacheControl('public', Studio::$cacheTimeout);
        }
        Studio::$page = $this->id;
        if($this->link!=S::scriptName()) S::scriptName($this->link);
        S::$variables['entry'] = $this;
        $m = 'render'.ucfirst(S::camelize($this->getType()));
        if(!method_exists($this, $m)) {
            Studio::error(404);
        }
        $r = $this->$m();
        S::$variables['template']=null;
        if(is_array($r)) {
            foreach($r as $k=>$v) {
                S::$variables[$k] = $v;
                unset($r[$k], $k, $v);
            }
        } else {
            S::$variables['layout'] = $r;
        }
        unset($r);
    }

    public function renderPage()
    {
        $master = $this->master;
        $c = $this->getCredentials('previewPublished');
        if(!$master || is_null($c)) {
            if(Studio::connected('content')) {
                $E = $this;
                while($E=$E->getParent()) {
                    if(!$master) {
                        $master=$E->master;
                    }
                    if(is_null($c)) {
                        $c = $E->getCredentials('previewPublished');
                    }
                    if($master && !is_null($c)) break;
                }
                unset($E);
            }
            if(is_null($c)) {
                $c = Studio::credential('previewPublished');
            }
        }
        if(is_null($c) && $this->credential) $c = $this->credential;
        else if($c==='*') $c = false;

        if($c && !(($U=S::getUser()) && $U->hasCredential($c, false))) {
            Studio::error($U->isAuthenticated() ?403 :401);
            return false;
        }

        if($c) {
            Studio::$private = (is_array($c))?($c):(array($c));
        }
        if(Studio::$private && is_array(Studio::$private) && !implode('', Studio::$private)) Studio::$private = [];
        if(Studio::$staticCache && !is_null($c)) {
            Studio::$staticCache=false;
        }
        $id = ($this->id)?($this->id):(S::hash($this->link, null, 'uuid'));
        unset($c);
        if(Studio::$staticCache && Studio::$cacheTimeout) {
            $ckey = 'studio/page/e'.$id.'-'.$this->version.'-'.S::$lang;
            if(($lmod=Cache::lastModified($ckey)) && (!Studio::$cacheTimeout || time()-$lmod < Studio::$cacheTimeout) && ($r=Cache::get($ckey))) {
                return $r;
            }
        }
        // layout file
        S::$variables['route']['layout'] = $master = Studio::templateFile($master, S::$variables['route']['layout'], self::$layout, 'layout');

        // find out which slots are available. These should be configured either in
        // app.yml or as a routing parameter
        $slots = self::$slots;
        $add=array();
        $this->dynamic = false;
        if(!isset($slots['title']))$add['title']=$this->title;
        if(!isset($slots['meta'])) $add['meta']=array();
        else if(!is_array($slots['meta'])) $slots['meta']=array($slots['meta']);

        if(count($add)>0) {
            $slots = array_merge($add,$slots);
        }
        unset($add);
        $contents = $this->getRelatedContent();
        $langs = '<meta name="language" content="'.S::$lang.'" />';
        if(isset(Studio::$app->tecnodesign['languages'])) {
            if(!Studio::$languages) {
                Studio::$languages=Studio::$app->tecnodesign['languages'];
            }
            ksort(Studio::$languages);
            $la = Studio::$languages;
            foreach($la as $lang) {
                if($lang==S::$lang) continue;
                $langs .= '<link rel="alternate" hreflang="'.$lang.'" href="'.$this->link.'?!'.$lang.'" />';
                unset($lang);
            }
        }

        array_unshift(
            $slots['meta'], 
            '<meta name="generator" content="Tecnodesign Studio - https://tecnodz.com" />'
            . $langs
        );

        foreach($slots as $slotname=>$slot) {
            if(is_null($slot)) {
                $slots[$slotname] = array();
            } else if(is_array($slot) && isset($slot[0])) {
                $slots[$slotname] = $slot;
            } else {
                $slots[$slotname] = array(array($slot));
            }
            unset($slotname, $slot);
        }

        self::$s=1;
        if($contents && count($contents)>0) {
            foreach($contents as $i=>$C) {
                $dyn = false;
                if($C->content_type=='php') {
                    $dyn = $this->dynamic = true;
                } else if($C->content_type=='widget') {
                    $d = $C->getContents();
                    if(isset($d['app']) && isset(Contents::$widgets[$d['app']]['cache']) && !Contents::$widgets[$d['app']]['cache'])
                        $dyn = $this->dynamic = true;
                    unset($d);
                }
                if(!($slot=$C->slot)) $slot = 'body';
                if(!isset($slots[$slot])) $slots[$slot]=array();
                if(!$C->entry) $C->entry=$this->id;
                $pos = (int)$C->position;
                if(!$dyn) {
                    if(!isset($slots[$slot][$pos])) $slots[$slot][$pos]='';
                    if(!is_array($slots[$slot][$pos])) {
                        $slots[$slot][$pos].= $C->render(true);
                    } else {
                        $slots[$slot][$pos][] = $C->render(true);
                    }
                } else {
                    if(!isset($slots[$slot][$pos])) {
                        $slots[$slot][$pos]=array();
                    } else if(!is_array($slots[$slot][$pos])) {
                        $slots[$slot][$pos]=array($slots[$slot][$pos]);
                    }
                    $slots[$slot][$pos][] = $C->render(false);
                }
                unset($contents[$i], $i, $C, $pos);
            }
        }

        if(!$dyn && $this->modified) {
            App::response(array('headers'=>array('Last-Modified'=>gmdate('D, d M Y H:i:s', $this->modified).' GMT')));
        } else {
            $this->modified = time();
        }

        $slots['meta'][] = '<meta http-equiv="last-modified" content="'. gmdate('D, d M Y H:i:s',$this->modified) . ' GMT" />';

        $sid = $this->studioId();
        $merge = array();
        $slotelements = array();
        foreach(static::$slotElements as $n) {
            if(is_string($n)) $slotelements[$n] = true;
        }

        $a = ['variables'=>[]];
        $parts = ['before', /*'export',*/ 'content', 'after'];
        if($dyn && Studio::$staticCache) Studio::$staticCache=false;
        foreach($slots as $slotname=>$slot) {
            ksort($slot);
            $a['variables'][$slotname] = '';

            foreach($slot as $slotfrag) {
                if(!is_array($slotfrag)) {
                    $a['variables'][$slotname] .= $slotfrag;
                } else {
                    foreach($slotfrag as $v) {
                        if(is_array($v)) {
                            foreach($parts as $part) {
                                if(isset($v[$part])) {
                                    $a['variables'][$slotname] .= $v[$part];
                                }
                            }
                        } else {
                            $a['variables'][$slotname] .= $v;
                        }
                    }
                }
                unset($slot, $slotfrag);
            }

            if($slotname!='meta' && $slotname!='title') {
                $merge[]=$slotname;
                $a['variables'][$slotname] = "<div id=\"{$slotname}\" data-studio=\"{$sid}\">"
                  . S::get('before-'.$slotname)
                  . $a['variables'][$slotname]
                  . S::get($slotname)
                  . S::get('after-'.$slotname)
                  . '</div>';
                if(isset($slotelements[$slotname]) && $slotelements[$slotname]) {
                    $a['variables'][$slotname] = "<{$slotname}>{$a['variables'][$slotname]}</{$slotname}>";
                }
            }

            unset($slots[$slotname], $slotname, $slot);
        }
        if($merge && $this->wrapper && is_array($this->wrapper)) {
            foreach($this->wrapper as $n=>$s) {
                $mrg = array();
                $idx = null;
                foreach($merge as $i=>$slotname) {
                    if(in_array($slotname, $s)) {
                        if(is_null($idx)) {
                            $idx = $i;
                            $mrg[$idx] = "<div id=\"{$n}\">";
                        }
                        $mrg[$idx] .= (isset($a['variables'][$slotname])) ?$a['variables'][$slotname] :'';
                    } else {
                        $mrg[$i] = $slotname; 
                    }
                    unset($i, $slotname);
                }
                if(!is_null($idx)) {
                    $mrg[$idx] .= '</div>';
                    $merge = array_values($mrg);
                }
                unset($n, $s, $idx, $mrg);
            }
        }

        if($merge) {
            $a['variables']['content'] = '';
            foreach($merge as $slotname) {
                $a['variables']['content'] .= (substr($slotname, 0, 1)==='<') ?$slotname :$a['variables'][$slotname];
            }
        }
        if(!isset($a['variables']['meta'])) $a['variables']['meta'] = '';
        $a['variables']['meta'] .= S::meta();

        // in App::runTemplate
        $app = get_class(Studio::$app);
        if($app::$assets) {
            foreach($app::$assets as $i=>$n) {
                $app::asset($n);
                unset($app::$assets[$i], $i, $n);
            }
        }
        unset($app);
        if(Studio::$private) {
            Studio::$private = array_unique(Studio::$private);
            $cch = 'private';
            if(!Studio::$staticCache) $cch .= ', no-cache';
            S::cacheControl($cch, Studio::$staticCache);
            unset($cch);
        }
        $a['script'] = $master;
        $a['variables'] += App::response();
        $r = S::exec($a);
        unset($a);
        if(Studio::$staticCache && Studio::$cacheTimeout && isset($ckey)) {
            Cache::set($ckey, $r, Studio::$cacheTimeout);
        }

        return $r;
    }

    public function getFile()
    {
        $file = null;
        if($this->source) {
            $f = $this->source;
            if(strpos($f,'|')) {
                $fpart = explode('|', $f);
                $f = array_shift($fpart);
                if($fpart && !$this->format) $this->format = array_shift($fpart);
            } else if(!(($file=static::file($f)) && file_exists($file))
                && !file_exists($file=S_VAR.'/'.$f)
                && !file_exists($file=S_DOCUMENT_ROOT.'/'.$f)
                && !file_exists($file=Studio::$app->tecnodesign['document-root'].'/'.$f)
            ) {
                $file = null;
            }
            if(file_exists($ufile=S::uploadDir().'/'.$f)) {
                if(!$file || filemtime($ufile)>filemtime($file)) {
                    $file = $ufile;
                }
            }
        }

        return $file;
    }

    public function filePreview($optimize=false)
    {
        if($this->type!='file') return false;

        if(!isset(S::$variables['cache-control'])) {
            if(Studio::$private) {
                $cch = 'private';
                if(!Studio::$staticCache) $cch .= ', no-cache';
                S::set('cache-control', $cch);
            } else {
                S::set('cache-control', 'public');
            }
        }

        $file = $this->getFile();
        if(!$file) {
            Studio::error(404);
            return false;
        }
    
        $link = $this->link;
        if(!$link) $link = S::scriptName();
        $fname = basename($link);
        if($optimize) {
            $ext = strtolower(preg_replace('/.*\.([a-z0-9]{1,5})$/i','$1',basename($file)));
            $actions=Studio::$app->studio['assets_optimize_actions'];
            $cache=S_VAR.'/optimize/'.md5_file($file);
            if(isset($actions[$optimize]) && in_array(strtolower($ext),$actions[$optimize]['extensions'])) {
                $options=$actions[$optimize];
                if(isset($options['params'])) $params=$options['params'];
                $method=$options['method'];
                $ext = strtolower(preg_replace('/.*\.([a-z0-9]{1,5})$/i','$1',$fname));
                $extl = ($ext)?('.'.$ext):(0);
                $cachefile = $cache.'.'.$optimize.$extl;
                if(file_exists($cachefile) && filemtime($cachefile)>filemtime($file))
                    $file = $cachefile;
                else {
                    $data='';
                    if(method_exists('tdz', $method))
                        $data=S::$method($file,$params);
                    else if(function_exists($method))
                        $data=$method($file,$params);
                    if($data!='') {
                        if(!is_dir(dirname($cachefile))) mkdir(dirname($cachefile),0777,true);
                        file_put_contents($cachefile, $data);
                        @chmod($cachefile,0666);
                        $file = $cachefile;
                    }
                }
            }
        }
        if(substr($this->format, -4)!='html') S::download($file,($this->format)?($this->format):(S::fileFormat($file)));
        return $file;
    }
    public function renderFile()
    {
        $c = $this->getCredentials('previewPublished');
        $U=S::getUser();
        if(is_null($c)) {
            $c = Studio::credential('previewPublished');
        }

        if($c && !(($U=S::getUser()) && $U->hasCredential($c, false))) {
            Studio::error($U->isAuthenticated() ?403 :401);
            return false;
        }
        $file = $this->filePreview();
        S::download($file,$this->format);
    }

    public function renderEntry($template=false, $args=array())
    {
        $a = array('script'=>Studio::templateFile($template, 'tdz_entry'),
            'variables'=>$this->asArray()
        );
        if(is_array($args) && count($args)>0)
            $a['variables'] +=$args;
        $a['variables']['entry']=$this;
        return S::exec($a);
    }

    public function renderFeed($template=false, $args=array())
    {
        $tpl=(substr(S::scriptName(), 0, strlen($this->link))==$this->link)?('tdz_atom'):('tdz_feed');
        $template = Studio::templateFile($template, $tpl);
        return $this->renderEntry(substr($template, 0, strlen($template)-4), $args);
    }
    

    public static function feedPreview($o)
    {
        $entry=false;
        if(isset($o['entry'])) {
            $entry = (is_object($o['entry']))?($o['entry']):(static::latest($o['entry']));
        }
        if($entry && !Studio::getPermission('previewEntry', $entry)) {
            $entry=false;
        }
        if(!$entry) return '';
        $o['entry']=$entry;
        if(isset($o['master'])) {
            return S::exec(array('script'=>$o['master'], 'variables'=>$o));
        }
        return '';
    }

    public function getType()
    {
        if(!$this->type && !$this->isNew()) {
            $this->type = 'page';
        }
        return $this->type;
    }

    public function getParent($scope=null)
    {
        if(!$this->id) return null;
        return static::find(array('Children.entry'=>$this->id),1,$scope,false);
    }

    public function getCredentials($role='previewPublished')
    {
        if(!is_null($this->credential)) {
            if(!is_array($this->credential)) return $this->credential;
            else if(isset($this->credential[$role])) return $this->credential[$role];
            else if(isset($this->credential['default'])) return $this->credential['default']; 
            else if(isset($this->credential['auth'])) return $this->credential['auth']; 
        }
        if($this->id && Studio::connected('content')) {
            $P = Permissions::find(array('entry'=>$this->id,'role'=>'previewPublished'),1,array('credentials'));
            if($P) {
                if($P->credentials) return explode(',', $P->credentials);
                else return false;
            }
        }
        return null;
    }

    public function previewPermission()
    {
        if($P = $this->getRelation('Permission', null, null, false)) {
            $html = (Api::format()=='html');
            $r = $g = $all = $none = null;
            $s = ($html) ?'' :[];
            foreach($P as $i=>$o) {
                if(!$r) $r = $o->choicesRole();
                if(!$g) $g = $o->choicescredentials();
                if(!$all) $all = S::t('Everyone', 'model-tdz_permissions');
                if(!$none) $none = S::t('No one', 'model-tdz_permissions');

                if($co=$o->credentials) {
                    if(!is_array($co)) {
                        if(substr($co, 0, 1)=='{') $co = S::unserialize($co, 'json');
                        else $co = preg_split('/\s*\,\s*/', $co, -1, PREG_SPLIT_NO_EMPTY);
                    }
                }

                if($co) {
                    $c = null;
                    foreach($co as $cid) {
                        $c .= (($c) ?', ' :'')
                            . ((isset($g[$cid])) ?$g[$cid] :$cid)
                            ;
                    }
                } else {
                    $c = $all;
                }

                if($html) {
                    if(isset($r[$o->role])) {
                        $c = $r[$o->role].': '.$c;
                    } else {
                        $c = $o->role.': '.$c;
                    }
                    $s .= '<li>'.S::xml($c).'</li>';
                } else {
                    $r[$o->role] = $c;
                }
            }
            if($html && $s) $s = '<ul>'.$s.'</ul>';

            return $s;
        }
    }

    public function getAncestors($stopId=false, $scope='string')
    {
        $as = array();
        $a=$this;
        $found = false;
        if($a->id!=$stopId) {
            while($a=$a->getParent($scope)) {
                if($a->id==$stopId) {
                    $found=true;
                    break;
                }
                array_unshift($as, $a);
            }
        }
        if($stopId && !$found) $as=array();
        return $as;
    }

    public function getContent($type, $scope='content', $asCollection=false, $orderBy=null, $groupBy=null)
    {
        return $this->getContents(['content_type'=>$type]);

    }

    public function getContents($search=array(), $scope='content', $asCollection=false, $orderBy=null, $groupBy=null, $limit=null)
    {
        if(!$this->id) return null;
        if(!is_array($search)) $search = array();
        $search['entry'] = $this->id;
        return Contents::find($search,$limit,$scope,$asCollection,$orderBy,$groupBy);
    }

    public function getEntries($search=array(), $scope='link', $asCollection=false, $orderBy=null, $groupBy=null, $limit=null)
    {
        if(!$this->id) return null;
        if(!is_array($search)) $search = [];
        $search['published<']=TDZ_TIMESTAMP;
        $search['type'] = 'entry';
        if(!$orderBy) $orderBy = ['published'=>'desc', 'Related.updated'=>'desc'];
        return $this->getChildren($search, $scope, $asCollection, $orderBy, $groupBy, $limit);
    }

    public function getChildren($search=array(), $scope='link', $asCollection=false, $orderBy=null, $groupBy=null, $limit=null)
    {
        if(!$this->id) return null;
        if(!is_array($search)) $search = [];
        $search['published<']=TDZ_TIMESTAMP;
        $search['Related.parent'] = $this->id;
        if(!isset($search['type'])) {
            $search['type'] = ['page','entry'];
        }
        if(!$orderBy) {
            if($this->type=='feed') {
                $orderBy = ['published'=>'desc', 'title'=>'asc'];
            } else {
                $orderBy = ['Related.position'=>'asc', 'title'=>'asc'];
            }
        }
        if($limit==1) {
            $r = static::find($search,$limit,$scope,$asCollection,$orderBy,$groupBy);
            if($r) $r=[$r];
            return $r;
        }
        return static::find($search,$limit,$scope,$asCollection,$orderBy,$groupBy);
    }

    public function getTags($search=array(), $scope='link', $asCollection=false, $orderBy=null, $groupBy=null)
    {
        if(!$this->id) return null;
        if(!is_array($search)) $search = array();
        $search['entry'] = $this->id;
        $L = Tags::find($search,null,$scope,true,$orderBy,$groupBy);
        if($L) $L->setQueryKey('slug');
        else if($asCollection) $L = new Collection(null, 'Tecnodesign_Studio_Tags', null, 'slug');
        else $L = [];

        return ($asCollection || !$L) ?$L :$L->getItems();
    }

    public function validateTags($v)
    {
        $rel = [];
        if($v) {
            if(!is_array($v)) $v = preg_split('/\s*\,\s*/', $v, -1, PREG_SPLIT_NO_EMPTY);
            $tags = $this->getTags();
            if(!$tags) $tags = [];
            foreach($v as $o) {
                $o = trim($o);
                if(!$o) continue;
                $k = S::slug($o);
                if(isset($tags[$k])) {
                    if($tags[$k]->tag!=$o) $tags[$k]->tag = $o;
                    $rel[] = $tags[$k];
                    unset($tags[$k]);
                } else {
                    $rel[] = ['entry'=>$this->id, 'tag'=>$o, 'slug'=>S::slug($o)];
                }
            }

            foreach($tags as $o) {
                $o->delete(false);
                $rel[] = $o;
            }
        }
        $this->setRelation('Tag', $rel);

        return $v;
    }

    public function validateTemplate($v)
    {
        return $v;
    }

    public static function file($url, $check=true, $pat=null)
    {
        static $pat0;
        if(!$check && !$pat && is_null($pat0)) {
            $pat0 = '{,.'.S::$lang.'}{,.'.implode(',.',array_keys(Contents::$contentType)).'}';
        }
        if(!$check && !$pat) $pat = $pat0;

        $src = [];
        if($rs = Studio::config('web-repos')) {
            foreach($rs as $rn=>$repo) {
                if(isset($repo['id'])) $rn = $repo['id'];
                if(!is_dir($d=S_REPO_ROOT.'/'.$rn)) continue;
                $mu = (isset($repo['mount'])) ?$repo['mount'] :'';
                $murl = $url;
                if($mu) {
                    if($mu===$url) {
                        $murl = '';
                    } else if(substr($url, 0, strlen($mu)+1)===$mu.'/') {
                        $murl = substr($url, strlen($mu));
                    } else {
                        continue;
                    }
                }

                if(isset($repo['mount-src']) && ($msrc=preg_replace('/([^\:]+\:)/', '', $repo['mount-src'])) && !preg_match('#/\.\./#', $repo['mount-src'])) {
                    if($msrc!='.' && $msrc!='/') {
                        $d .= '/'.$msrc;
                    }
                }

                $f = $d.$murl;
                if($check) {
                    if(file_exists($f)) return $f;
                    continue;
                }
                if(is_dir($f)) $f .= ((substr($f, -1)=='/') ?'' :'/') . static::$indexFile;
                $src[] = $f;
                unset($f, $d, $murl, $mu);
            }
        }

        $f = Studio::documentRoot() . ((substr($url, 0, 1)!='/') ?'/' :'').$url;

        if($check) {
            return (file_exists($f)) ?$f :null;
        }

        if(is_dir($f)) $f .= ((substr($f, -1)=='/') ?'' :'/') . static::$indexFile;

        if($src) {
            $src[] = $f;
            $glob = '{'.implode(',',$src).'}';
        } else {
            $glob = $f;
        }

        return self::glob($glob.$pat);
    }

    public static function meta(&$p)
    {
        $m = null;
        if(preg_match('/^\<\!\-\-[\s\n]*\-\-\-/', $p, $x) && ($n = strpos($p, '-->'))) {
            $m = substr($p, strlen($x[0]) -3, $n - strlen($x[0]) + 3);
            $p = substr($p, strlen($m)+strlen($x[0]));
            unset($x, $n);
        } else if(substr($p, 0, 2)=='/*') {
            $m = substr($p,3,strpos($p,'*/')-3);
            $p = substr($p, strlen($m)+5);
        }
        return $m;
    }

    public static function findPage($url, $multiview=false, $redirect=false)
    {
        // get file-based page definitions
        if(substr(basename($url),0,1)=='.') return;
        $P=null;
        if(!$multiview) {
            if($pages = static::file(str_replace('.', '[-.]', $url), false)) {
                foreach($pages as $page) {
                    if($P=self::_checkPage($page, $url)) {
                        break;
                    }
                }
            }

            //@TODO
            if($redirect) {} // redirect rules: if it's a folder, S::scriptName() must end with / otherwise, can't end with /
        } else if($url) {
            if(in_array('php', Contents::$multiviewContentType) && is_file($f=static::file($url.'.php')))
                $P=self::_checkPage($f, $url, $multiview);
            if(in_array('md', Contents::$multiviewContentType) && is_file($f=static::file($url.'.md')))
                $P=self::_checkPage($f, $url, $multiview);
        }

        return $P;
    }

    public static function glob($pat)
    {
        if(defined('GLOB_BRACE')) {
            return glob($pat, GLOB_BRACE);
        } else if (strpos($pat, '{')===false) {
            return glob($pat);
        }
        $pat0 = $pat;
        $p = array();
        while(preg_match('/\{([^\}]+)\}/', $pat, $m)) {
            $dosub = ($p);
            $n = explode(',', $m[1]);
            $p0 = $p;
            $p = array();
            foreach($n as $v) {
                if(!$dosub) {
                    $p[] = $pat;
                    $p = str_replace($m[0], $v, $p);
                } else {
                    foreach($p0 as $np) {
                        $p[] = str_replace($m[0], $v, $np);
                        unset($np);
                    }
                }
                unset($v);
            }
            $pat = $p[count($p)-1];
            unset($p0, $n, $dosub);
        }
        $r = array();
        foreach($p as $i=>$o) {
            $r = array_merge($r, glob($o));
        }
        if($r) {
            asort($r);
            $r = array_unique($r);
        }
        return $r;
    }

    protected static function _checkPage($page, $url, $multiview=false, $extAttr=null)
    {
        if(is_dir($page)) return;
        $base = preg_replace('/\..*/', '', basename($page));
        $pn = basename($page);
        //if(substr($pn, 0, strlen($base)+1)==$base.'.') $pn = substr($pn, strlen($base)+1);
        $pp = explode('.', $pn);
        if(in_array('_tpl_', $pp) || $pp[0]=='') return; // templates cannot be pages, neither .dotted files
        $ext = strtolower(array_pop($pp));
        //if(is_array(Contents::$disableExtensions) && in_array($ext, Contents::$disableExtensions)) return;

        if($ext=='html' && stripos(fgets(fopen($page, 'r')), 'doctype')) $isPage=false;
        else $isPage = isset(Contents::$contentType[$ext]);

        if($isPage) {
            if($pn==$base) return; // cannot access content directly
            else if($base.'.'.$ext!=$pn && $base.'.'.S::$lang.'.'.$ext!=$pn) return; // cannot have slots/position
            // last condition: cannot have any valid slotname within $pp > 0
            foreach(array_keys(static::$slots) as $slot) {
                if(in_array($slot, $pp)) return;
                unset($slot);
            }
            $format='text/html';
        } else {
            $format = (isset(S::$formats[$ext]))?(S::$formats[$ext]):(null);
        }
        $meta=array();

        if($isPage) {
            $p = file_get_contents($page);
            // look for metadata in comments
            $m = null;
            if(preg_match('/^\<\!\-\-[\s\n]*\-\-\-/', $p, $r) && ($n = strpos($p, '-->'))) {
                $m = substr($p, strlen($r[0]) -3, $n - strlen($r[0]) + 3);
                $p = substr($p, strlen($m)+strlen($r[0]));
                unset($r, $n);
            } else if(substr($p, 0, 2)=='/*') {
                $m = substr($p,3,strpos($p,'*/')-3);
                $p = substr($p, strlen($m)+5);
            } else if($multiview) return;

            if($m) {
                $meta = Yaml::load($m);
                if($multiview && (!isset($meta['multiview']) || !$meta['multiview'])) return;
            }
        } else if($multiview) return;


        $id = $source = null;

        if($extAttr) {
            $source = ((isset($extAttr['src'])) ?$extAttr['src'] :'').substr($page, strlen($extAttr['file']));
        } else if(strpos($page, S_REPO_ROOT.'/')===0) {
            $source = preg_replace('#^/?([^/]+)/(.+)$#', '$1:/$2', substr($page, strlen(S_REPO_ROOT)+1));
        } else {
            $source = substr($page, strlen(Studio::documentRoot()));
        }
        if(Studio::connected('content') && ($E=self::find(['source'=>$source],1,['id']))) {
            $id = $E->id;
            unset($E);
        }
        if($url===true) {
            $urlb = '';
            $urlr = $source;
            if($extAttr) {
                if(isset($extAttr['url']) && $extAttr['url']) $urlb = $extAttr['url'];
                if(isset($extAttr['src']) && substr($urlr, 0, strlen($extAttr['src']))==$extAttr['src']) $urlr = substr($urlr, strlen($extAttr['src']));
            }
            if($p=strrpos($urlr, ':')) $urlr = substr($urlr, $p);
            $url = $urlb;
            if($url) {
                if(substr($url, 0, 1)!=='/') $url='/'.$url;
                if(substr($url, -1)==='/' && $url!=='/') $url = substr($url, 0, strlen($url)-1);
            }

            if($urlr) {
                $url .= (substr($urlr, 0, 1)==='/') ?$urlr :'/'.$urlr;
            }

            if($isPage) {
                $url = preg_replace('/\.[a-z]+$/', '', $url);
                if(basename($url)===static::$indexFile) {
                    $url = substr($url, 0, strlen($url) - strlen(static::$indexFile));
                }
            }
        }

        $meta = static::loadMeta($url, $page, $meta);
        $t = date('Y-m-d\TH:i:s', filemtime($page));
        $d = [
            'id' => $id,
            //'id'=>S::hash($id, null, 'uuid'),
            'source'=>$source,
            'link'=>$url,
            'published'=>$t,
            'format'=>$format,
            'type'=>($isPage)?('page'):('file'),
            'updated'=>$t,
        ];
        if($extAttr) {
            $d['title'] = str_replace(['_', '-'], ' ', basename($url));
            $d['created'] = date('Y-m-d\TH:i:s', filectime($page));
            $d['__skip_timestamp_created'] = true;
            $d['__skip_timestamp_updated'] = true;
        }

        if(S::isempty($d['id'])) unset($d['id']);

        $cn = get_called_class();
        $P = new $cn($d);

        // reindex?
        if($meta) {
            foreach($meta as $fn=>$v) {
                if(property_exists($P, $fn)) {
                    if($fn=='layout' || $fn=='slots') static::$$fn = $v;
                    else $P->$fn = $v;
                }
                unset($meta[$fn], $fn, $v);
            }
        }
        unset($meta, $t, $id, $format, $isPage);
        return $P;
    }

    public static function loadMeta($url, $page=null, $meta=array())
    {
        if(is_null($page) && $url) {
            $page = static::file($url, false);
            if(!$page) $page = '';
            else if($page && is_array($page)) $page = array_shift($page);
        }

        // get metadata
        if(file_exists($mf=$page.'.'.S::$lang.'.meta') || file_exists($mf=$page.'.meta')) {
            $m = Yaml::load($mf);
            if(is_array($m)) {
                $meta += $m;
            }
            unset($m);
        }
        $d=$url;
        $p=preg_replace('#/'.static::$indexFile.'.[a-z]+$#', '', $page);
        while(strrpos($d, '/')!==false) {
            if(file_exists($mf=$p.'/.meta')) {
                $m = Yaml::load($mf);
                if(is_array($m)) {
                    foreach($meta as $mn=>$mv) if(!$mv) unset($meta[$mn]); // ignore blanks
                    $meta += $m;
                }
                unset($m);
            }
            if(file_exists($mf=$p.'.meta')) {
                $m = Yaml::load($mf);
                if(is_array($m)) {
                    foreach($meta as $mn=>$mv) if(!$mv) unset($meta[$mn]); // ignore blanks
                    $meta += $m;
                }
                unset($m);
            }
            unset($mf);
            $d = substr($d, 0, strrpos($d, '/'));
            $p = substr($p, 0, strrpos($p, '/'));
        }
        unset($d, $p);

        if($meta) {
            if(isset($meta['link']) && $meta['link']!=$url && $meta['link']!=S::requestUri()) {

                if(isset($meta['credential']) && $meta['credential'] && !(($U=S::getUser()) && $U->hasCredential($meta['credential'], false))) {
                    unset($meta['link']);
                } else {
                    S::redirect($meta['link']);
                }
            }
            if(isset($meta['languages'])) Studio::$languages = $meta['languages'];
            Studio::addResponse($meta);
        }
        return $meta;
    }


    /**
     * Content loader
     *
     * Load all contents, including template-based information
     * [__tpl__.]?[baseurl][.slot]?[.position]?[.lang]?.ext
     */
    public function getRelatedContent($where='', $wherep=array(), $checkLang=true, $checkTemplate=true)
    {
        $this->modified = strtotime($this->updated);
        if($this->id) {
            $published = !(Studio::$private);
            $f = array(
                '|entry'=>$this->id,
                '|ContentDisplay.link'=>array('*', $this->link),
            );
            if(substr($this->link, -1)!=='/') $f['|ContentDisplay.link'][] = $this->link.'/';
            if(strrpos($this->link, '/')>1) {
                $l = substr($this->link, 0, strrpos($this->link, '/'));
                while($l) {
                    $f['|ContentDisplay.link'][] = $l.'/';
                    $l = substr($l, 0, strrpos($l, '/'));
                }
            }
            $r = Contents::find($f,null,null,false);
            if($r) {
                $updated = false;
                foreach($r as $i=>$o) {
                    if($o->entry!=$this->id) {
                        // confirm matching ContentDisplay
                        if(!$o->showAt($this->link)) {
                            $updated = true;
                            unset($r[$i]);
                        }
                    }
                    unset($i, $o);
                }
                if($updated && $r) $r = array_values($r);

            }

            return $r;
        } else {
            $r = null;
        }

        // get file-based page definitions
        $u = $this->link;
        static $pat;
        if(is_null($pat)) {
            $pat = '{,.*}{,.'.S::$lang.'}{.'.implode(',.',array_keys(Contents::$contentType)).'}';
        }

        if(strpos($u, '.')) $u = str_replace('.', '[-.]', $u);
        if(!($pages = self::file($u, false, $pat))) {
            $pages = [];
        }

        if($checkTemplate) {
            $tu = [];
            while(strrpos($u, '/')!==false) {
                $u = substr($u, 0, strrpos($u, '/'));
                $tu[] = $u.'/_tpl_.';
            }

            if($tu) {
                $tup = (count($tu)==1)?$tu[0] :'{'.implode(',',$tu).'}';
                $pt = self::file($tup, false, '*');
                if($pt) $pages = array_merge($pages, $pt);
                unset($pt);
                if(Studio::$templateRoot) {
                    $pt = self::glob(Studio::$templateRoot.$tup.'*');
                    if($pt) $pages = array_merge($pages, $pt);
                    unset($pt);
                }
            }
            unset($tu);
        }
        unset($f, $u);
        $sort = false;
        if($pages) {
            //$link = (substr($this->link, -1)=='/')?(self::$indexFile):(basename($this->link));
            if(!$r) $r=array();
            foreach($pages as $page) {
                if(is_dir($page)) continue;
                $C = Studio::content($page, $checkLang, $checkTemplate);
                $mod=null;
                if($C) {
                    $mod = $C->modified;
                    if($mod && $mod > $this->modified) $this->modified = $mod;
                    if($C->_position) {
                        if(!isset($r[$C->_position])) $r[$C->_position] = $C;
                        $sort = true;
                    } else {
                        $r[] = $C;
                    }
                }
                unset($C, $page, $mod);
            }
            unset($link);
            if($sort) {
                ksort($r);
                unset($sort);
            }
        }

        unset($pages);
        return $r;
    }


    public function validateLink($v)
    {
        $v = trim($v);
        if(static::$hostnames && preg_match('#^(https?:)?//([^/]+)(/|$)#', $v, $m)) {
            if(in_array($m[2], static::$hostnames)) {
                $v = substr($v, strlen($m[0])-strlen($m[3]));
            }
        }

        if(App::request('post', 'link')) {
            if($this->id && !$this->type) $this->refresh(['type']);

            if($this->type=='page' || $this->type=='file') {
                // search for duplicates
                $q = ['type'=>['page', 'file'], 'link'=>$v];
                if($this->id) $q['id!=']=$this->id;
                if(self::find($q,1,['link'])) {
                    throw new Exception(S::t('There\'s already a page or file with this link.', 'exception'));
                }
            }
        }

        return $v;
    }

    public function previewRelated()
    {
        if($L=$this->getAncestors(null, 'string')) {
            $n = S::xml((string) array_pop($L));
            if($L) {
                $sep = Studio::config('breadcrumb_separator');
                $n = '<em>'.S::xml(implode($sep, $L).$sep).'</em>'.$n;
            }
            return $n;
        }
    }

    public function previewLink()
    {
        $v = null;
        if($this->link) {
            $v = $this->validateLink($this->link);
        }

        if(Api::format()=='html') {
            $v = '<a class="z-ellipsis" title="'.S::xml(S::buildUrl($v)).'" href="'.S::xml($v).'" target="_blank">'.S::xml($v).'</a>';
        }
        return $v;
    }

    public function previewTitleTags()
    {
        $t = (isset($this->_title_tags)) ?$this->_title_tags :$this->title;
        if(Api::format()=='html') {
            $t = S::xml($t);
            if($s=$this->previewTags(true)) {
                $t = '<span class="i-float-right">'.$s.'</span>'.$t;
            }
        }

        return $t;
    }

    public function previewTags($link=null)
    {
        static $url;
        if($T = $this->getRelation('Tag', null, 'link', false)) {
            $html = (Api::format()=='html');
            $s = ($html) ?null :[];
            if($link && is_null($url)) {
                $url = S::scriptName(true);
                $tag = S::slug(S::t('Tag', 'model-tdz_entries'));
                $qso = array_diff_key(App::request('get'), ['_uid'=>null, 'ajax'=>null, $tag=>null]);
                $url .= (($qso) ?'?'.http_build_query($qso).'&' :'?').$tag.'=';
                unset($qso);
            }
            foreach($T as $i=>$o) {
                if($html) {
                    $s.= ($link) ?'<a class="z-i-a z-tag" href="'.S::xml($url.$o->slug).'">'.S::xml($o->tag).'</a>' :'<span class="z-tag">'.S::xml($o->tag).'</span>';
                } else {
                    $s[$o->slug] = $o->tag;
                }
                unset($T[$i], $i, $o);
            }
            unset($T);
            return $s;
        }
    }

    public function interfaceLink()
    {
        if(!$this->type) $this->refresh(['type']);
        return Studio::$home.'/'.$this->type.'/preview';
    }

    public function previewContents()
    {
        $tpl = '<div class="tdz-i-scope-block" data-action-schema="preview" data-action-url="'.S::scriptName(true).'">'
           .     '<a href="'.Studio::$home.'/content/new?entry='.$this->id.'&amp;position={position}&amp;slot={slot}&amp;scope=entry-content&amp;next=preview" class="tdz-i-button z-align-bottom z-i--new" data-inline-action="new"></a>'
           . '</div>';
        $r = str_replace(['{position}', '{slot}'], [1, static::$slot], $tpl);
        $slots = [];

        if($L=$this->getContents([], 'content', false, ['position'=>'asc', 'id'=>'desc'])) {
            $E = (isset(S::$variables['entry'])) ?S::$variables['entry'] :null;
            S::$variables['entry'] = $this;
            foreach($L as $i=>$o) {
                $ct = ($o->content_type) ?$o->content_type :'text';
                $slot = ($o->slot) ?$o->slot :static::$slot;
                if(!isset($slots[$slot])) $slots[$slot] = '';
                $slots[$slot] .= '<div class="ih5 z-item z-inner-block">'
                    .   '<div class="tdz-i-scope-block" data-action-expects-url="'.Studio::$home.'/content/update/'.$o->id.'" data-action-schema="preview" data-action-url="'.S::scriptName(true).'">'
                    .     '<a href="'.Studio::$home.'/content/update/'.$o->id.'?scope=u-'.$ct.'&amp;next=preview" class="tdz-i-button z-i--update" data-inline-action="update"></a>'
                    .     '<a href="'.Studio::$home.'/content/delete/'.$o->id.'?scope=u-'.$ct.'&amp;next='.S::scriptName(true).'" class="tdz-i-button z-i--delete" data-inline-action="delete"></a>'
                    . (($o->content_type && in_array($o->content_type, $o::$previewContentType))
                        ?'<div class="z-t-center z-app-image"><span class="z-t-inline z-t-left">'.$o->previewContent().'</span></div>'
                        :$o->previewContent()
                      )
                    .    '<dl class="z-i-field i1s2"><dt>'.S::t('Content Type', 'model-tdz_contents').'</dt><dd>'.$o->previewContentType().'</dd></dl>'
                    .    '<dl class="z-i-field i1s2"><dt>'.S::t('Position', 'model-tdz_contents').'</dt><dd>'.S::xml($o->position).'</dd></dl>'
                    .   '</div>'
                    . '</div>'
                    . str_replace(['{position}', '{slot}'], [$o->position+1, $slot], $tpl);
            }
            S::$variables['entry'] = $E;
            unset($E);
        }

        foreach(static::$slots as $slot=>$c) {
            if(isset($slots[$slot])) {
                $r .= '<h2 class="z-title">'.$slot.'</h2>'.str_replace(['{position}', '{slot}'], [1, $slot], $tpl);
                $r .= '<div class="z-items">'.$slots[$slot].'</div>';
                unset($slots[$slot]);
            }
        }

        return $r;
    }

    public function previewContentsInline()
    {
        if($L=$this->getContents(null, ['content'])) {
            $r = null;
            foreach($L as $i=>$o) {
                $r .= $o->renderSource();
                if(strlen($r)>750) break;
            }

            $c = (strpos($r, '<img')!==false) ?'z-ellipsis z-clip' :'z-ellipsis';
            return '<span class="'.$c.'" title="'.S::xml(substr(strip_tags($r), 0, 500)).'">'
                . $r
                . '</div>';
        }
    }

    public function getStudioLink()
    {
        if($this->id && strpos($this->id, '-')!==false) {
            // grouped folder
            $url = (isset($this->_studio_link)) ?$this->_studio_link :$this->link;
            return ['_studio_link' => Studio::$home.'/i/q?url='.urlencode($url)];
        }


        if(!isset($this->type)) {
            $this->refresh(['type']);
        }

        if($this->type) {
            return Studio::$home.'/'.$this->type.'/q';
        }
    }

    public function previewSource()
    {
        $this->refresh(['source', 'format']);
        if(!$this->source) return;

        if(Api::format()=='html' && ($f=$this->getFile())) {
            if(substr($this->format, 0, 6)=='image/') {
                return '<img class="z-app-image" src="data:'.S::xml($this->format).';base64,'.base64_encode(file_get_contents($f)).'" alt="'.S::xml($this->title).'" />';
            }
            return basename($f);
        }

        return $this->source;
    }

    public function previewStudioLink()
    {
        $url = (isset($this->_studio_link)) ?$this->_studio_link :$this->link;
        $html = (Api::format()=='html');

        $url = '/'.basename($url);

        if($this->id && strpos($this->id, '-')!==false) {
            // grouped folder
        }


        return ($html) ?\S::xml($url) :$url;

    }

    public function previewType()
    {
        if($this->type) {
            return S::xml($this->choicesTypes($this->type));
        }
    }

    public function choicesTypes($type=null)
    {
        static $r;

        if(is_null($r)) {
            $r = [];
            foreach(static::$types as $k=>$v) {
                if(substr($v, 0, 1)==='*') {
                    $v = S::t(substr($v, 1), 'model-'.static::$schema->tableName);
                }
                $r[$k] = $v;
            }

        }

        if($type) {
            if(isset($r[$type])) {
                return $r[$type];
            }
            return;
        }

        return $r;
    }

    public function choicesMaster()
    {
        static $master;

        if(!$master && Studio::$indexTimeout) {
            $master = Cache::get('studio/entry/master', Studio::$indexTimeout);
        }

        if(!$master) {
            $master = [];
            $g = Studio::templateDir();
            while($g) {
                $d = array_shift($g);
                if(is_dir($d) && ($nd=glob($d.'/*'))) {
                    $g = array_merge($g, $nd);
                } else if(is_file($d) && substr($d, -4)=='.php') {
                    $n = $l = basename($d, '.php');
                    if(!isset($master[$n])) {
                        $c = file_get_contents($d);
                        if(strpos($c, '<html')!==false) {
                            if(preg_match('#^\<\?php\s*\n/\*\*?\s*\n\s*\*?([^\n]+)#s', $c, $m)) {
                                $l = trim($m[1]).' ('.$n.')';
                            }
                            $master[$n] = $l;
                        }
                        unset($c);
                    }
                    unset($n, $l);
                }
                unset($d, $nd);
            }
            if($master && Studio::$indexTimeout) {
                Cache::set('studio/entry/master', $master, Studio::$indexTimeout);
            }
        }

        return $master;
    }

    public function previewSummary()
    {
        return S::markdown($this->summary);
    }

    public static function executeList($Interface, $req=[])
    {
        $A = $Interface['text'];

        $A['listLimit'] = 100;
        $A['listOffset'] = 0;
        $p=App::request('get', $Interface::REQ_OFFSET);
        if($p!==null && is_numeric($p)) {
            $p = (int) $p;
            if($p < 0) {
                $p = $p*-1;
                if($p > $count) $p = $p % $count;
                if($p) $p = $count - $p;
            }
            $A['listOffset'] = $p;
        } else if(($pag=App::request('get', $Interface::REQ_PAGE)) && is_numeric($pag)) {
            if(!$pag) $pag=1;
            $A['listOffset'] = (($pag -1)*$A['listLimit']);
            if($A['listOffset']>$count) $A['listOffset'] = $count;
            $Interface::$headers['offset'] = $A['listOffset'];
        } else if(isset($Interface::$headers['limit'])) {
            $Interface::$headers['offset'] = $A['listOffset'];
        }

        $q = $Interface['search'];

        if(!is_array($q)) $q = [];

        $url = null;
        if(isset($q[0])) {
            $q += $q[0];
            unset($q[0]);
        }

        $url0 = null;
        if(isset($q['link'])) {
            $url = $q['link'];
            unset($q['link']);
            $Interface['search'] = $q;
            if($url) {
                if(substr($url, 0, 1)!='/') $url = '/'.$url;
                if(strlen($url)>1 && substr($url, -1)!=='/') {
                    $url0 = $url;
                    $url .= '/';
                }
            }
        } else {
            $url = '/';
        }
        $q['type'] = static::$previewEntryType;
        if($url0) {
            $q[] = [
              '|left(`link`,'.(strlen($url)).')'=>$url,
              '|link'=>$url0,
            ];
        } else {
            $q['left(`link`,'.(strlen($url)).')']=$url;
        }

        // $q['published<']=TDZ_TIMESTAMP;
        $link = 'substring_index(`link`, \'/\', '.(substr_count($url, '/')+1).')';

        $scope = static::$schema->scope['list'];
        $scope['*Link'] = $link.' _studio_link';
        $A['list'] = static::find($q,null,$scope, true, [$link=>'asc'], [$link]);
        $A['count'] = ($A['list']) ?$A['list']->count() :0;
        $Interface['text'] = $A;
    }

    public function executeSitemap($Interface=null)
    {
        $params = App::request('get', 'q');

        $q = null;
        if($params) {
            $q = [
                '|id'=>$params,
                '|title%='=>$params,
                '|summary%='=>$params,
                '|link%='=>$params,
            ];
        }

        $r = [];
        $L = static::find($q, null, null, false);
        if($L) {
            $sep = Studio::config('breadcrumb_separator');
            foreach($L as $i=>$o) {
                $g = null;
                if($a = $o->getAncestors(null, 'string')) {
                    $g = implode($sep, $a).$sep;
                }
                $r[] = ['value'=>$o->id, 'label'=>$o->title, 'group'=>$g, 'position'=>1];
                $o->childrenOptions($r);
            }
        }
        S::output($r, 'json');
    }

    public function childrenOptions(&$r)
    {
        if($L = $this->getChildren(null, 'string')) {
            foreach($L as $i=>$o) {
                $r[] = ['value'=>$o->id, 'label'=>$o->title, 'position'=>1, 'className'=>'z-indent'];
                $r[] = ['value'=>$this->id, 'label'=>$this->title, 'position'=>$i+2, 'className'=>'z-nolabel'];
                //$o->childrenOptions($r);
            }
        }
    }

    public static function fromFile($file, $attr=[])
    {
        return self::_checkPage($file, true, false, $attr);
    }

}