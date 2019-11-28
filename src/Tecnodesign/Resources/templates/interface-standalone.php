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
$id = tdz::slug($url);
$link = $url;
if(strpos($url, '?')!==false) list($url, $qs)=explode('?', $url, 2);
else $qs='';

if($title) Tecnodesign_App::response('title', $title);
if(!isset($action)) $action = $Interface['action'];

// .tdz-i
?><div class="tdz-i tdz-i-standalone" data-base-url="<?php echo $Interface->getUrl(); ?>" data-url="<?php echo $url ?>"<?php 
    if($qs) echo ' data-qs="',str_replace(',', '%2C', tdz::xmlEscape($qs)),'"';
    if($Interface['id']) echo ' data-id="',tdz::xmlEscape($Interface['id']),'"';
    ?>><?php

    if($title && $Interface::$breadcrumbs) {
        $urls = Tecnodesign_Interface::$urls;
        if(!$urls) {
            $urls = array(array('title'=>$title));
        } else {
            array_splice($urls,0, 1);
        }
        $b = '';
        foreach($urls as $iurl=>$t) {
            if($iurl && $iurl!=$link) {
                $b .= '<a href="'.$iurl.'">'.tdz::xmlEscape($t['title']).'</a>';
            } else {
                $b .= '<span>'.tdz::xmlEscape($t['title']).'</span>';
            }
        }

        if($b) {
            echo str_replace('$LABEL', $b, $Interface::$headingTemplate);
        }
    }

    if(isset($options['before-'.$Interface['action']])) echo \tdz::markdown($options['before-'.$Interface['action']]);
    else if(isset($options['before'])) echo \tdz::markdown($options['before']);

    ?><div class="tdz-i-summary tdz-i--<?php echo $Interface['action']; ?>"><?php

        if(!$Interface::$standalone && isset($summary)) {
            echo $summary;
            Tecnodesign_App::response('summary', $summary);
        }

        echo $Interface->message(), (isset($app))?($app):('');

        if($buttons): ?><div class="z-standalone-buttons"><?php
            echo $buttons; 
        ?></div><?php endif;

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
                $options['checkbox'] = false;
                $options['radio'] = false;
                $listRenderer = (isset($options['list-renderer']) && $options['list-renderer']) ?$options['list-renderer'] :'renderUi';
                $sn = tdz::scriptName(true);
                tdz::scriptName($Interface->link());
                echo $list->paginate($listLimit, $listRenderer, array('options'=>$options), $Interface::$listPagesOnTop, $Interface::$listPagesOnBottom);
                tdz::scriptName($sn);
                unset($sn);
            }

        ?></div><?php
    endif;

    if(isset($preview)): 
        ?><div class="<?php echo $Interface::$attrPreviewClass; ?>"><?php
            if(is_object($preview) && $preview instanceof Tecnodesign_Model) {
                $box = $preview::$boxTemplate;
                $preview::$boxTemplate = $Interface::$boxTemplate;
                $excludeEmpty=(isset($options['preview-empty'])) ?!$options['preview-empty'] :null;
                $showOriginal=(isset($options['preview-original'])) ?$options['preview-original'] :null;
                echo $preview->renderScope($options['scope'], $xmlEscape, false, $Interface::$previewTemplate, $Interface::$headingTemplate, $excludeEmpty, $showOriginal);
                $preview::$boxTemplate = $box;
                unset($preview);
            } else {
                echo (string) $preview;
            }
            unset($preview);
        ?></div><?php
    endif;

    if(isset($options['after-'.$Interface['action']])) echo \tdz::markdown($options['after-'.$Interface['action']]);
    else if(isset($options['after'])) echo \tdz::markdown($options['after']);

    // .tdz-i-actions
    if(!$Interface::$standalone): ?><div class="<?php echo $Interface::$attrFooterClass; ?>"><div class="tdz-i-buttons"><?php
        echo $buttons; 
    ?></div></div><?php endif;


?></div><?php
