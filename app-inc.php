<?php
/**
 * Studio client application loader
 *
 * This is the only resource that needs to be called. No app will be run.
 * 
 * PHP version 7.2+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @link      https://tecnodz.com
 */
if(file_exists($a=__DIR__.'/vendor/autoload.php') || file_exists($a=__DIR__.'/../../autoload.php')) require $a;
unset($a);
require_once __DIR__.'/src/Studio.php';
$appMemoryNamespace = file_exists(S_APP_ROOT.'/.appkey') ? Studio::slug(file_get_contents(S_APP_ROOT.'/.appkey')) : 'app';
Studio::app(__DIR__.'app.yml', $appMemoryNamespace, 'dev');
