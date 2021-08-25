<?php
$title = tdz::t('Not authenticated', 'exception');
$summary = tdz::t('Authentication is required, and we could not authenticate your request. Please try signing in.', 'exception');
if(!Tecnodesign_App::request('shell')) {
    $summary .= Tecnodesign_User::signInWidget();
}
Tecnodesign_App::response(array('title'=>$title,'summary'=>$summary)); // IE refuses to show page properly 'headers'=>array('HTTP/1.1 403 Forbidden')
?>
<h1><?php echo $title; ?> <span class="emoticon">:(</span></h1>
<p><?php echo $summary ?></p>