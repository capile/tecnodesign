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
require (!file_exists($a=__DIR__.'/vendor/autoload.php') && !file_exists($a=__DIR__.'/../../autoload.php'))
    ?__DIR__.'/src/tdz.php'
    :$a;
unset($a);
$env = tdz::env();
$appMemoryNamespace = file_exists(TDZ_APP_ROOT.'/.appkey') ? \tdz::slug(file_get_contents(TDZ_APP_ROOT.'/.appkey')) : 'app';
tdz::app(__DIR__.'/app.yml', $appMemoryNamespace, $env)->run();
