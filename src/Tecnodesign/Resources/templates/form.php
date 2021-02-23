<?php
/**
 * Form template
 * 
 * PHP version 7
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
$fieldsets=array();
$hasFieldset = false;
$fs = '';
foreach($fields as $fn=>$fo) {
    if((string)$fo->fieldset!=='1') $fs = (string)$fo->fieldset;
    if(!$hasFieldset && $fs) $hasFieldset = true;

    if(!$fs) {
        $fieldsets[] = $fo->render();
    } else {
        if(!isset($fieldsets[$fs])) $fieldsets[$fs]='';
        $fieldsets[$fs] .= $fo->render();
    }
}

if(!isset($before)) $before = '';
if(isset($limits) && $limits) {
    if(isset($limits['error']) && $limits['error']) {
        $before .= '<p class="tdz-i-msg tdz-i-error">'.tdz::xml($limits['error']).'</p>';
    } else if(isset($limits['warn']) && $limits['warn']) {
        $before .= '<p class="tdz-i-msg tdz-i-warn">'.tdz::xml($limits['warn']).'</p>';
    }
    if(isset($limits['fields'])) {
        foreach($limits['fields'] as $fn=>$fo) {
            if(isset($fo->fieldset)) {
                $fs = (string) $fo->fieldset;
                if(!$hasFieldset && $fs) $hasFieldset = true;
            }
            if(!$fs) {
                $fieldsets[] = $fo->render();
            } else {
                if(!isset($fieldsets[$fs])) $fieldsets[$fs]='';
                $fieldsets[$fs] .= $fo->render();
            }
        }
    }
}

if($hasFieldset) {
    $attributes['class'] .= ' z-fieldset';
}

?><form<?php if($id): ?> id="<?php echo $id ?>"<?php endif; ?> action="<?php echo tdz::xmlEscape($action) ?>" method="<?php echo $method ?>"<?php
if(isset($Form) && $Form) $attributes = $Form->attributes;
foreach($attributes as $an=>$av) echo ' '.tdz::xmlEscape($an).'="'.tdz::xmlEscape($av).'"';
?>><?php

foreach($fieldsets as $fn=>$fv) {
    if(!$fv) continue;
    if(!is_int($fn) && !tdz::isempty($fn)) {
        $fl = (substr($fn, 0, 1)=='*')?(tdz::t(substr($fn,1), 'form')):($fn);
        echo '<fieldset id="'.tdz::slug($fn).'"><legend>'.$fl.'</legend>'.$before.$fv.'</fieldset>';
    } else {
        echo $before.$fv;
    }
    if($before) $before = '';
}

if(isset($limits['recaptcha']) && $limits['recaptcha']) {
    $rc = $limits['recaptcha'];
    $rckey = null;
    if(is_array($rc) && isset($rc['site-key'])) $rckey = $rc['site-key'];
    else if(isset(tdz::$variables['recaptcha-site-key'])) $rckey = tdz::$variables['recaptcha-site-key'];
    if($rckey) {
        if(!isset($rc['submit']) || !$rc['submit']) {
            echo '<div class="z-recaptcha" data-sitekey="'.tdz::xml($rckey).'"></div>';
        } else if(isset($buttons['submit'])) {
            echo '<script src="https://www.google.com/recaptcha/api.js"></script>';
            if(!is_array($buttons['submit'])) $buttons['submit'] = ['label'=>$buttons['submit'], 'attributes'=>[]];
            else if(!isset($buttons['submit']['attributes'])) $buttons['submit']['attributes'] = [];

            if(isset($buttons['submit']['attributes']['class'])) $buttons['submit']['attributes']['class'] .= ' g-recaptcha';
            else $buttons['submit']['attributes']['class'] = 'g-recaptcha';
            $buttons['submit']['attributes']['data-sitekey'] = tdz::xml($rckey);
        }
    }
}

if($buttons): ?>
<p class="ui-buttons"><?php
foreach($buttons as $bn=>$label) {
    $bt = ($bn=='submit')?('submit'):('button');
    $a = ' type="'.$bt.'"';
    if(is_array($label)) {
        if(isset($label['attributes'])) {
            foreach($label['attributes'] as $n=>$v) {
                $a .= ' '.tdz::xmlEscape($n).'="'.tdz::xmlEscape($v).'"';
                unset($label['attributes'][$n], $n, $v);
            }
        }
        if(isset($label['label'])) $label = $label['label'];
        else $label = $bn;
    }
    if(substr($label, 0, 1)=='*') {
        $label = tdz::t(substr($label, 1), 'form');
    }
    if(strpos($bn, '/')!==false) {
        echo '<a href="'.$bn.'">'.$label.'</a>';
    } else if($bn!=='submit' && preg_match('#\<(a|button|input)[\s/]#', $label)) {
        echo $label;
    } else {
        echo '<button class="'.$bn.'"'.$a.'>'.$label.'</button>';
    }
}
?></p><?php endif; ?></form>