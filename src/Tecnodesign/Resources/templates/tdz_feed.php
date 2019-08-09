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
$entries = $entry->getEntries();
if($entries && $entries->count()>0) {
    if($hpp) {
        $s .= $entries->paginate($hpp, 'renderEntry', array($template), true, true);
    } else {
        $entries->setQuery(false);
        foreach($entries as $entry) {
            $class=($i%2)?($odd):($even);
            $s .= str_replace('<div class="hentry tdze', '<div class="hentry tdze '.$class, $entry->renderEntry($template));
            if(isset($limit) && $i++>=$limit)break;
            else if(!isset($limit))$i++;
        }
    }
}
$s = $before.$s.'</div>'.$after;
echo $s;