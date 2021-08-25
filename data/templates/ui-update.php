<?php
/**
 * Tecnodesign_UI review template
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
$title = tdz::t('Update', 'ui-labels').' '.$ui->getModelLabel($model).': '.(string)$model;
tdz::$variables['title']=$title;
Tecnodesign_App::response('title', $title);
//Tecnodesign_App::response('summary', $summary);
?><section>
<div id="ui">
<div id="ui-app" class="app-update"><div class="ui-container">
<h1><?php echo $title ?></h1>
<?php echo $ui->getButtons(), tdz::getUser()->getMessage(false, true) ?>
<div class="ui-page" data-role="page"><?php if($error): ?><div class="error"><?php echo $error; ?></div><?php endif; echo $form; ?></div>
<?php
    $hpp = 20;
    $checkbox = false;//$pk = $class::pk();
    $link = ($tn && $linkModel)?("$tn/"):('');
    $link .= Tecnodesign_ui::$actions['update'];
    $link = $ui->getLink($link, false);
    echo $search;
    if($list) echo $list->paginate($hpp, 'renderUi', array('options'=>array('link'=>$link, 'checkbox'=>$checkbox)), true, true); ?>
</div></div>
<?php if($nav): ?><div id="ui-nav"><div class="ui-container"><?php echo $ui->getNavigation('ui-nav ui-home'); ?></div></div><?php endif; ?>
</div>
</section>