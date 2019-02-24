<?php
/**
 * Form template
 *
 * PHP version 5.2
 *
 * @category  Form
 * @package   Form
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: form.php 1132 2012-12-07 13:25:05Z capile $
 * @link      http://tecnodz.com/
 */
$fieldsets=array();
$hasFieldset = false;
foreach($fields as $fn=>$fo) {
    $fs = (string)$fo->fieldset;
    if(!$hasFieldset && $fs) $hasFieldset = true;
    if(!isset($fieldsets[$fs])) $fieldsets[$fs]='';
    $fieldsets[$fs] .= $fo->render();
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
        echo '<fieldset id="'.tdz::slug($fn).'"><legend>'.$fl.'</legend>'.$fv.'</fieldset>';
    } else {
        echo $fv;
    }
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