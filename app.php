<?php

/**
 * This makes our life easier when dealing with paths. Everything is relative
 * to the application root now.
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
chdir(__DIR__);

require 'vendor/autoload.php';
$appMemoryNamespace = file_exists('.appkey') ? \tdz::slug(file_get_contents('.appkey')) : 'app';
tdz::app('app.yml', $appMemoryNamespace, 'dev')->run();
