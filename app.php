<?php
require_once 'tdz.php';
@include_once 'vendor/autoload.php';
tdz::app(array_merge(['app.yml'],glob(dirname(__FILE__).'/data/config/*.yml')), 'app', 'dev')->run();
