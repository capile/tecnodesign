<?php
/**
 * E-Studio UI Base layout 
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: e-studio.php 878 2011-09-05 07:39:10Z capile $
 *
 */
if(!isset($message))$message='';
$message.=tdz::getUser()->getMessage(false,true);
?><header><div id="estudio-header"><div class="estudio-icon logo"><a href="/"></a></div><span class="estudio-icon estudio-<?php echo $action ?>"></span><h1><?php echo $title ?></h1></div></header><section><div class="estudio-body"><?php echo $message.$content ?></div></section>