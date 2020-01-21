<?php
/**
 * Tecnodesign Test Suite
 * 
 * This package enable the testing of Tecnodesign framework and applications
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */

require preg_replace('#/src/Tecnodesign/.*$#', '', __FILE__).'/vendor/autoload.php';
tdz::autoloadParams('tdz');