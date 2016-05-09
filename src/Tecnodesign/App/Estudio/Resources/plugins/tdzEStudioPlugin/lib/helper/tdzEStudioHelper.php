<?php

function tdz_eval($a)
{
  return tdz::exec($a);
};

function tdz_encode($s)
{
  return tdz::encode($s);
}
function tdz_decode($s)
{
  return tdz::decode($s);
}

function tdz_feed($a)
{
    if(class_exists('tdzEntry')) {
        if(!($a['entry'] instanceof tdzEntry)) {
            $a['entry'] = tdzEntry::find($a['entry']);
        }
        $s = '';
        if($a['entry']) {
            $s = $a['entry']->renderFeed($a['master'], $a);
        }
    } else {
        // load doctrine, if not loaded
        $s=tdzEntries::feedPreview($a['entry'],$a['master'], $a);
    }
    return $s;
}

function tdz_feed_figures($entry)
{
    if(class_exists('tdzEntry')) {
        if(!is_object($entry)) $entry = tdzEntry::find($entry);
        $figures = $entry->getContent('media');
        $media=array();
        if($figures && $figures->count()>0) {
            foreach($figures as $f){
                $fig = $f->asArray();
                $fig['content'] = $fig['contents'];
                $media[$fig['id']] = $fig;
            }
        }
    } else {
        if(is_object($entry)) $entry = $entry->getId();
        if(!is_numeric($entry))return false;
        $connection = Doctrine::getConnectionByTableName('tdz_entries');
        $query = "select ifnull(c.position,0) as position, c.id, c.slot, c.content from tdz_contents as c where c.entry=:entry and c.content_type='media' order by 1 asc";
        $statement = $connection->prepare($query);
        $statement->bindValue('entry', $entry);
        $statement->execute();
        $media = $statement->fetchAll(Doctrine_Core::FETCH_ASSOC);
    }
    $s = '<div class="media-thumbnail">';

    foreach($media as $m) {
        $s .= '<figure id="fig'.$m['id'].'">';
        $fig = (is_array($m['content']))?($m['content']):(sfYaml::load($m['content']));
        $fig += array('alt'=>'','title'=>'','src'=>'');
        $s .= '<img alt="'.htmlentities($fig['alt'], ENT_QUOTES, 'UTF-8', false).'" src="'.htmlentities($fig['src'], ENT_QUOTES, 'UTF-8', false).'" border="0" />';
        $s .= '<legend>'.htmlentities($fig['title'], ENT_QUOTES, 'UTF-8', false).'</legend>';
        $s .= '</figure>';
    }
    $s .= '</div>';
    return $s;
}
function tdz_navigation_list($e=null, $master='tdz_navigation_list', $options=array(), $level=9, $current=null)
{
    return tdz_navigation($e, $master, $options, $level, $current);
}
function tdz_navigation($e=null, $master='tdz_navigation_list', $options=array(), $level=9, $current=null)
{
    if(class_exists('tdzEntry')) {
        return 'falta ajustar!!!';
    }
  if(is_null($current))
    $current=tdzEntries::match(tdz::scriptName());
  if(is_null($e))
  {
    $e = $current;
  }
  if(!$e)return '';

  $use_entry=false;
  if(isset($options['link_channel']) && $options['link_channel'])
  {
    $use_entry=true;
    $options['link_channel']=false;
  }
  $id=(is_object($e))?($e['id']):($e);
  $co=new sfFileCache(array('cache_dir'=>sfConfig::get('sf_app_cache_dir').'/tdzNavigation'));
  $items=false;
  $lifetime=sfConfig::get('app_e-studio_cache_timeout');
  $timeout=time() - $lifetime;
  $timeout=tdzEntries::lastModified(true);
  if($co->getLastModified('e'.$id)>$timeout)
  {
    $items=$co->get('e'.$id);
    if($items)
      $items=unserialize($items);
  }
  if(!$items)
  {
    $e=($e instanceof tdzEntries)?($e):(tdzEntries::getPage($e));
    $oitems = $e->getChild();
    $items=array();
    foreach($oitems as $o)
    {
      $items[]=array(
        'id'=>$o->id,
        'credentials'=>$o->getPermission('preview'),
        'link'=>$o->link,
        'title'=>$o->title,
        'published'=>$o->published,
        'summary'=>$o->summary,
      );
    }
    $co->set('e'.$id,serialize($items),$lifetime);
  }
  $s = '';
  $user=false;
  foreach($items as $i=>$item)
  {
    if(is_array($item['credentials']))
    {
      if(!$user) $user=sfContext::getInstance()->getUser();
      if(!$user->hasCredential($item['credentials'],false)) unset($items[$i]);
    }
    else
    {
      if(!$item['credentials']) unset($items[$i]);
    }
  }
  $currentid=($current)?($current->id):(false);
  if(count($items)>0)
  {
    $level--;
    $s .= '<ul>';
    if($use_entry)
    {
      $e=($e instanceof tdzEntries)?($e):(tdzEntries::getPage($e));
      $item=array(
        'id'=>$e->id,
        'link'=>$e->link,
        'title'=>$e->title,
        'published'=>$e->published,
        'summary'=>$e->summary,
        'level'=>0,
      );
      array_unshift($items, $item);
    }
    foreach($items as $item)
    {
      if(!isset($item['level']))
        $item['level']=$level;
      $item['current']=$current;
      $item['current_id']=$currentid;
      if($master!='' && file_exists($master))
        $s .= tdz_eval(array('script'=>$master,'variables'=>$item));
      else if($master!='' && file_exists(sfConfig::get('app_e-studio_template_dir').'/'.$master.'.php'))
        $s .= tdz_eval(array('script'=>sfConfig::get('app_e-studio_template_dir').'/'.$master.'.php','variables'=>$item));
      else
      {
        $s .= '<li>';
        $url = $item['link'];
        if($url)
          $s .= '<a href="'.$url.'">'.$item['title'].'</a>';
        else
          $s .= $item['title'];
        if($item['level'])
          $s .= tdz_navigation($item, $master, $level, $current);
        $s .= '</li>';
      }
    }
    $s .= '</ul>';
  }
  return $s;
}

function tdz_navigation_ancestors($e=null, $master='tdz_navigation_ancestors', $options=array(), $level=9)
{
  $stope=$e;
  // find current page
  $e = tdzEntries::match(sfContext::getInstance()->getRequest()->getPathInfo());
  //$e=($e instanceof tdzEntries)?($e):(tdzEntries::getPage($e));
  if(!$e)return '';
  $s = '';
  while($e)
  {
    $item = $e;
    $e = $item->getParent();
    if($master!='' && file_exists($master))
      $s = tdz_eval(array('script'=>$master,'variables'=>$item)).$s;
    else if($master!='' && file_exists(sfConfig::get('app_e-studio_template_dir').'/'.$master.'.php'))
      $s = tdz_eval(array('script'=>sfConfig::get('app_e-studio_template_dir').'/'.$master.'.php','variables'=>$item)).$s;
    else
    {
      $s0 = '<li>';
      $url = $item->getLink();
      if($url)
        $s0 .= '<a href="'.$url.'">'.$item->getTitle().'</a>';
      else
        $s0 .= $item->getTitle();
      if($level)
        $s0 .= tdz_navigation($item, $format, $level-1);
      $s0 .= '</li>';
      $s = $s0.$s;
    }
    //if($e->id==$stope) break;
  }
  if($s!='') $s = "<ul class=\"ancestors\">$s</ul>";
  return $s;
}