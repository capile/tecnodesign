<?php

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 */
chdir(__DIR__);

require 'vendor/autoload.php';

/**
 * @todo create a decent init to avoid constants creation
 */
require 'tdz.php';

$appMemoryNamespace = file_exists('.appkey') ? \tdz::slug(file_get_contents('.appkey')) : 'app';
tdz::app('app.yml', $appMemoryNamespace, 'dev')->run();
