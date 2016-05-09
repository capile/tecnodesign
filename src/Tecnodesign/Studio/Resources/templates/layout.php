<?php
/**
 * E-Studio default template
 *
 * @package      Studio
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         https://tecnodz.com/
 * @copyright    Tecnodesign (c) 2014
 */
if(!$title && isset(tdz::$variables['variables']['title'])) $title = tdz::$variables['variables']['title'];
if(!isset($script)) $script = array();
else if(!is_array($script)) $script = array($script);
ksort($script);
$script = tdz::minify($script, false, true, false);

if(!isset($style)) $style = array();
else if(!is_array($style)) $style = array($style);
ksort($style);
$style = tdz::minify($style);


if(!isset($content)) {
    if(isset($data)) $content = $data;
    else $content = '';
}

if(Tecnodesign_App::request('shell')) {
	echo tdz::text($content)."\n";
	return;
}

?><!doctype html><html><head><meta http-equiv="Content-Type" content="text/html;charset=utf-8" /><title><?php if(isset($title)) echo tdz::xmlEscape($title) ?></title><?php echo $style ?><?php if(isset($meta)) echo $meta ?><link rel="icon" type="image/png" href="/favicon.png" /></head><body><?php echo $content ?><?php echo $script ?></body></html>