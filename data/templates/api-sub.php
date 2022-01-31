<?php
/**
 * Tecnodesign_Interface generic template
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */

if(isset($error)): 
    ?><div class="tdz-error"><?php echo $error; ?></div><?php 
endif;

if(isset($preview)) echo $preview;

if(isset($list))
    echo 
        $listCounter,
        ((isset($searchForm))?('<input type="checkbox" id="s-api-s-'.$id.'" class="s-switch s-api-search" /><label for="s-api-s-'.$id.'">'.$Interface::$labelFilter.'</label><div class="s-api-search s-switched">'.$searchForm.'</div>'):('')),
        $list; 
