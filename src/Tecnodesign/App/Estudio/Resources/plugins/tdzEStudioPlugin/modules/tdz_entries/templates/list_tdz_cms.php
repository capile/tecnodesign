<?php
/**
 * Tecnodesign E-Studio
 *
 * List of existing entries
 * 
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id$
 */
$qs = ($query)?('?q='.$query):('');
?>
<div class="tdz">
<div class="searchform toolbar"><div class="logo"><a href="/"><img src="<?php echo $img ?>" /></a></div><form action="<?php echo $url ?>" method="get" enctype="multipart/form-data" class="tdzf" onsubmit="return tdz.cms_submit(this);">
<input name="q" id="q" type="text" class="searchinput" value="<?php echo $query ?>" />
<div class="typelist">
<?php foreach($types as $tn=>$td): ?>
<p><label for="<?php echo $tn ?>"><span class="type <?php echo $tn ?>"><input type="checkbox" name="t[]" id="t_<?php echo $tn ?>" value="<?php echo $tn ?>"<?php if($td['display']){ $qs.= ($qs!='')?('&amp;t[]='.$tn):('?t[]='.$tn); echo ' checked="checked"'; } ?> /><?php echo __($td['label']) ?></span></label></p>
<?php endforeach; ?>
<?php if($status): ?>
<div class="statuslist">
<?php foreach($status as $tn=>$td): ?>
<p><label for="<?php echo $tn ?>"><input type="checkbox" name="<?php echo $tn ?>" id="<?php echo $tn ?>" value="1"<?php if($td) echo ' checked="checked"'; ?> /> <?php echo __(ucfirst($tn)); ?></label></p>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
<input name="p" id="p" type="hidden" value="<?php echo $page ?>" />
<button type="submit" class="search submit"><span class="icon"></span><?php echo __('Search') ?></button>
</form><div class="tdzcms"><?php
$e=new tdzEntries();
echo $e->uiButtons('search');
?></div></div>
<?php if($message): ?><div class="message"><?php echo $message ?></div><?php endif; ?>
<?php $pages = tdz::pages($entries, $qs, 10, array('first'=>__('first'),'last'=>__('last'),'next'=>__('next').' &#8594;','previous'=>'&#8592; '.__('previous'))); echo $pages;?>
<?php if($entries->haveToPaginate()) echo '<p class="counter">'.sprintf(__('Found %d results, showing from %d-%d'),$entries->count(), $entries->getFirstIndice(), $entries->getLastIndice()).'</p>'; ?>
<div class="list">
<table cellpadding="0" cellspacing="0"><thead><tr><th scope="col" class="summary"><?php echo __('Summary') ?></th><th scope="col" class="updated"><?php echo __('Updated') ?></th><th scope="col" class="status"><?php echo __('Status') ?></th></thead><tbody>
<?php if(!is_array($entries) && $entries->count()>0): ?>
<?php
$rawe=$sf_data->getRaw('entries');
$permissions=array(
//'preview-published'=>tdzEntries::hasStaticPermission('preview', 'Entry', 'published'),
//'preview-unpublished'=>tdzEntries::hasStaticPermission('preview', 'Entry', 'unpublished'),
'edit-published'=>tdzEntries::hasStaticPermission('edit', 'Entry', 'published'),
'edit-unpublished'=>tdzEntries::hasStaticPermission('edit', 'Entry', 'unpublished'),
);
foreach($rawe->getResults() as $i=>$row): ?>
<?php
$pub=($row->published)?('published'):('unpublished');
$class=($i%2)?('odd'):('even');
$status = $row->getStatus('list');
$class .= ' '.strtolower($status);
$link = ($permissions["edit-$pub"])?($ui_url.'/e/edit/'.$row->getId()):($ui_url.'/e/preview/'.$row->getId());
?>
<tr class="<?php echo $class ?>">
  <td><a href="<?php echo $link ?>"><?php echo $row->getSummary('list') ?></a></td>
  <td><a href="<?php echo $link ?>"><?php echo $row->getUpdated('list') ?></a></td>
  <td class="status"><a href="<?php echo $link ?>"><?php echo __($status) ?></a></td>
</tr>
<?php endforeach; ?>
<?php else: ?>
<tr><td colspan="4"><em><?php echo __('There are no entries available for this query.') ?></em></td></tr>
<?php endif; ?>
</tbody></table>
</div>
<?php echo $pages;?>


  <script type="text/javascript">/*<![CDATA[*/<?php echo $sf_data->getRaw('js') ?>/*]]>*/</script>
</div>