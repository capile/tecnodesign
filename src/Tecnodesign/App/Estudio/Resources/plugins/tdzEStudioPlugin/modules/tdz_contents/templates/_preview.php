<?php
/**
 * Tecnodesign E-Studio
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: _preview.php 520 2010-11-12 19:48:30Z capile $
 */
use_helper('tdzEStudio');
if($preview):
?><?php if(isset($export))
{
  ob_start();
  echo eval("return {$sf_data->getRaw('export')};");
  $export = ob_get_contents();
  ob_end_clean();
} ?>
<div class="tdzc" id="c<?php echo $id ?>"><?php if(isset($before)) echo $sf_data->getRaw('before'); ?>
<?php if(isset($export)) echo $export; ?>
<?php if(isset($content)) echo $sf_data->getRaw('content'); ?>
<?php if(isset($after)) echo $sf_data->getRaw('after'); ?></div>
<?php endif; ?>