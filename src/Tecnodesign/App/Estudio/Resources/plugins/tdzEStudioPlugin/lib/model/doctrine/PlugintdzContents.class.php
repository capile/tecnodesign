<?php
/**
 * PlugintdzContents
 * 
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: PlugintdzContents.class.php 1000 2012-01-25 15:57:50Z capile $
 */
abstract class PlugintdzContents extends BasetdzContents
{
  private static $matches=array(), $content_types=null;
  public static function connect()
  {
    return tdzEntries::connect();
  }
  public static function query()
  {
    return new Doctrine_RawSql(tdzEntries::connect());
  }
  public static function find($id=null,$options=array())
  {
    if($id instanceof tdzContents) return $id;
    $q = self::query();
    $q->select('{c.*}')
      ->from("tdz_contents as c")
      ->addComponent('c', 'tdzContents')
      ->where("c.id=?",$id);
    if(!isset($options['expired']) || !$options['expired'])
      $q->andWhere('c.expired is null');
    if(!isset($options['hydrate']))
      $options['hydrate']=Doctrine::HYDRATE_RECORD;
    $c = $q->execute(array(),$options['hydrate']);
    if(isset($c[0]))return $c[0];
    else return false;
  }

  public static function getContentTypes()
  {
    if(is_null(self::$content_types))
    {
      $ct=sfConfig::get('app_e-studio_content_types');
      $widgets=sfConfig::get('app_e-studio_widgets');
      if(is_array($widgets) && count($widgets)>0)
      {
        $wg=array();
        foreach($widgets as $wk=>$w)
          $wg[$wk]=$w['label'];
        $ct['widget']=array('title'=>'Widgets','fields'=>array('app'=>array('label'=>'Widget','type'=>'choice','required'=>true,'choices'=>$wg)));
      }
      self::$content_types=$ct;
    }
    return self::$content_types;
  }

  /**
   * Shows a tag cloud to navigate the by year
   *
   * @param tdzEntries $e entry to use as reference
   */
  public static function getFeedYears($e=null)
  {
    $eid=false;
    if(is_object($e))
      $eid=$e->id;
    else if(!is_null($e))
      $eid=$e;

    $ids=array();
    $conn = self::connect();
    if($eid)
    {
      $sql='select distinct c.content from tdz_contents as c where c.entry=? and c.content_type=\'feed\' and c.expired is null';
      $query = $conn->prepare($sql);
      $query->execute(array($eid));
      $c = $query->fetchAll(Doctrine_Core::FETCH_ASSOC);
      if(isset($c[0]))
      {
        foreach($c as $cy)
        {
          $ce=sfYaml::load($cy['content']);
          if($ce['entry']!='')$ids[]=$ce['entry'];
        }
      }
    }
    $qs=tdz::scriptName().'?';
    $y=false;
    if(isset($_GET['y']) && preg_match('/^(20|19)[0-9]{2}$/',$_GET['y']))
    {
      $y=(int)$_GET['y'];
      tdzTags::$filter['year']=$y;
    }
    //if($y) $qs = '?y='.$y.'&amp;';
    $sql='select distinct year(e.published) as year from tdz_entries as e inner join tdz_relations as r on r.entry=e.id and r.parent in (\''.implode('\',\'',$ids).'\') where e.published is not null and e.expired is null order by 1 desc';
    $query = $conn->prepare($sql);
    $query->execute();
    $years = $query->fetchAll(Doctrine_Core::FETCH_ASSOC);
    $s='';
    foreach($years as $yo)
    {
      $s .= '<li><a href="'.$qs.'y='.$yo['year'].'">'.$yo['year'].'</a></li>';
    }
    if($s!='')
    {
      $s = '<ul class="years-nav">'.$s.'</ul>';
    }
    return $s;
  }

  public function render($display=false)
  {
    if(!$this->hasPermission('preview'))
    {
      return false;
    }
    $code = $this->getContent();
    $type = $this->getContentType();
    $content_types = tdzContents::getContentTypes();
    $ct = (isset($content_types[$type]))?($content_types[$type]):(array());
    if(!file_exists($code)) {
      $code = sfYaml::load(trim($code));
    }

    $class = $this;
    $method = 'render'.ucfirst($type);
    $component = '';
    if(isset($ct['class']) && class_exists($ct['class']))
      $class = $ct;
    if(isset($ct['method']) && method_exists($class, $ct['method']))
      $method = $ct;
    if(isset($ct['component']))
      $component = $ct['component'];

    if($component != '')
      // render component
      ;
    else if(is_object($class) && method_exists($class, $method))
      $code = $class->$method($code, $this->getEntry());
    else if(is_string($class))
      $code = $class::$method($code, $this->getEntry());


    $class='tdzc';

    if(!is_array($code))
      $code = array('content'=>$code);
    /*
    if(!isset($code['before']))$code['before']='';
    $code['before'].='<div class="'.$class.'" id="c'.$this->getId().'">';
    if(!isset($code['after']))$code['after']='';
    $code['after'].='</div>';
     */
    if($display)
    {
      if(!function_exists('tdz_eval')) require_once sfConfig::get('app_e-studio_helper_dir').'/tdzEStudioHelper.php';

      $result='';
      if(is_array($code) && isset($code['before']))
        $result .= $code['before'];

      if(is_array($code) && isset($code['export']))
        $result .= eval("return {$code['export']};");

      else if(is_array($code))
        $result .= $code['content'];

      else
        $result .= $code;

      return $result;

    }
    //$code['before'] .= '<h1>Position: '.$this->getPosition().'</h1>';
    return $code;
  }

  public static function renderMedia($code=null, $e=null)
  {
    if(!isset($code['src'])||$code['src']=='') return '';
    if(!isset($code['format'])||$code['format']=='')$code['format']=tdz::fileFormat($code['src']);
    $s='';
    if(preg_match('/(image|pdf|flash|download|video|audio)/', strtolower($code['format']), $m))$f=$m[1];
    else $f='download';
    if($f=='image')
    {
      $s = '<img src="'.tdz::xmlEscape($code['src']).'"';
      if(isset($code['alt']) && $code['alt']) $s .= ' alt="'.tdz::xmlEscape($code['alt']).'"';
      if(isset($code['title']) && $code['title']) $s .= ' title="'.tdz::xmlEscape($code['title']).'"';
      if(isset($code['id']) && $code['id']) $s .= ' id="'.tdz::xmlEscape($code['id']).'"';
      $s .= ' />';
      if(isset($code['href']) && $code['href'])$s = '<a href="'.tdz::xmlEscape($code['href']).'">'.$s.'</a>';
    } else if($f=='video') {
      $s = '<video src="'.tdz::xmlEscape($code['src']).'"';
      if(isset($code['alt']) && $code['alt']) $s .= ' alt="'.tdz::xmlEscape($code['alt']).'"';
      if(isset($code['title']) && $code['title']) $s .= ' title="'.tdz::xmlEscape($code['title']).'"';
      if(isset($code['id']) && $code['id']) $s .= ' id="'.tdz::xmlEscape($code['id']).'"';
      $s .= ' autobuffer="true" controls="true">alternate part';
      // alternate -- using flash?
      $s .= '</video>';
    } else if($f=='flash') {
      $s = '<div src="'.tdz::xmlEscape($code['src']).'"';
      if(isset($code['alt']) && $code['alt']) $s .= ' alt="'.tdz::xmlEscape($code['alt']).'"';
      if(isset($code['title']) && $code['title']) $s .= ' title="'.tdz::xmlEscape($code['title']).'"';
      if(isset($code['id']) && $code['id']) $s .= ' id="'.tdz::xmlEscape($code['id']).'"';
      $s .= ' autobuffer="true" controls="true">alternate part';
      // alternate -- using flash?
      $s .= '</video>';
    }
    else
    {
      $s = '<p';
      if(isset($code['id']) && $code['id']) $s .= ' id="'.tdz::xmlEscape($code['id']).'"';
      $s .= '><a href="'.tdz::xmlEscape($code['src']).'">';
      $s .= (isset($code['title']) && $code['title'])?(tdz::xmlEscape($code['title'])):(basename($code['src']));
      $s .= '</a></p>';
    }
    return $s;
  }


  public static function renderHtml($code=null, $e=null)
  {
    if(is_null($code) && isset($this))
    {
      $code = $this->getContent();
      if(!file_exists($code))
        $code = sfYaml::load($code);
    }
    if(is_array($code))
      $code = $code['html'];

    return $code;
  }
  public static function renderWidget($code=null, $e=null)
  {
    $widgets=sfConfig::get('app_e-studio_widgets');
    if(!is_array($code) || !isset($code['app']) || !isset($widgets[$code['app']])) return false;

    $app=$widgets[$code['app']];
    $class=$method=false;
    if(isset($app['model']) && class_exists($app['model']))
      $class = $app['model'];
    if(isset($app['method']) && method_exists($class, $app['method']))
      $method = $app['method'];
    
    $s='problema';
    if(!$class || !$method)
    {
        tdz::log("class: [$class], method: [$method]");
    }
    else if(isset($app['cache']) && $app['cache'])
    {
      if(is_object($class) && method_exists($class, $method))
        $s = $class->$method($e);
      else if(is_string($class))
        $s = $class::$method($e);
      $code=$s;
    }
    else
    {
      if(is_object($class) && method_exists($class, $method))
        $s = '$class='.var_export($class,true).';return $class->'.$method.'('.var_export($e,true).');';
      else if(is_string($class))
        $s = "return {$class}::{$method}(".var_export($e,true).');';
      $code=array('export'=>'tdz_eval(array(\'pi\'=>"'.$s.'"))');
    }
    return $code;
  }

  public static function renderPhp($code=null, $e=null)
  {
    if(is_null($code) && isset($code))
    {
      $code = $this->getContent();
      if(!file_exists($code))
        $code = sfYaml::load($code);
    }
    if(!is_array($code))
      $code = array('pi'=>$code);

    if(isset($code['script']))
    {
      if(file_exists(sfConfig::get('sf_root_dir').'/'.$code['script']))
        $code['script']=sfConfig::get('sf_root_dir').'/'.$code['script'];
      else
        unset($code['script']);
    }
    return array('export'=>'tdz_eval('.var_export($code,true).')');
  }

  public static function renderFeed($code=null, $e=null)
  {
    if(is_null($code) && isset($this))
    {
      $code = $this->getContent();
      if(!file_exists($code))
        $code = sfYaml::load($code);
    }

    if(!is_array($code))
      $code = array('entry'=>$code);
    /**
     * $code should contain:
     *
     *   entry  (mandatory) integer  The feed id
     *   master (optional) string   The template to use
     *
     * If the entry is not found, it should use current feed as a parameter
     */
    $feed = false;
    if(isset($code['master']) && file_exists(sfConfig::get('sf_app_template_dir').'/'.$code['master'].'.php'))
      $code['master']=sfConfig::get('sf_app_template_dir').'/'.$code['master'].'.php';
    else if(isset($code['master']) && file_exists(sfConfig::get('app_e-studio_template_dir').'/'.$code['master'].'.php'))
      $code['master']=sfConfig::get('app_e-studio_template_dir').'/'.$code['master'].'.php';
    else if(file_exists(sfConfig::get('sf_app_template_dir').'/tdz_feed.php'))
      $code['master']=sfConfig::get('sf_app_template_dir').'/tdz_feed.php';
    else if(file_exists(sfConfig::get('app_e-studio_template_dir').'/tdz_feed.php'))
      $code['master']=sfConfig::get('app_e-studio_template_dir').'/tdz_feed.php';
    else
      unset($code['master']);

    if(!is_numeric($code['entry']))
      $code['entry']=$e;
    return array('export'=>'tdz_feed('.var_export($code,true).')');
  }

  public function fixPosition()
  {
    // get all contents in the same entry that share the same slot
    $position = (int)$this->getPosition();
    if($position<1)$position=1;
    $q = new Doctrine_RawSql(self::connect());
    $q->select('{c.*}')
      ->from("tdz_contents as c")
      ->addComponent('c', 'tdzContents')
      ->where("c.entry is not null and c.entry=:entry and c.slot=:slot and ifnull(c.position,1)>=:position and c.id<>:id",array('entry'=>$this->getEntry(),'slot'=>$this->getSlot(),'position'=>$position,'id'=>$this->getId()))
      ->orderBy('c.position asc');
    $contents = $q->execute();
    foreach($contents as $content)
    {
      $position++;
      $q = Doctrine_Query::create()->update('tdzContents')->set('position',$position)->where('id=?', $content->getId())->execute();
      //$content->setPosition($position);
      //$content->save();
    }
    $q = Doctrine_Query::create()->update('tdzEntries')->set('updated','?',date('Y-m-d H:i:s'))->where('id=?', $this->entry)->execute();
  }

  /**
   * Checks if the content is eligible for preview, edit, publish etc.
   *
   * @param <type> $role
   * @return <type>
   */
  public function getPermission($role='preview',$object='Content',&$published='')
  {
    // if it has an entry, run permissions from it
    $entry = $this->getEntries();
    if($this->published=='')$published='Unpublished';

    //tdz::debug("\n{$role}{$object}{$published}\nentry: {$this->entry}\ncontent: ".$this->id,"\n".$this->content.'...',false);

    if($object=='Content' && $this->entry && $entry->id)
      return $entry->getPermission($role,$object,$published);

    // Contents without entries (commonly used as templates), can only check
    // global parameters
    $valid_roles = array('all','new','edit','publish','delete','preview');
    if(!in_array($role,$valid_roles))return false;
    $valid_objects = array('Content','Permission','Template','ContentType');
    if(!in_array($object,$valid_objects))return false;

    // new role: {$role}Template
    $order=array("{$role}{$object}{$published}", "{$role}{$object}", "{$role}Template{$published}", "{$role}Template", "{$role}{$published}", "{$role}", 'all');
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

    return $result;
  }

  /**
   * Checks if the content is eligible for preview, edit, publish etc.
   * 
   * @param <type> $role
   * @return <type>
   */
  public function hasPermission($role='preview',$object='Content',$published='Published')
  {
    if($object=='Content')
    {
      $d=sfConfig::get('app_e-studio_permissions');
      $ct=$this->content_type;
      $pid=$role.'ContentType'.ucfirst($ct);
      if(isset($d[$pid]) && !($d[$pid]=='*' || sfContext::getInstance()->getUser()->hasCredential($d[$pid])))
        return false;
    }
    $credentials=$this->getPermission($role, $object, $published);
    $valid=false;
    if(is_bool($credentials)) $valid=$credentials;
    else $valid=sfContext::getInstance()->getUser()->hasCredential($credentials,false);
    
    //if($valid) tdz::debug('ok', false); else tdz::debug('no',false);
    return $valid;
  }

  public function setEntry($s)
  {
    if(!is_object($s) && $s=='') $s=null;
    return $this->_set('entry',$s);
  }
  public function cleanCache()
  {
    if($this->id!='')
      tdz::cleanCache('tdzContents/c'.$this->id);
  }
  public function getLatestVersion()
  {
    $pub=$this->published;
    if($pub)$pub=strtotime($pub);
    $upd=$this->updated;
    if($upd)$upd=strtotime($upd);
    if((!$pub || $upd>$pub) && !$this->hasPermission('preview','Content','Unpublished'))
    {
      // get latest published version
      $conn = self::connect();
      $sql='select c.* from tdz_contents_version as c inner join tdz_contents as c0 on c0.id=c.id where c.id=? and c.expired is null and c.updated <= c0.published order by c.version desc limit 1';
      $query = $conn->prepare($sql);
      $query->execute(array($this->id));
      $v = $query->fetchAll(Doctrine_Core::FETCH_ASSOC);
      //tdz::debug($this->getData(),$v[0],false);
      if(isset($v[0]['id']))
      {
        $fns=$this->getData();
        foreach($v[0] as $fn=>$fv)
          $this[$fn]=$fv;
      }
      //tdz::debug($this->getData());
    }
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