<?php
/**
 * Form template
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
$fieldsets=array();
$hasFieldset = false;
$fs = '';
foreach($fields as $fn=>$fo) {
    $fs = (string)$fo->fieldset;
    if(!$hasFieldset && $fs) $hasFieldset = true;
    if(!isset($fieldsets[$fs])) $fieldsets[$fs]='';
    $fieldsets[$fs] .= $fo->render();
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
            $fieldsets[$fs] .= $fo->render();
        }
    }
}

if($hasFieldset) {
    $attributes['class'] .= ' z-fieldset';
}

?><form<?php if($id): ?> id="<?php echo $id ?>"<?php endif; ?> action="<?php echo tdz::xmlEscape($action) ?>" method="<?php echo $method ?>"<?php
foreach($attributes as $an=>$av) echo ' '.tdz::xmlEscape($an).'="'.tdz::xmlEscape($av).'"';
?>><?php
foreach($fieldsets as $fn=>$fv) {
    if($fn) {
        $fl = (substr($fn, 0, 1)=='*')?(tdz::t(substr($fn,1), 'form')):($fn);
        echo '<fieldset id="'.tdz::slug($fn).'"><legend>'.$fl.'</legend>'.$before.$fv.'</fieldset>';
    } else {
        echo $before.$fv;
    }
    if($before) $before = '';
}

 ?>
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
    } else if(substr($label,0,1)=='<') {
        echo $label;
    } else {
        echo '<button class="'.$bn.'"'.$a.'>'.$label.'</button>';
    }
}
?></p></form>