<?php
/**
 * Tecnodesign_Ui generic template
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
$id = tdz::slug($url);
Tecnodesign_App::response('title', $title);
?>
<div class="tdz-i-header"><?php foreach(Tecnodesign_Interface::$urls as $iurl=>$t): ?><a href="<?php echo $iurl ?>" class="tdz-i-title<?php if($iurl==$url) echo ' tdz-i-title-active'; ?>" data-url="<?php echo $iurl ?>"><?php echo $t; ?></a><?php endforeach;?></div>
<div class="tdz-i-body">
<div class="tdz-i<?php if($active) echo ' tdz-i-active'; ?>" data-url="<?php echo $url ?>"<?php if(isset($_SERVER['QUERY_STRING']) && $_SERVER['QUERY_STRING']) echo ' data-qs="'.tdz::xmlEscape($_SERVER['QUERY_STRING']).'"'; ?>><div class="tdz-i-container">
<?php if(isset($summary)): ?><p><?php echo $summary;Tecnodesign_App::response('summary', $summary); ?></p><?php endif; ?>
<?php echo tdz::getUser()->getMessage(false, true), $app ?>
<?php if(isset($error)): ?><div class="tdz-error"><?php echo $error; ?></div><?php endif; ?>
<?php if(isset($preview)): ?><div class="<?php echo $Interface::$attrPreviewClass; ?>"><?php echo $preview; ?></div><?php endif; ?>
<?php if(isset($list)): ?><div class="<?php echo $Interface::$attrListClass; ?>"><?php 
echo 
	'<div class="'.trim('z-i-actions '.$Interface::$attrButtonsClass).'">'
	. '<input type="checkbox" id="tdz-i-b-'.$id.'" class="tdz-i-switch z-i-actions" /><label for="tdz-i-b-'.$id.'">'.$Interface::$labelActions.'</label><div class="tdz-i-buttons tdz-i-switched">'
	.  $buttons
	.  '</div>'
	.'</div>'
	. $listCounter
	. ((isset($searchForm))?('<input type="checkbox" id="tdz-i-s-'.$id.'" class="tdz-i-switch tdz-i-search" /><label for="tdz-i-s-'.$id.'">'.$Interface::$labelFilter.'</label><div class="tdz-i-search tdz-i-switched">'.$searchForm.'</div>'):(''))
	. $list; 
?></div><?php endif; ?>
</div></div>
</div>