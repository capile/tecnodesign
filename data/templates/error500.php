<?php
$title = tdz::t('Bad Server!', 'exception');
$summary = tdz::t('The server has become unresponsive. Please try again in a few moments.', 'exception');
if(TDZ_CLI) {
    exit("{$title}\n{$summary}\n");
}
Tecnodesign_App::response(array('title'=>$title,'summary'=>$summary, 'headers'=>array('HTTP/1.1 500 Internal Server Error')));
?><div id="container" class="centered"><div class="round bg-white">
<h1><?php echo $title; ?> <span class="emoticon">:(</span></h1>
<p><?php echo $summary ?></p>
</div></div>