<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
 */
$title = tdz::t('Oops, where is this page?', 'exception');
$summary = tdz::t('We haven\'t found the page you requested. Are you sure the address is correct?', 'exception');
if(TDZ_CLI) {
    exit("{$title}\n{$summary}\n");
}
Tecnodesign_App::response(array('title'=>$title,'summary'=>$summary, 'headers'=>array('HTTP/1.1 404 Not Found')));
?><div id="container" class="centered"><div class="round bg-white">
<h1><?php echo $title; ?> <span class="emoticon">:(</span></h1>
<p><?php echo $summary ?></p>
</div></div>