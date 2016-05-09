<?php
/**
 * Tecnodesign_App_Studio_Entry table description
 *
 * PHP version 5.3
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @link      http://tecnodz.com/
 * @version   SVN: $Id$
 */

/**
 * Tecnodesign_App_Studio_Entry table description
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @link      http://tecnodz.com/
 */
class Tecnodesign_App_Studio_Entry extends Tecnodesign_Model
{
    /**
     * Tecnodesign_Model schema
     *
     * Remove the comment below to disable automatic schema updates
     */
    //--tdz-schema-start--2012-02-29 19:44:01
    public static $schema = array (
      'database' => 'studio',
      'tableName' => 'tdz_entries_version',
      'className' => 'tdzEntry',
      'columns' => array (
        'id' => array ( 'type' => 'int', 'null' => false, 'primary' => true, ),
        'version' => array ( 'type' => 'int', 'null' => false, 'primary' => true, ),
        'title' => array ( 'type' => 'string', 'size' => '200', 'null' => true, ),
        'summary' => array ( 'type' => 'string', 'size' => '', 'null' => true, ),
        'link' => array ( 'type' => 'string', 'size' => '200', 'null' => true, ),
        'source' => array ( 'type' => 'string', 'size' => '200', 'null' => true, ),
        'format' => array ( 'type' => 'string', 'size' => '100', 'null' => true, ),
        'language' => array ( 'type' => 'string', 'size' => '10', 'null' => true, ),
        'type' => array ( 'type' => 'string', 'size' => '100', 'null' => true, ),
        'master' => array ( 'type' => 'string', 'size' => '100', 'null' => true, ),
        'published' => array ( 'type' => 'datetime', 'null' => true, ),
        'created' => array ( 'type' => 'datetime', 'null' => false, ),
        'updated' => array ( 'type' => 'datetime', 'null' => false, ),
        'expired' => array ( 'type' => 'datetime', 'null' => true, ),
      ),
      'relations' => array (
        'Content' => array ( 'local' => 'id', 'foreign' => 'entry', 'type' => 'many', 'className' => 'Tecnodesign_App_Studio_Content', ),
      ),
      'scope' => array (
        'studio-new'=>array('type', 'title', 'link', 'summary', 'published'),
        'studio-edit'=>array('type', 'title', 'link', 'summary', 'published','contents'),
      ),
      //'group'=>array('id'),
      'order' => array(
        'version'=>'desc',
        //'created'=>'desc',
      ),
      'events' => array (
        'before-save' => array ( 'Tecnodesign_App_Studio::forceNew', ),
        'before-insert' => array ( 'actAs', ),
        'before-update' => array ( 'actAs', ),
        'before-delete' => array ( 'actAs', ),
        'active-records' => 'expired is null',
        'after-insert' => array ( 'Tecnodesign_App_Studio::updateVersion', ),
      ),
      'form' => array (
        'type'=>array('bind'=>'type', 'type'=>'select', 'choices'=>'Tecnodesign_App_Studio::config(\'entry_types\')', 'fieldset'=>'*Properties', 'class'=>'studio-left'),
        'title'=>array('bind'=>'title', 'fieldset'=>'*Properties', 'class'=>'studio-clear', 'required'=>true),
        'link'=>array('bind'=>'link', 'attributes'=>array('data-type'=>'url'), 'fieldset'=>'*Properties'),
        'summary'=>array('bind'=>'summary', 'type'=>'html', 'fieldset'=>'*Properties','class'=>'studio-clear'),
        'published'=>array('bind'=>'published', 'type'=>'datetime', 'fieldset'=>'*Properties', 'class'=>'studio-left'),
        'contents'=>array('bind'=>'Content', 'type'=>'form','fieldset'=>'*Content'),
      ),
      'actAs' => array (
        'before-insert' => array ( 'auto-increment'=> array('id'), 'timestampable' => array ( 'created', 'updated'), ),
        'before-update' => array ( 'auto-increment'=> array('version'), 'timestampable' => array ( 'updated', ), ),
        'before-delete' => array ( 'auto-increment'=> array('version'), 'timestampable' => array( 'updated', ), 'soft-delete' => array ( 'expired', ), ),
      ),
    );
    protected $id, $created, $title, $summary, $link, $source, $format, $published, $language, $type, $master, $expired, $Content;
    //--tdz-schema-end--
    
    protected $tags=null;
    protected static $matches=array(),$conn=null;
    public static $current=array('page'=>false, 'feed'=>false, 'file'=>false, 'entry'=>false);

    /**
     * URL mapping to entries
     * 
     * Searches the database for entries that correspond to the given address. 
     * Allows multiviews, just like Apache behavior, on html pages
     * 
     * @param type $url
     * @param type $exact
     * @return boolean 
     */
    public static function match($url, $exact=false, $published=true)
    {
        $url = tdz::validUrl($url);
        $url = preg_replace('/\/(\.)*\/+/','/',$url);
        $url = preg_replace('/\/\/+/','/',$url);
        if ($url=='') {
            return false;
        }
        if (isset(self::$matches[$url])) {
            return self::$matches[$url];
        }
        $cn = get_called_class();
        $page = false;

        $w = '';
        if($url=='/') {
            $purl=array('/');
        } else if ($exact) {
            $purl=array($url);
        } else {
            $urlp=preg_split('#/+#',$url,null,PREG_SPLIT_NO_EMPTY);
            $purl=array();
            $surl='';
            $alt = array();
            foreach ($urlp as $p) {
                $surl.='/'.$p;
                $purl[]=$surl;
                if (preg_match('/\.(php|html?)$/i', $p, $m)) {
                    $purl[]=substr($surl, 0, strlen($surl) - strlen($m[0]));
                }
            }
        }
        $tn = str_replace('_version', '', $cn::$schema['tableName']);
        $q = "select e.* from {$tn} as e where"
            . (($published)?(' e.published<now() and'):(''))
            . " e.expired is null and ((e.type in ('file') and e.link in ('{$url}'))"
            . " or (e.type in ('page','feed') and e.link in ('".implode("','", $purl)."'))"
            . ") order by e.link desc, e.created asc";
        $conn=tdz::connect($cn::$schema['database']);
        $page = false;
        try
        {
            $query = $conn->query($q);
            if ($query) {
                $page = $query->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch(PDOException $e) {
            tdz::log($e->getMessage());
            return false;
        }
        $numPages = count($page);
        if ($numPages==1) {
            $page=$page[0];
        } else if ($numPages>1) {
            $pages = array();
            foreach($page as $p) {
                $pages[$p['link']]=$p;
            }
            krsort($pages);
            $page = array_shift($pages);
        } else {
            $page=false;
        }
        if($page) {
            $class = $cn::$schema['className'];
            $page = new $class($page);
        }
        self::$matches[$url]=$page;
        return $page;
    }

    public static function latest($id, $published=true)
    {
        $cn = get_called_class();
        $q = "select e.* from {$cn::$schema['tableName']} as e inner join (select e.id, e.link, max(e.updated) as updated";
        if ($published) {
            $q .= " from {$cn::$schema['tableName']} as e"
                . " where e.expired is null and e.published<now()";
        } else {
            $q .= " from {$cn::$schema['tableName']} as e"
                . " where e.expired is null";
        }
        $q .= ' and e.id='.tdz::sqlEscape($id)
            . ' group by e.id order by e.published desc limit 1) as q on q.id=e.id and q.updated=e.updated and e.expired is null';
        $c=new Tecnodesign_Collection(null, $cn, $q);
        if($c && $c->count()>0) {
            return $c[0];
        } else {
            return false;
        }
    }

    public function getChildren()
    {
        return $this->getEntries('r.position asc,e.published desc');
    }

    protected static $_previewPublished, $_psql;
    protected static function getPermissions()
    {
        if(is_null(self::$_previewPublished)) {
            self::$_previewPublished = Tecnodesign_App_Studio::getPermission('previewEntryUnpublished');
        }
        if(is_null(self::$_psql)) {
            $credentials = tdz::getUser()->getCredentials();
            self::$_psql = '';
            foreach($credentials as $c) {
                self::$_psql .= ' or find_in_set('.tdz::sqlEscape($c).', p.credentials)';
            }
        }
    }
    public function getEntries($orderby='e.published desc', $tags=null)
    {
        if(!$orderby) $orderby='e.published desc';
        self::getPermissions();
        $cn = get_called_class();
        $tn = str_replace('_version', '', $cn::$schema['tableName']);
        $rn = str_replace('_version', '', tdzRelation::$schema['tableName']);
        $pn = str_replace('_version', '', tdzPermission::$schema['tableName']);
        $kn = str_replace('_version', '', tdzTag::$schema['tableName']);
        if(!is_null($tags)) {
            $tags = (!isset($tags[0]))?(array_keys($tags)):($tags);
            foreach($tags as $k=>$v) $tags[$k]=tdz::slug($v);
        }
        $q = "select e.* from $tn as e"
            . " inner join $rn as r on r.parent={$this->id} and e.id=r.entry and e.expired is null and r.expired is null"
            . (($tags)?(" inner join {$kn} as k on k.entry=e.id and k.slug in('".implode("', '", $tags)."')"):(''))
            . ((self::$_previewPublished)?(" and e.published<now()"):(''))
            . " left outer join {$pn} as p on p.entry=e.id and p.role='previewPublished' and p.expired is null"
            . " where p.credentials is null".self::$_psql
            . ' order by '.$orderby;
        return new Tecnodesign_Collection(null, $cn, $q);
    }

    public function getAncestors($stopId=false)
    {
        $as = array();
        $a=$this;
        $found = false;
        if($a->id!=$stopId) {
            while($a=$a->getParent()) {
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

    public function getTags()
    {
        if(is_null($this->tags)) {
            $q = 'select t.tag, t.slug from tdz_tags as t where t.entry='.$this->id.' and t.expired is null';
            $tags=new Tecnodesign_Collection(null, null, $q, null, tdz::connect(self::$schema['database']));
            $this->tags=array();
            if($tags && $tags->count()>0) {
                foreach($tags as $t) {
                    $this->tags[$t['slug']]=$t['tag'];
                }
            }
        }
        return $this->tags;
    }

    public function getParent()
    {
        self::getPermissions();
        $cn = get_called_class();
        $tn = str_replace('_version', '', $cn::$schema['tableName']);
        $rn = str_replace('_version', '', tdzRelation::$schema['tableName']);
        $pn = str_replace('_version', '', tdzPermission::$schema['tableName']);
        $q = "select e.* from $tn as e"
            . " inner join $rn as r on r.entry={$this->id} and e.id=r.parent and e.expired is null and r.expired is null"
            . ((self::$_previewPublished)?(" and e.published<now()"):(''))
            . " left outer join {$pn} as p on p.entry=e.id and p.role='previewPublished' and p.expired is null"
            . " where p.credentials is null".self::$_psql;
        $p = new Tecnodesign_Collection(null, $cn, $q);
        if($p && $p->count()>0) return $p[0];
        else return false;
    }
    
    public function getVersion()
    {
        return str_replace(array('-', ':', ' ', '.'), '', $this->created);
    }

    public static function meta($entry=null)
    {
        $s = '<meta name="generator" content="Tecnodesign E-Studio - http://tecnodz.com" />';
        $app = tdz::getApp();
        if(file_exists($app->tecnodesign['templates-dir'].'/tdz-meta.php')) $s .= tdz::exec(array('script'=>$app->tecnodesign['templates-dir'].'/tdz-meta.php', 'variables'=>array('entry'=>$entry)));
        return $s;
    }


    public function render()
    {
        tdzEntry::$current[$this->type]=$this->id;
        $ckey = "entry/render/e{$this->id}.{$this->version}";
        $cfile = Tecnodesign_Cache::cacheDir().'/'.$ckey.'.php';
        $lmod = self::timestamp(array('tdzEntry', 'tdzContent'));
        $app = tdz::getApp();
        if($app->start > $lmod) {
            $lmod = $app->start;
        }
        $cmod = (file_exists($cfile))?(filemtime($cfile)):(false);
        $timeout = tdz::getApp()->studio['cache_timeout'];
        if(!Tecnodesign_App_Studio::$enableCache || !$cmod || $cmod<=$lmod || ($cmod + $timeout)<time()) {
            $m = 'render'.ucfirst(tdz::camelize($this->type));
            if(method_exists($this, $m)) {
                $result = $this->$m();
            }
            tdz::save($cfile, $result, true);
        }
        return substr($cfile, 0, strlen($cfile) -4);
    }

    public function filePreview($optimize=false)
    {
        if($this->type!='file')
          return false;
        $file=false;
        //$this->getLatestVersion();
        tdz::set('cache-control', 'public');
        $app=tdz::getApp();
        if(file_exists($app->tecnodesign['document-root'].'/'.$this->source))
            $file = $app->tecnodesign['document-root'].'/'.$this->source;
        if(file_exists($app->studio['upload_dir'].'/'.$this->source)) {
            $ufile=$app->studio['upload_dir'].'/'.$this->source;
            if(!$file || filemtime($ufile)>filemtime($file))
              $file = $ufile;
        } else {
            return false;
        }
    
        $link = $this->link;
        if(!$link) $link = tdz::scriptName();
        $fname = basename($link);
        if($optimize) {
            $ext = strtolower(preg_replace('/.*\.([a-z0-9]{1,5})$/i','$1',basename($file)));
            $actions=$app->studio['assets_optimize_actions'];
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
        $file = $this->filePreview();
        tdz::download($file,$this->format,basename($this->link));
    }

    public function renderEntry($template=false, $args=array())
    {
        $a = array('script'=>Tecnodesign_App_Studio::templateFile($template, 'tdz_entry'),
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
        $template = Tecnodesign_App_Studio::templateFile($template, $tpl);
        return $this->renderEntry(substr($template, 0, strlen($template)-4), $args);
    }
    
    public function renderPage()
    {
        $url = $this->getLink();
        $master = $this->master;
        if($master=='') {
            $ids=array("{$this->id}"=>true);
            $entry = $this;
            while($entry->Parent) {
                $entry = $entry->Parent->Parent;
                $id=$this->id;
                if(!$id || isset($ids[$id])) break;
                $ids[$id]=true;
                $master = $entry->master;
                if($master!='') {
                    break;
                }
            }
        }
        $app = tdz::getApp();
        $master = Tecnodesign_App_Studio::templateFile($master, $app->studio['default_layout'], 'layout');

        /**
         * find out which slots are available. These should be configured either in
         * app.yml or as a routing parameter
         */
        $slots = $app->studio['slots'];
        $slotname = false;//sfContext::getInstance()->getRequest()->getParameter('tdz/slots');
        if(!$slotname || !isset($slots[$slotname])) {
            $slotname = $app->studio['default_slotname'];
        }

        $slots = $slots[$slotname];
        $add=array();
        if(!isset($slots['title']))$add['title']=$this->title;
        if(!isset($slots['meta'])) $add['meta']=array();
        else if(!is_array($slots['meta'])) $slots['meta']=array($slots['meta']);
        if(count($add)>0) {
            $slots = array_merge($add,$slots);
        }
        array_unshift($slots['meta'], '<meta http-equiv="last-modified" content="'.gmdate("D, d M Y H:i:s") . ' GMT" />');
        foreach($slots as $slotname=>$slot) {
            if(is_null($slot)) {
                $slots[$slotname] = array();
            } else if(is_array($slot) && isset($slot[0])) {
                $slots[$slotname] = $slot;
            } else {
                $slots[$slotname] = array(array($slot));
            }
        }
        $slots['meta'][] = array(array('export'=>" tdzEntry::meta(array('id'=>'{$this->id}','updated'=>'{$this->updated}'))"));
        $contents = $this->getRelatedContent();
        $layout_type='static';
        $widgets=false;
        if($contents->count()>0) {
            foreach($contents as $content) {
                //$content->getLatestVersion();
                if($content->content_type=='php') {
                    $layout_type='dynamic';
                } else if($content->content_type=='widget') {
                    if(!$widgets) {
                        $widgets=$app->studio['widgets'];
                    }
                    $ctmp=Tecnodesign_Yaml::load($content->content);
                    if(isset($ctmp['app']) && isset($widgets[$ctmp['app']]['cache']) && !$widgets[$ctmp['app']]['cache'])
                        $layout_type='dynamic';
                }
                $slot = $content->slot;
                if($slot=='') {
                    $slot = 'body';
                }
                $pos = (int)$content->position;
                if(!isset($slots[$slot])) {
                    $slots[$slot]=array();
                }
                if(!isset($slots[$slot][$pos])) {
                    $slots[$slot][$pos]=array();
                } else if(!is_array($slots[$slot][$pos])) {
                    $slots[$slot][$pos]=array($slots[$slot][$pos]);
                }
                if($content->entry=='') {
                    $content->entry=$this->id;
                }
                //$slots[$slot][$pos][] = $content->render();
                $slots[$slot][$pos][] = array('export'=>" tdzContent::preview(array('id'=>'{$content->id}','updated'=>'{$content->updated}'))");
            }
        }
        $phpheader = '<'."?php\n//{$layout_type}\n";
        if($layout_type=='static') {
            $phpheader .= "tdz::cacheControl('public');\n";
        }
        //$helper = sfConfig::get('app_e-studio_helper_dir').'/tdzEStudioHelper.php';
        //$phpheader .= "if(!function_exists('tdz_eval'))require_once '{$helper}';";
        $merge = array();
        $slotelements = array('header'=>true,'footer'=>true,'nav'=>true);
        $class='tdzs container';
        //if($this->hasPermission('new', 'Content'))$class .= ' new';
        //if($this->hasPermission('edit', 'Content'))$class .= ' edit';
        //if($this->hasPermission('publish', 'Content'))$class .= ' publish';
        $addbr='';

        foreach($slots as $slotname=>$slot) {
            ksort($slot);
            $first = true;
            $phpheader .= "\n\${$slotname} = ";
            if(isset($slotelements[$slotname]) && $slotelements[$slotname]) {
                //$phpheader .= "'<{$slotname}><div id=\"{$slotname}\"><div class=\"{$class}\">'";
                //$first = false;
                $merge[]='$'.$slotname;
            } else if($slotname!='meta' && $slotname!='title') {
                //$phpheader .= "'<section><div id=\"{$slotname}\"><div class=\"{$class}\">'";
                //$first = false;
                $merge[]='$'.$slotname;
            } else if(count($slot)==0) {
                $phpheader .= "''";
            }/*
            if ($first) {
                $phpheader .= "tdz::get('before-{$slotname}')";
                $first = false;
            } else {
                $phpheader .= "\n    . tdz::get('before-{$slotname}')";
            }
            */
            /*
            if($slotname=='body') {
                $phpheader .= "\n .\$sf_content";
            }
            */
            foreach($slot as $slotfrag) {
                if(!is_array($slotfrag))$slotfrag=array($slotfrag);
                foreach($slotfrag as $v) {
                    if($first) {
                        $first = false;
                        $phpheader .= "";
                    } else {
                        $phpheader .= "\n    .";
                    }
        
                    if(is_array($v) && isset($v['before'])) {
                        $phpheader .= var_export($v['before'],true).'.';
                    }
        
                    if(is_array($v) && isset($v['export'])) {
                        $phpheader .= $v['export'];
                    } else if(is_array($v)) {
                        $phpheader .= var_export($v['content'],true);
                    } else {
                        $phpheader .= var_export($v,true);
                    }
                    if(is_array($v) && isset($v['after'])) {
                        $phpheader .= '. '.var_export($v['after'],true);
                    }
                }
            }
            if($first) $phpheader .= "''";
            $phpheader .= ';';
            //$phpheader .= "\n    . tdz::get('after-{$slotname}');";
        }
        foreach($slots as $slotname=>$slot) {
            if(isset($slotelements[$slotname]) && $slotelements[$slotname]) {
                $phpheader .= "\n\${$slotname} = '<{$slotname}><div id=\"{$slotname}\"><div class=\"{$class}\">'\n    . tdz::get('before-{$slotname}').\${$slotname}.tdz::get('{$slotname}').tdz::get('after-{$slotname}')\n    . '</div></div></{$slotname}>{$addbr}';";
            } else if($slotname!='meta' && $slotname!='title') {
                $phpheader .= "\n\${$slotname} = '<section><div id=\"{$slotname}\"><div class=\"{$class}\">'\n    . tdz::get('before-{$slotname}').\${$slotname}.tdz::get('{$slotname}').tdz::get('after-{$slotname}')\n    . '</div></div></section>{$addbr}';";
            }
        }
        $phpheader .= "\n\$meta.=tdz::meta();";
        if(count($merge)>0) {
            $phpheader .= "\n\$content = ".implode('.',$merge).';';
        }
        $phpheader .= "\n?".'>';
        $layout = $phpheader.file_get_contents($master);
        return $layout;
    }

    public static function feedPreview($o)
    {
        $entry=false;
        if(isset($o['entry'])) {
            $entry = (is_object($o['entry']))?($o['entry']):(tdzEntry::latest($o['entry']));
        }
        if($entry && !Tecnodesign_App_Studio::getPermission('previewEntry', $entry)) {
            $entry=false;
        }
        if(!$entry) return '';
        $o['entry']=$entry;
        if(isset($o['master'])) {
            return tdz::exec(array('script'=>$o['master'], 'variables'=>$o));
        }
        return '';
    }
    public static function feedPreviewXXX($entry,$template='tdz_feed', $options=array())
    {
        /**
         * should cache:
         * {
         *   credentials: string|array
         *   result: string
         */
        $app = tdz::getApp();
        $credentials=false;
        $result='';
        $request=false;
        $usecache=true;
        $self=false;
        $tags=false;
        $meta=false;
        if(!is_object($entry)) {
            $self = tdzEntry::match(tdz::scriptName(true));
            if(!$self)
                $self = tdzEntry::find($entry,1);
            $entry=$self;
        }
        if(is_object($entry) && $entry instanceof tdzEntry)
            $id=$entry->id;
        else 
            $id=$entry;
        $ckey='tdzEntries/e'.$id;
        if($template=='')$template='tdz_feed';
        $tpl=basename($template,'.php');
        if(substr($tpl,0,4)=='tdz_')$tpl=ucfirst(substr($tpl,4));
        //$ckey .= ".{$entry->version}.{$tpl}";
        $ckey .= ".{$tpl}";

        $user=tdz::getUser();
        $uc=array();
        if($user->isAuthenticated()){
            $uc=$user->getCredentials();
            if(count($uc)>0) {
                asort($uc);
                $ckey.='.'.md5(implode("\n",$uc));
            }
        }
        $ucsql=(count($uc)>0)?(' or p.credentials rlike \'(.*,\s*)?('.implode('|', $uc).')(\s*,.*)\''):('');
        $add=array();
        $limit=false;
        if(isset($options['limit']) && $options['limit']) {
            $limit=$add['limit']=$options['limit'];
            $ckey.='.l'.$options['limit'];
        }
        if(isset($template) && substr(basename($template),0,14)=='tdz_navigation'){
            // context matters for navigation
            $ckey.='.u'.md5(tdz::scriptName());
        }
        $usepager=false;
        $add['hpp']=false;
        if(isset($options['hpp']) && $options['hpp']){
            $add['hpp']=$options['hpp'];
            //$ckey=false;
            $usepager=true;
            $usecache=false;
        }
        if(isset($options['options'])){
            foreach($options['options'] as $opt) {
                $add[$opt]=true;
                $ckey.='.'.$opt;
        
            }
            if(isset($add['related'])){
                if(!$self){
                    $self = tdzEntry::match(tdz::scriptName(true));
                    if(!$self)
                        $self = tdzEntry::find($entry,1);
                }
                if ($self) {
                    $ckey .= '.r'.$self->link;
                    $tags = array();
                    foreach($self->getTags() as $tag)
                        $tags[$tag['slug']]=$tag['tag'];
                }
            }
        }
        $preview=false;
        if(isset($add['preview']) && tdz::scriptName()!=tdz::scriptName(true)){
            $q = 'select e.id, e.title, e.summary, e.link'
                . ' from tdz_entries as e inner join tdz_relations as r on r.entry=e.id and r.expired is null left outer join tdz_permissions as p on p.entry=e.id and p.role=\'previewPublished\' and p.expired is null'
                . ' where e.expired is null and e.link='.tdz::sqlEscape(tdz::scriptName(true)).' and r.parent='.tdz::sqlEscape($id)
                . ' and (p.entry is null'.$ucsql.')';
            $ep = new Tecnodesign_Collection(null, $q, 'tdzEntry');
            if($ep && $ep->count()>0) {
                tdz::set('entry',$ep[0]);
                tdz::set('feed-preview',$ep[0]['id']);
                tdz::set('title',$ep[0]['title']);
                return self::entryPreview($ep,'tdz_entry_preview');
            }
        }
        //$co=new sfFileCache(array('cache_dir'=>sfConfig::get('sf_app_cache_dir').'/tdzEntries'));
        $cvar=false;
        $pass=false;
        $lifetime=$app->studio['cache_timeout'];
        $timeout=time() - $lifetime;
        $cmod=Tecnodesign_Cache::getLastModified($ckey, $lifetime, false);//$co->getLastModified('e'.$ckey);
        $emod=self::timestamp();
    
        if($usecache && $cmod>$emod) {
            $cvar=Tecnodesign_Cache::get($ckey, $lifetime, false);
        }
        $result=null;
        $exec=false;
        if(is_array($cvar)) {
            foreach($cvar as $k=>$v)
                $$k=$v;
        } else {
            $fe=$entry;
            if(!is_object($fe))
                $fe = tdzEntry::latest($id);
            $credentials = Tecnodesign_App_Studio::getPermission('preview', $fe);
      
            if($fe->type=='feed' && $fe->link!='' && !isset($add['nometa'])) {
                $link=tdz::buildUrl($fe->link);
                //if($tags) $link .= '?tag='.implode('&tag=',$tags);
                $meta = '<link rel="alternate" type="application/atom+xml" title="'.tdz::xmlEscape($fe->title).'" href="'.tdz::xmlEscape($link).'">';
            }
            if(is_numeric($id)) {
                if(isset($template) && substr(basename($template),0,14)=='tdz_navigation') {
                    $fn = 'tdz_navigation';
                    if(function_exists(basename($template,'.php')))$fn=basename($template,'.php');
                    $result = '<nav><div id="enav'.$id.'" class="nav">'.$fn($entry, $template, $options).'</div></nav>';
                    //return $result;
                } else {
                    $feed=array();
                    $q = 'select e.*'
                        . ' from tdz_entries as e'
                        . ' inner join tdz_relations as r on r.entry=e.id and r.expired is null and e.expired is null'
                        . (($tags)?(' inner join tdz_tags as t on t.entry=e.id and t.expired is null and t.slug in (\''.implode('\',\'',array_keys($tags)).'\')'):(''))
                        . ' left outer join tdz_permissions p on p.entry=e.id and p.role=\'previewPublished\' and p.expired is null'
                        . ' where r.parent='.tdz::sqlEscape($id)
                        . ' and p.credentials is null'.$ucsql
                        . ' order by e.published desc, e.updated desc';
                    $feed = new Tecnodesign_Collection(null, $q, 'tdzEntry');
                    if($limit) {
                        $feed=$feed->getItem(null, $limit, true);
                    }
                    if(!$usepager) {
                      $feed->setQuery(false);
                    }
                    if(substr($template,0,1)=='/')$template=basename($template,'.php');
                    if(strpos($template, 'feed')===false && strpos($template, 'atom')===false) {
                        $add['template']=$template;
                        $template='tdz_feed';
                    }
                    $template = Tecnodesign_App_Studio::templateFile($template, 'tdz_entry');
      
                    $vars=$fe->asArray();
                    $vars+=$add;
                    $vars['entry']=$entry;
                    $vars['entries']=$feed;
                    $vars['query']=$q;
                    $exec=array('variables'=>$vars,'script'=>$template);
                    $result=tdz::exec($exec);
                }
                if($usecache) {
                    $cvar=array('credentials'=>$credentials,'result'=>$result, 'meta'=>$meta);
                    Tecnodesign_Cache::set($ckey, $cvar, $lifetime, false);
                }
            }
        }
        if(!isset($credentials)||(is_bool($credentials)&&$credentials==false)||(!is_bool($credentials)&&!$user->hasCredential($credentials,false)))
          $result=false;

        if($meta)tdz::meta($meta);
        /*
        if(is_null($result) && $exec)
        {
          if($query)
          {
            $q = Doctrine_Query::create();
            foreach($query as $fn=>$arg)
              $q->$fn($arg);
            $exec['variables']['query']=$q;
          }
          $result=tdz::exec($exec);
        }
        */
        return $result;
    }
    /**
     * Content loader
     *
     * Load all contents, including template-bsed information
     */
    public function getContent($content_type=null)
    {
        if(is_null($this->Content)) $this->Content=Tecnodesign_App_Studio::getRelation($this, 'Content', 'c.slot, c.position asc');
        if($this->Content && !is_null($content_type)) {
            $this->Content->setQuery(false);
            $r=array();
            foreach($this->Content as $c) {
                if($c->content_type==$content_type) $r[]=$c;
            }
            return new Tecnodesign_Collection($r, 'tdzContent');
        }
        return $this->Content;
        $cs = tdzContent::$schema;
        $cn = get_class($this);
        $tn = str_replace('_version', '', $cs['tableName']);
        $schema = $cn::$schema;
        $q = "select c.* from {$tn} as c"
            . " where c.expired is null and c.entry='{$this->id}'"
            . ((!is_null($content_type))?(' and c.content_type='.tdz::sqlEscape($content_type)):(''))
            . " order by c.slot, c.position asc";
        $c = new Tecnodesign_Collection(null, 'tdzContent', $q);
        $c->setQuery(false);
        return $c;
    }

    /**
     * Content loader
     *
     * Load all contents, including template-based information
     */
    public function getRelatedContent($where='', $wherep=array())
    {
        $published = !Tecnodesign_App_Studio::hasPermission('previewContentUnpublished');
        $link = $this->link;
        $linkp = preg_split('#/+#', $link, null, PREG_SPLIT_NO_EMPTY);
        $id=$this->id;
        $w = '';
        $ws = 'find_in_set(\'*\',c.show_at)>0';
        $wh = 'c.hide_at is null or not(find_in_set(\'*\',c.hide_at)>0';
        $wp = array('id'=>$id);
        $ast='';
        $pi=1;
        if(count($linkp)==0) {
            $linkp[]='';
        }
        foreach($linkp as $p){
            $ast .= '/'.$p;
            if($ast==$link) {
                $wp['p'.$pi]= tdz::sqlEscape($ast);
            } else {
                $wp['p'.$pi]= tdz::sqlEscape($ast.'/*');
            }
            $ws .= " or find_in_set({$wp['p'.$pi]},c.show_at)>0";
            $wh .= " or find_in_set({$wp['p'.$pi]},c.hide_at)>0";
            $pi++;
        }
        $wh .= ')';
        if($id<=0) {
            $w = "(($ws) and ($wh))";
            unset($wp['id']);
        } else {
            $w = "(c.entry={$id} or (($ws) and ($wh)))";
        }
        if($where!='') {
            $w = "({$w}) and ({$where})";
            $wp += $wherep;
        }
        $cs = tdzContent::$schema;
        $cn = get_class($this);
        $schema = $cn::$schema;
        $q = "select c.entry<=>'{$this->id}' as subposition, c.* from {$cs['tableName']} as c inner join (select c.id, max(c.updated) as updated";
        if ($published) {
            $q .= " from {$cs['tableName']} as c"
                . " where c.expired is null and c.published<now()";
        } else {
            $q .= " from {$cs['tableName']} as c"
                . " where c.expired is null";
        }
        $q .= " group by c.id order by c.version desc) as q on q.id=c.id and {$w} and q.updated=c.updated and c.expired is null group by c.id order by c.slot, c.position asc, 1";
        $c = new Tecnodesign_Collection(null, 'tdzContent', $q);
        $c->setQuery(false);
        return $c;
    }

}
