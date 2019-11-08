<?php
/**
 * Atom/RSS News Feed Template
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
$indent=true;$i='';$i0='';
if($indent){$i0="\n";$i="\n ";}
$s ='<'.'?xml version="1.0" encoding="utf-8"?'.'>';
$s .= $i0.'<feed xmlns="http://www.w3.org/2005/Atom">';
$s .= $i.'<title type="html">'.tdz::xmlEscape($title).'</title>';
$s .= $i.'<subtitle type="html">'.tdz::xmlEscape($summary).'</subtitle>';
$s .= $i.'<link rel="self" type="application/atom+xml" href="'.tdz::xmlEscape(tdz::buildUrl($link)).'" />';
$mod = strtotime($updated);
if(!$mod)$mod=time();
$s .= $i.'<updated>'.date('c',$mod).'</updated>';
$s .= $i."<id>e-studio-e-{$id}</id>";
$limit=10;
$i=1;
$entries = $entry->getEntries();
if($entries && $entries->count()>0) {
    $entries->setQuery(false);
    foreach($entries as $entry) {
        $s .= $entry->renderEntry('tdz_atom_entry');
        if(isset($limit) && $i++>=$limit)break;
        else if(!isset($limit))$i++;
    }
}
echo $s;
