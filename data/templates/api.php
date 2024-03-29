<?php
/**
 * Api App Template
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

$id = S::slug($url);
$cPrefix = $Interface->config('attrClassPrefix');
$link = $url;
if(strpos($url, '?')!==false) list($url, $qs)=explode('?', $url, 2);
else $qs='';

if(isset($title)) App::response('title', $title);
if(!isset($action)) $action = $Interface['action'];

$nav = null;
if($Interface->config('navigation')) {
    if(!App::request('ajax') || App::request('headers', 'z-navigation')) {
        $nav = $Interface::listInterfaces();
    }
}

$a = [
    'class' => $cPrefix.'-app'.((isset($active) && $active) ?' '.$cPrefix.'-active' :''),
    'data-action'=>$Interface['action'],
    'data-url'=>$url,
];
if($qs) $a['data-qs'] = str_replace(',', '%2C', $qs);
if($Interface['id']) $a['data-id'] = $Interface['id'];
if(isset($ui)) $a['data-ui'] = base64_encode(S::serialize($ui, 'json'));

if(isset($attributes) && is_array($attributes)) {
    if(isset($attributes['class'])) $a['class'] .= ' '.S::xml($attributes['class']);
    $a += $attributes;
}


// .s-api-header
?><div class="s-api-header"<?php 
    if($nav) echo ' data-toggler="off"';
    if($Interface->config('headerOverflow')) echo ' data-overflow="1"';
    echo '>'; 
    if($nav) echo '<a href="'.S::xml($Interface::base()).'" class="z-spacer z-left z-nav" data-draggable-style="width:{w0}"></a>';
    $urls = $Interface::$urls;
    if(App::request('ajax')) {
        foreach(array_reverse($urls) as $iurl=>$t) {
            if($iurl!='/' && (!isset($t['interface']) || $t['interface'])) {
                $urls = [$iurl=>$t];
                break;
            }
        }
    }
    foreach($urls as $iurl=>$t) {
        $tclass = 's-api-title';
        $iqs='';
        if(strpos($iurl, '?')!==false) list($iurl, $iqs)=explode('?', $iurl, 2);
        if($iurl==$url) $tclass .= ' s-api-title-active';
        $taction = (isset($t['action']) && $t['action']) ?$t['action'] :'text';
        $tclass .= ' '.$cPrefix.'--'.$taction;
        if($iurl!='/' && (!isset($t['interface']) || $t['interface'])):
            ?><a href="<?php echo $iurl ?>" class="<?php echo $tclass; ?>" data-url="<?php echo $iurl ?>"<?php if($iqs) echo 'data-qs="', str_replace(',', '%2C', S::xml($iqs)), '"' ?>><span class="z-text"><?php echo S::xml($t['title']); ?></span></a><?php
        endif;
        unset($tclass, $taction);
    }

?></div><?php

// .s-api-body
?><div class="s-api-body"><?php

    if($nav) {
        $nclass = 'z-i-nav z-toggle-active';
        echo '<div id="z-nav" data-draggable-style="width:{w0}" data-draggable-default=style="width:{w1}" class="', $nclass, '" data-base-url="', $Interface::base(), '" data-toggler-attribute-target=".s-api-header" data-toggler-drag-target=".s-api-body" data-toggler-drag=".z-nav,.z-i-nav,.s-api-app.s-api-active" data-toggler-options="child,sibling,storage,draggable" data-toggler-default="800">', $nav, '</div>'; 
    }

    // .s-api-app
    ?><div<?php foreach($a as $k=>$v) echo ' '.S::slug($k, '-_').'="'.S::xml($v).'"'; ?>><?php
        // .z-i-actions
        if(!isset($buttons)) $buttons = null;
        if($buttons): ?><div class="<?php echo trim('z-i-actions '.$Interface->config('attrButtonsClass')); ?>"><?php
            ?><input type="checkbox" id="s-api-b-<?php echo $id; ?>" class="s-switch z-i-actions" /><label for="s-api-b-<?php echo $id; ?>"><?php
            echo $Interface::t('labelActions'); ?></label><div class="s-buttons s-switched"><?php
                echo $buttons; 
        ?></div></div><?php endif; 

        // .s-api-container
        ?><div class="s-api-container"><?php 

            if($title && $Interface->config('breadcrumbs')) {
                $urls = $Interface::$urls;
                if(!$urls) {
                    $urls = array(array('title'=>$title));
                }
                $b = '';
                $la = $Interface->config('actionAlias', 'list');
                if(!$la) $la = 'list';
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
                    echo str_replace('$LABEL', $b, $Interface->config('breadcrumbTemplate'));
                }
            }

            if(isset($options['before-'.$action])) echo S::markdown($options['before-'.$action]);
            else if(isset($options['before'])) echo S::markdown($options['before']);


            ?><div class="z-i-summary <?php echo $cPrefix, '--', $Interface['action']; ?>"><?php

                if(isset($summary)) {
                    echo $summary;
                    App::response('summary', $summary);
                }

                if(isset($app)) echo $app;

                if(isset($list) && ($g=$Interface->renderGraph())):
                    ?><div class="<?php echo $Interface->config('attrGraphClass'); ?>"><?php
                        echo $g;
                    ?></div><?php
                endif;


                if(isset($list)) {
                    if(isset($searchForm))
                        if(isset($options['before-search-form'])) echo S::markdown($options['before-search-form']);
                        echo '<div class="'.$cPrefix.'-search">'.$searchForm.'</div>';
                        if(isset($options['after-search-form'])) echo S::markdown($options['after-search-form']);
                    // list counter
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
                        echo ' ',sprintf($Interface::t('listCounter'), S::number($listOffset+1,0), S::number($end,0));
                        unset($end);
                    }
                    echo '</span>';

                }

            ?></div><?php 

            if(isset($list)): 
                ?><div class="<?php echo $cPrefix, '-list'; ?>"><?php
                    if(is_string($list)) {
                        echo $list;
                    } else if($count>0) {
                        $listRenderer = (isset($options['list-renderer']) && $options['list-renderer']) ?$options['list-renderer'] :'renderUi';
                        $sn = S::scriptName(true);
                        S::scriptName($Interface->link());
                        if(!is_object($list)) {
                            $list = new Tecnodesign_Collection($list, $Interface->getModel());
                        }
                        echo $list->paginate($listLimit, $listRenderer, array('options'=>$options), $Interface->config('listPagesOnTop'), $Interface->config('listPagesOnBottom'));
                        S::scriptName($sn);
                        unset($sn);
                    }

                ?></div><?php
            endif;

            if(isset($preview)): 
                ?><div class="<?php echo $cPrefix, '-preview'; ?>"><?php
                    $next = null;
                    if(in_array($Interface['action'], ['update', 'preview', 'new']) && !$Interface->config('standalone')) {
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

                    if(is_object($preview) && method_exists($preview, 'renderScope')) {
                        $box = $preview::$boxTemplate;
                        $preview::$boxTemplate = $Interface->config('boxTemplate');
                        $excludeEmpty=(isset($options['preview-empty'])) ?!$options['preview-empty'] :null;
                        $showOriginal=(isset($options['preview-original'])) ?$options['preview-original'] :null;
                        echo $preview->renderScope($options['scope'], $xmlEscape, false, $Interface->config('previewTemplate'), $Interface->config('headingTemplate'), $excludeEmpty, $showOriginal);
                        $preview::$boxTemplate = $box;
                        unset($preview);
                    } else {
                        echo (string) $preview;
                    }
                    unset($preview);
                    echo '</div>';
                ?></div><?php
            endif;


            if(isset($options['after-'.$action])) echo S::markdown($options['after-'.$action]);
            else if(isset($options['after'])) echo S::markdown($options['after']);

            // .z-i-actions
            ?></div><div class="<?php echo $cPrefix, '-footer'; ?>"><div class="s-buttons"><?php
                echo $buttons; 
            ?></div></div><?php 

?></div></div>