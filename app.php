<?php
/**
 * Tecnodesign Studio application loader
 *
 * This is the only resource that needs to be called.
 * 
 * PHP version 7.2+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */

// This makes our life easier when dealing with paths. Everything is relative to the application root now.
chdir(__DIR__);
// composer handles all class loading
require 'vendor/autoload.php';
$appMemoryNamespace = file_exists('.appkey') ? \tdz::slug(file_get_contents('.appkey')) : 'app';
tdz::app('app.yml', $appMemoryNamespace, 'dev')->run();
