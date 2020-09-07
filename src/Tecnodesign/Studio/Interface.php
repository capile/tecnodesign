<?php
/**
 * Tecnodesign Automatic Interfaces
 * 
 * This is an action for managing interfaces for all available models
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Studio_Interface extends Tecnodesign_Interface
{
    public static
        $breadcrumbs        = true,
        $displaySearch      = true,
        $displayList        = true,
        $listPagesOnTop     = true,
        $listPagesOnBottom  = true,
        $translate          = true,
        $headerOverflow     = true,
        $hitsPerPage        = 25,
        $attrListClass      = 'tdz-i-list',
        $attrPreviewClass   = 'tdz-i-preview',
        $attrParamClass     = 'tdz-i-param',
        $attrTermClass      = 'tdz-i-term',
        $attrErrorClass     = 'tdz-err tdz-msg',
        $attrCounterClass   = 'tdz-counter',
        $attrButtonsClass   = '',
        $attrButtonClass    = '',
        $dir                = [ 'interface' ],
        $indexFile          = 'i',
        $actionAlias        = [
            'n'=>'new',
            'v'=>'preview',
            'u'=>'update',
            'q'=>'list',
        ];

    protected static
        $base
        ;

    private static $t;

    public static function t($s, $alt=null)
    {
        return Tecnodesign_Studio::t($s, $alt, 'interface');
    }

    /**
     * Main static caller: just trigger a interface with it
     *
     *   (string) $n   interface to be called
     *   (string) $url optional base url
     *
     */
    public static function run($n=null, $url=null)
    {
        static::base();
        return '<div id="studio" class="studio-interface s-active">'.parent::run($n, $url).'</div>';
    }

    public static function base()
    {
        if(is_null(static::$base)) static::$base = Tecnodesign_Studio::$home;
        return static::$base;
    }

    public static function loadInterface($a=array(), $prepare=true)
    {
        $a = parent::loadInterface($a, $prepare);

        // overwrite credentials
        if($prepare && !isset($a['credential'])) {
            $min = null;
            foreach(self::$actionAlias as $aa=>$an) {
                if((isset(self::$models[$a['interface']]) && ($m=self::$models[$a['interface']]) && !is_null($c = Tecnodesign_Studio::credential($an.'Interface'.$m)))
                  || (isset($a['model']) && ($m=$a['model']) && !is_null($c = Tecnodesign_Studio::credential($an.'Interface'.$m)))) {
                    if($c===true) {
                        $min = $c;
                        $a['actions'][$an] = true;
                    } else if(!$c) {
                        continue;
                    } else {
                        if(is_null($min)) $min = $c;
                        else if(is_array($min)) $min = array_merge($min, $c);

                        if(isset($a['actions'][$an]) && !is_array($a['actions'][$an])) $a['actions'][$an]=array();
                        $a['actions'][$an]['credential'] = $c;
                    }
                }
            }
            if(!is_null($min)) {
                if(is_array($min)) $min = array_unique($min);
                $a['credential'] = $min;
            }
        }
        return $a;
    }

    public static function error($code=500, $msg=null)
    {
        Tecnodesign_Studio::error($code);
    }
}

if(TDZ_VAR!=TDZ_ROOT.'/data') {
    Tecnodesign_Studio_Interface::$dir[] = TDZ_ROOT.'/data/interface';
}