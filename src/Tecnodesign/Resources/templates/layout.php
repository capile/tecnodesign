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
 * @version   2.4
 */

if((!isset($script) || !$script) && isset($variables['script'])) $script = $variables['script'];
if((!isset($style) || !$style)   && isset($variables['style']))  $style  = $variables['style'];

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
$lang = (tdz::$lang) ?tdz::$lang :'en';
?><!doctype html><html lang="<?php echo $lang ?>"><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /><title><?php if(isset($title)) echo $title ?></title><?php if(isset($meta)) echo $meta; ?><?php if(isset($style)) echo $style; ?></head><body class="no-js"><?php if(isset($data)) echo $data;if(isset($content)) echo $content; ?><?php if(isset($script)) echo $script; ?></body></html>
