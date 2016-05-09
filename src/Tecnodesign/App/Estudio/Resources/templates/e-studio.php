<?php
/**
 * E-Studio Default Editing Template
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: e-studio.php 878 2011-09-05 07:39:10Z capile $
 *
 */
if(!isset($script)) $script = array();
else if(!is_array($script)) $script = array($script);
ksort($script);
$script = tdz::minify($script, false, true, false);
if(!isset($style)) $style = array();
else if(!is_array($style)) $style = array($style);
ksort($style);
$style = tdz::minify($style);

echo '<'.'?xml version="1.0" encoding="UTF-8" ?'.'>';
?><!doctype html><html class="studio"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title><?php if(isset($title)) echo tdz::xmlEscape($title) ?></title><?php
    echo $style;
    if(isset($meta)) echo $meta;
?><link rel="icon" type="image/png" href="/favicon.png" /></head><body><?php
    echo $data.$script
?></body></html>