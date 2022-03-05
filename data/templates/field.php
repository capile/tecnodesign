<?php
/**
 * Form field template
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

if(!isset($type)) $type='text';

if(isset(S::$variables['form-field-f__'.$id])) {
    $tpl = S::$variables['form-field-f__'.$id];
} else if($type=='hidden') {
    $tpl = ($label && $class)?('<div id="f__$ID" class="field field-hidden $CLASS"><p class="label">$LABEL</p>$INPUT$ERROR</div>'):('$INPUT$ERROR');
} else if(isset(S::$variables['form-field-template'])) {
    $tpl = S::$variables['form-field-template'];
} else if(strpos($input, '<div')!==false) {
    $tpl = '<div id="f__$ID" class="field $CLASS"><p class="label">$LABEL</p><div class="input">$INPUT</div>$ERROR</div>';
} else {
    $tpl = '<p id="f__$ID" class="field $CLASS"><label for="$UID"><span class="label">$LABEL</span><span class="input">$INPUT</span></label>$ERROR</p>';
}

if($error) {
    if(!is_array($error)) $error=array($error);
    $err = '<span class="error">'
          . implode('</span><span class="error">', $error)
          . '</span>';
} else {
    $err = '';
}

echo 
    $before,
    str_replace(
        array('$UID', '$ID', '$CLASS', '$LABEL', '$INPUT', '$ERROR'),
        array($id, preg_replace('/.*_[0-9§]+_/', '', $id), $class, $label, $input, $err),
        $tpl), 
    $after;

