<?php
/**
 * E-Studio default template
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: layout.php 978 2011-12-15 02:51:16Z capile $
 */

$mobile=tdz::isMobile();
if(!isset($title)) $title = '';
if(tdz::get('title')) {
    $title = tdz::get('title');
}
if(!isset($meta)) $meta = '';
$meta.= tdz::og();
$js = get_javascripts();
$css = get_stylesheets();
$jsontop=tdz::get('jsOnTop');
if ($jsontop) {
    $css.=$js;
    $js = '';
}
$js = trim(tdz::minify($js));
$css = trim(tdz::minify($css));

echo '<'.'?xml version="1.0" encoding="UTF-8" ?'.'>';
?>
<!doctype html><html><head><?php include_http_metas() ?><title><?php echo $title; ?></title><meta name="viewport" content="initial-scale=1,maximum-scale=2,user-scalable=yes" /><?php echo include_metas() ?><?php echo $css; ?><link rel="icon" href="/favicon.ico" type="image/x-icon" /><link rel="shortcut" href="/favicon.ico" type="image/x-icon" /><?php echo $meta; ?></head>
<body class="no-js"><?php echo $sf_content ?><?php echo $js ?></body></html>