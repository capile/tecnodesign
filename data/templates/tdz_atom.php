<?php
/**
 * Atom/RSS News Feed Template
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */


$mod = strtotime($updated);
$limit=10;
$i=1;
$s = '';
if($entries = $entry->getChildren(['type'=>'entry'], 'feed', false, ['published'=>'desc', 'title'=>'asc'], null, $limit *2)) {
    foreach($entries as $entry) {
        $es = $entry->renderEntry('tdz_atom_entry');
        if(!$es) continue;
        $s .= $es;
        $emod = strtotime($entry->updated);
        if($emod && $emod > $mod) $mod = $emod;
        if(isset($limit) && $i++>=$limit)break;
        else if(!isset($limit))$i++;
    }
}
if(!$mod)$mod=time();

$indent=true;$i='';$i0='';
if($indent){$i0="\n";$i="\n ";}

$s = '<'.'?xml version="1.0" encoding="utf-8"?'.'>'
   . $i0.'<feed xmlns="http://www.w3.org/2005/Atom">'
   . $i.'<title type="html">'.tdz::xml($title).'</title>'
   . ((isset($summary)) ?$i.'<subtitle type="html">'.tdz::xmlEscape($summary).'</subtitle>' :'')
   . $i.'<link rel="self" type="application/atom+xml" href="'.tdz::xmlEscape(tdz::buildUrl($link)).'" />'
   . $i.'<updated>'.date('c',$mod).'</updated>'
   . $i."<id>studio-e-{$id}</id>"
   . $s
   . $i0.'</feed>';
Tecnodesign_App::response('headers', ['content-type'=>'application/xml']);
echo $s;
