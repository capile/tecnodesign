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
        $attrErrorClass     = 'z-i-msg z-i-error',
        $attrCounterClass   = 'tdz-counter',
        $attrButtonsClass   = '',
        $attrButtonClass    = '',
        $dir                = [ 'interface' ],
        $indexFile          = 'i',
        $headingTemplate    = '<h2>$LABEL</h2><hr />',
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

    /*
    public static function find($q=null, $checkAuth=true)
    {
        \tdz::debug(__METHOD__);
    }
    */

    public static function configFile($s)
    {
        if(Tecnodesign_Studio::config('enable_interfaces') && ($I=Interfaces::find($s,1))) {
            $r = $I->cacheFile($s);
        } else {
            $r = parent::configFile($s);
        }

        return $r;
    }

    public static function loadInterface($a=array(), $prepare=true)
    {
        $a = parent::loadInterface($a, $prepare);

        $n = (isset($a['model'])) ?preg_replace('/^(Tecnodesign_Studio_|Studio\\\Model\\\)/', '', $a['model']) :'interface';

        if(!Tecnodesign_Studio::config('enable_interface_'.strtolower($n))) {
            $a['options']['navigation'] = null;
            $a['options']['list-parent'] = false;
            $a['options']['priority'] = null;
        }
        // overwrite credentials
        if($prepare && !isset($a['credential'])) {
            $min = null;
            foreach(self::$actionAlias as $aa=>$an) {
                if(!is_null($c = Tecnodesign_Studio::credential($an.'Interface'.$n))) {
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

    public function executeMethod()
    {
        $r = null;
        if(method_exists($o=$this->model, $a='execute'.tdz::camelize($this->action, true))) {
            if($this->id) {
                // get object
                $o = $this->model();
                $r = $o->$a($this);
            } else {
                $r = $o::$a($this);
            }
        }

        if($r) {
            if(is_array($r)) {
                $this->getButtons();
                $this->text = $r + $this->text;
            } else if(is_string($r) && !isset($this->text['preview'])) {
                $this->getButtons();
                $this->text['preview'] = $r;
            }

            if(!isset($this->text['summary'])) {
                $this->text['summary'] = $this->getSummary();
            }
            static::status(200);

            return;
        }
        return false;
    }

    public static function error($code=500, $msg=null)
    {
        Tecnodesign_Studio::error($code);
    }
}

if(TDZ_VAR!=TDZ_ROOT.'/data') {
    Tecnodesign_Studio_Interface::$dir[] = TDZ_ROOT.'/data/interface';
}