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
echo '<'.'?xml version="1.0" encoding="UTF-8" ?'.'>';

$js = get_javascripts();
if($js && !strpos($js, '"'.tdz::$assetsUrl)) $js = preg_replace('#"[^"]+'.tdz::$assetsUrl.'/#', '"'.tdz::$assetsUrl.'/', $js);
$js = str_replace(' async="async"', '', tdz::minify($js,sfConfig::get('app_e-studio_document_root')));

$css = get_stylesheets();
if($css && !strpos($css, '"'.tdz::$assetsUrl)) $css = preg_replace('#"[^"]+'.tdz::$assetsUrl.'/#', '"'.tdz::$assetsUrl.'/', $css);
$css = tdz::minify($css,sfConfig::get('app_e-studio_document_root'));

?>
<!doctype html>
<html>
<head>
<?php include_http_metas() ?><?php if(isset($title)) echo "<title>$title</title>"; else include_title(); ?>
<?php include_metas() ?>
<?php echo $css ?>
<?php echo $js ?>
<?php if(isset($meta)) echo $meta; ?>
</head>
<body>
<?php echo $sf_content ?>
</body>
</html>
