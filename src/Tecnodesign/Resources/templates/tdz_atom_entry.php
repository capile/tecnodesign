<?php
/**
 * Atom/RSS Entry
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
$indent=true;$i='';$i0='';$i1='';
if($indent){$i0="\n  ";$i="\n    ";$i1="\n      ";}
$s = $i0.'<entry>';
$s .= $i.'<title type="html">'.tdz::xmlEscape($title,false).'</title>';
$s .= $i.'<link href="'.tdz::xmlEscape(tdz::buildUrl($link)).'" />';
$s .= $i.'<id>e-studio-e-'.$id.'</id>';
$pub = strtotime($published);
if(!$pub)$pub=time();
$s .= $i.'<published>'.date('c',$pub).'</published>';
$mod = strtotime($updated);
if(!$mod)$mod=time();
$s .= $i.'<updated>'.date('c',$pub).'</updated>';
$su='';
$figures = $entry->getContent('media');
if($figures && $figures->count()>0) {
    foreach($figures as $media) {
        $imgid=$media['id'];
        $fig = $media['contents'];
        if(!isset($fig['src']) || $fig['src']=='')continue;

        $su .= $i1.'<figure id="fig'.$imgid.'">';
        $fig += array('alt'=>'','src'=>'');
        $src=$fig['src'];
        if(strpos($src,'.200x100')===false) {
            if(preg_match('/^(.+)(\.[a-z]{3,4})$/',$src,$m))
                $src=$m[1].'.200x100'.$m[2];
            else
                $src.='.200x100';
        }
        $su .= '<img alt="'.tdz::xmlEscape($fig['alt']).'" src="'.tdz::xmlEscape(tdz::buildUrl($src)).'" border="0" />';
        if(isset($fig['title'])) $su .= '<legend>'.tdz::xmlEscape($fig['title']).'</legend>';
        $su .= '</figure>';
    }
    $su = ($su)?($i1.'<div class="media-thumbnail">'.$su.$i1.'</div>'):('');
}
$su .= $i1.$summary;
$s .= $i.'<summary type="html">'.tdz::xmlEscape($su,false).'</summary>';
$s .= $i0.'</entry>';
echo $s;
