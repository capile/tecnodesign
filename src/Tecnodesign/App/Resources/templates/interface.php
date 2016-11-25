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
$id = tdz::slug($url);
if(strpos($url, '?')!==false) list($url, $qs)=explode('?', $url, 2);
else $qs='';

Tecnodesign_App::response('title', $title);

// .tdz-i-header
?><div class="tdz-i-header"><?php 
    $urls = Tecnodesign_Interface::$urls;
    if(!$Interface::$breadcrumbs) {
        $urls = array_slice($urls, -1, 1, true);
    }
    foreach($urls as $iurl=>$t): 
        ?><a href="<?php echo $iurl ?>" class="tdz-i-title<?php $iqs='';if(strpos($iurl, '?')!==false) list($iurl, $iqs)=explode('?', $iurl, 2);if($iurl==$url) echo ' tdz-i-title-active'; echo ' tdz-i--'.$t['action']; ?>" data-url="<?php echo $iurl ?>"<?php if($iqs) echo 'data-qs="', str_replace(',', '%2C', tdz::xmlEscape($iqs)), '"' ?>><?php echo $t['title']; ?></a><?php
    endforeach;

?></div><?php

// .tdz-i-body
?><div class="tdz-i-body"><?php

    // .tdz-i
    ?><div class="tdz-i<?php if(isset($active) && $active) echo ' tdz-i-active'; ?>" data-url="<?php echo $url ?>"<?php 
        if($qs) echo ' data-qs="',str_replace(',', '%2C', tdz::xmlEscape($qs)),'"';
        if($Interface['id']) echo ' data-id="',tdz::xmlEscape($Interface['id']),'"';
        ?>><?php

        // .tdz-i-actions
        ?><div class="<?php echo trim('tdz-i-actions '.$Interface::$attrButtonsClass); ?>"><?php
            /*if(count(Tecnodesign_Interface::$urls)>1): ?><a class="tdz-i-a tdz-i--close" href="<?php echo tdz::xmlEscape(array_shift(array_keys(Tecnodesign_Interface::$urls))) ?>"></a><?php endif;*/
            ?><input type="checkbox" id="tdz-i-b-<?php echo $id; ?>" class="tdz-i-switch tdz-i-actions" /><label for="tdz-i-b-<?php echo $id; ?>"><?php
            echo $Interface::$labelActions; ?></label><div class="tdz-i-buttons tdz-i-switched"><?php
                echo $buttons; 
        ?></div></div><?php 

        // .tdz-i-container
        ?><div class="tdz-i-container"><?php 

            ?><div class="tdz-i-summary tdz-i--<?php echo $Interface['action']; ?>"><?php

                if(isset($summary)) {
                    echo $summary;
                    Tecnodesign_App::response('summary', $summary);
                }

                echo tdz::getUser()->getMessage(false, true), (isset($app))?($app):('');

                if(isset($error)): 
                    ?><div class="tdz-error"><?php 
                        if(is_array($error)) {
                            foreach($error as $e) echo '<div class="tdz-i-msg tdz-i-error"><p>', $e, '</p></div>';
                        } else {
                            echo $error; 
                        }
                    ?></div><?php 
                endif;


                if(isset($list)) {
                    // list counter
                    echo '<span class="'.$Interface::$attrCounterClass.'">';
                    if(isset($searchCount)) {
                        if($searchCount<=0) {
                            echo sprintf($Interface::t('listNoSearchResults'), tdz::formatNumber($count,0), $searchTerms);
                        } else if($searchCount==1) {
                            echo sprintf($Interface::t('listSearchResult'), tdz::formatNumber($count,0), $searchTerms);
                        } else { 
                            echo sprintf($Interface::t('listSearchResults'), tdz::formatNumber($searchCount,0), tdz::formatNumber($count,0), $searchTerms);
                        }
                        $count = $searchCount;
                    } else if($count) {
                        echo sprintf($Interface::t(($count>1)?('listResults'):('listResult')), tdz::formatNumber($count,0));
                    } else {
                        echo $Interface::t('listNoResults');
                    }

                    if($count>1) {
                        $end = $listOffset + $listLimit -1;
                        if($end>$count) $end = $count;
                        echo sprintf($Interface::t('listCounter'), tdz::formatNumber($listOffset+1,0), tdz::formatNumber($end,0));
                        unset($end);
                    }
                    echo '</span>';

                    if(isset($searchForm))
                        echo '<input type="checkbox" id="tdz-i-s-'.$id.'" class="tdz-i-switch tdz-i-search" />',
                             '<label for="tdz-i-s-'.$id.'">'.$Interface::$labelFilter.'</label>',
                             '<div class="tdz-i-search tdz-i-switched">'.$searchForm.'</div>';
                }

            ?></div><?php 

            if(isset($list)): 
                ?><div class="<?php echo $Interface::$attrListClass; ?>"><?php
                    if(is_string($list)) {
                        echo $list;
                    } else if($count>0) {
                        $sn = tdz::scriptName(true);
                        tdz::scriptName($Interface->link());
                        echo $list->paginate($listLimit, 'renderUi', array('options'=>$options), $Interface::$listPagesOnTop, $Interface::$listPagesOnBottom);
                        tdz::scriptName($sn);
                        unset($sn);
                    }

                ?></div><?php
            endif;

            if(isset($preview)): 
                ?><div class="<?php echo $Interface::$attrPreviewClass; ?>"><?php
                    if($Interface['action']=='delete') {
                        echo '<div class="i--'.$interface.((isset($class))?(' '.$class):('')).'">';
                    } else {
                        $next = ($Interface['action']=='update')?('preview'):('update');
                        echo '<div data-action-schema="'.$next.'" data-action-url="'.$Interface->link($next).'" class="i--'.$interface.((isset($class))?(' '.$class):('')).'">';
                    }
                    if(is_object($preview) && $preview instanceof Tecnodesign_Model) {
                        $box = $preview::$boxTemplate;
                        $preview::$boxTemplate = $Interface::$boxTemplate;
                        echo $preview->renderScope($options['scope'], $xmlEscape, false, $Interface::$previewTemplate, $Interface::$headingTemplate);
                        $preview::$boxTemplate = $box;
                        unset($preview);
                    } else {
                        echo (string) $preview;
                    }
                    unset($preview);
                    echo '</div>';
                ?></div><?php
            endif;

?></div></div></div>