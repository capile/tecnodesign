<?php
/**
 * Pindorama migration
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: tdzPindoramamigrationTask.class.php 558 2011-01-12 11:57:59Z capile $
 */
class tdzPindoramamigrationTask extends sfBaseTask
{
  private $updated=array(),$next=array();
  protected function configure()
  {
    // // add your own arguments here
    $this->addArguments(array(
    //   new sfCommandArgument('id', sfCommandArgument::OPTIONAL, 'Document ID'),
    //   new sfCommandArgument('level', sfCommandArgument::OPTIONAL, 'Levels of recursion'),
    ));

    $this->addOptions(array(
      new sfCommandOption('app', null, sfCommandOption::PARAMETER_REQUIRED, 'Application', 'estudio'),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environment', 'dev'),
      new sfCommandOption('id', null, sfCommandOption::PARAMETER_OPTIONAL, 'Document ID', ''),
      new sfCommandOption('url', null, sfCommandOption::PARAMETER_OPTIONAL, 'Document ID', ''),
      new sfCommandOption('level', null, sfCommandOption::PARAMETER_OPTIONAL, 'Levels of recursion', '0'),
      new sfCommandOption('ask', null, sfCommandOption::PARAMETER_OPTIONAL, 'Ask for confirmation', false),
      // add your own options here
    ));

    $this->namespace        = 'tdz';
    $this->name             = 'pindorama-migration';
    $this->briefDescription = '';
    $this->detailedDescription = <<<EOF
The [tdz:pindorama-migration|INFO] task reads the XML database from Pindorama (v1.0) and delivers it as e-Studio pages and content.
Call it with:

  [php symfony tdz:pindorama-migration|INFO]
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {

    // initialize the database connection
    $configuration = ProjectConfiguration::getApplicationConfiguration($options['app'], $options['env'], true);
    sfContext::createInstance($configuration);

    // add your code here
    $this->confirm=($options['ask']!==false);
    $this->found_uris=array();
    $this->found_entries=array();
    $this->uris = new SimpleXMLElement(file_get_contents(sfConfig::get('sf_root_dir').'/../pindorama/var/index/uris.xml'));
    $this->sitemap = new SimpleXMLElement(file_get_contents(sfConfig::get('sf_root_dir').'/../pindorama/var/index/sitemap.xml'));
    $this->updated['uris']=filemtime(sfConfig::get('sf_root_dir').'/../pindorama/var/index/uris.xml');
    $this->updated['sitemap']=filemtime(sfConfig::get('sf_root_dir').'/../pindorama/var/index/sitemap.xml');
    $this->root=sfConfig::get('sf_root_dir').'/../pindorama/var/public/htdocs';
    $this->pages = array();
    $this->slotnames = sfConfig::get('app_e-studio_migration_slots');
    if(!is_array($this->slotnames))$this->slotnames=array();

    $id=$options['id'];
    if($id=='' && $options['url']!='')
    {
      $xp=array("//uri[.='{$options['url']}']");
      if(strpos($options['url'],'.')===false)
      {
        if(substr($options['url'],-1)!='/')
        {
          $xp[]="//uri[.='{$options['url']}/']";
          $xp[]="//uri[.='{$options['url']}/index.html']";
          $xp[]="//uri[.='{$options['url']}/index.php']";
        }
        else
        {
          $xp[]="//uri[.='{$options['url']}index.html']";
          $xp[]="//uri[.='{$options['url']}index.php']";
        }
      }

      foreach($xp as $k=>$v)
      {
        $pp=$this->uris->xpath($v);
        if($pp){
          $a=$pp[0]->attributes();
          $id=$a['id'];
          break;
        }
      }
      if($id=='') return false;
    }else if($id=='')$id='root';


    // create one entry for each page in sitemap
    $this->indent=9;
    $this->parsed=array();
    $this->data = array(
      'tdzEntries'=>array(),
      'tdzContents'=>array(),
      'tdzRelations'=>array(),
      'tdzPermissions'=>array(),
      'tdzTags'=>array(),
    );


    $this->conn = tdzEntries::connect();
    $sql = 'select max(e.id) as entry, max(c.id) as content, max(r.id) as relation from tdz_entries as e, tdz_contents as c, tdz_relations as r';
    $query = $this->conn->prepare($sql);
    $query->execute(array());
    $r = $query->fetchAll(Doctrine_Core::FETCH_ASSOC);
    if(count($r)>0)
    {
      $this->next['entry']=$r[0]['entry']+1;
      $this->next['content']=$r[0]['content']+1;
      $this->next['relation']=$r[0]['relation']+1;
    }
    else $this->next=array('entry'=>1,'content'=>1,'relation'=>1);

    $this->parse_sitemap($id);
    if($id=='root')
    {
      $uris = $this->uris->xpath("//uri[contains(.,'.html') or contains(.,'.php')]");
      foreach($uris as $uri)
      {
        $this->parse_page((string)$uri->attributes()->id);
      }
    }

    $query = $this->conn->prepare("select count(*) as num from tdz_contents where entry is null and expired is null");
    $query->execute(array());
    $r = $query->fetchAll(Doctrine_Core::FETCH_ASSOC);
    if($r[0]['num']=='0')
      $this->parse_template_positions();
    //print_r(sfYaml::dump($this->data,$this->indent));

    print_r(Spyc::YAMLDump($this->data,true,100));
    exit();
  }

  function findEntryBySourceOrChannel($src,$url,$channel)
  {
    $conn = Doctrine::getConnectionByTableName('tdz_entries');
    $q = new Doctrine_RawSql($conn);
    $q->select('{e.id},{e.title},{e.link},{e.published},{e.updated}')
      ->from("tdz_entries as e left outer join tdz_relations as r on r.entry=e.id")
      ->addComponent('e', 'tdzEntries')
      ->where("e.expired is null and e.type='entry' and (e.source=? or (e.link=? and r.parent=?))")
      ->orderBy('e.link asc,e.title asc,e.published desc');
    $entries = $q->execute(array($src,$url,$channel));
    if($entries->count()>0) return $entries[0];
    else return false;
  }

  function parse_sitemap($id)
  {
    if(is_string($id) && $id=='root')
      $rids=$this->sitemap->xpath('//root/page');
    else if(is_string($id))
      $rids=$this->sitemap->xpath('//page[@ref=\''.$id.'\']');
    else
    {
      $rids=$id->page;
      $id = $id->attributes()->ref;
    }
    $idnum = (int)preg_replace('/^[a-z\:\_]+0*/','',$id);
    $i=1;
    foreach($rids as $k=>$v)
    {
      $page = $this->parse_page((string)$v->attributes()->ref);
      if($idnum>0)
      {
        $yml = array('entry'=>$page,'parent'=>$this->parsed[$idnum],'position'=>$i++);
        //$this->data['tdzRelations'] .= "\n  - ".sfYaml::dump($yml,0);
        if(!$this->findRelation((string)$v->attributes()->ref))
          $this->data['tdzRelations'][] = $yml;
      }
      $this->parse_sitemap($v);
    }
  }

  function findEntry($id)
  {
    $oid=$id;
    if(isset($this->found_entries[$oid])) return $this->found_entries[$oid];
    if(substr($id,0,1)!='/')
      $id=$this->find_url($id);
    if(isset($this->found_entries[$id])) return $this->found_entries[$id];

    $f=false;
    $e=tdzEntries::match($id,true);
    //tdz::debug($id, (string)$e, false);
    if($e) $f=$e->id;
    $this->found_entries[$id]=$f;
    $this->found_entries[$oid]=$f;
    return $f;
  }
  function findRelation($entry)
  {
    if(!is_numeric($entry)) $entry=$this->findEntry($entry);
    if(!$entry)return false;
    $query = $this->conn->prepare("select count(*) as num from tdz_relations where entry=? and expired is null");
    $query->execute(array($entry));
    $r = $query->fetchAll(Doctrine_Core::FETCH_ASSOC);
    return ($r[0]['num']>0);
  }

  function parse_page($id)
  {
    $idnum = (int)preg_replace('/^[a-z\:\_]+0*/','',$id);
    if(isset($this->parsed[$idnum]))return $this->parsed[$idnum];
    $page = sfConfig::get('sf_root_dir').'/../pindorama/var/'.str_replace(':','/',$id).'.xml';
    if(!file_exists($page))return false;
    $updated=filemtime($page);
    $page = new SimpleXMLElement(file_get_contents($page));
    $updated=strtotime($this->fix_date(implode('',$page->xpath("meta/date[@type='modified']"))));

    $id = implode('',$page->xpath("meta/identifier[@type='id']"));
    $url = implode('',$page->xpath("meta/identifier[@type='url']"));
    $url = preg_replace('/\/?((index)?\.?(php|html?)?)?$/','',$url);
    $url = ($url=='')?('/'):($url);
    $eid=false;
    $e=tdzEntries::match($url,true);
    $update=true;
    if($e)
    {
      $this->parsed[$idnum]=$e->id;
      if(strtotime($e->updated)>=$updated)
      {
        $update=false;
        unset($page);
        return $this->parsed[$idnum];
      }
    }
    else
      $this->parsed[$idnum]=$this->next['entry']++;

    $yml = array(
      'id'=>$this->parsed[$idnum],
      'title'=>(string)$page->meta->title[0],
      'summary'=>(string)$page->meta->description[0],
      'link'=>$url,
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='created']")))."'",
      'updated'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'published'=>(file_exists($this->root.implode('',$page->xpath("meta/identifier[@type='url']"))))?("'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'"):(null),
      'language'=>(string)$page->meta->language[0],
      'type'=>'page',
    );
    if($update)
    {
      $this->data['tdzEntries'][] = $yml;
      if(isset($page->meta->subject))
      {
        foreach($page->meta->subject as $k)
        {
          $key=(string)$k->key;
          $this->data['tdzTags'][]=array(
            'entry'=>$this->parsed[$idnum],
            'tag'=>$key,
            'slug'=>tdz::textToSlug($key),
            'created'=>$yml['created'],
            'updated'=>$yml['updated'],
          );
        }
      }
    }
    //$this->data['tdzEntries'] .= "\n  - ".sfYaml::dump($yml,0);

    $i=1;
    foreach($page->resource as $r)
    {
      $ra = (array)$r;
      if(!isset($ra['position'])||$ra['position']=='')continue;

      $type = preg_replace('/[^a-z0-9]+/','_',$ra['@attributes']['type']);
      if(method_exists($this,'parse_content_'.$type))
      {
        $method = 'parse_content_'.$type;
        $this->$method($r, $idnum,$i++, $page);
      }
      else if(function_exists('parse_content_'.$type))
      {
        $method = 'parse_content_'.$type;
        $method($this, $r, $idnum,$i++, $page, $url);
      }
      else
      {
        exit("you need to create the parser for: ".$type."\n".print_r($ra,true));
      }
    }


    unset($page);
    return $this->parsed[$idnum];
  }

  public static function fix_date($d)
  {
    if($d=='')return $d;
    $d = preg_replace('/^([0-9]{4}-[0-9]{2}-[0-9]{2})([ T]([0-9]{2}:[0-9]{2}))?.*/','$1 $3',$d);
    if(strlen($d)==11)$d.='00:00';
    return $d;
  }
  function parse_content_headline($r, $idnum, $pos, $page, $url='')
  {
    if(!isset($this->channels))$this->channels=array();
    $ra = (array)$r;
    if(!isset($this->channels[$idnum]))
    {

      $e=tdzEntries::match($url.'.xml',true);
      $update=true;
      if($e)
      {
        $this->parsed["channel{$idnum}"]=$e->id;
        $updated=strtotime($this->fix_date(implode('',$page->xpath("meta/date[@type='modified']"))));
        if(strtotime($e->updated)>=$updated)
        {
          $update=false;
        }
      }
      else
        $this->parsed["channel{$idnum}"]=$this->next['entry']++;

      // create a news channel and a content for the page
      $cid = $this->parsed["channel{$idnum}"];
      $this->channels[$idnum]=$cid;

      $url = implode('',$page->xpath("meta/identifier[@type='url']"));
      $url = preg_replace('/\/?((index)?\.?(php|html?)?)?$/','',$url);
      $url = ($url=='')?('/'):($url);
      $url .= (substr($url,-1)=='/')?('atom'):('');
      $yml = array(
        'id'=>$cid,
        'title'=>'News channel: '.(string)$page->meta->title[0],
        'summary'=>(string)$page->meta->description[0],
        'link'=>$url.'.xml',
        'published'=>'"<'.'?php echo date(\'Y-m-d H:i:s\',time()+10) ?'.'>"',
        'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='created']")))."'",
        'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
        'updated'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
        'published'=>(file_exists($this->root.implode('',$page->xpath("meta/identifier[@type='url']"))))?("'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'"):(null),
        'language'=>(string)$page->meta->language[0],
        'type'=>'feed',
      );
      if($yml['published']=='')$yml['published']=$yml['updated'];
      if($update)
        $this->data['tdzEntries'][] = $yml;

      $co=false;
      if(!$update && $e)
      {
        foreach($e->getContents() as $c)
        {
          if($c['content_type']=='feed')
            $co=$c;break;
        }
      }
      if($update && !$co)
      {
        if(isset($this->slotnames[$ra['position']]))$ra['position']=$this->slotnames[$ra['position']];
        $this->data['tdzContents'][] = array(
          'entry'=>$this->parsed[$idnum],
          'slot'=>$ra['position'],
          'published'=>$yml['published'],
          'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='created']")))."'",
          'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
          'updated'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
          'published'=>(file_exists($this->root.implode('',$page->xpath("meta/identifier[@type='url']"))))?("'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'"):(null),
          'content_type'=>'feed',
          'content'=>"entry: ".$cid."\nmaster: ~",
          'position'=>$pos,
        );
      }
    }
    $cid = $this->channels[$idnum];

    // add entries to the channel
    $e=$this->findEntryBySourceOrChannel($ra['@attributes']['id'],$ra['url'],$cid);
    if($e)
    {
      $eid=$this->parsed[$ra['@attributes']['id']]=$e->id;
    }
    else
    {
      $eid = $this->parsed[$ra['@attributes']['id']]=$this->next['entry']++;
      $ra+=array('title'=>'','content'=>'','subtitle'=>'','image'=>'');
      $yml = array(
        'id'=>$eid,
        'title'=>$ra['title'],
        'link'=>$ra['url'],
        'summary'=>$ra['content'],
        'type'=>'entry',
        'source'=>$ra['@attributes']['id'],
        'published'=>'"<'.'?php echo date(\'Y-m-d H:i:s\',time()+10) ?'.'>"',
        'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
        'updated'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
        'published'=>(file_exists($this->root.implode('',$page->xpath("meta/identifier[@type='url']"))))?("'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'"):(null),
        'language'=>(string)$page->meta->language[0],
      );
      if($ra['subtitle']!='' && $yml['title']=='')$yml['title']=$ra['subtitle'];
      else if($ra['subtitle']!='')$yml['title'].=': '.$ra['subtitle'];
      if($yml['published']=='')$yml['published']=$yml['updated'];
      $this->data['tdzEntries'][] = $yml;
  
      $this->data['tdzRelations'][] = array('entry'=>$eid,'parent'=>$cid,'position'=>$pos);
    }

    // add images
    $imgs=array();
    if($e)
    {
      foreach($e->getContents() as $c)
      {
        if($c['content_type']!='media') continue;
        $o=sfYaml::load($c['content']);
        $imgs[$o['src']]=true;
      }
    }
    if(is_array($ra['image']))
    {
      $i=1;
      foreach($r->image as $img)
      {
        $url = $img;//->attributes()->ref;
        if(substr($url,0,11)=='index:uris#')
          $url = implode('',$this->uris->xpath("//uri[@id='".substr($url,11)."']"));
        else if(substr($url,0,12)=='php:uris#db:')
          $url = implode('',$this->uris->xpath("//uri[@id='".substr($url,9)."']"));
        else if(substr($url,0,12)=='php:uris#')
          $url = substr($url,9);
        if(!isset($imgs[$url]))
          $this->data['tdzContents'][] = array(
            'entry'=>$eid,
            'content_type'=>'media',
            'content'=>"src: ".$url."\ntitle: ~",
            'published'=>$yml['published'],
            'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
            'updated'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
            'published'=>(file_exists($this->root.implode('',$page->xpath("meta/identifier[@type='url']"))))?("'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'"):(null),
            'position'=>$i,
          );
        $i++;
      }
    }
  }

  function parse_content_content($r, $idnum, $pos, $page)
  {
    $ra = (array)$r;
    $html = (isset($ra['content']))?($ra['content']):('');
    if(isset($ra['title']) && $ra['title']!='')$html = "<h2>{$ra['title']}</h2>{$html}";
    if(isset($this->slotnames[$ra['position']]))$ra['position']=$this->slotnames[$ra['position']];
    $pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='available']")));
    if($pub=='')$pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")));
    $this->data['tdzContents'][] = array(
      'entry'=>$this->parsed[$idnum],
      'slot'=>$ra['position'],
      'content_type'=>'html',
      'content'=>sfYaml::dump(array('html'=>$html),$this->indent),
      'published'=>'"<'.'?php echo date(\'Y-m-d H:i:s\',time()+10) ?'.'>"',
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'updated'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'published'=>(file_exists($this->root.implode('',$page->xpath("meta/identifier[@type='url']"))))?("'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'"):(null),
      'position'=>$pos,
    );
  }
  function parse_content_toc($r, $idnum, $pos, $page)
  {
    $ra = (array)$r;
    $html = (isset($ra['content']))?($ra['content']):('');
    if(isset($ra['title']) && $ra['title']!='')$html = "<h2>{$ra['title']}</h2>{$html}";
    $html .= "<div class=\"h2toc\"></div>";
    if(isset($this->slotnames[$ra['position']]))$ra['position']=$this->slotnames[$ra['position']];
    $pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='available']")));
    if($pub=='')$pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")));
    $this->data['tdzContents'][] = array(
      'entry'=>$this->parsed[$idnum],
      'slot'=>$ra['position'],
      'content_type'=>'html',
      'content'=>sfYaml::dump(array('html'=>$html),$this->indent),
      'published'=>'"<'.'?php echo date(\'Y-m-d H:i:s\',time()+10) ?'.'>"',
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='created']")))."'",
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'updated'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'published'=>(file_exists($this->root.implode('',$page->xpath("meta/identifier[@type='url']"))))?("'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'"):(null),
      'position'=>$pos,
    );
  }
  function parse_content_cross_reference($r, $idnum, $pos, $page)
  {
    $ra = (array)$r;
    $html = (isset($ra['content']))?($ra['content']):('');
    $title = '&#187;';
    if(isset($ra['title']) && $ra['title']!=''){ $title='&#187; '.$ra['title'];$html = "<h2>{$ra['title']}</h2>{$html}"; }
    $url = $ra['ref'];
    $url = preg_replace('/\/((index)?\.?(php|html?)?)?$/','',$url);
    $url = ($url=='')?('/'):($url);
    $html .= "<p class=\"link\"><a href=\"{$url}\">{$title}</p>";
    if(isset($this->slotnames[$ra['position']]))$ra['position']=$this->slotnames[$ra['position']];
    $pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='available']")));
    if($pub=='')$pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")));
    $this->data['tdzContents'][] = array(
      'entry'=>$this->parsed[$idnum],
      'slot'=>$ra['position'],
      'content_type'=>'html',
      'content'=>sfYaml::dump(array('html'=>$html),$this->indent),
      'published'=>'"<'.'?php echo date(\'Y-m-d H:i:s\',time()+10) ?'.'>"',
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='created']")))."'",
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'updated'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'published'=>(file_exists($this->root.implode('',$page->xpath("meta/identifier[@type='url']"))))?("'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'"):(null),
      'position'=>$pos,
    );
  }
  function parse_content_external_reference($r, $idnum, $pos, $page)
  {
    $ra = (array)$r;
    $html = (isset($ra['content']))?($ra['content']):('');
    $title = '&#187;';
    if(isset($ra['title']) && $ra['title']!=''){ $title='&#187; '.$ra['title'];$html = "<h2>{$ra['title']}</h2>{$html}"; }
    $url = $ra['url'];
    $html .= "<p class=\"link\"><a href=\"{$url}\">{$title}</p>";
    if(isset($this->slotnames[$ra['position']]))$ra['position']=$this->slotnames[$ra['position']];
    $pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='available']")));
    if($pub=='')$pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")));
    $this->data['tdzContents'][] = array(
      'entry'=>$this->parsed[$idnum],
      'slot'=>$ra['position'],
      'content_type'=>'html',
      'content'=>sfYaml::dump(array('html'=>$html),$this->indent),
      'published'=>'"<'.'?php echo date(\'Y-m-d H:i:s\',time()+10) ?'.'>"',
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='created']")))."'",
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'updated'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'published'=>(file_exists($this->root.implode('',$page->xpath("meta/identifier[@type='url']"))))?("'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'"):(null),
      'position'=>$pos,
    );
  }

  function parse_content_component($r, $idnum, $pos, $page)
  {
    $ra = (array)$r;
    if(!isset($this->components))
      $this->components = new SimpleXMLElement(file_get_contents(sfConfig::get('sf_root_dir').'/../pindorama/var/index/web-components.xml'));

    $id = substr($ra['include'],21); // index:web-components#3
    $component = $this->components->xpath("//ref[@id='$id']");

    $php = array('script'=>'lib/includes/'.$component[0]->include,'pi'=>'// '.$component[0]->name);
    if(isset($this->slotnames[$ra['position']]))$ra['position']=$this->slotnames[$ra['position']];
    $pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='available']")));
    if($pub=='')$pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")));
    $this->data['tdzContents'][] = array(
      'entry'=>$this->parsed[$idnum],
      'slot'=>$ra['position'],
      'content_type'=>'php',
      'content'=>sfYaml::dump($php,$this->indent),
      'published'=>'"<'.'?php echo date(\'Y-m-d H:i:s\',time()+10) ?'.'>"',
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='created']")))."'",
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'updated'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'published'=>(file_exists($this->root.implode('',$page->xpath("meta/identifier[@type='url']"))))?("'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'"):(null),
      'position'=>$pos,
    );
  }

  function parse_content_image_lead($r, $idnum, $pos, $page)
  {
    $ra = (array)$r;
    $html = (isset($ra['content']))?($ra['content']):('');
    if(isset($ra['title']) && $ra['title']!='')$html = "<h2 class=\"image_lead\">{$ra['title']}</h2>{$html}";
    if(isset($this->slotnames[$ra['position']]))$ra['position']=$this->slotnames[$ra['position']];
    $pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='available']")));
    if($pub=='')$pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")));
    $this->data['tdzContents'][] = array(
      'entry'=>$this->parsed[$idnum],
      'slot'=>$ra['position'],
      'content_type'=>'html',
      'content'=>sfYaml::dump(array('html'=>$html),$this->indent),
      'published'=>'"<'.'?php echo date(\'Y-m-d H:i:s\',time()+10) ?'.'>"',
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='created']")))."'",
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'updated'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'published'=>(file_exists($this->root.implode('',$page->xpath("meta/identifier[@type='url']"))))?("'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'"):(null),
      'position'=>$pos,
    );
  }
  function parse_content_search_form($r, $idnum, $pos, $page)
  {
  }
  function parse_content_search_results($r, $idnum, $pos, $page)
  {
  }
  function parse_content_xdb_function_noticias($r, $idnum, $pos, $page)
  {
    tdzToolKit::debug($r);
  }
  function parse_content_xdb_function($r, $idnum, $pos, $page)
  {
    $ra = (array)$r;
    $ra+=array('content'=>'','content-success'=>'','content-error'=>'','pi'=>'','application'=>'','table'=>'','title'=>'');

    if(isset($r->{'access-rights'}))
    {
      $credentials=array();
      if($r->{'access-rights'}=='user-group')
        foreach($r->{'access-rights-groups'} as $g)
          $credentials[]='t'.preg_replace('/^[^\#]+\#/', '', $g);
      else
        $credentials[]=1;
      $pyml=array(
        'entry'=>$this->parsed[$idnum],
        'role'=>'previewPublished',
        'credentials'=>implode(',',$credentials),
        'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
        'updated'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      );
      $this->data['tdzPermissions'][] = $pyml;
    }
    /*
    entry: 1
    slot: ~
    content_type: php
    content: |
      script: lib/vendor/tupinamba/loader.php
      pi: "if(function_exists('xdb_application'))return xdb_application('test');"
    position: 3
     */
    $php = array('script'=>'lib/vendor/tupinamba/loader.php','pi'=>''.$ra['pi']);
    $info=array('content'=>$ra['content'],'content-success'=>$ra['content-success'],'content-error'=>$ra['content-error']);
    if($ra['title']!=''){
      if($info['content'])$info['content'] = "<h2>{$ra['title']}</h2>{$info['content']}";
      if($info['content-success'])$info['content-success'] = "<h2>{$ra['title']}</h2>{$info['content-success']}";
      if($info['content-error'])$info['content-error'] = "<h2>{$ra['title']}</h2>{$info['content-error']}";
    }
    if($ra['table']!='')
    {
      $ra['table']=preg_replace('/^[^\#]+\#/','',$ra['table']);
      $info['table']=$ra['table'];
      $php['pi'] = "\$table=\$xdbc['tables']['{$ra['table']}'];\n{$php['pi']}";
    }
    foreach($info as $k=>$v)
      if($v=='') unset($info[$k]);
      else 
        $php['pi'] = "\$info['$k']=utf8_decode(".var_export($v, true).");\n{$php['pi']}";

    if($ra['application']!='') $php['pi'] .= "if(file_exists(\$xdbc['includes'].'/{$ra['application']}.php')){\$xdb['application']=\$xdbc['application']='{$ra['application']}';require \$xdbc['includes'].'/{$ra['application']}.php';};\nif(isset(\$xdb['body'])){\$str=\$xdb['body'];unset(\$xdb['body']);return tdz_encode(\$str);};\n";
    $php['pi'] = "global \$xdbc, \$err, \$res, \$xdb;\$info=array();\n{$php['pi']}";
    if(isset($this->slotnames[$ra['position']]))$ra['position']=$this->slotnames[$ra['position']];
    $pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='available']")));
    if($pub=='')$pub=$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")));
    $this->data['tdzContents'][] = array(
      'entry'=>$this->parsed[$idnum],
      'slot'=>$ra['position'],
      'content_type'=>'php',
      'content'=>sfYaml::dump($php,$this->indent),
      'published'=>'"<'.'?php echo date(\'Y-m-d H:i:s\',time()+10) ?'.'>"',
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='created']")))."'",
      'created'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'updated'=>"'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'",
      'published'=>(file_exists($this->root.implode('',$page->xpath("meta/identifier[@type='url']"))))?("'".$this->fix_date(implode('',$page->xpath("meta/date[@type='modified']")))."'"):(null),
      'position'=>$pos,
    );
  }




  private function find_url($p)
  {
    $p = (string)$p;
    if(isset($this->found_uris[$p]))return $this->found_uris[$p];

    $url=$this->uris->xpath("//uri[@id='{$p}'][1]");
    if($url)
    {
      $url=preg_replace('/(\/index)?\.(php|html)/','',$url[0]);
      $url=($url=='')?('/'):($url);
      $url=(string)$url;
    }
    else
    {
      $url = false;
    }
    $url=str_replace('/admin','/intranet',$url);
    $this->found_uris[$p]=$url;
    return $url;
  }

  private function find_menu_item($url, $paro='')
  {
    $url = (string)$url;
    $slug = '';
    //if(isset($this->found_menu_items[$url]))return $this->found_menu_items[$url];
    if(substr($url,0,3)=='db:')
    {
      $nurl = (string)$this->find_url($url);
      if($nurl=='')
        $slug = str_replace('db:','',$url);
      else
        $url = $nurl;
    }
    $url=str_replace('/admin','/intranet',$url);

    if($slug!='')
      $p0=$this->mi->where('slug=?',$slug)->fetchArray();
    else if($paro!='')
      $p0=$this->mi->where('custom_path=?',$url)->addWhere('lft>?',$paro['lft'])->addWhere('rgt<?',$paro['rgt'])->fetchArray();
    else
      $p0=$this->mi->where('custom_path=?',$url)->fetchArray();

    if(!is_array($p0) || count($p0) == 0)
      $p0=array();
    else $p0 = $p0[0];
    $this->found_menu_items[$url]=$p0;
    return $p0;
  }

  function parse_template_positions()
  {
    $tpl = new SimpleXMLElement(file_get_contents(sfConfig::get('sf_root_dir').'/../pindorama/var/index/template-positions.xml'));
    $lang = (string)$tpl->meta->language;

    foreach($tpl->resource->ref as $ref)
    {
      $slot = (string)$ref->name;
      if(isset($this->slotnames[$slot]))$slot=$this->slotnames[$slot];
      if(isset($ref->contents))
      {
        $num = count($ref->contents->content);
        for($i=0;$i<$num;$i++)
        {
          $content = $ref->contents->content[$i];
          $html = (string)$content->text;
          $navigation = (string)$content->navigation;
          $show_at = (array)$content->permit;
          $hide_at = (array)$content->restrict;
          if($html != '' || $navigation != '')
          {
            foreach($show_at as $k=>$url)
            {
              $url = (string)$url;
              if($url=='') unset($show_at[$k]);
              else
              {
                $asterisk = (preg_match('/^(.+\/index\.?(php|html?)?|(\/(index)?\.?(php|html?)?))$/',$url))?(''):('*');
                $url = preg_replace('/\/((index)?\.?(php|html?)?)?$/','',$url);
                $url = ($url=='')?('/'):($url);
                if($asterisk)
                  $show_at[$k]="{$url}\n{$url}{$asterisk}";
                else
                  $show_at[$k]=$url;
              }
            }
            $show_at = implode("\n",$show_at);
            foreach($hide_at as $k=>$url)
            {
              $url = (string)$url;
              if($url=='') unset($hide_at[$k]);
              else
              {
                $asterisk = (preg_match('/^(.+\/index\.?(php|html?)?|(\/(index)?\.?(php|html?)?))$/',$url))?(''):('*');
                $url = preg_replace('/\/((index)?\.?(php|html?)?)?$/','',$url);
                $url = ($url=='')?('/'):($url);
                if($asterisk)
                  $hide_at[$k]="{$url}\n{$url}{$asterisk}";
                else
                  $hide_at[$k]=$url;
              }
            }
            $hide_at = implode("\n",$hide_at);
            if($hide_at=='' && $show_at=='')$show_at='*';
            if($navigation!='')
            {
              $navigation = preg_replace('/[^a-z0-9]+/','_',$navigation);
              $entry = (string)$content->ref;
              if($entry != '')
              {
                $entry = (int)preg_replace('/^[^1-9]+/', '', $entry);
                $entry = $this->parsed[$entry];
              }
              if($entry=='')$entry='~';
              $c = array(
                'slot'=>$slot,
                'content_type'=>'feed',
                'content'=>"entry: {$entry}\nmaster: tdz_navigation_{$navigation}",
                'position'=>$i - $num,
                'created'=>date("'Y-m-d H:i:s'"),
                'updated'=>date("'Y-m-d H:i:s'"),
                'published'=>date("'Y-m-d H:i:s'"),
              );
              if($hide_at)$c['hide_at']=$hide_at;
              if($show_at)$c['show_at']=$show_at;
              $this->data['tdzContents'][]=$c;
            }
            if($html != '')
            {
              $c = array(
                'slot'=>$slot,
                'content_type'=>'html',
                'content'=>sfYaml::dump(array('html'=>$html),$this->indent),
                'created'=>date("'Y-m-d H:i:s'"),
                'updated'=>date("'Y-m-d H:i:s'"),
                'published'=>date("'Y-m-d H:i:s'"),
                'position'=>$i - $num,
              );
              if($hide_at)$c['hide_at']=$hide_at;
              if($show_at)$c['show_at']=$show_at;
              $this->data['tdzContents'][]=$c;
            }
          }
        }
      }
    }

  }
}
