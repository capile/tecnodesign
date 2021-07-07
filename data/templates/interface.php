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

if(isset($title)) Tecnodesign_App::response('title', $title);
if(!isset($action)) $action = $Interface['action'];

$nav = null;
if($Interface::$navigation) {
    if(!Tecnodesign_App::request('ajax') || Tecnodesign_App::request('headers', 'z-navigation')) {
        $nav = $Interface::listInterfaces();
    }
}

$a = [
    'class' => 'tdz-i'.((isset($active) && $active) ?' tdz-i-active' :''),
    'data-action'=>$Interface['action'],
    'data-url'=>$url,
];
if($qs) $a['data-qs'] = str_replace(',', '%2C', $qs);
if($Interface['id']) $a['data-id'] = $Interface['id'];
if(isset($ui)) $a['data-ui'] = base64_encode(tdz::serialize($ui, 'json'));

if(isset($attributes) && is_array($attributes)) {
    if(isset($attributes['class'])) $a['class'] .= ' '.tdz::xml($attributes['class']);
    $a += $attributes;
}


// .tdz-i-header
?><div class="tdz-i-header"<?php 
    if($nav) echo ' data-toggler="off"';
    if($Interface::$headerOverflow) echo ' data-overflow="1"';
    echo '>'; 
    if($nav) echo '<a href="'.tdz::xml($Interface::base()).'" class="z-spacer z-left z-nav" data-draggable-style="width:{w0}"></a>';
    $urls = $Interface::$urls;
    if(Tecnodesign_App::request('ajax')) {
        foreach(array_reverse($urls) as $iurl=>$t) {
            if($iurl!='/' && (!isset($t['interface']) || $t['interface'])) {
                $urls = [$iurl=>$t];
                break;
            }
        }
    }
    foreach($urls as $iurl=>$t) {
        if($iurl!='/' && (!isset($t['interface']) || $t['interface'])):
            ?><a href="<?php echo $iurl ?>" class="tdz-i-title<?php $iqs='';if(strpos($iurl, '?')!==false) list($iurl, $iqs)=explode('?', $iurl, 2);if($iurl==$url) echo ' tdz-i-title-active'; echo ' z-i--'.$t['action']; ?>" data-url="<?php echo $iurl ?>"<?php if($iqs) echo 'data-qs="', str_replace(',', '%2C', tdz::xml($iqs)), '"' ?>><span class="z-text"><?php echo tdz::xml($t['title']); ?></span></a><?php
        endif;
    }

?></div><?php

// .tdz-i-body
?><div class="tdz-i-body"><?php

    if($nav) {
        $nclass = 'z-i-nav z-toggle-active';
        echo '<div id="z-nav" data-draggable-style="width:{w0}" data-draggable-default=style="width:{w1}" class="', $nclass, '" data-base-url="', $Interface::base(), '" data-toggler-attribute-target=".tdz-i-header" data-toggler-drag-target=".tdz-i-body" data-toggler-drag=".z-nav,.z-i-nav,.tdz-i.tdz-i-active" data-toggler-options="child,sibling,storage,draggable" data-toggler-default="800">', $nav, '</div>'; 
    }

    // .tdz-i
    ?><div<?php foreach($a as $k=>$v) echo ' '.tdz::slug($k, '-_').'="'.tdz::xml($v).'"'; ?>><?php
        // .z-i-actions
        if(!isset($buttons)) $buttons = null;
        if($buttons): ?><div class="<?php echo trim('z-i-actions '.$Interface::$attrButtonsClass); ?>"><?php
            /*if(count($Interface::$urls)>1): ?><a class="tdz-i-a z-i--close" href="<?php echo tdz::xmlEscape(array_shift(array_keys($Interface::$urls))) ?>"></a><?php endif;*/
            ?><input type="checkbox" id="tdz-i-b-<?php echo $id; ?>" class="tdz-i-switch z-i-actions" /><label for="tdz-i-b-<?php echo $id; ?>"><?php
            echo $Interface::$labelActions; ?></label><div class="tdz-i-buttons tdz-i-switched"><?php
                echo $buttons; 
        ?></div></div><?php endif; 

        // .tdz-i-container
        ?><div class="tdz-i-container"><?php 

            if($title && $Interface::$breadcrumbs) {
                $urls = $Interface::$urls;
                if(!$urls) {
                    $urls = array(array('title'=>$title));
                }
                $b = '';
                $la = ($Interface::$actionAlias && isset($Interface::$actionAlias['list']))?($Interface::$actionAlias['list']):('list');
                foreach($urls as $iurl=>$t) {
                    $ltitle = (isset($t['icon'])) ?'<img src="'.tdz::xml($t['icon']).'" title="'.tdz::xml($t['title']).'" />' :tdz::xml($t['title']);
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

            if(isset($options['before-'.$action])) echo \tdz::markdown($options['before-'.$action]);
            else if(isset($options['before'])) echo \tdz::markdown($options['before']);


            ?><div class="z-i-summary z-i--<?php echo $Interface['action']; ?>"><?php

                if(isset($summary)) {
                    echo $summary;
                    Tecnodesign_App::response('summary', $summary);
                }

                echo $Interface->message(), (isset($app))?($app):('');

                if(isset($list) && ($g=$Interface->renderGraph())):
                    ?><div class="<?php echo $Interface::$attrGraphClass; ?>"><?php
                        echo $g;
                    ?></div><?php
                endif;


                if(isset($list)) {
                    if(isset($searchForm))
                        if(isset($options['before-search-form'])) echo \tdz::markdown($options['before-search-form']);
                        echo '<div class="'.$Interface::$attrSearchClass.'">'.$searchForm.'</div>';
                        if(isset($options['after-search-form'])) echo \tdz::markdown($options['after-search-form']);
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
                        $end = $listOffset + $listLimit;
                        if($end>$count) $end = $count;
                        echo ' ',sprintf($Interface::t('listCounter'), tdz::formatNumber($listOffset+1,0), tdz::formatNumber($end,0));
                        unset($end);
                    }
                    echo '</span>';

                }

            ?></div><?php 

            if(isset($list)): 
                ?><div class="<?php echo $Interface::$attrListClass; ?>"><?php
                    if(is_string($list)) {
                        echo $list;
                    } else if($count>0) {
                        $listRenderer = (isset($options['list-renderer']) && $options['list-renderer']) ?$options['list-renderer'] :'renderUi';
                        $sn = tdz::scriptName(true);
                        tdz::scriptName($Interface->link());
                        if(!is_object($list)) $list = new Tecnodesign_Collection($list, $Interface->getModel());
                        echo $list->paginate($listLimit, $listRenderer, array('options'=>$options), $Interface::$listPagesOnTop, $Interface::$listPagesOnBottom);
                        tdz::scriptName($sn);
                        unset($sn);
                    }

                ?></div><?php
            endif;

            if(isset($preview)): 
                ?><div class="<?php echo $Interface::$attrPreviewClass; ?>"><?php
                    $next = null;
                    if(in_array($Interface['action'], ['update', 'preview', 'new']) && !$Interface::$standalone) {
                        $next = ($Interface['action']=='update')?('preview'):('update');
                        if(!isset($Interface['actions'][$next]) || (isset($Interface['actions'][$next]['auth']) && !$Interface::checkAuth($Interface['actions'][$next]['auth']))) {
                            $next = null;
                        }
                    }
                    if($next) {
                        echo '<div data-action-schema="'.$next.'" data-action-url="'.$Interface->link($next).'" class="i--'.$interface.((isset($class))?(' '.$class):('')).'">';
                    } else {
                        echo '<div class="i--'.$interface.((isset($class))?(' '.$class):('')).'">';
                    }

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
                    echo '</div>';
                ?></div><?php
            endif;


            if(isset($options['after-'.$action])) echo \tdz::markdown($options['after-'.$action]);
            else if(isset($options['after'])) echo \tdz::markdown($options['after']);

            // .z-i-actions
            ?></div><div class="<?php echo $Interface::$attrFooterClass; ?>"><div class="tdz-i-buttons"><?php
                echo $buttons; 
            ?></div></div><?php 

?></div></div>