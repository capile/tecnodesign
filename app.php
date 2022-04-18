<?php
/**
 * Studio application loader
 *
 * This is the only resource that needs to be called.
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
 */
if(file_exists($a=__DIR__.'/vendor/autoload.php') || file_exists($a=__DIR__.'/../../autoload.php')) require $a;
unset($a);
require_once __DIR__.'/src/Studio.php';
$appMemoryNamespace = file_exists(S_APP_ROOT.'/.appkey') ? Studio::slug(file_get_contents(S_APP_ROOT.'/.appkey')) : 'app';
if(!file_exists($configFile=S_PROJECT_ROOT.'/'.basename(S_PROJECT_ROOT).'.yml') &&
   !file_exists($configFile=S_APP_ROOT.'/'.basename(S_APP_ROOT).'.yml')) {
    $configFile = __DIR__.'/app.yml';
}
Studio::app($configFile, $appMemoryNamespace, Studio::env())->run();