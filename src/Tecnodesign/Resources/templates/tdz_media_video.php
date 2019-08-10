<?php
/**
 * Default media template
 *
 * @package      Studio
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         https://tecnodz.com/
 */

$meta = '';
if(is_array($src)) {
    if(isset($title))
        echo "<h3 class=\"video-label\">", tdz::xmlEscape($title), "</h3>";

    echo '<video';
    if(isset($id))
        echo ' id="', tdz::xmlEscape($id), '"';
    if(isset($attributes)) {
        foreach($attributes as $k=>$v)
            echo ' ', tdz::xmlEscape($k), '="', tdz::xmlEscape($v), '"';
    }
    echo '>';

    foreach($src as $i=>$o) {
        echo '<source';
        if(!is_array($o)) {
            echo ' src="', tdz::xmlEscape($o), '"';
            $meta .= '<meta property="og:video" content="'.tdz::xmlEscape($o).'" />';
        } else {
            if(isset($o['src'])) {
                $meta .= '<meta property="og:video" content="'.tdz::xmlEscape($o['src']).'" />';
                if(isset($o['type']))
                    $meta .= '<meta property="og:video:type" content="'.tdz::xmlEscape($o['type']).'" />';
            }
            foreach($o as $k=>$v)
                echo ' ', tdz::xmlEscape($k), '="', tdz::xmlEscape($v), '"';
        }
        echo '></source>';
    }
    if(isset($alt)) 
        echo tdz::xmlEscape($alt);

    echo '</video>';

} else {
    echo '<video src="', tdz::xmlEscape($src), '"';
    if(isset($alt)) 
        echo ' alt="', tdz::xmlEscape($alt), '"';

    if(isset($title))
        echo ' title="', tdz::xmlEscape($title), '"';

    if(isset($id))
        echo ' id="', tdz::xmlEscape($id), '"';

    if(isset($attributes)) {
        foreach($attributes as $k=>$v)
            echo ' ', tdz::xmlEscape($k), '="', tdz::xmlEscape($v), '"';
    }
    echo '></video>';
    $meta .= '<meta property="og:video" content="'.tdz::xmlEscape($src).'" />';
}

tdz::meta('', true);
tdz::meta($meta);
