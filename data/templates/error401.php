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

use Studio as S;
use Studio\App;
use Studio\User;

$title = S::t('Not authenticated', 'exception');
$summary = S::t('Authentication is required, and we could not authenticate your request. Please try signing in.', 'exception');
if(!App::request('shell')) {
    $summary .= User::signInWidget();
}
App::response(array('title'=>$title,'summary'=>$summary)); // IE refuses to show page properly 'headers'=>array('HTTP/1.1 403 Forbidden')
?>
<h1><?php echo $title; ?> <span class="emoticon">:(</span></h1>
<p><?php echo $summary ?></p>