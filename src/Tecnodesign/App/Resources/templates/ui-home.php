<?php
/**
 * Tecnodesign_UI home template
 *
 * PHP version 5.3
 *
 * @category  UI
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: ui-home.php 1078 2012-06-26 16:18:41Z capile $
 * @link      http://tecnodz.com/
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