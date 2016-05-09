<?php
/**
 * Navigation List
 *
 * @package      Studio
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2012
 * @version      SVN: $Id: tdz_navigation_list.php 518 2010-11-10 18:51:58Z capile $
 */

if(!isset($options)) $options=array();
$sub=array('master'=>$master, 'options'=>$options);
$s0 = $s = $s1 ='';
if(!isset($container) || $container==true) {
    $s0 = '<nav><div id="enav'.$entry->id.'" class="nav">';
    if(in_array('linkhome', $options)) {
    }
    $s1 = '</div></nav>';
    $sub['container']=false;
}

$c = $entry->getChildren();
$c->setQuery(false);
//$c = (array) $c;
if(in_array('linkhome', $options)) {
    array_unshift($c, $entry->asArray());
}

if(!function_exists('studio_navigation_list')) {
function studio_navigation_list($list)
{
    $s = '';
    if($list && count($list)>0) {
        foreach($list as $e) {
            $c = ($e['id']==tdzEntry::$current['page'])?(' class="current"'):('');
            $s .= '<li'.$c.'>'
                . (($e['link'])?('<a'.$c.' href="'.tdz::xmlEscape($e['link']).'">'.tdz::xmlEscape($e['title']).'</a>'):(tdz::xmlEscape($e['title'])))
                .  (($e instanceof tdzEntry)?(studio_navigation_list($e->getChildren())):(''))
                . '</li>';
        }
        if($s) {
            $s = '<ul>'.$s.'</ul>';
        }
    }
    return $s;
}};

$s = studio_navigation_list($c);

echo $s0.$s.$s1;

