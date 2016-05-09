<?php
/**
 * Tecnodesign E-Studio
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: new_tdz_cms.php 1150 2013-01-03 18:55:57Z capile $
 */

?>
<div class="tdz">
<div class="toolbar"><div class="logo"><a href="/"><img src="<?php echo $img ?>" /></a></div><h1><span class="btn new"></span><?php echo __('New entry') ?></h1><div class="tdzcms"><?php
$e=$sf_data->getRaw('entry');
echo $e->uiButtons('new');
?></div>
</div>
<?php if($message): ?><div class="message"><?php echo $message ?></div><?php endif; ?>
<?php if($form!=''): ?><form action="<?php echo $url ?>" method="post" enctype="multipart/form-data" class="tdzf" onsubmit="return tdz.cms_submit(this);">
<?php echo $sf_data->getRaw('form')->render_cms() ?>
<button class="submit new" type="submit"><span class="icon"></span><?php echo __('Create') ?></button>
</form><?php endif; ?>
<script type="text/javascript">/*<![CDATA[*/<?php echo $sf_data->getRaw('js') ?>/*]]>*/</script>
</div>