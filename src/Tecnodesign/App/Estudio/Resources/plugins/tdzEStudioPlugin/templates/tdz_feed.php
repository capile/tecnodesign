<?php
/**
 * Default News Feed Template
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: tdz_feed.php 879 2011-09-05 15:07:13Z capile $
 */
$s = '<div class="hfeed tdze" id="e'.$id.'">';
$after='';$before='';
$i=1;
$qs='';
$odd=(!$hpp)?('odd'):(__('odd'));
$even=(!$hpp)?('even'):(__('even'));
if(!isset($template)) $template='tdz_entry';
if(isset($linkhome) && $linkhome)
{
  $class=($i%2)?($odd):($even);
  $s .= str_replace('<div class="hentry tdze', '<div class="hentry tdze '.$class, tdzEntries::entryPreview($entry, $template));
  $i++;
}
if($hpp)
{
  $pager = new sfDoctrinePager('tdzEntries', $hpp);
  $pager->setQuery($query);
  if(isset($filter) && $filter)
  {
    $filters='';
    $year='';
    if(isset($_GET['y']) && preg_match('/^(20|19)[0-9]{2}$/',$_GET['y']))
    {
      $year=(int)$_GET['y'];
      $qs.=($qs!='')?('&'):('?');
      $qs.= 'y='.$year;
      $pager->getQuery()->andWhere('year(e.published)=\''.$year.'\'');
    }
    if(isset($_GET['tag']))
    {
      $etags=tdzTags::search();
      $tags=$_GET['tag'];
      $slugs=array();
      if(!is_array($tags)) $tags=array($tags);
      foreach($tags as $tag)
      {
        $slug=tdz::textToSlug($tag);
        if($slug=='' || !isset($etags[$slug]))continue;
        $slugs[$slug] = tdz::xmlEscape($etags[$slug]);
      }
    }
    if(count($slugs)>0)
    {
      //OR: $pager->getQuery()->innerJoin('e.Tags t with t.slug in (\''.implode('\',\'',array_keys($slugs)).'\')');
      //AND:
      $i=0;
      foreach($slugs as $slug=>$name){
        $pager->getQuery()->innerJoin('e.Tags t'.$i.' with t'.$i.'.expired is null and t'.$i.'.slug='.tdz::sqlEscape($slug));
        $i++;
      }
      $qs.=($qs!='')?('&'):('?');
      foreach($slugs as $slug=>$tag)
      {
        $oslugs=$slugs;
        unset($oslugs[$slug]);
        if(count($oslugs)==0)
          $filters .= '<a href="'.tdz::xmlEscape($qs).'">'.$tag.'<span class="close"></span></a>';
        else
          $filters .= '<a href="'.tdz::xmlEscape($qs).'tag[]='.implode('&amp;tag[]=',array_keys($oslugs)).'">'.$tag.'<span class="close"></span></a>';
      }
      $qs.= 'tag[]='.implode('&tag[]=',$slugs);
      if($year)
        $filters = '<a class="year" href="?tag[]='.implode('&amp;tag[]=',$slugs).'">'.$year.'<span class="close"></span></a>'.$filters;
    }
    else if($year)
      $filters = '<a class="year" href="?">'.$year.'<span class="close"></span></a>'.$filters;
    if($filters) tdz::set('filters', '<div class="tags">'.$filters.'</div>');
  }
  $page=(isset($_GET['p']) && is_numeric($_GET['p']))?((int)$_GET['p']):(1);
  if($page<=0)$page=1;
  $pager->setPage($page);
  $pager->init();
  $after = tdz::pages($pager, $qs, $hpp, array('first'=>__('first'),'last'=>__('last'),'next'=>__('next').' &#8594;','previous'=>'&#8592; '.__('previous')));
  $before = $after.$before;
  $entries=$pager->getResults();
}
foreach($entries as $entry)
{
  $class=($i%2)?($odd):($even);
  $s .= str_replace('<div class="hentry tdze', '<div class="hentry tdze '.$class, tdzEntries::entryPreview($entry, $template));
  if(isset($limit) && $i++>=$limit)break;
  else if(!isset($limit))$i++;
}
  //$s.= get_component('tdz_entries','entryPreview',array('id'=>$entry->id));
$s = $before.$s.'</div>'.$after;
echo $s;
