<?php
/**
 * Tecnodesign E-Studio
 * 
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: edit_tdz_cms.php 878 2011-09-05 07:39:10Z capile $
 */
$e=$sf_data->getRaw('entry');
?>
<div class="tdz">
<div class="toolbar"><div class="logo"><a href="/e-studio/e/preview/<?php echo $e->id; ?>"><img src="<?php echo $img ?>" /></a></div><h1><span class="btn edit"></span><?php echo $title ?></h1><div class="tdzcms"><?php echo $e->uiButtons('edit'); ?></div>
</div>
<?php if($message): ?><div class="message"><?php echo $message ?></div><?php endif; ?>
<?php if($form): ?>
<form action="<?php echo $url ?>" method="post" enctype="multipart/form-data" class="tdzf" onsubmit="return tdz.cms_submit(this);">
<?php echo $sf_data->getRaw('form')->render_cms() ?>
<div class="buttons">
<?php if($expired): ?><button class="submit edit" type="submit"><span class="icon"></span><?php echo __('Restore') ?></button>
<?php else: ?><button class="submit edit" type="submit"><span class="icon"></span><?php echo __('Save') ?></button>
<?php   if($publish): ?><button class="submit publish" type="button" onclick="tdz.cms_publish(this.form)"><span class="icon"></span><?php echo __('Save').' &amp; '.__('Publish') ?></button><?php endif; ?>
<?php   if($unpublish): ?><button class="submit unpublish" type="button" onclick="tdz.cms_unpublish(this.form)"><span class="icon"></span><?php echo __('Unpublish') ?></button><?php endif; ?>
<?php   if($delete): ?><button class="submit delete" type="button" onclick="tdz.cms_delete(this.form)"><span class="icon"></span><?php echo __('Delete') ?></button><?php endif; ?>
<?php endif ?>
</div>
</form>
<?php endif; ?>
<script type="text/javascript">/*<![CDATA[*/<?php $js=$sf_data->getRaw('js');if($js)echo "\$(function(){ {$js} });"; ?>/*]]>*/</script>
</div>