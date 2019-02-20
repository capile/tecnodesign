<?php
require_once 'tdz.php';
@include_once 'vendor/autoload.php';
tdz::app('app.yml', 'app', 'dev')->run();
