<?php
/**
 * Default media template
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */

if(isset($title)) echo "<h3>", tdz::xmlEscape($title), "</h3>"

} else if($f=='video') {
    $s = '<video src="'.tdz::xmlEscape($code['src']).'"';
    if(isset($code['alt']) && $code['alt']) {
        $s .= ' alt="'.tdz::xmlEscape($code['alt']).'"';
    }
    if(isset($code['title']) && $code['title']) {
        $s .= ' title="'.tdz::xmlEscape($code['title']).'"';
    }
    if(isset($code['id']) && $code['id']) {
        $s .= ' id="'.tdz::xmlEscape($code['id']).'"';
    }
    $s .= ' autobuffer="true" controls="true">alternate part';
    // alternate -- using flash?
    $s .= '</video>';
} else if($f=='flash') {
    $s = '<div src="'.tdz::xmlEscape($code['src']).'"';
    if(isset($code['alt']) && $code['alt']) {
        $s .= ' alt="'.tdz::xmlEscape($code['alt']).'"';
    }
    if(isset($code['title']) && $code['title']) {
        $s .= ' title="'.tdz::xmlEscape($code['title']).'"';
    }
    if(isset($code['id']) && $code['id']) {
        $s .= ' id="'.tdz::xmlEscape($code['id']).'"';
    }
    $s .= ' autobuffer="true" controls="true">alternate part';
    // alternate -- using flash?
    $s .= '</video>';
} else {
    $s = '<p';
    if(isset($code['id']) && $code['id']) {
        $s .= ' id="'.tdz::xmlEscape($code['id']).'"';
    }
    $s .= '><a href="'.tdz::xmlEscape($code['src']).'">';
    $s .= (isset($code['title']) && $code['title'])?(tdz::xmlEscape($code['title'])):(basename($code['src']));
    $s .= '</a></p>';
}
return $s;

