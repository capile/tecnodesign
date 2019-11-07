<?php
/**
 * Autoloader Interface
 * 
 * Classes adopting this interface must define a static method to process on load.
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
interface Tecnodesign_AutoloadInterface
{
    public static function staticInitialize();
}