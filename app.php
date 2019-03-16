<?php
@include_once 'vendor/autoload.php';
require_once 'tdz.php';
chdir(dirname(__FILE__));
$memkey=(file_exists('.appkey')) ?\tdz::slug(file_get_contents('.appkey')) :'app';
tdz::app('app.yml', $memkey, 'dev')->run();
