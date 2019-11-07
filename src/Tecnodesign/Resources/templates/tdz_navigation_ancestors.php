<?php
/**
 * Ancestors Navigation List
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
/*
$s = '<li>';
$s .= (($link)?('<a href="'.htmlspecialchars($link, ENT_QUOTES, 'UTF-8', false).'" rel="bookmark" title="'.htmlentities($title, ENT_QUOTES, 'UTF-8', false).'">'.htmlentities($title, ENT_QUOTES, 'UTF-8', false).'</a>'):(htmlentities($title, ENT_QUOTES, 'UTF-8', false)));
$s .= '</li>';
echo $s;
*/
$e = Tecnodesign_Studio::$response['entry'];
$s = '';
while($e) {
    $title = ($s)?(preg_replace('/ do Cravo$/i', '', preg_replace('/^Sobre (o|a) (Cravo )?/i', '', $e['title']))):($e['title']);
    $s = ($s)?(' &gt; '.$s):($s);
    $s = '<a href="'.tdz::xmlEscape($e['link']).'">'.tdz::xmlEscape($title).'</a>'.$s;
    $e = $e->getParent();
}

if($s) $s = '<p class="migalhas">'.$s.'</p>';
echo $s;
