<?php
/**
 * Subform field template
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */

if(isset(tdz::$variables['form-field-f__'.$id])) {
    $tpl = tdz::$variables['form-field-f__'.$id];
} else if(isset(tdz::$variables['form-field-template'])) {
    $tpl = tdz::$variables['form-field-template'];
} else {
    $tpl = '<div id="f__$ID" class="field $CLASS">$ERROR<p class="label subform">$LABEL</p><div class="input">$INPUT</div></div>';
}

if($error) {
    if(!is_array($error)) $error=array($error);
    $err = '<span class="error">'
          . implode('</span><span class="error">', $error)
          . '</span>';
} else {
    $err = '';
}

$class .= ' i-subform';

echo 
    $before,
    str_replace(
        array('$UID', '$ID', '$CLASS', '$LABEL', '$INPUT', '$ERROR'),
        array($id, preg_replace('/.*_[0-9§]+_/', '', $id), $class, $label, $input, $err),
        $tpl), 
    $after;
