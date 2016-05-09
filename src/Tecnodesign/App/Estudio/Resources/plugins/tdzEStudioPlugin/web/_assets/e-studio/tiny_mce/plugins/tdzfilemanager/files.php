<?php
/**
 * File manager
 *
 * @author      Tecnodesign <ti@tecnodz.com>
 * @link        http://tecnodz.com/
 * @copyright   Tecnodesign (c) 2009
 * @package     tiny_mce
 * @version     SVN: $Id$
 */
?>
<?xml version="1.0" encoding="UTF-8" ?><!doctype html>
<html>
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title><?php echo _('File manager'); ?></title>
<meta name="title" content="File manager" />
<link rel="stylesheet" type="text/css" media="screen" href="/_assets/e-studio/css/e-studio-ui.css" />
<script type="text/javascript" src="/_assets/e-studio/js/loader.js"></script>
<script type="text/javascript" src="/_assets/e-studio/tiny_mce/plugins/tdzfilemanager/filecontrol.js"></script>
<style type="text/css">
 .media-window, .files { height: 100%!important; max-height: 100%!important; }
 .files { margin: 0 0 0 0; } 
</style>
</head>
<body>
  <div class="tdz">
  <form action="#" method="get" >
    <input class="tinymedia" value="" type="hidden" />
  </form>
  </div>
  <script type="text/javascript">
  /*<![CDATA[*/
    $('input.tinymedia').bind('focus',tdz.tinymce_mediaSelect);
    $('input.tinymedia').trigger('focus');
  /*]]>*/
  </script>
</body>
</html>