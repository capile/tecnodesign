<?php
/**
 * Studio default layout
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
 */

use Studio as S;
use Studio\App;

if(($accept=App::request('headers', 'accept')) && preg_match('#^(text|application)/json\b#', $accept)) {
    $r = [];
    if(!isset($error)) {
        if(isset($title)) $error = $title;
        else $error = null;
    }
    if($error) $r['error'] = $title;

    if(!isset($message)) {
        if(isset($data)) $message = $data;
        else if(isset($content)) $message = $content;
        else $message = null;
    }

    if($message) {
        $r['message'] = $message;
    }

    S::output($r, 'json');
    exit();
}

if((!isset($script) || !$script || (count($script)==1 && isset($script[700]))) && isset($variables['script'])) $script = ($script) ?$script+$variables['script'] :$variables['script'];
if((!isset($style) || !$style || (count($style)==1 && isset($style[700]))) && isset($variables['style']))  $style  = $variables['style'];

if(isset($script)) {
    $js = '';
    if(!is_array($script)) $script = explode(',', $script);
    foreach($script as $k=>$v) {
        if(is_string($k)) {
            $js .= S::minify($v, S_DOCUMENT_ROOT, true, true, false, S::$assetsUrl.'/'.$k.'.js');
            unset($script[$k]);
        }
        unset($k, $v);
    }
    if($script) {
        $js .= S::minify($script);
    }
    $nonce = base64_encode(openssl_random_pseudo_bytes(10));
    header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline' https:; img-src 'self' https: data:; font-src 'self' data:; script-src 'nonce-{$nonce}' 'strict-dynamic' 'self'; form-action 'self'; media-src 'self'; connect-src 'self'; object-src 'none'; frame-src https:; frame-ancestors 'none'; base-uri 'self'");
    $js = str_replace('<script', '<script nonce="'.$nonce.'"', $js);
    $script = $js;
    unset($js);
}

if(isset($style)) {
    $css = '';
    if(!is_array($style)) $style = explode(',', $style);
    foreach($style as $k=>$v) {
        if(is_string($k)) {
            $css .= S::minify($v, S_DOCUMENT_ROOT, true, true, false, '/_/'.$k.'.css');
            unset($style[$k]);
        }
        unset($k, $v);
    }
    if($style) {
        $css .= S::minify($style);
    }
    $style = $css;
    unset($css);
}

/*
if(!isset($content) && isset($data) && isset($slots) && class_exists('tdzEntry')) {
    $content = '';
    foreach(tdzEntry::$slots as $n=>$v) {
        if($n===tdzEntry::$slot) {
            $content .= ( in_array($n, tdzEntry::$slotElements) ?"<{$n}>{$data}</{$n}>" :"<div id=\"{$n}\">{$data}</div>"  );
            $data = null;
        }
        if(isset($$n)) {
            $content .= $$n;
            $$n = null;
        }
    }
}
*/

?><!doctype html><html lang="<?php echo (S::$lang) ?S::$lang :'en'; ?>"<?php if(isset(S::$variables['html-layout'])) echo ' class="', S::xml(S::$variables['html-layout']), '"';?>><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title><?php if(isset($title)) echo $title ?></title><?php if(isset($meta)) echo $meta; ?><?php if(isset($style)) echo $style; ?></head><body class="no-js"><?php if(isset($data)) echo $data;if(isset($content)) echo $content; ?><?php if(isset($script)) echo $script; ?></body></html>
