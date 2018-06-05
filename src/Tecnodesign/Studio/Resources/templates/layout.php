<?php
/**
 * E-Studio default template
 *
 * @package      Studio
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         https://tecnodz.com/
 * @copyright    Tecnodesign (c) 2014
 */
if(!$title && isset(tdz::$variables['variables']['title'])) $title = tdz::$variables['variables']['title'];

if(isset($script)) {
	$js = '';
    if(!is_array($script)) $script = explode(',', $script);
    foreach($script as $k=>$v) {
        if(is_string($k)) {
            $js .= tdz::minify($v, TDZ_DOCUMENT_ROOT, true, true, false, '/_/'.$k.'.js');
            unset($script[$k]);
        }
        unset($k, $v);
    }
    if($script) {
        $js .= tdz::minify($script);
    }
	$nonce = base64_encode(openssl_random_pseudo_bytes(10));
	header("Content-Security-Policy: default-src 'none'; style-src 'self' 'unsafe-inline' https:; img-src 'self' https: data:; font-src 'self'; script-src 'nonce-{$nonce}' 'strict-dynamic' 'self'; form-action 'self'; media-src 'self' *.first.org; connect-src 'self' https://api.first.org; object-src 'none'; frame-src https:; frame-ancestors 'none'; base-uri 'self'");
	$js = str_replace('<script', '<script nonce="'.$nonce.'"', $js);
    $script = $js;
    unset($js);
} else {
	$script = '';
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
} else {
	$style = '';
}

if(!isset($content)) {
    if(isset($data)) $content = $data;
    else $content = '';
}

if(Tecnodesign_App::request('shell')) {
	echo tdz::text($content)."\n";
	return;
}

?><!doctype html><html><head><meta http-equiv="Content-Type" content="text/html;charset=utf-8" /><title><?php if(isset($title)) echo tdz::xmlEscape($title) ?></title><?php echo $style ?><?php if(isset($meta)) echo $meta ?><link rel="icon" type="image/png" href="/favicon.png" /></head><body><?php echo $content ?><?php echo $script ?></body></html>