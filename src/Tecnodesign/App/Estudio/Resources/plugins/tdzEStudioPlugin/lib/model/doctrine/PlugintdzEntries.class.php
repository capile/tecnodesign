<?php
/**
 * PlugintdzEntries
 * 
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: PlugintdzEntries.class.php 1193 2013-03-06 19:41:26Z capile $
 */
abstract class PlugintdzEntries extends BasetdzEntries
{
  private static $matches=array(),$conn=null;
  public static function connect()
  {
    if(is_null(self::$conn)) self::$conn = Doctrine_Manager::getInstance()->getConnection(sfConfig::get('app_e-studio_connection_name'));
    return self::$conn;
  }
  public static function query()
  {
    return new Doctrine_RawSql(self::connect());
  }
  public static function getInstance()
  {
    return Doctrine_Core::getTable('tdzEntries');
  }
  public function setUp()
  {
      parent::setUp();
        $this->hasMany('tdzEntriesVersion as Versions', array(
             'local' => 'id',
             'foreign' => 'id'));

  }
  public static function lastModified($unix=false)
  {
    $conn = self::connect();
    $sql='select greatest(max(e.updated),max(c.updated)) as updated from tdz_entries as e, tdz_contents as c';
    $query = $conn->prepare($sql);
    $query->execute(array());
    $m = $query->fetchAll(Doctrine_Core::FETCH_ASSOC);
    $lastmod = $m[0]['updated'];
    if($unix)$lastmod = strtotime($lastmod);
    return $lastmod;
  }
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
    public static function match($url, $exact=false)
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
        $q = self::query();
        $q->select('{e.*}');
        if (!tdzEntries::hasStaticPermission('previewEntryUnpublished')) {
            $q->from('tdz_entries_version as e')
              ->innerJoin('tdz_entries as o on o.id=e.id and (e.updated >= o.published || timediff(o.published,e.updated) <= "00:00:11") ')
              ->groupBy('e.id');
        } else {
            $q->from('tdz_entries as e');
        }
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
        $q->addComponent('e', 'tdzEntries')
          ->where('e.expired is null and ('
              . "(e.type in ('file') and e.link in ('{$url}'))"
              . " or (e.type in ('page','feed') and e.link in ('".implode("','", $purl)."'))"
              . ')')
          ->orderBy('e.version desc, e.link desc, e.published desc');
        $page= $q->execute();
        if ($page->count()==1) {
            $page=$page[0];
        } else if ($page->count()>1) {
            $pages = array();
            foreach($page as $p) {
                $pages[$p['link']]=$p;
            }
            krsort($pages);
            $page = array_shift($pages);
        } else {
            $page=false;
        }
        self::$matches[$url]=$page;
        
        return $page;
    }


  public static function find($entry=null,$options=array())
  {
    if($entry instanceof tdzEntries) return $entry;
    $q = self::query();
    $q->select('{e.*}')
      ->from("tdz_entries as e")
      ->addComponent('e', 'tdzEntries')
      ->where("e.id=?",$entry)
      ->orderBy('e.published desc, e.updated desc');
    if(!isset($options['expired']) || !$options['expired'])
      $q->andWhere('e.expired is null');
    if(!isset($options['hydrate']))
      $options['hydrate']=Doctrine::HYDRATE_RECORD;
    $e = $q->execute(array(),$options['hydrate']);
    if(isset($e[0]))return $e[0];
    else return false;
  }
  
  public static function getFiles()
  {
    $conn = self::connect();
    $sql='select e.link,e.source from tdz_entries as e where e.type=\'file\' and e.expired is null';
    $query = $conn->prepare($sql);
    $query->execute(array());
    $m = $query->fetchAll(Doctrine_Core::FETCH_ASSOC);
    $res = array();
    foreach($m as $f)
    {
      if(substr($f['link'],0,1)=='/')
        $res[$f['link']]=$f['link'];
      if(substr($f['source'],0,1)=='/')
        $res[$f['source']]=$f['link'];
    }
    return $res;
  }


  public static function getFolders($url='',$options=array())
  {
    $conn = self::connect();
    $url .= '/';
    $url = tdz::validUrl($url);
    if(substr($url,-1)!='/') $url .= '/';
    $len=strlen($url);
    $w='';
    if(isset($options['format']) && $options['format']!='')
    {
      $format=preg_replace('/[^a-z0-9]+/','',$options['format']).'/';
      $flen = strlen($format);
      if($format!='/') $w .= " and left(e.format,$flen)='$format'";
    }
    $sql="select distinct left(e.link,locate('/',e.link,$len +1)) as folder from tdz_entries as e where e.type in('page','file','feed') and left(e.link,$len)='$url' and locate('/',e.link,$len +1) > 0 and e.expired is null $w order by e.link";
    $query = $conn->prepare($sql);
    $query->execute(array());
    $m = $query->fetchAll(Doctrine_Core::FETCH_ASSOC);
    return $m;
  }
  public static function getLinks($url='',$options=array())
  {
    $url .= '/';
    $url = tdz::validUrl($url);
    if(substr($url,-1)!='/') $url .= '/';
    $len=strlen($url);
    $w='';
    if(isset($options['format']) && $options['format']!='')
    {
      $format=preg_replace('/[^a-z0-9]+/','',$options['format']).'/';
      $flen = strlen($format);
      if($format!='/') $w .= " and left(e.format,$flen)='$format'";
    }
    $q = new Doctrine_RawSql(self::connect());
    $q->select('{e.id},{e.updated},{e.link},{e.format},{e.type},{e.published},{e.source}')
      ->from("tdz_entries as e")
      ->addComponent('e', 'tdzEntries')
      ->where("e.type in('page','file','feed') and left(e.link,$len)='$url' and locate('/',e.link,$len +1) = 0 and e.expired is null $w")
      ->orderBy('e.link asc');
    if(!isset($options['hydrate']))
      $options['hydrate']=Doctrine::HYDRATE_RECORD;
    $entries = $q->execute(array(),$options['hydrate']);
    return $entries;
  }

  public function getLatestVersion()
  {
    $pub=$this->published;
    if($pub)$pub=strtotime($pub);
    $upd=$this->updated;
    if($upd)$upd=strtotime($upd);
    if($this->type!='entry' && (!$pub || $upd>$pub) && !$this->hasPermission('preview','Entry','Unpublished'))
    {
      // get latest published version
      $conn = self::connect();
      $sql='select e.* from tdz_entries_version as e inner join tdz_entries as o on o.id=e.id and e.updated >= o.published where e.id=? and e.expired is null group by e.id order by e.version desc, e.link desc, e.published desc limit 1';
      $query = $conn->prepare($sql);
      $query->execute(array($this->id));
      $v = $query->fetchAll(Doctrine_Core::FETCH_ASSOC);
      if(isset($v[0]['id']))
      {
        $fns=$this->getData();
        foreach($v[0] as $fn=>$fv)
          if($fn!='link' && isset($fns[$fn]))
            $this[$fn]=$fv;
      }
    }
  }

  public function filePreview($optimize=false)
  {
    if(!$this->hasPermission('preview') || $this->getType()!='file')
      return false;

    $file=false;
    $this->getLatestVersion();
    if (!tdzEntries::hasStaticPermission('previewEntryUnpublished')) {
        tdz::set('cache-control', 'public');
    }

    if(file_exists(sfConfig::get('app_e-studio_document_root').$this->source))
      $file = sfConfig::get('app_e-studio_document_root').$this->source;
    if(file_exists(sfConfig::get('app_e-studio_upload_dir').'/'.$this->source))
    {
      $ufile=sfConfig::get('app_e-studio_upload_dir').'/'.$this->source;
      if(!$file || filemtime($ufile)>filemtime($file))
        $file = $ufile;
    }
    else
      return false;

    $link = $this->getLink();
    if(!$link) $link = sfContext::getInstance()->getRequest()->getPathInfo();
    $fname = basename($link);
    if($optimize && sfConfig::get('app_e-studio_assets_optimize'))
    {
      $ext = strtolower(preg_replace('/.*\.([a-z0-9]{1,5})$/i','$1',basename($file)));
      $actions=sfConfig::get('app_e-studio_assets_optimize_actions');
      $cache=sfConfig::get('sf_app_cache_dir').'/tdzEntries/e'.$this->id.'.'.$this->version;
      if(isset($actions[$optimize]) && in_array(strtolower($ext),$actions[$optimize]['extensions']))
      {
        $options=$actions[$optimize];
        if(isset($options['params']))$params=$options['params'];

        $method=$options['method'];
        $ext = strtolower(preg_replace('/.*\.([a-z0-9]{1,5})$/i','$1',$fname));
        $extl = ($ext)?('.'.$ext):(0);
        $cachefile = $cache.'.'.$optimize.$extl;

        if(file_exists($cachefile) && filemtime($cachefile)>filemtime($file))
          $file = $cachefile;
        else
        {
          $data='';
          if(method_exists('tdz', $method))
            $data=tdz::$method($file,$params);
          else if(function_exists($method))
            $data=$method($file,$params);
          if($data!='')
          {
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

  public function getTags($options=array())
  {
    if(!is_array($options))$options=array();
    //$updated=$this->data['updated']);

    $q = tdzTags::query();
    if(!isset($options['hydrate'])) {
        $options['hydrate']=Doctrine::HYDRATE_RECORD;
    }
    if($this->hasPermission('previewUnpublished')) {
        $q->select('{t.*}')
          ->from("tdz_tags as t")
          ->addComponent('t', 'tdzTags')
          ->where("t.entry=?",$this->id);
        //->orderBy('t.tag desc');
        if(!isset($options['expired']) || !$options['expired'])
          $q->andWhere('t.expired is null');
        $tags = $q->execute(array(),$options['hydrate']);
    } else {
        $q->select('{t.*}')
          ->from("tdz_tags_version as t")
          ->addComponent('t', 'tdzTags')
          ->where("t.entry=? and t.updated < ?",array($this->id, $this->published))
          ->groupBy('t.id')
          ->orderBy('t.version desc');
        $tags = $q->execute(array(),$options['hydrate']);
        if(!isset($options['expired']) || !$options['expired']) {
            foreach ($tags as $i=>$tag) {
                if ($tag['expired']) {
                    unset($tags[$i]);
                }
            }
        }
    }
    return $tags;
  }

  public function getSortedContents($options=array())
  {
    if(!is_array($options))$options=array();
    $options['sort']=true;
    return $this->getContents($options);
  }

  public function getContents($options=array())
  {
    if(!is_array($options))return parent::_get('Contents');
    //if(!is_array($options))$options=array();
    //$updated=$this->data['updated']);

    $q = tdzContents::query();
    $q->select('{c.*}')
      ->from("tdz_contents as c")
      ->addComponent('c', 'tdzContents')
      ->where("c.entry=?",$this->id);
    if(isset($options['sort']) && $options['sort'])
      $q->orderBy('c.slot,ifnull(c.position,1)');
    if(!isset($options['expired']) || !$options['expired'])
      $q->andWhere('c.expired is null');
    if(!isset($options['hydrate']))
      $options['hydrate']=Doctrine::HYDRATE_RECORD;
    $contents = $q->execute(array(),$options['hydrate']);
    if(isset($options['sort']) && $options['sort'])
    {
      if(!isset($contents[0])) return array();
      $aslots=sfConfig::get('app_e-studio_slots');
      $sname=sfConfig::get('app_e-studio_default_slotname');
      $slots=(isset($aslots[$sname]))?($aslots[$sname]):(array_shift($aslots));
      $slots=array_keys($slots);
      $ac=array();
      foreach($contents as $k=>$c)
      {
        $sp=array_search($c['slot'],$slots);
        if($sp!==false)$sp=($sp*1000)+$k;
        else $sp=(count($slots)*1000)+$k;
        $ac[$sp]=$c;
      }
      ksort($ac);
      return array_values($ac);
    }
    return $contents;
  }


  public function getSummary($layout=null)
  {
    if("$layout"=='list')
    {
      $s = '';
      $type = $this->type;
      if(!$type) $type='entry';
      if($type=='file')
      {
        $format = $this->format;
        if(!$format) $format = tdz::fileFormat($this->source);
        if(substr($format,0,6)=='image/')
          $s .= '<span class="type '.$type.'"><img src="'.sfConfig::get('app_e-studio_prefix_url').sfConfig::get('app_e-studio_ui_url').'/e/preview/'.$this->id.'?optimize=icon" class="line-icon" /></span>';
      }
      if($s=='')
      {
        $types = sfConfig::get('app_e-studio_entry_types');
        $s .= '<span class="type '.$type.'">'.sfContext::getInstance()->getI18N()->__($types[$type]['label']).'</span>';
      }
      $link = $this->link;
      if($link)
      {
        $s .= '<span class="link">'.$link.'</span>';
      }
      $title=htmlspecialchars_decode(strip_tags($this->data['title']));
      $summary=htmlspecialchars_decode(strip_tags($this->data['summary']));
      if(strlen($title.$summary)>100)
        $summary = substr($summary,0,100-strlen($title));
      $s .= '<strong>'.$title.'</strong> '.$summary;

      return $s;
    }
    return $this->data['summary'];
  }
  public function getUpdated($layout=null)
  {
    if("$layout"=='list')
    {
      $updated=strtotime($this->data['updated']);
      $s = '';
      if($updated)
      {
        $today = mktime(0, 0, 0, date('n'), date('j'), date('Y'));
        $this_year = mktime(0, 0, 0, 1, 1, date('Y'));
        if($updated > $today)
          $s = date(sfConfig::get('app_e-studio_time_format'),$updated);
        else if($updated > $this_year)
          $s = date(sfConfig::get('app_e-studio_date_format'),$updated);
        else
          $s = date(sfConfig::get('app_e-studio_past_year_date_format'),$updated);
      }
      return $s;
    }
    return $this->data['updated'];
  }
  public function getStatus($layout=null)
  {
    $st='Unpublished';
    if($this->data['expired']!='' && strtotime($this->data['expired']))
      $st = 'Expired';
    else if($this->data['published']!='' && strtotime($this->data['published']))
      $st = 'Published';

    return $st;
  }

  public static function getPage($entry=null)
  {
    if($entry instanceof tdzEntries) return $entry;
    $q = new Doctrine_RawSql(self::connect());
    $q->select('{e.*}')
      ->from("tdz_entries as e")
      ->addComponent('e', 'tdzEntries')
      ->where("e.type='page' and e.id=?",$entry)
      ->orderBy('e.published desc, e.updated desc');
    $entries = $q->execute();
    return $entries->getFirst();
  }

  public function getParent($entry=null)
  {
    if(!$entry && isset($this))$entry=$this->getId();
    else if(is_object($entry)) $entry = $entry->getId();
    $q = new Doctrine_RawSql(self::connect());
    $q->select('{e.*}')
      ->from("tdz_entries as e inner join tdz_relations as r on r.parent=e.id and r.expired is null")
      ->addComponent('e', 'tdzEntries')
      ->where("e.type='page' and r.entry=?",$entry)
      ->orderBy('ifnull(r.position,0), e.published desc, e.updated desc');
    $entries = $q->execute();
    return $entries->getFirst();
  }

  public function getChild($entry=null, $options=array())
  {
    if(!$entry && isset($this))$entry=$this->getId();
    else if(is_object($entry)) $entry = $entry->getId();
    $q = new Doctrine_RawSql(self::connect());
    $q->select('{e.*}')
      ->from("tdz_entries as e inner join tdz_relations as r on r.entry=e.id and r.expired is null and e.expired is null")
      ->addComponent('e', 'tdzEntries')
      ->where("e.type='page' and r.parent=?",$entry)
      ->orderBy('ifnull(r.position,0), e.published desc, e.updated desc');
    if(!isset($options['hydrate']))
      $options['hydrate']=Doctrine::HYDRATE_RECORD;
    $rel = $q->execute(array(),$options['hydrate']);
    return $rel;
  }
  public function getParentFeed($entry=null, $options=array())
  {
    if(!$entry && isset($this))$entry=$this->getId();
    else if(is_object($entry)) $entry = $entry->getId();
    $q = new Doctrine_RawSql(self::connect());
    $q->select('{e.*}')
      ->from("tdz_entries as e inner join tdz_relations as r on r.parent=e.id and r.expired is null and e.expired is null")
      ->addComponent('e', 'tdzEntries')
      ->where("e.type='feed' and r.entry=?",$entry)
      ->orderBy('ifnull(r.position,0), e.published desc, e.updated desc');
    if(!isset($options['hydrate']))
      $options['hydrate']=Doctrine::HYDRATE_RECORD;
    $rel = $q->execute(array(),$options['hydrate']);
    return $rel;
  }
  public function getParentRelations($options=array())
  {
    $entry=$this->getId();
    $q = new Doctrine_RawSql(self::connect());
    $q->select('{r.*}')
      ->from("tdz_relations as r")
      ->addComponent('r', 'tdzRelations')
      ->where("r.expired is null and r.entry=?",$entry)
      ->orderBy('ifnull(r.position,0)');
    if(!isset($options['hydrate']))
      $options['hydrate']=Doctrine::HYDRATE_RECORD;
    $rel = $q->execute(array(),$options['hydrate']);
    return $rel;
  }
  public static function getPages($options=array())
  {
    $q = new Doctrine_RawSql(self::connect());
    $q->select('{e.*}')
      ->from("tdz_entries as e")
      ->addComponent('e', 'tdzEntries')
      ->where("e.type='page'")
      ->orderBy('e.title asc, e.published desc, e.updated desc');
    if(!isset($options['hydrate']))
      $options['hydrate']=Doctrine::HYDRATE_RECORD;
    $entries = $q->execute(array(),$options['hydrate']);
    return $entries;
  }

  public static function getSitemapChoices($e=null,$level=0)
  {
    if(is_null($e))
      $entries= tdzRelations::getRootEntries();
    else
    {
      $q = self::query();
      $q->select('{e.id},{e.title},{e.link},{e.published}')
        ->from("tdz_entries as e inner join tdz_relations as r on r.entry=e.id and r.expired is null")
        ->addComponent('e', 'tdzEntries')
        ->where("e.type='page' and e.expired is null and r.parent=?")
        ->orderBy('r.position, e.title asc, e.published desc, e.updated desc');
      $entries = $q->execute(array($e),Doctrine::HYDRATE_ARRAY);
    }
    $c=array();
    foreach($entries as $k=>$v)
    {
      $c[$v['id']]=str_repeat("&#160;&#160;",$level).'→ '.$v['title'];
      $c+=self::getSitemapChoices($v['id'],$level+1);
    }
    return $c;
  }

  public static function getFeedChoices($e=null,$level=0)
  {
    $c=array();
    $f=self::getFeeds(array('hydrate'=>Doctrine::HYDRATE_ARRAY));
    foreach($f as $k=>$v)
    {
      $c[$v['id']]=$v['title'];
    }
    $c+=self::getSitemapChoices();
    return $c;
  }

  public static function getFeeds($options=array())
  {
    $q = new Doctrine_RawSql(self::connect());
    $q->select('{e.*}')
      ->from("tdz_entries as e")
      ->addComponent('e', 'tdzEntries')
      ->where("e.type='feed'")
      ->orderBy('e.title asc, e.published desc, e.updated desc');
    if(!isset($options['hydrate']))
      $options['hydrate']=Doctrine::HYDRATE_RECORD;
    $entries = $q->execute(array(),$options['hydrate']);
    return $entries;
  }
  public static function getMasters()
  {
    $files = array_merge(glob(sfConfig::get('sf_app_template_dir').'/entry_*.php'),glob(sfConfig::get('sf_app_template_dir').'/feed_*.php'),glob(sfConfig::get('sf_app_template_dir').'/tdz_*.php'),glob(sfConfig::get('app_e-studio_template_dir').'/tdz_*.php'));
    $choices=array(''=>' Default');
    foreach($files as $file)
    {
      $master = basename($file,'.php');
      if(isset($choices[$master]))continue;
      $title = $master;
      $fl=file($file);
      if(preg_match('/\*\s*([^\s].*)/',$fl[2],$m))$title=$m[1];
      $choices[$master]=$title;
    }
    asort($choices);
    return $choices;
  }

  public function getLayout($master=null, $url=null)
  {
    if(is_null($url))
      $url = $this->getLink();

    $cachefile = false;
    if($url!='' && substr($url,0,1)=='/')
    {
      if($this->id>0)
        $cachefile=sfConfig::get('sf_app_cache_dir').'/tdzEntries/e'.$this->id.'.'.$this->version;
      else
      {
        if(substr($url,-1)=='/') $url.= 'index';
        $cachefile=sfConfig::get('sf_app_cache_dir').'/web'.$url;
      }
    }
    $timeout = sfConfig::get('app_e-studio_cache_timeout');
    $timeout = time() - (int)$timeout;
    $pagemtime = $this->lastModified(true);
    if($pagemtime && $pagemtime > $timeout) $timeout=$pagemtime;
    if(!file_exists("{$cachefile}.php") || filemtime("{$cachefile}.php")<=$timeout)
    {
      if(!$master)
        $master = $this->getMaster();
      if($master=='')
      {
        $ids=array("{$this->id}"=>true);
        $entry = $this;
        while($entry->getParent())
        {
          $entry = $entry->getParent();
          $id=$this->id;
          if(!$id || isset($ids[$id])) break;
          $ids[$id]=true;
          $master = $entry->getMaster();
          if($master!='')
            break;
        }
      }
      if(!$master || (!file_exists("{$master}.php") && !file_exists(sfConfig::get('sf_app_template_dir')."/{$master}.php")))
        $master = sfConfig::get('app_e-studio_default_layout');
      if(!file_exists("{$master}.php") && file_exists(sfConfig::get('app_e-studio_template_dir')."/{$master}.php"))
        $master = sfConfig::get('app_e-studio_template_dir')."/{$master}.php";
      else if(!file_exists("{$master}.php") && file_exists(sfConfig::get('sf_app_template_dir')."/{$master}.php"))
        $master = sfConfig::get('sf_app_template_dir')."/{$master}.php";
      if(!$master)
        $master=false;

      /**
       * find out which slots are available. These should be configured either in
       * app.yml or as a routing parameter
       */
      $slots = sfConfig::get('app_e-studio_slots');
      $slotname = sfContext::getInstance()->getRequest()->getParameter('tdz/slots');
      if(!$slotname || !isset($slots[$slotname]))
        $slotname = sfConfig::get('app_e-studio_default_slotname');

      $slots = $slots[$slotname];
      $add=array();
      if(!isset($slots['title']))$add['title']=$this->getTitle();
      if(!isset($slots['meta']))$add['meta']='<meta name="generator" content="Tecnodesign E-Studio - http://tecnodz.com" />';
      if(count($add)>0)
        $slots = array_merge($add,$slots);
      foreach($slots as $slotname=>$slot)
      {
        if(is_null($slot))
          $slots[$slotname] = array();
        else if(is_array($slot) && isset($slot[0]))
          $slots[$slotname] = $slot;
        else
          $slots[$slotname] = array(array($slot));
      }

      $contents = $this->getAllContents();
      $layout_type='static';
      $widgets=false;
      if($contents->count()>0)
      {
        foreach($contents as $content)
        {
          $content->getLatestVersion();
          if($content->content_type=='php')$layout_type='dynamic';
          else if($content->content_type=='widget')
          {
            if(!$widgets)$widgets=sfConfig::get('app_e-studio_widgets');
            $ctmp=sfYaml::load($content->content);
            if(isset($widgets[$ctmp['app']]['cache']) && !$widgets[$ctmp['app']]['cache'])
              $layout_type='dynamic';
          }
          $slot = $content->getSlot();
          if($slot=='')$slot = 'body';
          $pos = (int)$content->getPosition();
          if(!isset($slots[$slot]))
            $slots[$slot]=array();
          if(!isset($slots[$slot][$pos]))
            $slots[$slot][$pos]=array();
          else if(!is_array($slots[$slot][$pos]))
            $slots[$slot][$pos]=array($slots[$slot][$pos]);
          if($content->getEntry()=='')$content->setEntry($this->getId());
          //$slots[$slot][$pos][] = $content->render();
          $slots[$slot][$pos][] = array('export'=>"get_component('tdz_contents','preview',array('id'=>'{$content->id}','version'=>'{$content->version}'))");
        }
      }
      $phpheader = '<'."?php\n//{$layout_type}\n";
      $helper = sfConfig::get('app_e-studio_helper_dir').'/tdzEStudioHelper.php';
      $phpheader .= "if(!function_exists('tdz_eval'))require_once '{$helper}';";
      $merge = array();
      $slotelements = array('header'=>true,'footer'=>true,'nav'=>true);
      $class='tdzs container';
      //if($this->hasPermission('new', 'Content'))$class .= ' new';
      //if($this->hasPermission('edit', 'Content'))$class .= ' edit';
      //if($this->hasPermission('publish', 'Content'))$class .= ' publish';
      $addbr='';

      foreach($slots as $slotname=>$slot)
      {
        ksort($slot);
        $first = true;
        $phpheader .= "\n\${$slotname} = ";
        if(isset($slotelements[$slotname]) && $slotelements[$slotname])
        {
          $phpheader .= " '<{$slotname}><div id=\"{$slotname}\"><div class=\"{$class}\">'";
          $first = false;
          $merge[]='$'.$slotname;
        }
        else if($slotname!='meta' && $slotname!='title')
        {
          $phpheader .= " '<div id=\"{$slotname}\"><div class=\"{$class}\">'";
          $first = false;
          $merge[]='$'.$slotname;
        }
        else if(count($slot)==0) {
            $phpheader .= "''";
        }
        if ($first) {
            $phpheader .= "\n tdz::get('before-{$slotname}')";
            $first = false;
        } else {
            $phpheader .= "\n . tdz::get('before-{$slotname}')";
        }
        if($slotname=='body') $phpheader .= "\n .\$sf_content";
        foreach($slot as $slotfrag)
        {
          if(!is_array($slotfrag))$slotfrag=array($slotfrag);
          foreach($slotfrag as $v)
          {
            if($first)
            {
              $first = false;
              $phpheader .= " ";
            }
            else
              $phpheader .= "\n .";

            if(is_array($v) && isset($v['before']))
              $phpheader .= var_export($v['before'],true).'.';

            if(is_array($v) && isset($v['export']))
            {
              $phpheader .= $v['export'];
            }
            else if(is_array($v))
            {
              $phpheader .= var_export($v['content'],true);
            }
            else
            {
              $phpheader .= var_export($v,true);
            }
            if(is_array($v) && isset($v['after']))
              $phpheader .= '.'.var_export($v['after'],true);
          }
        }
        $phpheader .= ';';
      }
      foreach($slots as $slotname=>$slot)
      {
        if(isset($slotelements[$slotname]) && $slotelements[$slotname])
        {
          $phpheader .= "\n\${$slotname} .= tdz::get('{$slotname}').'</div></div></{$slotname}>{$addbr}';";
        }
        else if($slotname!='meta' && $slotname!='title')
        {
          $phpheader .= "\n\${$slotname} .= tdz::get('{$slotname}').'</div></div>{$addbr}';";
        }
      }
      $phpheader .= "\n\$meta.=tdz::meta();";
      if(count($merge)>0)
        $phpheader .= "\n\$sf_content = ".implode('.',$merge).';';
      $phpheader .= "\n?".'>';

      $layout = $phpheader.file_get_contents($master);
      if(!$layout)
        return sfConfig::get('app_e-studio_default_layout');
      if(!is_dir(dirname($cachefile))) mkdir(dirname($cachefile),0777,true);
      file_put_contents("{$cachefile}.php", $layout);
      @chmod("{$cachefile}.php",0666);
      
    }
    return $cachefile;
  }

  public function getAllContents($where='', $wherep=array())
  {
    $link = preg_replace('/[^a-z0-9\/\.\-\_]+/i', '', $this->getLink());
    $linkp = preg_split('#/+#', $link, null, PREG_SPLIT_NO_EMPTY);
    $id=$this->getId();
    $w = '';
    $ws = "find_in_set('*',replace(replace(c.show_at, '\\r', ''), '\\n', ','))";
    $wh = "c.hide_at is null or not(find_in_set('*',replace(replace(c.hide_at, '\\r', ''), '\\n', ','))";
    $wp = array('id'=>$id);
    $ast='';
    $pi=1;
    if(count($linkp)==0)$linkp[]='';
    foreach($linkp as $p){
      $up = '';
      $ast .= '/'.$p;
      if($ast==$link)
        $up = $ast;
      else
        $up= $ast.'/*';

      $ws .= " or find_in_set('{$up}',replace(replace(c.show_at, '\\r', ''), '\\n', ','))";
      $wh .= " or find_in_set('{$up}',replace(replace(c.hide_at, '\\r', ''), '\\n', ','))";
      $pi++;
    }
    $wh .= ')';
    if($id<=0)
      $w = "(($ws) and ($wh))";
    else
      $w = "c.entry=e.id or (($ws) and ($wh))";
    if($where!='')
    {
      $w = "({$w}) and ({$where})";
      $wp += $wherep;
    }
    
    $tn='tdz_contents as c';
    if(!tdzEntries::hasStaticPermission('previewContentUnpublished'))
      $tn = '(select c.id,c.entry,c.slot,c.content_type,c.content,c.position,c.published,c.show_at,c.hide_at,c.version,c.created,c.updated,c.expired from (tdz_contents_version c join tdz_contents o on(((o.id = c.id) and (c.updated >= o.published) and isnull(c.expired)))) group by c.id order by c.id,c.version desc) as c';
      //$tn="tdz_contents_published as c";
      
    if($id<=0)
    {
      $from = $tn;
      unset($wp['id']);
    }
    else
    {
      $from = "{$tn}, tdz_entries as e";
      $w="e.type='page' and e.expired is null and e.id=:id and c.expired is null and ($w)";
    }
    //tdz::debug("select c.* from {$from} where {$w} order by c.position", $wp);
    $q = new Doctrine_RawSql(self::connect());
      $q->select('{c.*}')
        ->from($from)
        ->addComponent('c', 'tdzContents')
        ->where($w,$wp)
        ->orderBy('c.position');
    $contents = $q->execute();
    return $contents;
  }

  public static function hasStaticPermission($role='preview',$object='Entry',$published='')
  {
    $credentials=self::getStaticPermission($role, $object, $published);
    if(is_bool($credentials))
      return $credentials;
    else
    {
      return sfContext::getInstance()->getUser()->hasCredential($credentials,false);
    }
  }
  public static function getStaticPermission($role='preview',$object='Entry',$published='')
  {
     // first it must recurse through all parents and global config to get all credentials that match the desired permission
    $valid_roles = array('all','new','edit','publish','delete','preview','search');
    $valid_objects = array('Entry','Content','Permission','ContentType');
    $valid_status = array('Published','Unpublished');

    if(preg_match_all('/[A-Z][a-z]+/',$role,$m))
    {
      $role=str_replace($m[0],array(),$role);
      if(in_array($m[0][0],$valid_objects))
        $object=array_shift($m[0]);
      if(in_array($m[0][0],$valid_status))
        $published=array_shift($m[0]);
    }
    if(!in_array($role,$valid_roles))return false;
    if(!in_array($object,$valid_objects))return false;

    $order=array("{$role}{$object}{$published}", "{$role}{$object}", "{$role}{$published}", "{$role}", 'all');
    $ep=array();
    $d=sfConfig::get('app_e-studio_permissions');
    foreach($d as $r=>$c)
    {
      $rk=array_search($r, $order);
      if($rk!==false && !isset($ep[$rk]))
        $ep[$rk]=$c;
    }
    $c=false;
    ksort($ep);
    foreach($ep as $r=>$c) break;

    if(!is_array($c)) $c = preg_split('/[\s\n]+/', $c, null, PREG_SPLIT_NO_EMPTY);
    $cs=implode('',$c);
    $result=false;
    if($cs=='*') $result=true;
    else if($cs=='') $result=false;
    else $result=$c;

    $credentials=$result;
    return $credentials;
  }

  public function getPermission($role='preview',$object='Entry',$published='')
  {
    // first it must recurse through all parents and global config to get all credentials that match the desired permission
    $valid_roles = array('all','new','edit','publish','delete','preview','search');
    $valid_objects = array('Entry','Content','Permission','ContentType');
    $valid_status = array('Published','Unpublished');

    if(preg_match_all('/[A-Z][a-z]+/',$role,$m))
    {
      $role=str_replace($m[0],array(),$role);
      if(in_array($m[0][0],$valid_objects))
        $object=array_shift($m[0]);
      if(in_array($m[0][0],$valid_status))
        $published=array_shift($m[0]);
    }
    if(!in_array($role,$valid_roles))return false;
    if(!in_array($object,$valid_objects))return false;
    if(!in_array($published,$valid_status))
    {
      if($this->published=='')$published='Unpublished';
      else $published='Published';
    }

    $entry = $this;

    $cache=false;
    if($cache)
      $co=new sfAPCCache();
    $key="{$role}{$object}{$published}-e{$this->id}";
    $updated=$this->lastModified(true);
    if($cache && $co->getLastModified($key)>=$updated)
      return $co->get($key);
    else $updated=time();

    // mat get this information from contents, or other objects
    //$published='Published';
    $order=array("{$role}{$object}{$published}", "{$role}{$object}", "{$role}{$published}", "{$role}", 'all');

    $ep=array();
    $q = false;

    // cache query results per entry
    $recurse=3;
    while($recurse && $entry)
    {
      $id=(is_object($entry))?($entry->id):($entry);
      $cid='permissions-e'.$id;
      if($cache && $co->getLastModified($cid)>=$updated)
        $permissions=$co->get($cid);
      else
      {
        if($q===false)
        {
          $q = Doctrine_Query::create(self::connect());
          $q->select('p.role, p.credentials')->from('tdzPermissions p');
        }

        $permissions = $q->where("p.entry=?",$id)->fetchArray();
        if($cache)
          $co->set($cid, $permissions, 3600);
      }
      if(count($permissions) > 0)
      {
        foreach($permissions as $k=>$v)
        {
          $rk=array_search($v['role'], $order);
          if($rk!==false && !isset($ep[$rk]))
            $ep[$rk]=$v['credentials'];
        }
        if(isset($ep[0])) break;
      }
      if(!$recurse--)break;
      $entry = $this->getParent($id);

    }
    if(!isset($ep[0]))
    {
      $d=sfConfig::get('app_e-studio_permissions');
      foreach($d as $r=>$c)
      {
        $rk=array_search($r, $order);
        if($rk!==false && !isset($ep[$rk]))
          $ep[$rk]=$c;
      }
    }
    $c=false;
    ksort($ep);
    foreach($ep as $r=>$c) break;

    if(!is_array($c)) $c = preg_split('/[,\s\n]+/', $c, null, PREG_SPLIT_NO_EMPTY);
    $cs=implode('',$c);
    $result=false;
    if($cs=='*') $result=true;
    else if($cs=='') $result=false;
    else $result=$c;

    if($cache)
      $co->set($key, $result, 3600);

    return $result;
  }

  public function hasPermission($role='preview',$object='Entry',$published='Published')
  {
    $credentials=$this->getPermission($role, $object, $published);
    if(is_bool($credentials))
      return $credentials;
    else
    {
      return sfContext::getInstance()->getUser()->hasCredential($credentials,false);
    }
  }

  public static function entryPreview($entry,$template='tdz_entry')
  {
    /**
     * should cache:
     * {
     *   credentials: string|array
     *   result: string
     */
    $credentials=false;
    $result='';
    $tpl=basename($template,'.php');
    if(substr($tpl,0,4)=='tdz_')$tpl=ucfirst(substr($tpl,4));
    $co=new sfFileCache(array('cache_dir'=>sfConfig::get('sf_app_cache_dir').'/tdzEntries'));
    $cvar=false;
    $pass=false;
    $lifetime=sfConfig::get('app_e-studio_cache_timeout');
    $timeout=time() - $lifetime;
    if(!is_object($entry) && $entry=='')
    {
      $request=sfContext::getInstance()->getRequest();
      $entry = tdzEntries::match($request->getPathInfo());
      if(!$entry && $request->getParameter('id')!='') $entry = Doctrine_Core::getTable('tdzEntries')->findById($request->getParameter('id'))->getFirst();
    }
    $id=$entry;
    if(is_object($entry) && $entry instanceof tdzEntries)
      $id=$entry->id;
    else
      $entry=Doctrine_Core::getTable('tdzEntries')->findById($id)->getFirst();

    $entry->getLatestVersion();
    $id .= ".{$entry->version}.".$tpl;
    $cmod=$co->getLastModified('e'.$id);
    $emod=strtotime($entry['updated']);
    if($cmod>$timeout && $cmod>$emod)
    {
      $cvar=$co->get('e'.$id);
      if($cvar) {
        $cvar=unserialize($cvar);
      }
    }
    if(is_array($cvar)) {
        if(isset($cvar['variables'])) {
            foreach($cvar['variables'] as $k=>$v) {
                tdz::set($k, $v);
            }
        }
      foreach($cvar as $k=>$v) {
        $$k=$v;
      }
    }
    else
    {
      $credentials=$entry->getPermission('preview');
      if(substr($template,0,1)=='/')$template=basename($template,'.php');
      if($template!='' && file_exists(sfConfig::get('sf_app_template_dir').'/'.$template.'.php'))
        $template=sfConfig::get('sf_app_template_dir').'/'.$template.'.php';
      else if($template!='' && file_exists(sfConfig::get('app_e-studio_template_dir').'/'.$template.'.php'))
        $template=sfConfig::get('app_e-studio_template_dir').'/'.$template.'.php';
      else if(file_exists(sfConfig::get('sf_app_template_dir').'/tdz_entry.php'))
        $template=sfConfig::get('sf_app_template_dir').'/tdz_entry.php';
      else if(file_exists(sfConfig::get('app_e-studio_template_dir').'/tdz_entry.php'))
        $template=sfConfig::get('app_e-studio_template_dir').'/tdz_entry.php';
      else
        $template=false;
      $contents=$entry->getContents();
      /*
      $q = new Doctrine_RawSql(self::connect());
      $q->select('{c.*}')
       ->from("tdz_contents as c")
       ->addComponent('c', 'tdzContents')
       ->where("c.entry is not null and c.entry=:entry and c.content_type='media'",array('entry'=>$entry->id))
       ->orderBy('c.position asc');
      $contents = $q->execute();
       */
      $fig=array();
      if($contents->count()>0)
      {
        foreach($contents as $content)
        {
          if($content->content_type=='media')
            $fig[$content->id]=sfYaml::load($content->content);
        }
      }
      $tags = array();
      $alltags = $entry->getTags();
      if ($alltags->count() > 0) {
          foreach($alltags as $tag) {
              $tags[$tag->slug] = $tag->tag;
          }
      }
      $vars=$entry->getData();
      $vars['tags'] = $tags;
      $vars['figures']=$fig;
      $vars['contents']=$contents->getData();
      $pv = tdz::$variables;
      $result=tdz::exec(array('variables'=>$vars,'script'=>$template));
      $rv = tdz::$variables;
      $vars = array_diff($rv, $pv);
      $cvar=array('credentials'=>$credentials,'result'=>$result);
      if(count($vars)>0) {
          $cvar['variables']=$vars;
      }
      $co->set('e'.$id,serialize($cvar),$lifetime);
    }
    if(!isset($credentials)||(is_bool($credentials)&&$credentials==false)||(!is_bool($credentials)&&!sfContext::getInstance()->getUser()->hasCredential($credentials,false)))
      $result=false;
    
    return $result;
  }

  public static function feedPreview($entry,$template='tdz_feed', $options=array())
  {
    /**
     * should cache:
     * {
     *   credentials: string|array
     *   result: string
     */
    $credentials=false;
    $result='';
    $request=false;
    $usecache=true;
    $self=false;
    $tags=false;
    $meta=false;
    if(!is_object($entry) && $entry=='')
    {
      $request=sfContext::getInstance()->getRequest();
      $self = $entry = tdzEntries::match(tdz::scriptName(true));
      if(!$entry && $request->getParameter('id')!='') $self = $entry = Doctrine_Core::getTable('tdzEntries')->findById($request->getParameter('id'))->getFirst();      
    }
    $id=$entry;
    if(is_object($entry) && $entry instanceof tdzEntries)
      $id=$entry->id;
    $ckey=$id;
    if($template=='')$template='tdz_feed';
    $tpl=basename($template,'.php');
    if(substr($tpl,0,4)=='tdz_')$tpl=ucfirst(substr($tpl,4));
    //$ckey .= ".{$entry->version}.{$tpl}";
    $ckey .= ".{$tpl}";

    $user=sfContext::getInstance()->getUser();
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
    if(isset($options['limit']) && $options['limit']){
      $limit=$add['limit']=$options['limit'];
      $ckey.='.l'.$options['limit'];
    }
    if(isset($template) && substr(basename($template),0,14)=='tdz_navigation'){
      // context matters for navigation
      $ckey.='.u'.md5(tdz::scriptName());
    }
    $usepager=false;
    $add['hpp']=false;
    if(isset($options['hpp']) && $options['hpp'] && (!isset($options['limit']) || $options['hpp']<=$options['limit'])){
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
          if(!$request)$request=sfContext::getInstance()->getRequest();
          $self = tdzEntries::match(tdz::scriptName(true));
          if(!$self && $request->getParameter('id')!='') $self = Doctrine_Core::getTable('tdzEntries')->findById($request->getParameter('id'))->getFirst();      
        }
        if($self){
          $ckey .= '.r'.$self->link;
          $tags = array();
          foreach($self->getTags(array('hydrate'=>Doctrine::HYDRATE_ARRAY)) as $tag)
            $tags[$tag['slug']]=$tag['tag'];
        }
      }
    }
    $preview=false;
    if(isset($add['preview']) && tdz::scriptName()!=tdz::scriptName(true)){
      $q = new Doctrine_RawSql(self::connect());
      $q->select('{e.id}, {e.title}, {e.summary}, {e.link}')
      ->from("tdz_entries as e inner join tdz_relations as r on r.entry=e.id and r.expired is null left outer join tdz_permissions as p on p.entry=e.id and p.role='previewPublished' and p.expired is null")
      ->addComponent('e', 'tdzEntries')
      ->where("e.expired is null and e.link=? and r.parent=?",array(tdz::scriptName(true), $id))
      ->andWhere('(p.entry is null'.$ucsql.')');
      $ep = $q->execute(array());
      if(isset($ep[0])) {
          tdz::set('entry',$ep[0]);
          tdz::set('feed-preview',$ep[0]['id']);
          tdz::set('title',$ep[0]['title']);
          return self::entryPreview($ep[0]['id'],'tdz_entry_preview');
      }
    }

    $co=new sfFileCache(array('cache_dir'=>sfConfig::get('sf_app_cache_dir').'/tdzEntries'));
    $cvar=false;
    $pass=false;
    $lifetime=sfConfig::get('app_e-studio_cache_timeout');
    $timeout=time() - $lifetime;
    $cmod=$co->getLastModified('e'.$ckey);
    $emod=self::lastModified(true);
    
    if($usecache && $ckey && $cmod>$timeout && $cmod>$emod){
      $cvar=$co->get('e'.$ckey);
      if($cvar)
        $cvar=unserialize($cvar);
    }
    $result=null;
    $exec=false;
    if(is_array($cvar)){
      foreach($cvar as $k=>$v)
        $$k=$v;
    } else {
      $fe=$entry;
      if(!is_object($fe))
       $fe = tdzEntries::find($id);
      if($fe) {
        $fe->getLatestVersion();
        $credentials=$fe->getPermission('preview');
      } else {
        $credentials=tdzEntries::getStaticPermission('preview');
      }
  
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
          $q = Doctrine_Query::create()->select('e.*')
          ->from('tdzEntries e')
          ->innerJoin('e.Relations r on r.entry=e.id and r.expired is null and e.expired is null')
          ->leftJoin('e.Permissions p on p.entry=e.id and p.role=\'previewPublished\' and p.expired is null')
          ->where('r.parent=?',$id)
          ->andWhere('p.credentials is null'.$ucsql)
          ->orderBy('e.published desc, e.updated desc');
          if($tags) {
              $q->innerJoin('e.Tags t on t.entry=e.id and t.expired is null and t.slug in (\''.implode('\',\'',array_keys($tags)).'\')');
          }
          if($limit) {
            $q->limit($limit);
          }
          if(!$usepager) {
            $feed = $q->execute();
          }
          if(substr($template,0,1)=='/')$template=basename($template,'.php');
          if(strpos($template, 'feed')===false && strpos($template, 'atom')===false) {
            $add['template']=$template;
            $template='tdz_feed';
          }
          if($template!='' && file_exists(sfConfig::get('sf_app_template_dir').'/'.$template.'.php'))
            $template=sfConfig::get('sf_app_template_dir').'/'.$template.'.php';
          else if($template!='' && file_exists(sfConfig::get('app_e-studio_template_dir').'/'.$template.'.php'))
            $template=sfConfig::get('app_e-studio_template_dir').'/'.$template.'.php';
          else if(file_exists(sfConfig::get('sf_app_template_dir').'/tdz_feed.php'))
            $template=sfConfig::get('sf_app_template_dir').'/tdz_feed.php';
          else if(file_exists(sfConfig::get('app_e-studio_template_dir').'/tdz_feed.php'))
            $template=sfConfig::get('app_e-studio_template_dir').'/tdz_feed.php';
          else
            $template=false;
  
          $vars=$fe->getData();
          $vars+=$add;
          $vars['entry']=$entry;
          $vars['entries']=$feed;
          $vars['query']=$q;
          $exec=array('variables'=>$vars,'script'=>$template);
          $result=tdz::exec($exec);
        }
        if($usecache) {
          $cvar=array('credentials'=>$credentials,'result'=>$result, 'meta'=>$meta);
          $co->set('e'.$ckey,serialize($cvar),$lifetime);
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

  public function uiButtons($cui='',$options=array())
  {
    $s = '';
    $referer=array('path'=>'');
    if(isset($_SERVER['HTTP_REFERER']))
      $referer=parse_url($_SERVER['HTTP_REFERER']);
    $ui = sfConfig::get('app_e-studio_prefix_url').sfConfig::get('app_e-studio_ui_url');
    $id = $this->id;
    if(!$id)$id='0';
    $t=sfContext::getInstance()->getI18N();
    $img = sfConfig::get('app_e-studio_prefix_url').sfConfig::get('app_e-studio_assets_url').'/images/icons.png';

    if($id)
      $btns=array('search','files','new','edit','preview','publish','unpublish');
    else
      $btns=array('search','files','new');
    foreach($btns as $btn)
    {
      $b = $btn;
      if($b=='unpublish' && !$this->published) continue;
      else if($b=='unpublish')$btn='publish';
      if($btn=='preview' && $this->link!='' && $this->type!='entry')
        $url = $this->link;
      else if($btn=='search')
        $url = "{$ui}/e";
      else if($btn=='files')
      {
        $url = "{$ui}/e/files";
        $btn = 'search';
      }
      else
        $url = "{$ui}/e/{$b}/{$id}";
      if(isset($options[$b])) $url .= '?'.htmlspecialchars($options[$b], ENT_QUOTES);
      else if($referer['path']==$url && isset($referer['query'])) $url .= '?'.htmlspecialchars($referer['query'], ENT_QUOTES);
      if($cui!=$b && $this->hasPermission($btn)) $s .= "<a class=\"btn {$b}\" href=\"{$url}\" title=\"{$t->__(ucfirst($b))}\"></a>";
      //else if($cui==$btn) $s .= "<a class=\"{$btn} selected\"><img src=\"{$img}\" alt=\"{$t->__(ucfirst($btn))}\" /></a>";
    }
    return $s;
  }


  public static function addContent($o, $slotname='default')
  {
    if(!is_array($o))$o=array('body'=>(string)$o);
    $cfgslots = sfConfig::get('app_e-studio_slots');
    $slots =& $cfgslots[$slotname];
    foreach($o as $k=>$v)
    {
      if(!isset($slots[$k]))$slots[$k]=array();
      else if(!is_array($slots[$k]))$slots[$k]=array($slots[$k]);
      if(!is_array($v)) $slots[$k][]=array($v);
      else
      {
        foreach($v as $ck=>$cv)
          $slots[$k][$ck].=$cv;
      }
    }
    sfConfig::set('app_e-studio_slots',$cfgslots);
  }
  public function cleanCache()
  {
    if($this->id!='')
      tdz::cleanCache('tdzEntries/e'.$this->id);
  }
  public function setLink($s)
  {
    if($this->type=='page')
      $s=tdz::validUrl($s);
    return $this->_set('link',$s);
  }
  public function setShowAt($s)
  {
    return $this->_set('show_at',str_replace("\r",'',$s));
  }
  public function setHideAt($s)
  {
    return $this->_set('hide_at',str_replace("\r",'',$s));
  }
  public function preInsert($event)
  {
    $web=(isset($_SERVER['REQUEST_URI']));
    if($web || !strtotime($this->created))
      $this->created = date('Y-m-d H:i:s', time());
    if($web || !strtotime($this->updated))
      $this->updated = date('Y-m-d H:i:s', time());
  }
  public function preUpdate($event)
  {
    $web=(isset($_SERVER['REQUEST_URI']));
    if($web || !strtotime($this->updated))
      $this->updated = date('Y-m-d H:i:s', time());
  }
}