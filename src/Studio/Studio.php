<?php
/**
 * Studio
 * 
 * Main application controller
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
namespace Studio;

class Studio extends \Tecnodesign_Studio
{
    public static 
        $webInterface=true,
        //$interfaceClass='Studio\\Api',
        $cliApps=[
            'start'=>['Studio\\Model\\Config','standaloneConfig'],
            'check'=>['Studio\\Model\\Index', 'checkConnection'],
            'index'=>['Studio\\Model\\Index','reindex'],
            'import'=>['Tecnodesign_Database','import'],
        ];
}