<?php
/**
 * Ancestors Navigation List
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
$e = Tecnodesign_Studio::$response['entry'];
$s = '';
while($e) {
    $title = $e['title'];
    $s = ($s)?(Tecnodesign_Studio::$breadcrumbSeparator.$s):($s);
    $s = '<a href="'.tdz::xmlEscape($e['link']).'">'.tdz::xmlEscape($title).'</a>'.$s;
    $e = $e->getParent();
}

if($s) $s = '<p class="breadcrumb">'.$s.'</p>';
echo $s;
