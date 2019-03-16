<?php
/**
 * Autoloader Interface
 *
 * Classes adopting this interface must define a static method to process on load.
 *
 * PHP version 5.4
 *
 * @category  Ui
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2019 Tecnodesign
 * @license   https://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      https://tecnodz.com/
 */
interface Tecnodesign_AutoloadInterface
{
    public static function staticInitialize();
}