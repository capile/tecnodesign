<?php
/**
 * Default media template
 *
 * @package      Studio
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         https://tecnodz.com/
 */

if(isset($href))
    echo '<a href="'.tdz::xmlEscape($href).'">';

echo "<img src=", tdz::xmlEscape($src), '"';
if(isset($alt)) 
    echo ' alt="', tdz::xmlEscape($alt), '"';

if(isset($title))
    echo ' title="', tdz::xmlEscape($title), '"';

if(isset($id))
    echo ' id="', tdz::xmlEscape($id), '"';


echo ' />';

if(isset($href)) 
    echo '</a>';
