<?php
/**
 * Tecnodesign_Interface generic template
 *
 * PHP version 5.3
 *
 * @category  Interface
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2015 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      https://tecnodz.com
 */

if(isset($error)): 
    ?><div class="tdz-error"><?php echo $error; ?></div><?php 
endif;

if(isset($preview)) echo $preview;

if(isset($list))
    echo 
        $listCounter,
        ((isset($searchForm))?('<input type="checkbox" id="tdz-i-s-'.$id.'" class="tdz-i-switch tdz-i-search" /><label for="tdz-i-s-'.$id.'">'.$Interface::$labelFilter.'</label><div class="tdz-i-search tdz-i-switched">'.$searchForm.'</div>'):('')),
        $list; 
