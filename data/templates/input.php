<?php
/**
 * Button form field template
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
 */

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
    $input,
    $err,
    $after;

