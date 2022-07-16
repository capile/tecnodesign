<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */

use Studio as S;
use Studio\App;

$title = S::t('Bad Server!', 'exception');
$summary = S::t('The server has become unresponsive. Please try again in a few moments.', 'exception');
if(S_CLI) {
    exit("{$title}\n{$summary}\n");
}
App::response(array('title'=>$title,'summary'=>$summary, 'headers'=>array('HTTP/1.1 500 Internal Server Error')));
?><div id="container" class="centered"><div class="round bg-white">
<h1><?php echo $title; ?> <span class="emoticon">:(</span></h1>
<p><?php echo $summary ?></p>
</div></div>