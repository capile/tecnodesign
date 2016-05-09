<?php
/**
 * Tecnodesign E-Studio
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: preview_tdz_cms.php 494 2010-10-04 13:27:32Z capile $
 */
use_helper('tdzEStudio');
?><div class="tdzc" id="c<?php echo $id ?>"><?php if(isset($before)) echo $sf_data->getRaw('before'); ?>
<?php if(isset($export)) echo eval("return {$sf_data->getRaw('export')};"); ?>
<?php if(isset($content)) echo $sf_data->getRaw('content'); ?>
<?php if(isset($after)) echo $sf_data->getRaw('after'); ?></div>