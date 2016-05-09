<?php
/**
 * Atom/RSS Entry
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: tdz_atom_entry.php 530 2010-11-29 12:00:44Z capile $
 */
$indent=true;$i='';$i0='';$i1='';
if($indent){$i0="\n  ";$i="\n  ";$i1="\n    ";}
$s = '<entry>';
$s .= $i.'<title type="html">'.tdz::xmlEscape($title,false).'</title>';
$s .= $i.'<link href="'.tdz::xmlEscape(tdz::buildUrl($link)).'" />';
$s .= $i.'<id>'.$id.'</id>';
$pub = strtotime($published);
if(!$pub)$pub=time();
$s .= $i.'<published>'.date('c',$pub).'</published>';
$mod = strtotime($updated);
if(!$mod)$mod=time();
$s .= $i.'<updated>'.date('c',$pub).'</updated>';
$su='';
if(is_array($figures) && count($figures)>0)
{
  $su .= $i1.'<div class="media-thumbnail">';
  foreach($figures as $imgid=>$fig)
  {
    $su .= $i1.'<figure id="fig'.$imgid.'">';
    $fig += array('alt'=>'','src'=>'');
    $src=$fig['src'];
    if(strpos($src,'.200x100')===false)
    {
      if(preg_match('/^(.+)(\.[a-z]{3,4})$/',$src,$m))
        $src=$m[1].'.200x100'.$m[2];
      else
        $src.='.200x100';
    }
    $su .= '<img alt="'.tdz::xmlEscape($fig['alt']).'" src="'.tdz::xmlEscape(tdz::buildUrl($src)).'" border="0" />';
    if(isset($fig['title'])) $su .= '<legend>'.tdz::xmlEscape($fig['title']).'</legend>';
    $su .= '</figure>';
  }
  $su .= $i1.'</div>';
}
$su .= $i1.$summary;
$s .= $i.'<summary type="html">'.tdz::xmlEscape($su,false).'</summary>';
$s .= $i0.'</entry>';
echo $s;
