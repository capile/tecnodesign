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
use Studio\User;

$title = S::t('Not enough privileges', 'exception');
$summary = S::t('Looks like you don\'t have enough credentials to access this page. Please try logging in or accessing it with a different username.', 'exception');
if(!App::request('shell')) {
    $summary .= User::signInWidget();
}
App::response(array('title'=>$title,'summary'=>$summary)); // IE refuses to show page properly 'headers'=>array('HTTP/1.1 403 Forbidden')
?>
<h1><?php echo $title; ?> <span class="emoticon">:(</span></h1>
<p><?php echo $summary ?></p>