<?php
/**
 * Tecnodesign_UI generic template
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
?><section>
<div id="ui">
<div id="ui-app"><div class="ui-container">
<?php if(isset($title)): ?><h1><?php echo $title;Tecnodesign_App::response('title', $title); ?></h1><?php endif; ?>
<?php if(isset($summary)): ?><p><?php echo $summary;Tecnodesign_App::response('summary', $summary); ?></p><?php endif; ?>
<?php echo tdz::getUser()->getMessage(false, true), $app ?>
</div></div>
<div id="ui-nav"><div class="ui-container"><?php echo $ui->getNavigation('ui-nav ui-home'); ?></div></div>
</div>
</section>