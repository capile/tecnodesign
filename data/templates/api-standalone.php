<?php
/**
 * Standalone API/App
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
 */

use Studio as S;
use Studio\App;
use Studio\Model;
use Tecnodesign_Form as Form;

$id = S::slug($url);
$cPrefix = $Interface->config('attrClassPrefix');
$link = $url;
if(strpos($url, '?')!==false) list($url, $qs)=explode('?', $url, 2);
else $qs='';

if($title) App::response('title', $title);
if(!isset($action)) $action = $Interface['action'];

// .s-api-app
?><div class="<?php echo $cPrefix ?>-standalone" data-base-url="<?php echo $Interface->getUrl(); ?>" data-url="<?php echo $url ?>"<?php 
    if($qs) echo ' data-qs="',str_replace(',', '%2C', S::xml($qs)),'"';
    if($Interface['id']) echo ' data-id="',S::xml($Interface['id']),'"';
    ?>><?php

    if($title && $Interface::$breadcrumbs) {
        $urls = $Interface::$urls;
        if(!$urls) {
            $urls = array(array('title'=>$title));
        } else {
            array_splice($urls,0, 1);
        }
        $b = '';
        $la = ($Interface::$actionAlias && isset($Interface::$actionAlias['list']))?($Interface::$actionAlias['list']):('list');
        foreach($urls as $iurl=>$t) {
            $ltitle = (isset($t['icon'])) ?'<img src="'.S::xml($t['icon']).'" title="'.S::xml($t['title']).'" />' :S::xml($t['title']);
            if($iurl && $iurl!=$link && !($t['title']==$title && $link=$iurl.'/'.$la)) {
                $b .= '<a href="'.$iurl.'">'.$ltitle.'</a>';
            } else {
                $b .= '<span>'.$ltitle.'</span>';
                break;
            }
        }

        if($b) {
            echo str_replace('$LABEL', $b, $Interface::$breadcrumbTemplate);
        }
    }

    if(isset($options['before-'.$Interface['action']])) echo S::markdown($options['before-'.$Interface['action']]);
    else if(isset($options['before'])) echo S::markdown($options['before']);
    $content = false;

    ?><div class="<?php echo $cPrefix, '-summary ', $cPrefix, '--', $Interface['action']; ?>"><?php

        if(!$Interface::$standalone && isset($summary)) {
            echo $summary;
            App::response('summary', $summary);
        }

        echo $Interface->message(), (isset($app))?($app):('');

        if($buttons && $Interface::$listPagesOnTop): ?><div class="<?php echo $cPrefix ?>-standalone-buttons"><?php
            echo $buttons; 
        ?></div><?php endif;

        if(isset($list)) {
            // list counter
            if(isset($searchForm)) {
                if(isset($options['before-search-form'])) echo S::markdown($options['before-search-form']);
                echo '<div class="'.$Interface->config('attrSearchClass').'">'.$searchForm.'</div>';
                if(isset($options['after-search-form'])) echo S::markdown($options['after-search-form']);
                $content = true;
            }

            echo '<span class="'.$Interface->config('attrCounterClass').'">';
            if(isset($searchCount)) {
                if($searchCount<=0) {
                    echo sprintf($Interface::t('listNoSearchResults'), S::number($count,0), $searchTerms);
                } else if($searchCount==1) {
                    echo sprintf($Interface::t('listSearchResult'), S::number($count,0), $searchTerms);
                } else { 
                    echo sprintf($Interface::t('listSearchResults'), S::number($searchCount,0), S::number($count,0), $searchTerms);
                }
                $count = $searchCount;
            } else if($count) {
                echo sprintf($Interface::t(($count>1)?('listResults'):('listResult')), S::number($count,0));
            } else {
                echo $Interface::t('listNoResults');
            }

            if($count>1) {
                $end = $listOffset + $listLimit;
                if($end>$count) $end = $count;
                echo sprintf($Interface::t('listCounter'), S::number($listOffset+1,0), S::number($end,0));
                unset($end);
            }
            echo '</span>';
        }

    ?></div><?php 

    if(isset($list)): 
        ?><div class="<?php echo $cPrefix, '-list'; ?>"><?php
            if(is_string($list)) {
                echo $list;
                $content = true;
            } else if($count>0) {
                $options['checkbox'] = false;
                $options['radio'] = false;
                if($key=$Interface['key']) $options['key'] = $key;
                $listRenderer = (isset($options['list-renderer']) && $options['list-renderer']) ?$options['list-renderer'] :'renderUi';
                $listOptions = (isset($options['list-options']) && is_array($options['list-options'])) ?$options['list-options'] +$options :$options;
                $sn = S::scriptName(true);
                S::scriptName($Interface->link());
                echo $list->paginate($listLimit, $listRenderer, array('options'=>$listOptions), $Interface->config('listPagesOnTop'), $Interface->config('listPagesOnBottom'));
                S::scriptName($sn);
                unset($sn);
                $content = true;
            }

        ?></div><?php
    endif;

    if(isset($preview)): 
        ?><div class="<?php echo $cPrefix; ?>-preview"><?php
            if(is_object($preview) && method_exists($preview, 'renderScope')) {
                $box = $preview::$boxTemplate;
                $preview::$boxTemplate = $Interface::$boxTemplate;
                $excludeEmpty=(isset($options['preview-empty'])) ?!$options['preview-empty'] :null;
                $showOriginal=(isset($options['preview-original'])) ?$options['preview-original'] :null;
                echo $preview->renderScope($options['scope'], $xmlEscape, false, $Interface::$previewTemplate, $Interface::$headingTemplate, $excludeEmpty, $showOriginal);
                $preview::$boxTemplate = $box;
                unset($preview);
            } else if(is_object($preview) && $preview instanceof Form) {
                if($buttons && $Interface::$listPagesOnTop) {
                    $preview->buttons[] = $buttons;
                    $buttons = null;
                }
                echo (string) $preview;
            } else {
                echo (string) $preview;
            }
            unset($preview);
            $content = true;
        ?></div><?php
    endif;

    // .z-i-actions
    if($buttons && $content && $Interface::$listPagesOnBottom): ?><div class="s-api-standalone-buttons"><?php
        echo $buttons; 
    ?></div><?php endif;

    if(isset($options['after-'.$Interface['action']])) echo S::markdown($options['after-'.$Interface['action']]);
    else if(isset($options['after'])) echo S::markdown($options['after']);

?></div><?php
