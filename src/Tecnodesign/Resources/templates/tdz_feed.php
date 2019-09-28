<?php
/**
 * Default News Feed Template
 *
 * @package      Studio
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         https://tecnodz.com/
 * @copyright    Tecnodesign (c) 2019
 */
$s = '<div class="hfeed tdze" id="e'.$entry->id.'">';
$after='';$before='';
$i=1;
$qs='';
if(!isset($template)) $template='tdz_entry';
if(isset($linkhome) && $linkhome) {
    $class=($i%2)?($odd):($even);
    $s .= str_replace('<div class="hentry tdze', '<div class="hentry tdze '.$class, $entry->renderEntry($template));
    $i++;
}
if($entries && is_object($entries)) {
    if($hpp) {
        $s .= $entries->paginate($hpp, 'renderEntry', array($template), true, true);
        $entries = null;
    } else {
        $entries = $entries->getItems();
    }
}
if($entries) {
    foreach($entries as $entry) {
        $class=($i%2)?($odd):($even);
        $s .= str_replace('<div class="hentry tdze', '<div class="hentry tdze '.$class, $entry->renderEntry($template));
        if(isset($limit) && $i++>=$limit)break;
        else if(!isset($limit))$i++;
    }
}
$s = $before.$s.'</div>'.$after;
echo $s;