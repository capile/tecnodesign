<?php
/**
 * Studio pages and files
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
class Tecnodesign_Studio_Page extends Tecnodesign_Studio_Entry
{
    public static $schema=[
        'title' =>  '*Pages',
        'ref' =>  'Tecnodesign_Studio_Entry',
        'className' =>  'Tecnodesign_Studio_Page',
        'events' =>  [
            'before-insert' =>  [ 'actAs' ],
            'before-update' =>  [ 'actAs' ],
            'before-delete' =>  [ 'actAs' ],
            'active-records' =>  '`expired` is null and `type` = \'page\'',
        ],
    ];
}
