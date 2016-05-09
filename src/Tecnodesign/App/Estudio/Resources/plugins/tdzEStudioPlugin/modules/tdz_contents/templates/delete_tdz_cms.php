<?php
/**
 * Tecnodesign E-Studio
 * 
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: delete_tdz_cms.php 521 2010-11-15 19:55:19Z capile $
 */
?>
<div class="tdz">
<?php if($toolbar): ?><div class="toolbar"><div class="logo"><a href="/"><img src="<?php echo $img ?>" /></a></div><h1><?php echo __('Delete content at <em>%1%</em>',array('%1%'=>$title)) ?></h1><div class="tdzcms"><?php
$e=$sf_data->getRaw('entry');
echo $e->uiButtons('delete','Content');
?></div></div><?php endif; ?>
<?php if($message): ?><div class="message"><?php echo $message ?></div><?php endif; ?>
<script type="text/javascript">/*<![CDATA[*/<?php echo $sf_data->getRaw('js') ?>/*]]>*/</script>
</div>