<?php
/**
 * Tecnodesign E-Studio
 * 
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: edit_tdz_cms.php 502 2010-10-20 04:28:03Z capile $
 */
?>
<div class="tdz">
<div class="toolbar"><div class="logo"><a href="/"><img src="<?php echo $img ?>" /></a></div><h1><span class="btn edit"></span><?php echo __('Delete').' '.lcfirst(__(ucfirst($type)))." <em>{$title}</em>" ?></h1><div class="tdzcms"><?php
$e=$sf_data->getRaw('entry');
echo $e->uiButtons('edit');
?></div>
</div>

<?php if($message): ?><div class="message"><?php echo $message ?></div><?php endif; ?>
<?php if($form): ?>
<form action="<?php echo $url ?>" method="post" enctype="multipart/form-data" class="tdzf" onsubmit="return tdz.cms_submit(this);">
<?php echo $sf_data->getRaw('form')->render_cms() ?>
<button class="save" type="submit"><?php echo __('Save') ?></button><?php if($delete): ?>
<button class="delete" type="button" onclick="tdz.cms_delete(this.form)"><?php echo __('Delete') ?></button><?php endif; ?>
</form>
<?php endif; ?>
<script type="text/javascript">/*<![CDATA[*/<?php echo $sf_data->getRaw('js') ?>/*]]>*/</script>
</div>