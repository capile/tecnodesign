<?php
/**
 * Tecnodesign_Interface generic template
 *
 * PHP version 5.3
 *
 * @category  Interface
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2015 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      https://tecnodz.com
 */
$id = tdz::slug($url);
if(strpos($url, '?')!==false) list($url, $qs)=explode('?', $url, 2);
else $qs='';

// add headers
// add messages
// echo tdz::getUser()->getMessage(false, true), (isset($app))?($app):('');

// add errors
/*
if(isset($error)): 
    ?><div class="tdz-error"><?php 
        if(is_array($error)) {
            foreach($error as $e) echo '<div class="tdz-i-msg tdz-i-error"><p>', $e, '</p></div>';
        } else {
            echo $error; 
        }
    ?></div><?php 
endif;
*/
// add preview
// add summary
/*
if(isset($summary)) {
    echo $summary;
    Tecnodesign_App::response('summary', $summary);
}
*/
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

$m = 'to'.ucfirst($Interface::format());
echo $Interface::$m($r);

