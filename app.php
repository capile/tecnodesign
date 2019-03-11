<?php
require_once 'tdz.php';
@include_once 'vendor/autoload.php';
chdir(dirname(__FILE__));
tdz::app(array_merge(['app.yml'],glob('data/config/*.yml')), 'app', 'dev')->run();
