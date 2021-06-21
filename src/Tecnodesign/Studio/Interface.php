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
use Studio\Model\Interfaces as Interfaces;
use Tecnodesign_Studio as Studio;
use tdz as S;

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
        $newFromQueryString = true,
        $hitsPerPage        = 25,
        $attrListClass      = 'tdz-i-list',
        $attrPreviewClass   = 'tdz-i-preview',
        $attrParamClass     = 'tdz-i-param',
        $attrTermClass      = 'tdz-i-term',
        $attrErrorClass     = 'z-i-msg z-i-error',
        $attrCounterClass   = 'tdz-counter',
        $attrButtonsClass   = '',
        $attrButtonClass    = '',
        $dir                = [ 'interface' ],
        $headingTemplate    = '<h2 class="z-title">$LABEL</h2><hr />',
        $actionAlias        = [
            'n'=>'new',
            'v'=>'preview',
            'u'=>'update',
            'q'=>'list',
            'd'=>'delete',
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

    public static function find($q=null, $checkAuth=true)
    {
        $Is = parent::find($q, $checkAuth);
        if(!Studio::config('enable_interfaces')) {
            return $Is;
        }

        if($L = Interfaces::find($q,null,null,false)) {
            $n = (isset(Studio::$interfaces['interfaces'])) ?Studio::$interfaces['interfaces'] :'interfaces';
            if(isset($Is[$n])) {
                $base['options'] = ['list-parent'=>$n];
            }

            foreach($L as $i=>$o) {
                $a = $o->asArray('interface');
                if(isset($Is[$o->id])) {
                    $Is[$o->id] = $a + $Is[$o->id];
                } else {
                    $a += $base;
                    $a['options']['priority'] = $i;
                    $a['options']['index'] = ($o->index_interval > 0);
                    $Is[$o->id] = $a;
                }
            }
        }

        return $Is;
    }

    public static function configFile($s, $enableStudio=null)
    {
        if(Tecnodesign_Studio::config('enable_interfaces') && ($I=Interfaces::find($s,1))) {
            $r = $I->cacheFile($s);
        } else {
            $r = parent::configFile($s, $enableStudio);
        }

        return $r;
    }

    public static function loadInterface($a=array(), $prepare=true)
    {
        $a = parent::loadInterface($a, $prepare);

        $re = '/^(Tecnodesign_Studio_|Studio\\\Model\\\)/';
        if(isset($a['model']) && preg_match($re, $a['model'])) {
            $n = preg_replace($re, '', $a['model']);
            if(!Tecnodesign_Studio::enabledModels($a['model'])) {
                $a['options']['navigation'] = null;
                $a['options']['list-parent'] = false;
                $a['options']['priority'] = null;
            }
        } else {
            $n = tdz::camelize($a['interface'], true);
        }

        // overwrite credentials
        if($prepare && !isset($a['credential'])) {
            $min = null;
            if(!isset($a['actions'])) $a['actions'] = [];
            $defaultActions = array_keys(static::$actionsAvailable);
            if(isset($a['default-actions'])) {
                if(!$a['default-actions']) {
                    $defaultActions = array_keys($a['actions']);
                } else {
                    $defaultActions = (!is_array($a['default-actions'])) ?[$a['default-actions']] :$a['default-actions'];
                    if(!isset($a['config'])) {
                        $a['config'] = [];
                    }
                    $a['config']['actionsDefault'] = $defaultActions;
                }
            }
            foreach(static::$actionsAvailable as $an=>$ad) {
                if(!isset($a['actions'][$an]) && !in_array($an, $defaultActions)) {
                    $a['actions'][$an] = false;
                } else if(!is_null($c = Tecnodesign_Studio::credential($an.'Interface'.$n))) {
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

        if(!isset($a['credential'])) {
            if(!is_null($c = Tecnodesign_Studio::credential('interface'.$n))
                || !is_null($c = Tecnodesign_Studio::credential('interface'))
                || !is_null($c = Tecnodesign_Studio::credential('edit'))
            ) {
                $a['credential'] = $c;
            }
        }

        return $a;
    }

    public function checkEntryLink($o=null)
    {
        if(!$o) $o = $this->model();
        $link = $o->getStudioLink();
        if($link!=$this->url) {
            $oldurl = $this->link();
            $this->url = $link;
            return $this->redirect($this->link(), $oldurl);
        }
    }

    public function renderPreview($o=null, $scope=null, $class=null, $translate=false, $xmlEscape=true)
    {
        if($this->model=='Tecnodesign_Studio_Entry' && isset($this->text['interface']) && $this->text['interface']=='i') {
            if($r=$this->checkEntryLink()) {
                return $r;
            }
        }
        return parent::renderPreview($o, $scope, $class, $translate, $xmlEscape);
    }

    public function renderUpdate($o=null, $scope=null)
    {
        if($this->model=='Tecnodesign_Studio_Entry' && isset($this->text['interface']) && $this->text['interface']=='i') {
            if($r=$this->checkEntryLink()) {
                return $r;
            }
        }
        return parent::renderUpdate($o, $scope);
    }

    public function renderDelete($o=null, $scope=null)
    {
        if($this->model=='Tecnodesign_Studio_Entry' && isset($this->text['interface']) && $this->text['interface']=='i') {
            if($r=$this->checkEntryLink()) {
                return $r;
            }
        }
        return parent::renderDelete($o, $scope);
    }

    public static function error($code=500, $msg=null)
    {
        Tecnodesign_Studio::error($code);
    }
}

if(TDZ_VAR!=TDZ_ROOT.'/data') {
    Tecnodesign_Studio_Interface::$dir[] = TDZ_ROOT.'/data/interface';
}