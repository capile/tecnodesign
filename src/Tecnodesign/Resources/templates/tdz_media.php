<?php
/**
 * Default media template
 *
 * @package      Studio
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         https://tecnodz.com/
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

