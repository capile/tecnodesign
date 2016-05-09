<?php
/**
 * E-Studio default template
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: layout.php 748 2011-05-16 21:22:01Z capile $
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
?>
<!doctype html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php if(isset($title)) echo tdz::xmlEscape($title) ?></title>
<?php echo $style ?><?php if(isset($meta)) echo $meta ?>
<link rel="icon" type="image/png" href="/favicon.png" /></head>
<body>
<?php echo $content ?>
<?php echo $script ?>
</body>
</html>