<?php
/**
 * Ancestors Navigation List
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: tdz_navigation_ancestors.php 494 2010-10-04 13:27:32Z capile $
 */
/*
$s = '<li>';
$s .= (($link)?('<a href="'.htmlspecialchars($link, ENT_QUOTES, 'UTF-8', false).'" rel="bookmark" title="'.htmlentities($title, ENT_QUOTES, 'UTF-8', false).'">'.htmlentities($title, ENT_QUOTES, 'UTF-8', false).'</a>'):(htmlentities($title, ENT_QUOTES, 'UTF-8', false)));
$s .= '</li>';
echo $s;
*/
$e = Tecnodesign_App_Studio::$response['entry'];
$s = '';
while($e) {
    $title = ($s)?(preg_replace('/ do Cravo$/i', '', preg_replace('/^Sobre (o|a) (Cravo )?/i', '', $e['title']))):($e['title']);
    $s = ($s)?(' &gt; '.$s):($s);
    $s = '<a href="'.tdz::xmlEscape($e['link']).'">'.tdz::xmlEscape($title).'</a>'.$s;
    $e = $e->getParent();
}

if($s) $s = '<p class="migalhas">'.$s.'</p>';
echo $s;
