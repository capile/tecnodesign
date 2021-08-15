<?php
$title = tdz::t('Not enough privileges', 'exception');
$summary = tdz::t('Looks like you don\'t have enough credentials to access this page. Please try logging in or accessing it with a different username.', 'exception');
if(!Tecnodesign_App::request('shell')) {
    $summary .= Tecnodesign_User::signInWidget();
}
Tecnodesign_App::response(array('title'=>$title,'summary'=>$summary)); // IE refuses to show page properly 'headers'=>array('HTTP/1.1 403 Forbidden')
?>
<h1><?php echo $title; ?> <span class="emoticon">:(</span></h1>
<p><?php echo $summary ?></p>