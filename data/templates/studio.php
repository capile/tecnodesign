<?php
/**
 * Tecnodesign default layout
 * 
 * PHP version 7+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */

if(isset($script)) {
    $js = '';
    if(!is_array($script)) $script = explode(',', $script);
    foreach($script as $k=>$v) {
        if(is_string($k)) {
            $js .= tdz::minify($v, TDZ_DOCUMENT_ROOT, true, true, false, tdz::$assetsUrl.'/'.$k.'.js');
            unset($script[$k]);
        }
        unset($k, $v);
    }
    if($script) {
        $js .= tdz::minify($script);
    }
    $nonce = base64_encode(openssl_random_pseudo_bytes(10));
    header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline' https:; img-src 'self' https: data:; font-src 'self' data:; script-src 'nonce-{$nonce}' 'strict-dynamic'; form-action 'self'; media-src 'self'; connect-src 'self'; object-src 'none'; frame-src https:; frame-ancestors 'none'; base-uri 'self'");
    $js = str_replace('<script', '<script nonce="'.$nonce.'"', $js);
    $script = $js;
    unset($js);
}

if(isset($style)) {
    $css = '';
    if(!is_array($style)) $style = explode(',', $style);
    foreach($style as $k=>$v) {
        if(is_string($k)) {
            $css .= tdz::minify($v, TDZ_DOCUMENT_ROOT, true, true, false, '/_/'.$k.'.css');
            unset($style[$k]);
        }
        unset($k, $v);
    }
    if($style) {
        $css .= tdz::minify($style);
    }
    $style = $css;
    unset($css);
}


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


$App = tdz::getApp();
if(!($lang=$App->config('app', 'language')) && ($langs=$App->config('app', 'languages'))) {
    $lang = tdz::$lang;
    $sn = tdz::scriptName(true);
    $ls = '<div class="s-language-selector">';
    foreach($langs as $k=>$n) {
        $ln = (is_int($k)) ?$n :$k;
        $ls .= ($n==$lang) ?'<span><strong>'.tdz::xml($ln).'</strong></span>' :'<span><a href="'.tdz::xml($sn.'?!'.$n).'">'.tdz::xml($ln).'</a></span>';
        unset($k, $n);
    }
    $ls .= '</div>';
    if(!isset($data)) $data = $ls;
    else $data = $ls.$data;
    unset($ls, $sn);
}
unset($App);

$lang = (tdz::$lang) ?tdz::$lang :'en';
?><!doctype html><html lang="<?php echo $lang ?>"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title><?php if(isset($title)) echo $title ?></title><?php if(isset($meta)) echo $meta; ?><?php if(isset($style)) echo $style; ?></head><body class="no-js"><?php if(isset($data)) echo $data;if(isset($content)) echo $content; ?><?php if(isset($script)) echo $script; ?></body></html>
