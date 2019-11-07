<?php
/**
 * Tecnodesign_UI home template
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
$title = tdz::t('Administrative User Interface', 'ui');
$summary = tdz::t('Please select one entity below to review its contents', 'ui');
Tecnodesign_App::response('title', $title);
Tecnodesign_App::response('summary', $summary);
?><section>
<div id="ui">
<div id="ui-app"><div class="ui-container">
<h1><?php echo $title ?></h1>
<p><?php echo $summary ?></p>
<?php echo tdz::getUser()->getMessage(false, true) ?>
</div></div>
<div id="ui-nav"><div class="ui-container"><?php echo $ui->getNavigation('ui-nav ui-home'); ?></div></div>
</div>
</section>