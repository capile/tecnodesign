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
} else if(isset($list) || (isset($preview) && ($preview instanceof Tecnodesign_Model))) {
    // list counter
    if(isset($preview)) {
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
            if(strpos($label, ':') || (substr($fn, 0, 2)=='--' && substr($fn, 0, -2)=='--')) continue;
            if($p=strrpos($fn, ' ')) $fn = substr($fn, $p+1);
            if(is_int($label)) $label = $fn;
            $S[$label]=$fn;
            if(method_exists($cn, $m='preview'.tdz::camelize($fn, true))) $M[$fn]=$m;
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
                $e=array();
                foreach($S as $k=>$c) {
                    $vc = (isset($M[$c]))?($v->{$M[$c]}()):($v[$c]);
                    if($vc!=='' && $vc!==false && $vc!==null) {
                        $e[$k] = str_replace("\t",' ', tdz::raw($vc));
                    } else if(!$nonull) {
                        $e[$k] = null;
                    }
                    unset($k, $c, $vc);
                }
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
    }
    $R += $r;
    $Interface::error(422, $R);
} else if(isset($success)) {
    $R = array('message'=>$success);
    if(isset($status)) {
        $code = $status;
        $R += $r;
        $Interface::error($code, $R);
    }
    $R += $r;
    $r = $R;
}

$m = 'to'.ucfirst($Interface::format());
echo $Interface::$m($r);

