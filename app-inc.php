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
 * @version   2.4
 */
chdir(__DIR__);
require (!file_exists($a='vendor/autoload.php') && !file_exists($a='../../autoload.php')) 
    ?'src/tdz.php'
    :$a;
unset($a);
$appMemoryNamespace = file_exists('.appkey') ? \tdz::slug(file_get_contents('.appkey')) : 'app';
tdz::app('app.yml', $appMemoryNamespace, 'dev');
