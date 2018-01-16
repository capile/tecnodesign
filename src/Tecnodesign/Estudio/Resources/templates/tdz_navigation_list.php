<?php
/**
 * Navigation List
 *
 * @package      Estudio
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2012
 * @version      SVN: $Id: tdz_navigation_list.php 518 2010-11-10 18:51:58Z capile $
 */

if(!isset($options)) $options=array();
$sub=array('master'=>$master, 'options'=>$options);
$s0 = $s = $s1 ='';
if(!isset($container) || $container==true) {
    $s0 = '<nav><div id="enav'.$id.'" class="nav">';
    if(in_array('linkhome', $options)) {
    }
    $s1 = '</div></nav>';
    $sub['container']=false;
}

if($entries instanceof Tecnodesign_Collection) $entries = $entries->getItems();
if(in_array('linkhome', $options)) {
    if(!$entries) $entries = array();
    array_unshift($entries, $entry->asArray());
}

echo $s0.Tecnodesign_Estudio::li($entries).$s1;