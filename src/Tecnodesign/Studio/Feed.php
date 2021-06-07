<?php
/**
 * Studio feeds
 * 
 * PHP version 7+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Studio_Feed extends Tecnodesign_Studio_Entry
{
    public static $schema=[
        'title' =>  '*News Channels',
        'ref' =>  'Tecnodesign_Studio_Entry',
        'className' =>  'Tecnodesign_Studio_Feed',
        'events' =>  [
            'before-insert' =>  [ 'actAs' ],
            'before-update' =>  [ 'actAs' ],
            'before-delete' =>  [ 'actAs' ],
            'active-records' =>  '`expired` is null and `type` = \'feed\'',
        ],
    ];
}
