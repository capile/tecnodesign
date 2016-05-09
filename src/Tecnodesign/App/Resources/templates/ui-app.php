<?php
/**
 * Tecnodesign_UI generic template
 *
 * PHP version 5.3
 *
 * @category  UI
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: ui-app.php 1078 2012-06-26 16:18:41Z capile $
 * @link      http://tecnodz.com/
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