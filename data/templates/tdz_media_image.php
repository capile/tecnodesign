<?php
/**
 * Default media template
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
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
