<?php
/**
 * e-Studio Default Template for Tupinamba-enabled websites
 *
 * Just update
 * app_e-studio_default_layout: tupinamba
 * to enable it.
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id$
 * 
 */
echo '<'.'?xml version="1.0" encoding="UTF-8" ?'.'>';
 if(!isset($title)) $title = '';
if(!isset($meta)) $meta = '';
$js = get_javascripts();
global $xdbc;
if(strpos($sf_content,'xdb')!==false || (isset($xdbc) && isset($xdbc['title'])))
{
  $js .= '<script type="text/javascript" src="/_scripts/xdb-components.js"></script>';
  $meta .= '<link rel="stylesheet" type="text/css" media="screen" href="/_styles/calendar.css" />';
  if(isset($xdbc['meta']))$meta .= tdz_encode($xdbc['meta']);
  if(isset($xdbc['title']))$title .= ': '.tdz_encode($xdbc['title']);
}
?><!doctype html>
<html class="no-js page">
<head>
<?php include_http_metas() ?><?php if($title) echo "<title>$title</title>"; else include_title();  ?>
<?php include_metas() ?>
<?php include_stylesheets() ?>
<?php echo $meta ?>
</head>
<body>
<?php echo $sf_content ?>
<?php echo $js ?>
</body>
</html>