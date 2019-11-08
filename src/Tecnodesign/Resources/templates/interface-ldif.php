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

$format = 'text/directory';
$loopLimit = 100;
$flush = false;
// add headers
// add messages
// echo tdz::getUser()->getMessage(false, true), (isset($app))?($app):('');

$nonull = true;

$r = '';

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
            $d = $list->getItems($listOffset, ($listLimit<$loopLimit)?($listLimit):($loopLimit));
        }

        $prop = array('dn', 'dc', 'ou', 'cn', 'objectClass');
        if(isset($cn::$schema['scope']['ldap'])) {
            $prop = $cn::$schema['scope']['ldap'];
        }


        $o = $listOffset;
        $l = $o + $listLimit;
        while($d && $o<$l) {
            foreach($d as $i=>$v) {
                $o++;
                $e=array();
                foreach($prop as $pk=>$pm) {
                    if(method_exists($v, $m='get'.tdz::camelize($pm, true))) {
                        $e[$pm] = $v->$m();
                    } else if(property_exists($v, $pm)) {
                        $e[$pm] = $v::$$pm;
                    } else if(strpos($pm, ': ')) {
                        $e[] = $pm;
                    } else {
                        unset($prop[$pk]);
                    }
                }
                foreach($S as $k=>$c) {
                    $vc = (isset($M[$c]))?($v->{$M[$c]}()):($v[$c]);
                    if($vc!=='' && $vc!==false && $vc!==null) {
                        $e[$k] = str_replace("\t",' ', tdz::raw($vc));
                    } else if(!$nonull) {
                        $e[$k] = null;
                    }
                    unset($k, $c, $vc);
                }

                $e = $Interface::ldif($e);

                if(isset($key)) {
                    $r .= "\n# {$v[$key]}{$e}\n";
                } else {
                    $r .= "{$e}\n";
                }
                unset($e, $d[$i], $i, $v);
            }
            unset($d);
            if($o>=$l) break;
            if(!$flush) {
                $Interface::headers();
                header('content-type: '.$format.';charset=utf8');
                $flush = true;
            }
            echo $r;
            $r = '';
            tdz::flush(false);
            $d = $list->getItems($o, ($l-$o<$loopLimit)?($l-$o):($loopLimit));
            \tdz::tune();
        }
        unset($d);
    }
    $r = ltrim($r);
    if($flush) {
        echo $r;
        $r = '';
        tdz::flush(true);
        Tecnodesign_App::end();
    }

} else if(isset($response)) {
    $r = $response;
}

if(isset($error) && $error) {
    $R = "# error: ".str_replace("\n", "\n# ", $error)."\n";
    if(isset($errorMessage)) {
        $R .= "# message: ".str_replace("\n", "\n# ", $errorMessage)."\n";
    }
    $R .= $r;
    $Interface::error(422, $R);
} else if(isset($success)) {
    $R = "# ".str_replace("\n", "\n# ", $success)."\n";
    if(isset($status)) {
        $code = $status;
        $R .= $r;
        $Interface::error($code, $R);
    }
    $R .= $r;
} else {
    $R = $r;
}

$Interface::headers();
tdz::output($R, $format);

