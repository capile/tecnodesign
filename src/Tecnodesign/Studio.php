<?php
/**
 * Tecnodesign Studio
 * 
 * Stand-alone Content Management System.
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
class Tecnodesign_Studio extends Studio\Studio
{
    public static 
        $webInterface,
        $interfaceClass='Tecnodesign_Studio_Interface';
}

/*
if(!class_exists('tdzEntry')) {
    if(!in_array($libdir = dirname(__FILE__).'/Studio/Resources/model', S::$lib)) S::$lib[]=$libdir;
    unset($libdir);
}

if(!defined('TDZ_ESTUDIO')) define('TDZ_ESTUDIO', Tecnodesign_Studio::VERSION);
*/