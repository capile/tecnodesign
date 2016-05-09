<?php
/**
 * Navigation List
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: tdz_navigation_list.php 967 2011-12-08 09:45:41Z capile $
 */
$c = ($id==$current_id)?(' class="current"'):('');
$s = "<li{$c}>";
$s .= (($link)?('<a'.$c.' href="'.htmlspecialchars($link, ENT_QUOTES, 'UTF-8', false).'">'.htmlspecialchars($title, ENT_QUOTES, 'UTF-8', false).'</a>'):(htmlspecialchars($title, ENT_QUOTES, 'UTF-8', false)));
$s .= tdz_navigation($id, __FILE__,$level,$current);
$s .= '</li>';
echo $s;
