<?php
/**
 * E-Studio pages and files
 *
 * PHP version 5.3
 *
 * @category  Model
 * @package   Estudio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Estudio_Entry extends Tecnodesign_Model
{
    /**
     * Configurable behavior
     * This is only available for customizing Estudio, please use the tdzEntry class
     * within your lib folder (not TDZ_ROOT!) or .ini files
     */
    public static 
        $layout='layout',                               // default layout
        $slots=array(                                   // default slots
            'header'=>null,
            'body'=>null,
            'footer'=>null,
        ),
        $types = array(
            'page'=>'Page',
            'feed'=>'Newsfeed',
            'entry'=>'Article',
            'file'=>'Uploaded file',
        ),
        $pageDir='estudio/page',                        // where pages are stored (relative to TDZ_VAR)
        $uploadDir='estudio/upload',                    // where uploads are stored (relative to TDZ_VAR)
        $previewEntryType=array('feed','file','page');  // which entry types can be previewed

    public static $s=1;
    /**
     * Tecnodesign_Model schema
     */
    //--tdz-schema-start--2014-12-27 18:32:23
    public static $schema = array (
      'database' => 'estudio',
      'tableName' => 'tdz_entries',
      'label' => '*Entries',
      'className' => 'tdzEntry',
      'columns' => array (
        'id' => array ( 'type' => 'int', 'increment' => 'auto', 'null' => false, 'primary' => true, ),
        'title' => array ( 'type' => 'string', 'size' => '200', 'null' => true, ),
        'summary' => array ( 'type' => 'string', 'size' => '', 'null' => true, ),
        'link' => array ( 'type' => 'string', 'size' => '200', 'null' => true, ),
        'source' => array ( 'type' => 'string', 'size' => '200', 'null' => true, ),
        'format' => array ( 'type' => 'string', 'size' => '100', 'null' => true, ),
        'published' => array ( 'type' => 'datetime', 'null' => true, ),
        'language' => array ( 'type' => 'string', 'size' => '10', 'null' => true, ),
        'type' => array ( 'type' => 'string', 'size' => '100', 'null' => true, ),
        'master' => array ( 'type' => 'string', 'size' => '100', 'null' => true, ),
        'version' => array ( 'type' => 'int', 'null' => true, ),
        'created' => array ( 'type' => 'datetime', 'null' => false, ),
        'updated' => array ( 'type' => 'datetime', 'null' => false, ),
        'expired' => array ( 'type' => 'datetime', 'null' => true, ),
      ),
      'relations' => array (
        'Tag' => array ( 'local' => 'id', 'foreign' => 'entry', 'type' => 'many', 'className' => 'Tecnodesign_Estudio_Tag', ),
        'Content' => array ( 'local' => 'id', 'foreign' => 'entry', 'type' => 'many', 'className' => 'Tecnodesign_Estudio_Content', ),
        'Permission' => array ( 'local' => 'id', 'foreign' => 'entry', 'type' => 'many', 'className' => 'Tecnodesign_Estudio_Permission', ),
        'Child' => array ( 'local' => 'id', 'foreign' => 'entry', 'type' => 'many', 'className' => 'Tecnodesign_Estudio_Relation', ),
        'Parent' => array ( 'local' => 'id', 'foreign' => 'parent', 'type' => 'many', 'className' => 'Tecnodesign_Estudio_Relation', ),
        'Relation' => array ( 'local' => 'id', 'foreign' => 'entry', 'type' => 'many', 'className' => 'tdzRelation', ),
        'Children' => array ( 'local' => 'id', 'foreign' => 'parent', 'type' => 'many', 'className' => 'tdzRelation', ),
      ),
      'scope' => array (
        'link' => array ( 'id', 'link', 'title', ),
        'estudio-new' => array ( 'type', 'title', 'link', 'summary', 'published', ),
        'estudio-edit' => array ( 'type', 'title', 'link', 'summary', 'published', 'contents', ),
      ),
      'order' => array (
        'version' => 'desc',
      ),
      'events' => array (
        'before-insert' => array ( 'actAs', ),
        'before-update' => array ( 'actAs', ),
        'before-delete' => array ( 'actAs', ),
        'active-records' => 'expired is null',
      ),
      'form' => array (
        'type' => array ( 'bind' => 'type', 'type' => 'select', 'choices' => 'Tecnodesign_Estudio::config(\'entry_types\')', 'fieldset' => '*Properties', 'class' => 'estudio-left', ),
        'title' => array ( 'bind' => 'title', 'fieldset' => '*Properties', 'class' => 'estudio-clear', 'required' => true, ),
        'link' => array ( 'bind' => 'link', 'attributes' => array ( 'data-type' => 'url', ), 'fieldset' => '*Properties', ),
        'summary' => array ( 'bind' => 'summary', 'type' => 'html', 'fieldset' => '*Properties', 'class' => 'estudio-clear', ),
        'published' => array ( 'bind' => 'published', 'type' => 'datetime', 'fieldset' => '*Properties', 'class' => 'estudio-left', ),
        'contents' => array ( 'bind' => 'Content', 'type' => 'form', 'fieldset' => '*Content', ),
      ),
      'actAs' => array (
        'before-insert' => array ( 'auto-increment' => array ( 'id', ), 'timestampable' => array ( 'created', 'updated', ), ),
        'before-update' => array ( 'auto-increment' => array ( 'version', ), 'timestampable' => array ( 'updated', ), ),
        'before-delete' => array ( 'auto-increment' => array ( 'version', ), 'timestampable' => array ( 'updated', ), 'soft-delete' => array ( 'expired', ), ),
      ),
    );
    protected $id, $title, $summary, $link, $source, $format, $published, $language, $type, $master, $version=false, $created, $updated=false, $expired, $Tag, $Content, $Permission, $Child, $Parent, $Relation, $Children;
    //--tdz-schema-end--
    
    protected $dynamic=false, $credential;

    public function render()
    {
        if(Tecnodesign_Estudio::$private) tdz::cacheControl('nocache, private', 0);
        else if(Tecnodesign_Estudio::$staticCache) {
            Tecnodesign_App::$afterRun['staticCache']=array('callback'=>array('Tecnodesign_Estudio','setStaticCache'));
            tdz::cacheControl('public', Tecnodesign_Estudio::$cacheTimeout);
        }
        Tecnodesign_Estudio::$page = $this->id;
        if($this->link!=tdz::scriptName()) tdz::scriptName($this->link);
        tdz::$variables['entry'] = $this;
        $m = 'render'.ucfirst(tdz::camelize($this->type));
        if(!method_exists($this, $m)) {
            Tecnodesign_Estudio::error(404);
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
            if(is_null($c)) {
                $c = Tecnodesign_Estudio::credential('previewPublished');
            }
        }
        if($c && !(($U=tdz::getUser()) && $U->hasCredential($c, false))) {
            //tdz::debug(__METHOD__, $c);
            Tecnodesign_Estudio::error(403);
            return false;
        }
        if(Tecnodesign_Estudio::$staticCache && !is_null($c)) {
            Tecnodesign_Estudio::$staticCache=false;
        }
        $id = ($this->id)?($this->id):(tdz::hash($this->link, null, 'uuid'));
        unset($c);
        if(Tecnodesign_Estudio::$cacheTimeout) {
            $cf = Tecnodesign_Cache::cacheDir().'/'.Tecnodesign_Cache::siteKey().'/'.tdz::env().'/e-studio/page/e'.$id.'-'.$this->version.'-'.tdz::$lang.'.php';
            if(file_exists($cf) && (!Tecnodesign_Estudio::$cacheTimeout || time()-filemtime($cf) < Tecnodesign_Estudio::$cacheTimeout)) {
                return array('layout'=>substr($cf, 0, strlen($cf)-4), 'template'=>'');
            }
        }
        // layout file
        tdz::$variables['route']['layout'] = $master = Tecnodesign_Estudio::templateFile($master, tdz::$variables['route']['layout'], self::$layout, 'layout');
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
        if(isset(Tecnodesign_Estudio::$app->tecnodesign['languages'])) {
            if(!Tecnodesign_Estudio::$languages) {
                Tecnodesign_Estudio::$languages=Tecnodesign_Estudio::$app->tecnodesign['languages'];
            }
            ksort(Tecnodesign_Estudio::$languages);
            $la = Tecnodesign_Estudio::$languages;
            foreach($la as $lang) {
                if($lang==tdz::$lang) continue;
                $langs .= '<link rel="alternate" hreflang="'.$lang.'" href="'.$this->link.'?!'.$lang.'" />';
                unset($lang);
            }
        }

        array_unshift(
            $slots['meta'], 
            '<meta http-equiv="last-modified" content="'.gmdate("D, d M Y H:i:s") . ' GMT" />'
            . '<meta name="generator" content="Tecnodesign E-Studio - https://tecnodz.com" />'
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

        $merge = array();
        $slotelements = array('header'=>true,'footer'=>true,'nav'=>true);
        $addbr='';
        $layout = '<'."?php\n"
            .(($this->dynamic)?("//dynamic\n"):("//static\ntdz::cacheControl('public');\n"));
        if($dyn && Tecnodesign_Estudio::$staticCache) Tecnodesign_Estudio::$staticCache=false;
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
        foreach($slots as $slotname=>$slot) {
            if(isset($slotelements[$slotname]) && $slotelements[$slotname]) {
                $layout .= "\n\${$slotname} = '<{$slotname}><div id=\"{$slotname}\" data-estudio-s=\"{$slotname}\">'\n    . tdz::get('before-{$slotname}').\${$slotname}.tdz::get('{$slotname}').tdz::get('after-{$slotname}')\n    . '</div></{$slotname}>{$addbr}';";
            } else if($slotname!='meta' && $slotname!='title') {
                $layout .= "\n\${$slotname} = '<section><div id=\"{$slotname}\" data-estudio-s=\"{$slotname}\">'\n    . tdz::get('before-{$slotname}').\${$slotname}.tdz::get('{$slotname}').tdz::get('after-{$slotname}')\n    . '</div></section>{$addbr}';";
            }
        }
        $layout .= "\n\$meta.=tdz::meta();";
        if(count($merge)>0) {
            $layout .= "\n\$content = ".implode('.',$merge).';';
        }

        $mc = file_get_contents($master);
        if(substr($mc, 0, 5)=='<'.'?php') $layout .= "\n".substr($mc, 5);
        else $layout .= "\n?".'>'.$mc;
        unset($mc, $master);
        if(Tecnodesign_Estudio::$cacheTimeout && isset($cf) && tdz::save($cf, $layout, true)) {
            return array('layout'=>substr($cf, 0, strlen($cf)-4), 'template'=>'');
        } else {
            return tdz::exec(array('pi'=>substr($layout,5), 'variables'=>Tecnodesign_App::response()));
        }
    }

    public function filePreview($optimize=false)
    {
        if($this->type!='file') return false;

        tdz::set('cache-control', 'public');
        if(!file_exists($file=TDZ_VAR.'/'.$this->source)
            && !file_exists($file=Tecnodesign_Estudio::$app->tecnodesign['document-root'].'/'.$this->source)
        ) {
            $file = false;
        }
        if(file_exists($ufile=TDZ_VAR.'/'.static::$uploadDir.'/'.$this->source)) {
            if(!$file || filemtime($ufile)>filemtime($file))
                $file = $ufile;
        }
        if(!$file) {
            Tecnodesign_Estudio::error(404);
            return false;
        }
    
        $link = $this->link;
        if(!$link) $link = tdz::scriptName();
        $fname = basename($link);
        if($optimize) {
            $ext = strtolower(preg_replace('/.*\.([a-z0-9]{1,5})$/i','$1',basename($file)));
            $actions=Tecnodesign_Estudio::$app->estudio['assets_optimize_actions'];
            $cache=TDZ_VAR.'/optimize/'.md5_file($file);
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
        return $file;
        tdz::download($file,$this->format,$fname);
    }
    public function renderFile()
    {
        $c = $this->getCredentials('previewPublished');
        $U=tdz::getUser();
        if(is_null($c)) {
            $c = Tecnodesign_Estudio::credential('previewPublished');
        }
        if($c && !(($U=tdz::getUser()) && $U->hasCredential($c, false))) {
            Tecnodesign_Estudio::error(403);
            return false;
        }
        $file = $this->filePreview();
        tdz::download($file,$this->format,basename($this->link));
    }

    public function renderEntry($template=false, $args=array())
    {
        $a = array('script'=>Tecnodesign_Estudio::templateFile($template, 'tdz_entry'),
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
        $template = Tecnodesign_Estudio::templateFile($template, $tpl);
        return $this->renderEntry(substr($template, 0, strlen($template)-4), $args);
    }
    

    public static function feedPreview($o)
    {
        $entry=false;
        if(isset($o['entry'])) {
            $entry = (is_object($o['entry']))?($o['entry']):(tdzEntry::latest($o['entry']));
        }
        if($entry && !Tecnodesign_Estudio::getPermission('previewEntry', $entry)) {
            $entry=false;
        }
        if(!$entry) return '';
        $o['entry']=$entry;
        if(isset($o['master'])) {
            return tdz::exec(array('script'=>$o['master'], 'variables'=>$o));
        }
        return '';
    }


    public function getParent($scope=null)
    {
        if(!$this->id) return null;
        return tdzEntry::find(array('Children.entry'=>$this->id),1,$scope,false);
    }

    public function getCredentials($role='previewPublished')
    {
        if(!is_null($this->credential)) {
            if(!is_array($this->credential)) return $this->credential;
            else if(isset($this->credential[$role])) return $this->credential[$role];
            else if(isset($this->credential['default'])) return $this->credential['default']; 
        }
        if($this->id) {
            $P = tdzPermission::find(array('entry'=>$this->id,'role'=>'previewPublished'),1,array('credentials'));
            if($P) {
                if($P->credentials) return explode(',', $P->credentials);
                else return false;
            }
        }
        return null;
    }

    public function getContents($search=array(), $scope='content', $asCollection=false, $orderBy=null, $groupBy=null)
    {
        if(!$this->id) return null;
        if(!is_array($search)) $search = array();
        $search['entry'] = $this->id;
        return tdzContent::find($search,0,$scope,$asCollection,$orderBy,$groupBy);
    }

    public function getChildren($search=array(), $scope='link', $asCollection=false, $orderBy=null, $groupBy=null)
    {
        if(!$this->id) return null;
        if(!is_array($search)) $search = array();
        $search['Relation.parent'] = $this->id;
        $search['type'] = 'page';
        return tdzEntry::find($search,0,$scope,$asCollection,$orderBy,$groupBy);
    }

    public function getTags($search=array(), $scope='link', $asCollection=false, $orderBy=null, $groupBy=null)
    {
        if(!$this->id) return null;
        if(!is_array($search)) $search = array();
        $search['entry'] = $this->id;
        return tdzTag::find($search,0,$scope,$asCollection,$orderBy,$groupBy);
    }


    public static function findPage($url, $multiview=false)
    {
        // get file-based page definitions
        if(substr(basename($url),0,1)=='.') return;
        $P=null;
        if(!$multiview) {
            $pages = glob(TDZ_VAR.'/'.static::$pageDir.$url.'{,.*}', GLOB_BRACE);
            if($pages && count($pages)>0) {
                foreach($pages as $page) {
                    if($P=self::_checkPage($page, $url)) {
                        break;
                    }
                }
            }
        } else if($url) {
            if(is_file($f=TDZ_VAR.'/'.static::$pageDir.$url.'.php'))
                $P=self::_checkPage($f, $url, $multiview);
        }
        /*
        if(!$P && $multiview && strrpos($url, '/')>1) {
            $l = substr($url, 0, strrpos($url, '/'));
            while($l) {
                if(is_file($f=TDZ_VAR.'/'.static::$pageDir.$l.'.php') && ($P=self::_checkPage($f, $l, $multiview))) {
                    break;
                }
            }
        }
        */
        return $P;
    }

    protected static function _checkPage($page, $url, $multiview=false)
    {
        if(is_dir($page)) return;
        $base = (substr($url, -1)=='/')?(''):(basename($url));
        $pn = basename($page);
        //if(substr($pn, 0, strlen($base)+1)==$base.'.') $pn = substr($pn, strlen($base)+1);
        $pp = explode('.', $pn);
        if(in_array('_tpl_', $pp)) return; // templates cannot be pages
        $ext = strtolower(array_pop($pp));
        if(is_array(tdzContent::$disableExtensions) && in_array($ext, tdzContent::$disableExtensions)) return;
        $isPage = isset(tdzContent::$contentType[$ext]);
        if($isPage) {
            if($pn==$base) return; // cannot access content directly
            else if($base.'.'.$ext!=$pn) return; // cannot have slots/position
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
            if(substr($p, 0, 2)=='/*') {
                $m = substr($p,3,strpos($p,'*/')-3);
                $p = substr($p, strlen($m)+5);
                $meta = Tecnodesign_Yaml::load($m);
                if($multiview && (!isset($meta['multiview']) || !$meta['multiview'])) return;
            } else if($multiview) return;
        } else if($multiview) return;

        // get metadata
        if(file_exists($mf=$page.'.'.tdz::$lang.'.meta') || file_exists($mf=$page.'.meta')) {
            $m = Tecnodesign_Yaml::load($mf);
            if(is_array($m)) {
                $meta += $m;
            }
            unset($m);
        }
        $d=$url;
        $p=$page;
        while(strrpos($d, '/')!==false) {
            $d = substr($d, 0, strrpos($d, '/'));
            $p = substr($p, 0, strrpos($p, '/'));
            if(file_exists($mf=$p.'/.meta')) {
                $m = Tecnodesign_Yaml::load($mf);
                if(is_array($m)) {
                    $meta += $m;
                }
                unset($m);
            }
            if(file_exists($mf=$p.'.meta')) {
                $m = Tecnodesign_Yaml::load($mf);
                if(is_array($m)) {
                    $meta += $m;
                }
                unset($m);
            }
            unset($mf);
        }
        unset($d, $p);

        $id = substr($page, strlen(TDZ_VAR)+1);
        $t = date('Y-m-d\TH:i:s', filemtime($page));
        $P = new tdzEntry(array(
            //'id'=>tdz::hash($id, null, 'uuid'),
            'source'=>$id,
            'link'=>$url,
            'published'=>$t,
            'format'=>$format,
            'type'=>($isPage)?('page'):('file'),
            'updated'=>$t,
        ));
        if($meta) {
            if(isset($meta['languages'])) Tecnodesign_Estudio::$languages = $meta['languages'];
            Tecnodesign_Estudio::addResponse($meta);
            foreach($meta as $fn=>$v) {
                if(property_exists($P, $fn)) {
                    $P->$fn = $v;
                }
                unset($meta[$fn], $fn, $v);
            }
        }
        unset($meta, $t, $id, $format, $isPage);
        return $P;
    }

    /**
     * Content loader
     *
     * Load all contents, including template-based information
     * [__tpl__.]?[baseurl][.slot]?[.position]?[.lang]?.ext
     */
    public function getRelatedContent($where='', $wherep=array())
    {
        if($this->id) {
            $published = !Tecnodesign_Estudio::$private;
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

            $r = tdzContent::find($f,0,null,false);
        } else {
            $r = null;
        }

        // get file-based page definitions
        $pages = glob(TDZ_VAR.'/'.static::$pageDir.$this->link.'.*');
        if(substr($this->link, -1)=='/') {
            $pages = array_merge($pages, glob(TDZ_VAR.'/'.static::$pageDir.$this->link.'_tpl_.*'));
        }
        if(strrpos($this->link, '/')>1) {
            $l = $this->link;
            while(strrpos($l, '/')!==false) {
                $l = substr($l, 0, strrpos($l, '/'));
                $pages = array_merge($pages, glob(TDZ_VAR.'/'.static::$pageDir.$l.'/_tpl_.*'));
            }
            unset($l);
        } else if(substr($this->link, -1)!='/') {
            $pages = array_merge($pages, glob(TDZ_VAR.'/'.static::$pageDir.'/_tpl_.*'));
        }
        if($pages) {
            $link = (substr($this->link, -1)=='/')?(''):(basename($this->link));
            if(!$r) $r=array();
            foreach($pages as $page) {
                if(is_dir($page)) continue;
                $slotname = null;
                $pos = 0;
                $pn = basename($page);
                if(substr($pn, 0, strlen($link)+1)==$link.'.') $pn = substr($pn, strlen($link)+1);
                $pp = explode('.', $pn);
                if($pp[0]=='_tpl_') array_shift($pp);
                if(count($pp)>1) {
                    $slotname = array_shift($pp);
                    if(count($pp)>1) {
                        $pos = (int) array_shift($pp);
                        if(count($pp)>1) {
                            $lang = array_shift($pp);
                            if(!in_array($lang, Tecnodesign_Estudio::$languages)) Tecnodesign_Estudio::$languages[array_search($lang, Tecnodesign_Estudio::$app->tecnodesign['languages'])]=$lang;
                            if($lang!=tdz::$lang) continue;
                        }
                    }
                }
                $ext = strtolower(array_pop($pp));
                if(!isset(tdzContent::$contentType[$ext])) continue;
                else if(is_array(tdzContent::$disableExtensions) && in_array($ext, tdzContent::$disableExtensions)) continue;

                $p = file_get_contents($page);
                if(!$p) continue;
                if(substr($p, 0, 2)=='/*') {
                    $m = substr($p,3,strpos($p,'*/')-3);
                    $p = substr($p, strlen($m)+5);
                    $meta = Tecnodesign_Yaml::load($m);
                    if(isset($meta['credential'])) {
                        if(!($U=tdz::getUser()) || !$U->hasCredential($meta['credential'], false)) continue;
                        Tecnodesign_Estudio::$private = true;
                        unset($meta['credential']);
                    }
                }
                $id = substr($page, strlen(TDZ_VAR)+1);
                $C = new tdzContent(array(
                    'id'=>tdz::hash($id, null, 'uuid'),
                    'slot'=>$slotname,
                    'content'=>$p,
                    'content_type'=>$ext,
                    'position'=>$pos,
                ));
                $C->pageFile = $id;
                if(isset($meta)) {
                    Tecnodesign_Estudio::addResponse($meta);
                    foreach(tdzContent::$schema['columns'] as $fn=>$fd) {
                        if(isset($meta[$fn])) $C->$fn = $meta[$fn];
                        unset($fd, $fn);
                    }
                    unset($meta);
                }
                $r[] = $C;
                unset($C, $page, $id);
            }
            unset($link);
        }
        unset($pages);
        return $r;
    }
}
