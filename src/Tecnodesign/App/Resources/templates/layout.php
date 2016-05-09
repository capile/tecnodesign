<?php 
/**
 * Tecnodesign default layout
 *
 * PHP version 5.3
 *
 * @category  App
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: layout.php 1098 2012-08-14 15:59:48Z capile $
 * @link      http://tecnodz.com/
 */
?><!doctype html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title><?php if(isset($title)) echo $title ?></title><?php if(isset($meta)) echo $meta; ?><?php if(isset($style)) echo tdz::minify($style); ?></head><body class="no-js"><?php echo $data;if(isset($content)) echo $content; ?><?php if(isset($script)) echo tdz::minify($script); ?></body></html>