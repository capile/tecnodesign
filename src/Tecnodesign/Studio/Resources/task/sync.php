<?php
/*
---
title: Page and Contents Synchronization
description: Dumps or loads contents between database and files.
arguments:
  source: 
    short-version: s
    required: false
    description: Where to load contents from
    type: dir
  force:
    short-version: f
    required: false
    description: Force update even if timestamps are equal
    type: bool
  dump:
    short-version: d
    required: false
    description: Dumps all contents from database to files
    type: bool
---
*/

$source = tdzEntry::file(tdzEntry::$pageDir, false);
if(!Tecnodesign_Studio::$index || !$source) return false;
if(Tecnodesign_Cache::get('indexing')) return false;
Tecnodesign_Cache::set('indexing', $t=time(), 20);

$db=TDZ_VAR.'/'.Tecnodesign_Studio::$index;
tdz::log('Indexing static content... '.$db);
if(!file_exists($db) || $force) {
    $tmpdb = tempnam(dirname($db), basename($db));
    Tecnodesign_Studio::indexDb($tmpdb);
    $conn = tdz::connect('studio');
    tdz::setConnection('', $conn);
    Tecnodesign_Studio_Install::upgrade();
} else {
    Tecnodesign_Studio::indexDb($db);
    $conn = tdz::connect('studio');
    tdz::setConnection('', $conn);
}

tdz::$database = array('studio'=>tdz::$database['studio']) + tdz::$database;
if(substr($source, -1)=='/') $source = substr($source, 0, strlen($source)-1);
$l = strlen($source);
$S = glob($source.'/*');
$pdir = '/';
$E = array();
$C = array();
$I = array();
tdz::log('start!');
while($S) {
    $f = array_shift($S);
    if(!file_exists($f)) continue;
    if(isset($I[$f])) {
        tdz::log('opa!!!!!!', $f, $S);
    }
    $I[$f]=true;
    tdz::log('scanning... '.$f. ' next: '.$S[0]);  

    foreach(Tecnodesign_Studio::$indexIgnore as $p) {
        if(substr($f, -1*strlen($p))==$p) {
            unset($f, $p);
            break;
        }
        unset($p);
    }
    if(!isset($f)) {
        tdz::log('opa1');
        continue;
    }

    if(is_dir($f)) {
        $S = array_merge($S, glob($f.'/*'));
        unset($f);
        continue;
    } else if(is_link($f) || substr($b=basename($f), 0, 6)=='_tpl_.' || substr($b, 0, 1)=='.') {
        // skip symbolic links and template files
        unset($f);
        tdz::log('opa3');
        continue;
    }
    $u = substr($f, $l);
    $page = false;
    $ct = 'media';
    $d = strrpos($b, '.');
    $e = ($d)?(substr($b, $d+1)):('');
    if($e) {
        if(is_array(tdzContent::$disableExtensions) && in_array($e, tdzContent::$disableExtensions)) {
            unset($f, $e, $b, $page);
            continue;
        }
        if(isset(tdzContent::$contentType[$e])) {
            $ct = $e;
            $page = true;
            /*
            if(preg_match('/\..*\./', $b)) {
                $page = false;
            }
            */
            $url = preg_replace('/\..+/', '', $b);
            if($url==tdzEntry::$indexFile) $url = '';
            $u = dirname($u);
            if($u!='/') $u .= '/';
            $u .= $url;
            unset($url);
        }
    }
    if(!isset($E[$u])) {
        $E[$u] = tdzEntry::findPage($u, false, true);
    }
    if($page && $E[$u]) {
        $c = Tecnodesign_Studio::content($f, false, false);
        if($c) {
            $C[$u][$c->position] = $c;
        }
    }
    unset($f, $e, $b, $page, $c);

    // save once dir changes or at the end
    if($pdir!=dirname($u) || !$S) {
        $pdir = dirname($u);
        tdz::log('saving...'.$pdir);
        foreach($E as $u=>$n) {
            tdz::log('indexing '.$u);
            if(!$n) continue;
            $o = tdzEntry::find(array('source'=>$n->source),1);
            $save = false;
            if($o) {
                $E[$u] = $o;
                foreach($n->asArray() as $k=>$v) {
                    if($E[$u]->$k!=$v) {
                        $save = true;
                        $E[$u]->$k=$v;
                    }
                }
            }
            if(!$o || $force) {
                if(!$E[$u]->created) {
                    $E[$u]->isNew(true);
                    $save = true;
                }
            }
            if($save) $E[$u]->save();
            unset($o, $n, $save);

            // save contents
            if(isset($C[$u])) {
                foreach($C[$u] as $id=>$c) {
                    $o = tdzContent::find(array('position'=>$id),1);
                    $save = false;
                    if($o) {
                        $C[$u][$id] = $o;
                        foreach($c->asArray() as $k=>$v) {
                            if($C[$u][$id]->$k!=$v) {
                                $save = true;
                                $C[$u][$id]->$k=$v;
                            }
                        }
                    }
                    if(!$o || $force) {
                        if(!$C[$u][$id]->created) {
                            $C[$u][$id]->isNew(true);
                            $save = true;
                        }
                    }
                    if($C[$u][$id]->entry!=$E[$u]->id) {
                        $C[$u][$id]->entry=$E[$u]->id;
                        $save = true;
                    }
                    if($save) {
                        $C[$u][$id]->pageFile = null;
                        $C[$u][$id]->save();
                    }
                    unset($C[$u][$id], $o, $c, $id, $save);
                }
            }
            // remove from memory
            unset($E[$u], $C[$u], $u);
        }
        //$E=array();
        //$C=array();
    }
    tdz::log(count($S).' remaining...');
}
tdz::log('end???');


if(isset($tmpdb)) rename($tmpdb, $db);
Tecnodesign_Cache::delete('indexing', true);

