#!/usr/bin/env php
<?php

/**
 * Meta updater
 */

$meta = <<<EOF
PHP version 5.6+

@package   capile/tecnodesign
@author    Tecnodesign <ti@tecnodz.com>
@license   GNU General Public License v3.0
@link      https://tecnodz.com
@version   2.3
EOF;

$remove = '/^(@(package|version|author|copyright|license|link|version|category)|PHP version)/';

$DIR = dirname(dirname(dirname(dirname(__FILE__))));

applyMeta($DIR, $meta, $remove);


function applyMeta($d, $meta, $remove=null, $skip=['tests', 'vendor'])
{
    if(is_dir($d)) {
        foreach(glob($d.'/*') as $f) {
            $b = preg_replace('#.*/([^/]+)$#', '$1', $f);
            if($b && $b!=='..' && $b!='.' && !in_array($b, $skip)) {
                if(is_dir($f) || substr($b, -4)==='.php') applyMeta($f, $meta, $remove);
            }
        }
        return;
    }
    $a = file($d);
    $m = [];
    $m0 = $m1 = null;
    $i = 0;
    if(isset($a[$i]) && $a[$i]==="<?php\n") {
        while($i++<100) {
            if(!isset($a[$i])) {
                break;
            } else if(!$m0) {
                if($a[$i]==="/**\n") $m0 = $i;
            } else if(strpos($a[$i], '*/')===false) {
                $s = preg_replace('/^ *\* ?|\s+$/', '', $a[$i]);
                if(!$remove || !preg_match($remove, $s)) {
                    $m[] = rtrim($s);
                }
            } else {
                if($i<100) $m1 = $i+1;
                break;
            }
        }

        if($m1) {
            $s = "/**\n * ".str_replace("\n", "\n * ", preg_replace('/\n\n{2,}/', "\n\n", implode("\n", $m)."\n\n".$meta))."\n */\n";
            array_splice($a, $m0, $m1 - $m0, [$s]);
            file_put_contents($d, implode('', $a));
            echo "ApplyMeta: $d\n";
        }
    }
}