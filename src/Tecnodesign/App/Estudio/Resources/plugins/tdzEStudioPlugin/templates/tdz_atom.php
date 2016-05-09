<?php
/**
 * Atom/RSS News Feed Template
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: tdz_atom.php 554 2011-01-10 16:11:40Z capile $
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
$s .= $i."<id>/e-studio/e/{$id}</id>";
$limit=10;
foreach($entries as $pos=>$entry)
{
  if($pos>=$limit)break;
  $s.= $i.tdzEntries::entryPreview($entry,'tdz_atom_entry');
}
$s .= $i0.'</feed>';
echo $s;
