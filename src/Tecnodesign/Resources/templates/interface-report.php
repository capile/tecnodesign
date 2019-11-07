<?php
/**
 * Tecnodesign_Excel template
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
$id = tdz::slug($url);
if(strpos($url, '?')!==false) list($url, $qs)=explode('?', $url, 2);
else $qs='';

$st = $Interface::worker();
$scope = (isset($options['scope']))?($options['scope']):($Interface->scope());
if(!isset($action)) $action = $Interface['action'];
if(isset($scope[$action]) && is_array($scope[$action])) $scope=$scope[$action];

if(!isset($r)) $r=array(
    '{interface}'=>$interface,
    '{title}'=>$title,
    '{action}'=>$action,
    '{model}'=>$model,
    '{count}'=>$count,
    '{now}'=>date('YmdHis'),
);
$r['{columns}'] = count($scope);

$m = (isset($meta) && is_array($meta))?($meta):(array());

$format = $Interface::format();
if($format=='html') {
    $format = 'xlsx';
    $Interface::format($format);
}

if(!isset($m['filename'])) $m['filename']=$interface;
$fname = $m['filename'];
$filename = TDZ_VAR.'/cache/interface-report/'.date('YmdHis', floor(TDZ_TIME)).substr(fmod(TDZ_TIME,1), 1, 5).'-'.$fname;
if(!is_dir(dirname($filename))) mkdir(dirname($filename), 0777, true);
if(!isset($m['title'])) $m['title']=$title;
$R = new Tecnodesign_Excel($m);
foreach($m as $k=>$v) {
    $r['{'.$k.'}'] = $v;
    unset($m[$k], $k, $v);
}
unset($m);
$R->addReplacement($r);
if(isset($stylesheet) && file_exists($S=tdz::getApp()->tecnodesign['document-root'].'/'.$stylesheet)) {
    $R->addStylesheet(file_get_contents($S));
}
unset($S);
if(isset($style) && is_array($style)) {
    $R->addStylesheet($style);
}
if(isset(${'before-report'}) && is_array(${'before-report'})) {
    foreach(${'before-report'} as $k=>$c) {
        $R->addContent($c);
        unset(${'before-report'}[$k], $k, $c);
    }
    unset(${'before-report'});
}
// set parameters: envelope, pretty, fields, etc.
if(isset($list) || (isset($preview) && ($preview instanceof Tecnodesign_Model))) {
    if(isset($preview)) {
        $scope = $preview::columns($scope);
        $total = 1;
        $listOffset = 0;
        $listLimit = 1;
        $list = array($preview);
        $cn = get_class($preview);
        unset($preview);
    } else if($list) {
        $total = (isset($searchCount))?($searchCount):($count);
        $cn = $list->getClassName();
    } else {
        $total = 0;
    }
}
if(isset($worksheet) && $worksheet) {
    if(!is_array($worksheet)) {
        $worksheet = array($worksheet=>array('scope'=>$scope));
    }
    $sheets = array_keys($worksheet);
    $sheets = array_reverse($sheets);
    foreach($sheets as $sheet) {
        $R->sheet($sheet);
        unset($sheet);
    }
    $sheets = array_flip($sheets);
}
if(!isset($worksheet)) $worksheet=false;
$n=0;
while($list || $worksheet) {
    $sheet=null;
    if($st) $Interface::worker(tdz::t('Fetching records.', 'interface'));
    if(isset($worksheet) && $worksheet) {
        foreach($worksheet as $sheet=>$so) {
            if(!is_array($so)) {
                $newscope = $cn::columns($so);
            } else {
                if(isset($so['relation'])) {
                    $rs = (isset($so['scope']))?($so['scope']):($interface);
                    $order = (isset($so['order']))?($so['order']):(null);
                    $f = $Interface['search'];
                    $rcn = $cn::relate($so['relation'], $f);
                    $scope = $rcn::columns($rs);
                    $list = $rcn::find($f,0,$rs,true,$order);
                    if($st) $Interface::worker(tdz::t('Fetching records.', 'interface'));
                    $cn = $rcn;
                } else if(isset($so['scope'])) {
                    $newscope = $cn::columns($so['scope']);
                }
                if(isset($so['style'])) {
                    $R->setStylesheet($so['style']);
                }
            }
            if(isset($newscope) && $newscope!=$scope) {
                $scope = $newscope;
                $order = (is_array($so) && isset($so['order']))?($so['order']):(null);
                $list = $cn::find($Interface['search'],0,$scope,true, $order);
                if($st) $Interface::worker(tdz::t('Fetching records.', 'interface'));
            }
            unset($worksheet[$sheet], $so, $newscope, $order);
            break;
        }
    }
    if($sheet) {
        $R->sheet($n++);
    }
    if($list && $listLimit && $listOffset < $total) {
        $S = array();
        $M = array();
        foreach($scope as $label=>$fn) {
            if(strpos($label, ':') || (is_string($fn) && substr($fn, 0, 2)=='--' && substr($fn, 0, -2)=='--')) continue;
            if(is_array($fn)) {
                $fd = $fn;
                if(isset($fd['bind'])) $fn=$fd['bind'];
                else continue;
                if(isset($fd['credential'])) {
                    if(!isset($U)) $U=tdz::getUser();
                    if(!$U || !$U->hasCredentials($fd['credential'], false)) continue;
                }
                unset($fd['bind']);
                if(isset($fd['label'])) $label = $fd['label'];
            }
            if($p=strrpos($fn, ' ')) $fn = substr($fn, $p+1);
            if(is_int($label)) $label = $fn;
            $S[$label]=$fn;
            if(method_exists($cn, $m='preview'.tdz::camelize($fn, true))) $M[$fn]=$m;
            unset($label, $fn, $p, $m, $fd);
        }
        if($st) $Interface::worker(tdz::t('Fetching records.', 'interface'));
        $R->addContent(array(
            'content'=>array_keys($S),
            'use'=>'.header',
            'position'=>array(1, '+1'),
        ));
        if(is_array($list)) {
            $d = $list;
            $list=null;
        } else {
            $d = ($listLimit==1)?(array($list->getItems($listOffset, $listLimit))):($list->getItems($listOffset, ($listLimit<100)?($listLimit):(100)));
        }
        $o = $listOffset;
        $l = $o + $listLimit;
        while($d && $o<$l) {
            if($st) $Interface::worker(tdz::t('Adding content.', 'interface'));
            foreach($d as $i=>$v) {
                $o++;
                $e=array();
                foreach($S as $k=>$c) {
                    $vc = (isset($M[$c]))?($v->{$M[$c]}()):($v[$c]);
                    if($vc!=='' && $vc!==false && $vc!==null) {
                        $vc = tdz::raw($vc);
                        if(is_array($vc)) $vc = implode("; ", $vc);
                        $e[$k] = $vc;
                    } else {
                        $e[$k] = '';
                    }
                    unset($k, $c, $vc);
                }
                $R->addContent(array(
                    'content'=>$e,
                    'use'=>'.normal',
                    'position'=>array(1, '+1'),
                ));
                unset($e, $d[$i], $i, $v);
            }
            unset($d);
            if($o>=$l) {
                $list=null;
                break;
            }
            tdz::tune(__FILE__.': '.__LINE__);
            $d = $list->getItems($o, ($l-$o<100)?($l-$o):(100));
            if(!$d || count($d)==0) break;
        }
        unset($d);
        $list=null;
    } else {
        break;
    }
}
if($st) $Interface::worker(tdz::t('Final touches.', 'interface'));

if(isset(${'after-report'}) && is_array(${'after-report'})) {
    tdz::tune(__FILE__.': '.__LINE__);
    foreach(${'after-report'} as $k=>$c) {
        $R->addContent($c);
        unset(${'after-report'}[$k], $k, $c);
    }
    unset(${'after-report'});
}
$R->sheet(0);

//Do you need more time to render?
$mem = $sec = 20 + (5 * (ceil($total/1000) - 2));
tdz::tune(__FILE__.': '.__LINE__, $mem, $sec);

if($st) {
    $Interface::worker(tdz::t('Packaging...', 'interface'));
    $download = false;
    $keepFile = true;
    $fname = $filename.'.'.$format;
} else {
    $download=true;
    $keepFile=false;
    $fname .= '.'.$format;
}
$s = $R->render($format, $fname, $download, $keepFile);

if($st) {
    $Interface::worker(tdz::t('Download!', 'interface'), $s);
}
return null;
