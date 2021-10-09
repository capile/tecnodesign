<?php
/**
 * E-Studio pages and files
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
class Tecnodesign_Studio_Entry extends Tecnodesign_Studio_Model
{
    /**
     * Configurable behavior
     * This is only available for customizing Studio, please use the tdzEntry class
     * within your lib folder (not TDZ_ROOT!) or .ini files
     */
    public static 
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
        $uploadDir,                                    // deprecated, use tdz::uploadDir
        $indexFile='index',                            // filename to use for directory reads
        $previewEntryType=array('feed','file','page'), // which entry types can be previewed
        $hostnames=[];                                 // list of hostnames that should be skipped in the link validation

    public static $s=1;
    public static $schema;
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

        return Tecnodesign_Studio::interfaceId($this, $prefix);
    }

    public function render()
    {
        if(Tecnodesign_Studio::$private) {
            $cch = 'private';
            if(!Tecnodesign_Studio::$staticCache) $cch .= ', no-cache';
            tdz::cacheControl($cch, Tecnodesign_Studio::$staticCache);
        } else if(Tecnodesign_Studio::$staticCache) {
            Tecnodesign_App::$afterRun['staticCache']=array('callback'=>array('Tecnodesign_Studio','setStaticCache'));
            tdz::cacheControl('public', Tecnodesign_Studio::$cacheTimeout);
        }
        Tecnodesign_Studio::$page = $this->id;
        if($this->link!=tdz::scriptName()) tdz::scriptName($this->link);
        tdz::$variables['entry'] = $this;
        $m = 'render'.ucfirst(tdz::camelize($this->getType()));
        if(!method_exists($this, $m)) {
            Tecnodesign_Studio::error(404);
        }
        $r = $this->$m();
        tdz::$variables['template']=null;
        if(is_array($r)) {
            foreach($r as $k=>$v) {
                tdz::$variables[$k] = $v;
                unset($r[$k], $k, $v);
            }
        } else {
            tdz::$variables['layout'] = $r;
        }
        unset($r);
    }

    public function renderPage()
    {
        $master = $this->master;
        $c = $this->getCredentials('previewPublished');
        if(!$master || is_null($c)) {
            if(Tecnodesign_Studio::$connection) {
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
                $c = Tecnodesign_Studio::credential('previewPublished');
            }
        }
        if(is_null($c) && $this->credential) $c = $this->credential;
        else if($c==='*') $c = false;

        if($c && !(($U=tdz::getUser()) && $U->hasCredential($c, false))) {
            Tecnodesign_Studio::error($U->isAuthenticated() ?403 :401);
            return false;
        }
        if($c) {
            Tecnodesign_Studio::$private = (is_array($c))?($c):(array($c));
        }
        if(Tecnodesign_Studio::$staticCache && !is_null($c)) {
            Tecnodesign_Studio::$staticCache=false;
        }
        $id = ($this->id)?($this->id):(tdz::hash($this->link, null, 'uuid'));
        unset($c);
        if(Tecnodesign_Studio::$staticCache && Tecnodesign_Studio::$cacheTimeout) {
            $cf = Tecnodesign_Cache::cacheDir().'/'.Tecnodesign_Cache::siteKey().'/'.tdz::env().'/e-studio/page/e'.$id.'-'.$this->version.'-'.tdz::$lang.'.php';
            if(file_exists($cf) && (!Tecnodesign_Studio::$cacheTimeout || time()-filemtime($cf) < Tecnodesign_Studio::$cacheTimeout)) {
                return array('layout'=>substr($cf, 0, strlen($cf)-4), 'template'=>'');
            }
        }
        // layout file
        tdz::$variables['route']['layout'] = $master = Tecnodesign_Studio::templateFile($master, tdz::$variables['route']['layout'], self::$layout, 'layout');

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
        $contents = $this->getRelatedContent();
        $langs = '<meta name="language" content="'.tdz::$lang.'" />';
        if(isset(Tecnodesign_Studio::$app->tecnodesign['languages'])) {
            if(!Tecnodesign_Studio::$languages) {
                Tecnodesign_Studio::$languages=Tecnodesign_Studio::$app->tecnodesign['languages'];
            }
            ksort(Tecnodesign_Studio::$languages);
            $la = Tecnodesign_Studio::$languages;
            foreach($la as $lang) {
                if($lang==tdz::$lang) continue;
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
            foreach($contents as $C) {
                $dyn = false;
                if($C->content_type=='php') {
                    $dyn = $this->dynamic = true;
                } else if($C->content_type=='widget') {
                    $d = $C->getContents();
                    if(isset($d['app']) && isset(tdzContent::$widgets[$d['app']]['cache']) && !tdzContent::$widgets[$d['app']]['cache'])
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
            }
        }

        if(!$dyn && $this->modified) {
            Tecnodesign_App::response(array('headers'=>array('Last-Modified'=>gmdate('D, d M Y H:i:s', $this->modified).' GMT')));
        } else {
            $this->modified = time();
        }

        $slots['meta'][] = '<meta http-equiv="last-modified" content="'. gmdate('D, d M Y H:i:s',$this->modified) . ' GMT" />';

        $merge = array();
        $slotelements = array();
        foreach(static::$slotElements as $n) {
            if(is_string($n)) $slotelements[$n] = true;
        }
        $addbr='';
        $layout = '<'."?php\n"
            .(($this->dynamic)?("//dynamic\n"):("//static\n"));
        if($dyn && Tecnodesign_Studio::$staticCache) Tecnodesign_Studio::$staticCache=false;
        foreach($slots as $slotname=>$slot) {
            ksort($slot);
            $first = true;
            $layout .= "\n\${$slotname} = ";
            if(isset($slotelements[$slotname]) && $slotelements[$slotname]) {
                $merge[]='$'.$slotname;
            } else if($slotname!='meta' && $slotname!='title') {
                $merge[]='$'.$slotname;
            } else if(count($slot)==0) {
                $layout .= "''";
                $first = false;
            }
            foreach($slot as $slotfrag) {
                if(!is_array($slotfrag))$slotfrag=array($slotfrag);
                foreach($slotfrag as $v) {
                    if($first) {
                        $first = false;
                        $layout .= "";
                    } else {
                        $layout .= "\n    .";
                    }
        
                    if(is_array($v) && isset($v['before'])) {
                        $layout .= var_export($v['before'],true).'.';
                    }
        
                    if(is_array($v) && isset($v['export'])) {
                        $layout .= $v['export'];
                    } else if(is_array($v)) {
                        $layout .= var_export($v['content'],true);
                    } else {
                        $layout .= var_export($v,true);
                    }
                    if(is_array($v) && isset($v['after'])) {
                        $layout .= '. '.var_export($v['after'],true);
                    }
                }
            }
            if($first) $layout .= "''";
            $layout .= ';';
        }

        $sid = $this->studioId();

        foreach($slots as $slotname=>$slot) {
            if(isset($slotelements[$slotname]) && $slotelements[$slotname]) {
                $layout .= "\n\${$slotname} = '<{$slotname}><div id=\"{$slotname}\" data-studio=\"{$sid}\">'\n    . tdz::get('before-{$slotname}').\${$slotname}.tdz::get('{$slotname}').tdz::get('after-{$slotname}')\n    . '</div></{$slotname}>{$addbr}';";
            } else if($slotname!='meta' && $slotname!='title') {
                $layout .= "\n\${$slotname} = '<div id=\"{$slotname}\" data-studio=\"{$sid}\">'\n    . tdz::get('before-{$slotname}').\${$slotname}.tdz::get('{$slotname}').tdz::get('after-{$slotname}')\n    . '</div>{$addbr}';";
            }
        }
        $layout .= "\n\$meta.=tdz::meta();";
        if($this->wrapper && is_array($this->wrapper)) {
            foreach($this->wrapper as $n=>$s) {
                $mrg = array();
                $idx = null;
                foreach($merge as $i=>$slotname) {
                    if(in_array(substr($slotname,1), $s)) {
                        if(is_null($idx)) {
                            $idx = $i;
                            $mrg[$idx] = "'<div id=\"{$n}\">'.".$slotname;
                        } else {
                            $mrg[$idx] .= '.'.$slotname;
                        }
                    } else {
                        $mrg[$i] = $slotname; 
                    }
                }
                if(!is_null($idx)) {
                    $mrg[$idx] .= ".'</div>'";
                    $merge = array_values($mrg);
                }
            }
        }
        if(count($merge)>0) {
            $layout .= "\n\$content = ".implode('.',$merge).';';
        }
        // in Tecnodesign_App::runTemplate
        $app = get_class(Tecnodesign_Studio::$app);
        if($app::$assets) {
            foreach($app::$assets as $i=>$n) {
                $app::asset($n);
                unset($app::$assets[$i], $i, $n);
            }
        }
        unset($app);

        $mc = file_get_contents($master);
        if(substr($mc, 0, 5)=='<'.'?php') $layout .= "\n".substr($mc, 5);
        else $layout .= "\n?".'>'.$mc;
        unset($mc, $master);
        if(Tecnodesign_Studio::$private) {
            Tecnodesign_Studio::$private = array_unique(Tecnodesign_Studio::$private);
            $cch = 'private';
            if(!Tecnodesign_Studio::$staticCache) $cch .= ', no-cache';
            tdz::cacheControl($cch, Tecnodesign_Studio::$staticCache);
        }
        if(Tecnodesign_Studio::$staticCache && Tecnodesign_Studio::$cacheTimeout && isset($cf) && tdz::save($cf, $layout, true)) {
            return array('layout'=>substr($cf, 0, strlen($cf)-4), 'template'=>'');
        } else {
            return tdz::exec(array('pi'=>substr($layout,5), 'variables'=>Tecnodesign_App::response()));
        }
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
            } else if(!file_exists($file=static::file($f))
                && !file_exists($file=S_VAR.'/'.$f)
                && !file_exists($file=TDZ_DOCUMENT_ROOT.'/'.$f)
                && !file_exists($file=Tecnodesign_Studio::$app->tecnodesign['document-root'].'/'.$f)
            ) {
                $file = null;
            }
            if(file_exists($ufile=tdz::uploadDir().'/'.$f)) {
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

        if(!isset(tdz::$variables['cache-control'])) {
            if(Tecnodesign_Studio::$private) {
                $cch = 'private';
                if(!Tecnodesign_Studio::$staticCache) $cch .= ', no-cache';
                tdz::set('cache-control', $cch);
            } else {
                tdz::set('cache-control', 'public');
            }
        }

        $file = $this->getFile();
        if(!$file) {
            Tecnodesign_Studio::error(404);
            return false;
        }
    
        $link = $this->link;
        if(!$link) $link = tdz::scriptName();
        $fname = basename($link);
        if($optimize) {
            $ext = strtolower(preg_replace('/.*\.([a-z0-9]{1,5})$/i','$1',basename($file)));
            $actions=Tecnodesign_Studio::$app->studio['assets_optimize_actions'];
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
                        $data=tdz::$method($file,$params);
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
        if(substr($this->format, -4)!='html') tdz::download($file,($this->format)?($this->format):(tdz::fileFormat($file)));
        return $file;
    }
    public function renderFile()
    {
        $c = $this->getCredentials('previewPublished');
        $U=tdz::getUser();
        if(is_null($c)) {
            $c = Tecnodesign_Studio::credential('previewPublished');
        }

        if($c && !(($U=tdz::getUser()) && $U->hasCredential($c, false))) {
            Tecnodesign_Studio::error($U->isAuthenticated() ?403 :401);
            return false;
        }
        $file = $this->filePreview();
        tdz::download($file,$this->format);
    }

    public function renderEntry($template=false, $args=array())
    {
        $a = array('script'=>Tecnodesign_Studio::templateFile($template, 'tdz_entry'),
            'variables'=>$this->asArray()
        );
        if(is_array($args) && count($args)>0)
            $a['variables'] +=$args;
        $a['variables']['entry']=$this;
        return tdz::exec($a);
    }

    public function renderFeed($template=false, $args=array())
    {
        $tpl=(substr(tdz::scriptName(), 0, strlen($this->link))==$this->link)?('tdz_atom'):('tdz_feed');
        $template = Tecnodesign_Studio::templateFile($template, $tpl);
        return $this->renderEntry(substr($template, 0, strlen($template)-4), $args);
    }
    

    public static function feedPreview($o)
    {
        $entry=false;
        if(isset($o['entry'])) {
            $entry = (is_object($o['entry']))?($o['entry']):(static::latest($o['entry']));
        }
        if($entry && !Tecnodesign_Studio::getPermission('previewEntry', $entry)) {
            $entry=false;
        }
        if(!$entry) return '';
        $o['entry']=$entry;
        if(isset($o['master'])) {
            return tdz::exec(array('script'=>$o['master'], 'variables'=>$o));
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
        if($this->id && Tecnodesign_Studio::$connection) {
            $P = tdzPermission::find(array('entry'=>$this->id,'role'=>'previewPublished'),1,array('credentials'));
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
            $html = (Tecnodesign_Interface::format()=='html');
            $r = $g = $all = $none = null;
            $s = ($html) ?'' :[];
            foreach($P as $i=>$o) {
                if(!$r) $r = $o->choicesRole();
                if(!$g) $g = $o->choicescredentials();
                if(!$all) $all = tdz::t('Everyone', 'model-tdz_permissions');
                if(!$none) $none = tdz::t('No one', 'model-tdz_permissions');

                if($co=$o->credentials) {
                    if(!is_array($co)) {
                        if(substr($co, 0, 1)=='{') $co = tdz::unserialize($co, 'json');
                        else $co = preg_split('/\s*\,\s*/', $co, null, PREG_SPLIT_NO_EMPTY);
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
                    $s .= '<li>'.tdz::xml($c).'</li>';
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
        return tdzContent::find($search,$limit,$scope,$asCollection,$orderBy,$groupBy);
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
            $r = Tecnodesign_Studio_Entry::find($search,$limit,$scope,$asCollection,$orderBy,$groupBy);
            if($r) $r=[$r];
            return $r;
        }
        return Tecnodesign_Studio_Entry::find($search,$limit,$scope,$asCollection,$orderBy,$groupBy);
    }

    public function getTags($search=array(), $scope='link', $asCollection=false, $orderBy=null, $groupBy=null)
    {
        if(!$this->id) return null;
        if(!is_array($search)) $search = array();
        $search['entry'] = $this->id;
        $L = tdzTag::find($search,null,$scope,true,$orderBy,$groupBy);
        if($L) $L->setQueryKey('slug');
        else if($asCollection) $L = new Tecnodesign_Collection(null, 'Tecnodesign_Studio_Tags', null, 'slug');
        else $L = [];

        return ($asCollection || !$L) ?$L :$L->getItems();
    }

    public function validateTags($v)
    {
        $rel = [];
        if($v) {
            if(!is_array($v)) $v = preg_split('/\s*\,\s*/', $v, null, PREG_SPLIT_NO_EMPTY);
            $tags = $this->getTags();
            if(!$tags) $tags = [];
            foreach($v as $o) {
                $o = trim($o);
                if(!$o) continue;
                $k = tdz::slug($o);
                if(isset($tags[$k])) {
                    if($tags[$k]->tag!=$o) $tags[$k]->tag = $o;
                    $rel[] = $tags[$k];
                    unset($tags[$k]);
                } else {
                    $rel[] = ['entry'=>$this->id, 'tag'=>$o, 'slug'=>tdz::slug($o)];
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

    public static function file($url, $check=true)
    {
        $f = Tecnodesign_Studio::documentRoot() . ((substr($url, 0, 1)!='/') ?'/' :'').$url;
        if($check && !file_exists($f)) $f=null;
        return $f;
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
            $f = static::file(str_replace('.', '[-.]', $url), false);
            if(substr($f, -1)=='/') {
                $f.=static::$indexFile;
            } else if(is_dir($f)) {
                if($redirect && substr(tdz::scriptName(), -1)!='/') {
                    tdz::redirect($url.'/');
                }
                $f .= '/'.static::$indexFile;
            } else if($redirect && substr(tdz::scriptName(), -1)=='/') {
                tdz::redirect($url);
            }
            static $pat;
            if(is_null($pat)) {
                $pat = '{,.'.tdz::$lang.'}{,.'.implode(',.',array_keys(tdzContent::$contentType)).'}';
            }
            $pages = self::glob($f.$pat);
            if($pages && count($pages)>0) {
                foreach($pages as $page) {
                    if($P=self::_checkPage($page, $url)) {
                        break;
                    }
                }
            }
        } else if($url) {
            if(in_array('php', tdzContent::$multiviewContentType) && is_file($f=static::file($url.'.php')))
                $P=self::_checkPage($f, $url, $multiview);
            if(in_array('md', tdzContent::$multiviewContentType) && is_file($f=static::file($url.'.md')))
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

    protected static function _checkPage($page, $url, $multiview=false)
    {
        if(is_dir($page)) return;
        $base = preg_replace('/\..*/', '', basename($page));
        $pn = basename($page);
        //if(substr($pn, 0, strlen($base)+1)==$base.'.') $pn = substr($pn, strlen($base)+1);
        $pp = explode('.', $pn);
        if(in_array('_tpl_', $pp)) return; // templates cannot be pages
        $ext = strtolower(array_pop($pp));
        //if(is_array(tdzContent::$disableExtensions) && in_array($ext, tdzContent::$disableExtensions)) return;

        if($ext=='html' && stripos(fgets(fopen($page, 'r')), 'doctype')) $isPage=false;
        else $isPage = isset(tdzContent::$contentType[$ext]);

        if($isPage) {
            if($pn==$base) return; // cannot access content directly
            else if($base.'.'.$ext!=$pn && $base.'.'.tdz::$lang.'.'.$ext!=$pn) return; // cannot have slots/position
            // last condition: cannot have any valid slotname within $pp > 0
            foreach(array_keys(static::$slots) as $slot) {
                if(in_array($slot, $pp)) return;
                unset($slot);
            }
            $format='text/html';
        } else {
            $format = (isset(tdz::$formats[$ext]))?(tdz::$formats[$ext]):(null);
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
                $meta = Tecnodesign_Yaml::load($m);
                if($multiview && (!isset($meta['multiview']) || !$meta['multiview'])) return;
            }
        } else if($multiview) return;

        $meta = static::loadMeta($url, $page, $meta);

        $id = substr($page, strlen(Tecnodesign_Studio::documentRoot()));
        $t = date('Y-m-d\TH:i:s', filemtime($page));
        $P = new Tecnodesign_Studio_Entry(array(
            //'id'=>tdz::hash($id, null, 'uuid'),
            'source'=>$id,
            'link'=>$url,
            'published'=>$t,
            'format'=>$format,
            'type'=>($isPage)?('page'):('file'),
            'updated'=>$t,
        ));
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
        }

        // get metadata
        if(file_exists($mf=$page.'.'.tdz::$lang.'.meta') || file_exists($mf=$page.'.meta')) {
            $m = Tecnodesign_Yaml::load($mf);
            if(is_array($m)) {
                $meta += $m;
            }
            unset($m);
        }
        $d=$url;
        $p=preg_replace('#/'.static::$indexFile.'.[a-z]+$#', '', $page);
        while(strrpos($d, '/')!==false) {
            if(file_exists($mf=$p.'/.meta')) {
                $m = Tecnodesign_Yaml::load($mf);
                if(is_array($m)) {
                    foreach($meta as $mn=>$mv) if(!$mv) unset($meta[$mn]); // ignore blanks
                    $meta += $m;
                }
                unset($m);
            }
            if(file_exists($mf=$p.'.meta')) {
                $m = Tecnodesign_Yaml::load($mf);
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
            if(isset($meta['link']) && $meta['link']!=$url && $meta['link']!=tdz::requestUri()) {

                if(isset($meta['credential']) && $meta['credential'] && !(($U=tdz::getUser()) && $U->hasCredential($meta['credential'], false))) {
                    unset($meta['link']);
                } else {
                    tdz::redirect($meta['link']);
                }
            }
            if(isset($meta['languages'])) Tecnodesign_Studio::$languages = $meta['languages'];
            Tecnodesign_Studio::addResponse($meta);
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
            $published = !(Tecnodesign_Studio::$private);
            $f = array(
                '|entry'=>$this->id,
                '|ContentDisplay.link'=>array('*', $this->link),
            );
            if(strrpos($this->link, '/')>1) {
                $l = substr($this->link, 0, strrpos($this->link, '/'));
                while($l) {
                    $f['|ContentDisplay.link'][] = $l.'/*';
                    $l = substr($l, 0, strrpos($l, '/'));
                }
            }
            $r = Tecnodesign_Studio_Content::find($f,null,null,false);
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
        } else {
            $r = null;
        }

        // get file-based page definitions
        $u = $this->link;
        $root = Tecnodesign_Studio::documentRoot();
        if(substr($u, -1)=='/') $u.=static::$indexFile;
        else if(is_dir($root.$u)) $u .= '/'.static::$indexFile; // redirect?
        else if(preg_match('/\.('.implode('|',array_keys(tdzContent::$contentType)).')$/', $u, $m)) {
            $u = substr($u, 0, strlen($u)-strlen($m[0]));
        }

        if(strpos($u, '.')) $u = str_replace('.', '[-.]', $u);

        static $pat;
        if(is_null($pat)) {
            $pat = '{,.*}{,.'.tdz::$lang.'}{.'.implode(',.',array_keys(tdzContent::$contentType)).'}';
        }
        $pages = self::glob($root.$u.$pat);

        //$pages = glob($f.'.*');
        if($checkTemplate) {
            while(strrpos($u, '/')!==false) {
                $u = substr($u, 0, strrpos($u, '/'));
                $pages = array_merge($pages, glob($root.$u.'/_tpl_.*'));
                if(Tecnodesign_Studio::$templateRoot) {
                    $pages = array_merge($pages, glob(Tecnodesign_Studio::$templateRoot.$u.'/_tpl_.*'));
                }
            }
        }
        unset($f, $u);
        $sort = false;
        if($pages) {
            //$link = (substr($this->link, -1)=='/')?(self::$indexFile):(basename($this->link));
            if(!$r) $r=array();
            foreach($pages as $page) {
                if(is_dir($page)) continue;
                $C = Tecnodesign_Studio::content($page, $checkLang, $checkTemplate);
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

        if(Tecnodesign_App::request('post', 'link')) {
            if($this->id && !$this->type) $this->refresh(['type']);

            if($this->type=='page' || $this->type=='file') {
                // search for duplicates
                $q = ['type'=>['page', 'file'], 'link'=>$v];
                if($this->id) $q['id!=']=$this->id;
                if(self::find($q,1,['link'])) {
                    throw new Tecnodesign_Exception(tdz::t('There\'s already a page or file with this link.', 'exception'));
                }
            }
        }

        return $v;
    }

    public function previewRelated()
    {
        if($L=$this->getAncestors(null, 'string')) {
            $n = tdz::xml((string) array_pop($L));
            if($L) {
                $sep = Tecnodesign_Studio::config('breadcrumb_separator');
                $n = '<em>'.tdz::xml(implode($sep, $L).$sep).'</em>'.$n;
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

        if(Tecnodesign_Studio_Interface::format()=='html') {
            $v = '<a class="z-ellipsis" title="'.tdz::xml(tdz::buildUrl($v)).'" href="'.tdz::xml($v).'" target="_blank">'.tdz::xml($v).'</a>';
        }
        return $v;
    }

    public function previewTitleTags()
    {
        $t = (isset($this->_title_tags)) ?$this->_title_tags :$this->title;
        if(Tecnodesign_Interface::format()=='html') {
            $t = tdz::xml($t);
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
            $html = (Tecnodesign_Interface::format()=='html');
            $s = ($html) ?null :[];
            if($link && is_null($url)) {
                $url = tdz::scriptName(true);
                $tag = tdz::slug(tdz::t('Tag', 'model-tdz_entries'));
                $qso = array_diff_key(Tecnodesign_App::request('get'), ['_uid'=>null, 'ajax'=>null, $tag=>null]);
                $url .= (($qso) ?'?'.http_build_query($qso).'&' :'?').$tag.'=';
                unset($qso);
            }
            foreach($T as $i=>$o) {
                if($html) {
                    $s.= ($link) ?'<a class="z-i-a z-tag" href="'.tdz::xml($url.$o->slug).'">'.tdz::xml($o->tag).'</a>' :'<span class="z-tag">'.tdz::xml($o->tag).'</span>';
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
        return Tecnodesign_Studio::$home.'/'.$this->type.'/v';
    }

    public function previewContents()
    {
        $tpl = '<div class="tdz-i-scope-block" data-action-schema="preview" data-action-url="'.tdz::scriptName(true).'">'
           .     '<a href="'.Tecnodesign_Studio::$home.'/content/n?entry='.$this->id.'&amp;position={position}&amp;slot={slot}&amp;scope=entry-content&amp;next=preview" class="tdz-i-button z-align-bottom z-i--new" data-inline-action="new"></a>'
           . '</div>';
        $r = str_replace(['{position}', '{slot}'], [1, static::$slot], $tpl);
        $slots = [];

        if($L=$this->getContents([], 'content', false, ['position'=>'asc', 'id'=>'desc'])) {
            $E = (isset(tdz::$variables['entry'])) ?tdz::$variables['entry'] :null;
            tdz::$variables['entry'] = $this;
            foreach($L as $i=>$o) {
                $ct = ($o->content_type) ?$o->content_type :'text';
                $slot = ($o->slot) ?$o->slot :static::$slot;
                if(!isset($slots[$slot])) $slots[$slot] = '';
                $slots[$slot] .= '<div class="ih5 z-item z-inner-block">'
                    .   '<div class="tdz-i-scope-block" data-action-expects-url="'.Tecnodesign_Studio::$home.'/content/u/'.$o->id.'" data-action-schema="preview" data-action-url="'.tdz::scriptName(true).'">'
                    .     '<a href="'.Tecnodesign_Studio::$home.'/content/u/'.$o->id.'?scope=u-'.$ct.'&amp;next=preview" class="tdz-i-button z-i--update" data-inline-action="update"></a>'
                    .     '<a href="'.Tecnodesign_Studio::$home.'/content/d/'.$o->id.'?scope=u-'.$ct.'&amp;next='.tdz::scriptName(true).'" class="tdz-i-button z-i--delete" data-inline-action="delete"></a>'
                    . (($o->content_type && in_array($o->content_type, $o::$previewContentType))
                        ?'<div class="z-t-center z-app-image"><span class="z-t-inline z-t-left">'.$o->previewContent().'</span></div>'
                        :$o->previewContent()
                      )
                    .    '<dl class="z-i-field i1s2"><dt>'.tdz::t('Content Type', 'model-tdz_contents').'</dt><dd>'.$o->previewContentType().'</dd></dl>'
                    .    '<dl class="z-i-field i1s2"><dt>'.tdz::t('Position', 'model-tdz_contents').'</dt><dd>'.tdz::xml($o->position).'</dd></dl>'
                    .   '</div>'
                    . '</div>'
                    . str_replace(['{position}', '{slot}'], [$o->position+1, $slot], $tpl);
            }
            tdz::$variables['entry'] = $E;
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
            return '<span class="'.$c.'" title="'.tdz::xml(substr(strip_tags($r), 0, 500)).'">'
                . $r
                . '</div>';
        }
    }

    public function getStudioLink()
    {
        if($this->id && strpos($this->id, '-')!==false) {
            // grouped folder
            $url = (isset($this->_studio_link)) ?$this->_studio_link :$this->link;
            return ['_studio_link' => Tecnodesign_Studio::$home.'/i/q?url='.urlencode($url)];
        }


        if(!isset($this->type)) {
            $this->refresh(['type']);
        }

        if($this->type) {
            return Tecnodesign_Studio::$home.'/'.$this->type.'/q';
        }
    }

    public function previewSource()
    {
        $this->refresh(['source', 'format']);
        if(!$this->source) return;

        if(Tecnodesign_Interface::format()=='html' && ($f=$this->getFile())) {
            if(substr($this->format, 0, 6)=='image/') {
                return '<img class="z-app-image" src="data:'.tdz::xml($this->format).';base64,'.base64_encode(file_get_contents($f)).'" alt="'.tdz::xml($this->title).'" />';
            }
            return basename($f);
        }

        return $this->source;
    }

    public function previewStudioLink()
    {
        $url = (isset($this->_studio_link)) ?$this->_studio_link :$this->link;
        $html = (Tecnodesign_Studio_Interface::format()=='html');

        $url = '/'.basename($url);

        if($this->id && strpos($this->id, '-')!==false) {
            // grouped folder
        }


        return ($html) ?\tdz::xml($url) :$url;

    }

    public function previewType()
    {
        if($this->type) {
            return tdz::xml($this->choicesTypes($this->type));
        }
    }

    public function choicesTypes($type=null)
    {
        static $r;

        if(is_null($r)) {
            $r = [];
            foreach(static::$types as $k=>$v) {
                if(substr($v, 0, 1)==='*') {
                    $v = tdz::t(substr($v, 1), 'model-'.static::$schema->tableName);
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

        if(!$master && Tecnodesign_Studio::$indexTimeout) {
            $master = Tecnodesign_Cache::get('studio/entry/master', Tecnodesign_Studio::$indexTimeout);
        }

        if(!$master) {
            $master = [];
            $g = Tecnodesign_Studio::templateDir();
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
            if($master && Tecnodesign_Studio::$indexTimeout) {
                Tecnodesign_Cache::set('studio/entry/master', $master, Tecnodesign_Studio::$indexTimeout);
            }
        }

        return $master;
    }

    public function previewSummary()
    {
        return tdz::markdown($this->summary);
    }

    public static function executeList($Interface, $req=[])
    {
        $A = $Interface['text'];

        $A['listLimit'] = 100;
        $A['listOffset'] = 0;
        $p=Tecnodesign_App::request('get', $Interface::REQ_OFFSET);
        if($p!==null && is_numeric($p)) {
            $p = (int) $p;
            if($p < 0) {
                $p = $p*-1;
                if($p > $count) $p = $p % $count;
                if($p) $p = $count - $p;
            }
            $A['listOffset'] = $p;
        } else if(($pag=Tecnodesign_App::request('get', $Interface::REQ_PAGE)) && is_numeric($pag)) {
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
        $params = Tecnodesign_App::request('get', 'q');

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
            $sep = Tecnodesign_Studio::config('breadcrumb_separator');
            foreach($L as $i=>$o) {
                $g = null;
                if($a = $o->getAncestors(null, 'string')) {
                    $g = implode($sep, $a).$sep;
                }
                $r[] = ['value'=>$o->id, 'label'=>$o->title, 'group'=>$g, 'position'=>1];
                $o->childrenOptions($r);
            }
        }
        tdz::output($r, 'json');
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

}
