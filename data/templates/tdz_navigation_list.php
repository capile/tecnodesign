<?php
/**
 * Navigation List
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
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

echo $s0.Tecnodesign_Studio::li($entries).$s1;