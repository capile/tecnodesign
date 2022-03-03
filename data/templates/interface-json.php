<?php
/**
 * Tecnodesign_Interface generic template
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
$id = tdz::slug($url);
if(strpos($url, '?')!==false) list($url, $qs)=explode('?', $url, 2);
else $qs='';

$nonull = (in_array($Interface::format(), array('json', 'xml')));

$r = array();

// set parameters: envelope, pretty, fields, etc.
if(isset($list) && is_array($list)) {
    $r = $list;
} else if(isset($list) || (isset($preview) && (($preview instanceof Tecnodesign_Model)||($preview instanceof Tecnodesign_Form)))) {
    // list counter
    if(isset($preview)) {
        if($preview instanceof Tecnodesign_Form) $preview = $preview->getModel();
        $options['scope'] = $preview::columns($options['scope']);
        $total = 1;
        $listOffset = 0;
        $listLimit = 1;
        $list = false;
        $cn = get_class($preview);
    } else if($list) {
        $total = (isset($searchCount))?($searchCount):($count);
        $cn = $list->getClassName();
    } else {
        $total = 0;
    }
    if(isset($preview) || ($list && $listLimit && $listOffset < $total)) {
        $S = array();
        $M = array();
        foreach($options['scope'] as $label=>$fn) {
            if(is_array($fn)) {
                $fd = $fn;
                if(isset($fn['bind'])) $fn = $fn['bind'];
                else continue;
                if(isset($fd['credential'])) {
                    if(!isset($U)) $U=tdz::getUser();
                    if(!$U || !$U->hasCredentials($fd['credential'], false)) continue;
                }
            } else {
                $fd = null;
            }
            if(substr($fn, 0, 2)=='--' && substr($fn, 0, -2)=='--') continue;
            if($p=strrpos($fn, ' ')) $fn = substr($fn, $p+1);
            if(is_int($label)) $label = $fn;
            $S[$label]=$fn;
            unset($label, $fn, $p, $m);
        }
        if(isset($preview)) {
            $d = array($preview);
            unset($preview);
        } else {
            $d = $list->getItems($listOffset, ($listLimit<100)?($listLimit):(100));
        }
        $o = $listOffset;
        $l = $o + $listLimit;
        while($d && $o<$l) {
            foreach($d as $i=>$v) {
                $o++;
                $e=array_filter($v->asArray($S, null, true));
                if(isset($key)) {
                    $r[$v[$key]] = $e;
                } else {
                    $r[] = $e;
                }
                unset($e, $d[$i], $i, $v);
            }
            unset($d);
            if($o>=$l) break;
            $d = $list->getItems($o, ($l-$o<100)?($l-$o):(100));
        }
        unset($d);
    }

    if(!$list && $r) $r = array_shift($r);
} else if(isset($response)) {
    $r = $response;
}

if(isset($error) && $error) {
    $R = array('error'=>$error);
    if(isset($errorMessage)) {
        $R['message'] = $errorMessage;
        if(!$Interface::$envelope) {
            $Interface::$headers[$Interface::H_MESSAGE] = $errorMessage;
        }
    }
    $R += $r;
    $Interface::error(422, $R);
} else if(isset($success)) {
    if($Interface::$envelope) {
        $R = array('message'=>$success);
    } else {
        $R = array();
        $Interface::$headers[$Interface::H_MESSAGE] = $success;
    }
    if(isset($status)) {
        $code = $status;
        $R += $r;
        $Interface::error($code, $R);
    }
    $R += $r;
    $r = $R;
}

if($Interface::$schema) {
    if(is_string($Interface::$schema)) {
        $schema=tdz::buildUrl($Interface::$schema);
    } else {
        $qs = null;
        if($p=Tecnodesign_App::request('get', $Interface::REQ_ENVELOPE)) {
            $qs = '?'.$Interface::REQ_ENVELOPE.'='.var_export((bool)$Interface::$envelope, true);
        }
        $schema=tdz::buildUrl($Interface->link('schema')).$qs; // add scope/action to link
    }
    header('link: <'.$schema.'> rel=describedBy');
    if(isset($ret) && isset($add)) $ret = $add + $ret;
}

if($env=$Interface->config('envelopeAttributes')) {
    $offset = $listOffset;
    $limit = $listLimit;
    foreach($env as $i=>$o) {
        $n = (is_int($i)) ?$o :$i;
        if(isset($$o)) $Interface::$headers[$n] = $$o;
        unset($env[$i], $i, $o, $n);
    }
}

$m = 'to'.ucfirst($Interface::format());
echo $Interface::$m($r);

