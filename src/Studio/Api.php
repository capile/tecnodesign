<?php
/**
 * Api
 *
 * Formerly Interface, this enables application definitions using API specifications
 *
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
namespace Studio;

use Studio as S;
use Studio\Model as Model;
use Studio\Model\Interfaces as Interfaces;
use Studio\Studio as Studio;
use Tecnodesign_App as App;
use Tecnodesign_Cache as Cache;
use Tecnodesign_Form as Form;
use Tecnodesign_Exception as Exception;
use Tecnodesign_App_End as App_End;
use Tecnodesign_Yaml as Yaml;
use ArrayAccess;

class Api implements ArrayAccess
{

    const MAX_LIMIT=10000;
    const REQ_LIMIT='limit';
    const REQ_OFFSET='offset';
    const REQ_ENVELOPE='envelope';
    const REQ_PRETTY='pretty';
    const REQ_CALLBACK='callback';
    const REQ_SCOPE='scope';
    const REQ_FIELDS='fields';
    const REQ_ORDER='order';
    const REQ_PAGE='p';
    const H_STATUS='status';
    const H_STATUS_CODE='status-code';
    const H_TOTAL_COUNT='total-count';
    const H_LAST_MODIFIED='last-modified';
    const H_CACHE_CONTROL='cache-control';
    const H_MESSAGE='message';
    const P_REAL_BASE=false;

    public static
        $request,
        $envelope           = true,
        $pretty             = true,
        $schema,
        $envelopeProperty   = 'data',
        $doNotEnvelope      = array('access-control-allow-origin'),
        $navigation         = true,
        $breadcrumbs        = true,
        $displaySearch      = true,
        $displayList        = true,
        $displayGraph       = true,
        $graphAutoMax       = 4,
        $graphLegendMax     = 5,
        $listPagesOnTop     = true,
        $listPagesOnBottom  = true,
        $translate          = true,
        $standalone         = false,
        $headerOverflow     = true,
        $newFromQueryString = true,
        $hitsPerPage        = 25,
        $attrClassPrefix    = 's-api',
        $attrErrorClass     = 's-msg s-msg-error',
        $attrSearchFormClass= 's-form-search',
        $attrCounterClass   = 's-counter',
        $attrGraphClass     = 'z-i-graph',
        $attrFormClass      = 'z-form',
        $attrButtonsClass   = '',
        $attrButtonClass    = '',
        $actionAlias        = array(),
        $actionsAvailable   = array(
                                'list'      => array('position'=>0,  'identified'=>false, 'batch'=>false, 'query'=>true,  'additional-params'=>true, ),
                                'report'    => array('position'=>10, 'identified'=>false, 'batch'=>false, 'query'=>true,  'additional-params'=>true,   'renderer'=>'renderReport',),
                                //'share'     => array('position'=>15, 'identified'=>false, 'batch'=>false, 'query'=>true,  'additional-params'=>true,   'renderer'=>'renderShare',),
                                'new'       => array('position'=>20, 'identified'=>false, 'batch'=>false, 'query'=>false, 'additional-params'=>false,  'renderer'=>'renderNew', 'next'=>'preview'),
                                'preview'   => array('position'=>30, 'identified'=>true,  'batch'=>true,  'query'=>false, 'additional-params'=>false,  'renderer'=>'renderPreview',),
                                'update'    => array('position'=>40, 'identified'=>true,  'batch'=>true,  'query'=>false, 'additional-params'=>false,  'renderer'=>'renderUpdate', 'next'=>'preview'),
                                'delete'    => array('position'=>50, 'identified'=>true,  'batch'=>true,  'query'=>false, 'additional-params'=>false,  'renderer'=>'renderDelete', 'next'=>'list'),
                                'schema'    => array('position'=>false, 'identified'=>false, 'batch'=>true,  'query'=>false, 'additional-params'=>true,  'renderer'=>'renderSchema', 'next'=>'list'),
                            ),
        $relationAction     =                  array('position'=>60,    'action' => 'executeInterface','identified'=>true,  'batch'=>false, 'query'=>false, 'renderer'=>'renderInterface'),
        $additionalActions  = [],
        $listAction         = 'preview',
        $modelRenderPrefix  = 'render',
        $actionsDefault     = [ 'preview', 'list' ],
        $share              = null,
        $boxTemplate        = '<div class="s-api-scope-block scope-$ID" data-action-scope="$ID">$INPUT</div>',
        $breadcrumbTemplate = '<div class="z-breadcrumbs">$LABEL</div>',
        $headingTemplate    = '<hr /><h3 class="z-title">$LABEL</h3>',
        $previewTemplate    = '<dl class="if--$ID z-i-field $CLASS"><dt>$LABEL</dt><dd data-action-item="$ID">$INPUT$ERROR</dd></dl>',
        $updateTemplate,
        $newTemplate,
        $renderer           = 'renderPreview',
        $dir                = [ 'api' ],
        $baseMap            = [],
        $urls               = [],
        $indexFile          = 'index',
        $baseInterface      = array(
            'interface'     => 'index',
            'run'           => 'listInterfaces',
        ),
        $authDefault        = false,
        $optionsDefault,
        $dateFormat         = 'D, d M Y H:i:s T',
        $currentAction,
        $xmlRoot = 'response',
        $xmlRootAttributes = array(),
        $xmlContainer = 'data',
        $xmlContainerAttributes = array(),
        $xmlItem = 'item',
        $xmlItemAttributes = array(),
        $xmlPropertiesAsElements = false,
        $xmlItemAttributesNode,
        $csvDelimiter=',',
        $csvEnclosure='"',
        $csvEncloseAll = false,
        $csvPrintNull = false,
        $csvFixedDelimiter = '|',
        $csvFixedHeaderDelmiter = '|',
        $csvFixedBorder='-',
        $csvFixedCorner='+',
        $csvFixedTopBorder = false,
        $csvFixedBottomBorder = false,
        $csvFixedHeaderBorder = true,
        $headers=array(),
        $status,
        $expires,
        $errorModule,
        $className='Studio\\Api',
        $removeQueryString=['ajax'=>null,'_uid'=>null],
        $ui;


    protected $uid, $model, $action, $id, $search, $searchError, $groupBy, $orderBy, $key, $url, $options, $parent, $relation, $scope, $auth, $actions, $text, $template, $run, $params, $source, $graph, $originalText, $config;
    protected static
        $instances=array(),
        $is=0,
        $base,
        $formats=array( 'html', 'json', 'xls', 'xlsx', 'csv', 'yml', 'xml' ),
        $format,
        $ext,
        $msg;


    /**
     * Interface creator: load a interface configuration, checking its contents and authentication
     *
     * Each interface definition should follow the syntax:
     *
     *   (string) model:        instanceof Studio\Model that should be loaded
     *   (string) key:          key to use for URLs and links, if not the $model::pk()
     *   (string) relation:     for sub-interfaces, which relation this interface refers to — might replace model information
     *    (array) search:       $model::find() parameters for restricting the scope of this interface.
     *                          this parameter is automatically filled when sub-interfaces are called
     *    (array) options:      different options for controlling the interface, is also set as the action parameter[0]
     * (callable) action:       action definition. If it's a string, then it's checked for a method of $model
     *                          or an Api action. Arrays might contain the className/Object in the first parameter.
     *    (mixed) auth:         who is able to perform this action
     *     (bool) batch:        if this action might be performed in batch actions
     *     (bool) identified:   if this action should be performed only when at least one record is identified
     *    (array) actions:      array of dependent actions: ( $url => $action ). For each action, if it's a string,
     *                          then the interface is checked at TDZ_VAR/interfaces/{$action}.yml
     *   (string) actionsDefault: list of actions to be tried as default options
     */
    public function __construct($d=null, $pI=null, $expand=1)
    {
        $d = static::loadInterface($d);
        if(self::$className!=get_called_class()) self::$className = get_called_class();
        if(isset($d['enable']) && !$d['enable']) {
            return static::error(404, static::t('errorNotFound'));
        }
        $this->register();
        $this->setParent($pI);
        /*
        if(!is_null($this->parent) && isset($d['relation'])) {
            $pcn = $this->getParent()->getModel();
            if(isset($pcn::$schema['relations'][$d['relation']])) {
                $this->relation = $d['relation'];
                $d['model'] = (isset($pcn::$schema['relations'][$d['relation']]['className']))?($pcn::$schema['relations'][$d['relation']]['className']):($d['relation']);
            }
            unset($d['relation']);
        }
        */
        if(isset($d['run'])) {
            if(is_string($d['run'])) {
                if(method_exists($this, $d['run'])) {
                    $this->run = array(array(self::$className, $d['run']));
                }
            } else if(is_array($d['run']) && count($d['run'])>0) {
                $r = array_values($d['run']);
                if(!is_array($r[0])) {
                    $r[0] = array(self::$className, $r[0]);
                }
                $this->run = $r;
                unset($r);
            }
            unset($d['run']);
        }
        if(isset($d['model']) && class_exists($d['model'])) {
            $this->model = $d['model'];
            $cn = $this->model;
            if(isset($d['key']) && $d['key']) {
                $this->key = $d['key'];
                unset($d['key']);
            } else {
                $this->key = $cn::pk();
                if(is_array($this->key) && count($this->key)==1) {
                    $this->key = array_shift($this->key);
                    if($p=strrpos($this->key, ' ')) $this->key =substr($this->key, 0, $p);
                }
            }
            if(isset($d['id']) && $d['id']) {
                $this->id = $d['id'];
            }
        } else if(isset($d['text'])) {
            $this->text = [];
            if(!is_array($d['text'])) {
                $d['text'] = ['preview'=>$d['text']];
            }
            foreach($d['text'] as $k=>$v) {
                if(is_string($v)) $this->text[$k] = S::markdown($v);
            }
            $this->originalText = true;
        } else if(!$this->run) {
            return static::error(404, static::t('errorNotFound'));
        }
        if(isset($d['auth'])) {
            $this->auth = $d['auth'];
            unset($d['auth']);
        } else {
            $this->getAuth();
        }
        if(isset($d['search']) && is_array($d['search'])) {
            $this->search = $d['search'];
            unset($d['search']);
        }
        if(isset($d['graph'])) {
            $this->graph = $d['graph'];
        }
        if(isset($d['url'])) {
            $this->url = $d['url'];
        }
        if(isset($d['relation'])) {
            $this->relation = $d['relation'];
        }
        if((!isset($d['actions']) && $expand) || $d['actions']) {
            $actions = (isset($d['actions']))?($d['actions']):(null);
            unset($d['actions']);
        } else {
            $actions = false;
        }

        if(isset($d['template'])) {
            $this->template = $d['template'];
            unset($d['template']);
        } else if(App::request('headers', 'z-api-mode')=='standalone') {
            $this->template = 'api-standalone';
        }
        if(isset($d['config'])) {
            if(is_array($d['config'])) {
                $this->config = $d['config'];
            }
            unset($d['config']);
        }
        if(is_null($this->config)) $this->config = [];

        if(isset($d['formats']) && is_array($d['formats'])) {
            $this->config['formats'] = $d['formats'];
            unset($d['formats']);
            if(static::$format && !in_array(static::$format, $this->config['formats'])) return static::error(400, static::t('errorNotSupported'));
        }

        if(count($d)>0) {
            $cn = self::$className;
            foreach($d as $k=>$v) {
                if(property_exists($cn, $k) && isset($cn::$$k) && gettype($v)==gettype($cn::$$k)) {
                    $cn::$$k = $v;
                    unset($d[$k]);
                }
                unset($v, $k);
            }

            if(is_null($this->text)) $this->text = $d;
            else $this->text += array_filter($d);
        }
        if($actions !== false) $this->setActions($actions, $expand);
        unset($actions);

        static $boolopt=array('envelope', 'pretty');

        if(isset($d['options']) && is_array($d['options'])) {
            $this->options = $d['options'];
            if(isset($this->options['headers'])) static::$headers += $this->options['headers'];
            if(isset($d['options']['scope'])) {
                $this->checkScope($d['options']['scope']);
            }
            foreach($boolopt as $opt) {
                if(isset($this->options[$opt]) && $this->options[$opt]!=static::$$opt) {
                    $this->config[$opt] = (bool) $this->options[$opt];
                    //static::$$opt = (bool) $this->options[$opt];
                }
            }
            unset($d['options']);
            if(isset($this->options['group-by'])) {
                $this->groupBy = $this->options['group-by'];
            }
            //if(isset($this->options['order-by'])) $this->orderBy = $this->options['order-by'];
        }

        if(static::$optionsDefault) {
            if(!$this->options) $this->options = static::$optionsDefault;
            else $this->options += static::$optionsDefault;
        }
    }

    public function config($n=null)
    {
        if($n) {
            if($this->config && isset($this->config[$n])) $r = $this->config[$n];
            else if(property_exists($this, $n)) $r = $this::$$n;
            else return null;

            $a = func_get_args();
            array_shift($a);
            while($r && $a) {
                $n = array_shift($a);
                if($r && is_array($r) && isset($r[$n])) $r = $r[$n];
                else $r = null;
            }

            return $r;
        }

        return $this->config;
    }

    public function checkScope($a=array())
    {
        if(!$a || !is_array($a)) return;
        foreach($a as $sn=>$scope) {
            if(!is_array($scope)) continue;
            foreach($scope as $fn=>$fd) {
                if(is_array($fd) && isset($fd['interface']) && isset($fd['bind'])) {
                    $fid = $fd['interface'];
                    if(!isset($this->actions[$fid])) {
                        $this->actions[$fid] = array(
                            'interface'=>$fd['interface'],
                            'relation'=>$fd['bind'],
                            'position'=>false,
                        ) + $this->config('relationAction');
                    }
                }
            }
        }
    }

    public static function base()
    {
        return static::$base;
    }

    public static function app()
    {
        if(($r=App::response('route')) && isset($r['url'])) S::scriptName($r['url']);
        return static::run();
    }

    public static function format($format=null)
    {
        $formats = ($I=static::current()) ? $I->config('formats') : static::$formats;
        unset($I);
        if($format && in_array($format, $formats)) static::$format = $format;
        return static::$format;
    }

    public static function checkFormat($ext=null)
    {
        $formats = ($I=static::current()) ? $I->config('formats') : static::$formats;
        if($ext) {
            static::$ext = '.'.$ext;
            unset($I);
            if(!in_array($ext, $formats)) {
                return static::error(400, static::t('errorNotSupported'));
            } else {
                static::$format = $ext;
            }
        }

        $accept = (App::request('headers', 'z-action')==='choices') ?null :App::request('headers', 'accept');
        if($accept && preg_match('#^application/([a-z]+)#', $accept, $m)) {
            if($m[1]=='yaml') $m[1]='yml';
            if(!in_array($m[1], $formats)) {
                if(!in_array('*', $formats)) {
                    return static::error(400, static::t('errorNotSupported'));
                }
            } else if(static::$ext && static::$ext!='.'.$m[1]) {
                return static::error(400, static::t('errorConflictFormat'));
            } else if($m[1]!='*') {
                static::$format = $m[1];
            }
            unset($m);
        }
        if(is_null(static::$format)) {
            $f = array_values($formats);
            static::$format = array_shift($f);
            unset($f);
        }

        return static::$format;
    }

    public static function loadAssets()
    {
        App::$assets[] = 'Z.Api';
        App::$assets[] = '!'.Form::$assets;
        App::$assets[] = '!Z.Graph';
    }

    public static function action()
    {
        $a = ($I=static::current())?($I->action):(null);
        unset($I);
        return $a;
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
        if(self::$className!=get_called_class()) {
            self::$className = get_called_class();
        }
        if(S_VAR!=S_ROOT.'/data' && Studio::config('enable_interface_index')) {
            static::$dir[] = S_ROOT.'/data/api';
        }
        try {
            if(!is_null($url)) S::scriptName($url);
            else if(($route=App::response('route')) && isset($route['url']) && !preg_match('/[\*\|\(\)]/', $route['url'])) S::scriptName($route['url']);
            static::$request = S::requestUri();

            $p = S::urlParams(null, true);
            $l = count($p) -1;
            // remove extension from last parameter, if there's any
            $ext = null;
            if(isset($p[$l]) && preg_match('/\.([a-z0-9]{3,4})$/', $p[$l], $m)) {
                $ext = $m[1];
                $p[$l] = substr($p[$l], 0, strlen($p[$l]) - strlen($m[0]));
            } else if($m=App::request('extension')) {
                $ext = $m;
            }
            unset($m);

            static::$base = S::scriptName();
            if($n) {
                array_unshift($p, $n);
                if(substr(static::$base, -1*strlen('/'.$n))==='/'.$n) {
                    static::$base = substr(static::$base, 0, strlen(static::$base) - strlen($n) -1);
                }
            } else if(static::$base=='/') static::$base='';

            if($apid=App::config('app', 'api-dir')) {
                if(!is_array($apid)) {
                    if(!in_array($apid, static::$dir)) array_unshift(static::$dir, $apid);
                } else {
                    static::$dir = array_unique(array_merge($apid, static::$dir));
                }
            }
            unset($apid);

            if(static::$share) {
                $sf = (static::$share===true || static::$share===1)?('api-shared'):(S::slug(static::$share, '_', true));
                if(!in_array($sf, static::$dir)) static::$dir[] = $sf;
            }

            $I = static::currentInterface($p);

            static::checkFormat($ext);

            if(!$I) return false;

            if(static::$breadcrumbs && isset($I->options['list-parent'])) {
                $pi = $I->options['list-parent'];
                $urls = [];
                while($pi && ($Pi=static::find($pi)) && isset($Pi[0]['interface']) && isset($Pi[0]['title'])) {
                    $pi = null;
                    $urls[static::$base.'/'.$Pi[0]['interface']] = ['title'=> $Pi[0]['title'],'interface'=>false];
                    if(isset($Pi[0]['options']['list-parent'])) {
                        $pi = $Pi[0]['options']['list-parent'];
                    }
                    unset($Pi);
                }
                unset($Pi, $pi);
                if($urls) {
                    static::$urls += array_reverse($urls, true);
                }
            }

            static::loadAssets();
            S::$variables['html-layout'] = 'studio-api';

            //if($I && $I->auth) S::cacheControl('private, no-store, no-cache, must-revalidate',0);
            $sn = S::scriptName();
            S::scriptName($I->url);

            static::$ui = (!TDZ_CLI && static::$format==='html');
            return $I->output($p);

        } catch(App_End $e) {
            static::headers();
            throw $e;
        } catch(Exception $e) {
            S::log('[ERROR] '.__METHOD__.'->'.get_class($e).':'.$e);
            static::error(500);
        }
    }

    public function output($p=null)
    {
        $s = $this->render($p);

        static::headers();
        if(static::$format!='html') {
            App::response(array('headers'=>array('Content-Type'=>'application/'.static::$format.'; charset=utf-8')));
            App::end($s);
        }
        $s = '<div class="s-api-box" base-url="'.$this::$base.'">'.$s.'</div>';

        if(App::request('headers', 'z-action')=='Interface') {
            App::end($s);
            //exit($s);
        }
        return $s;
    }

    protected function __clone()
    {
        $this->register();
    }

    public static function current()
    {
        if(is_null(self::$instances) || !self::$instances) return null;
        return array_values(self::$instances)[count(self::$instances)-1];
    }

    private function register()
    {
        $this->uid=self::$is++;
        if(is_null(self::$instances)) self::$instances = new ArrayObject();
        self::$instances[$this->uid] = $this;
    }

    public function getParent()
    {
        if(!is_null($this->parent) && isset(self::$instances[$this->parent])) {
            return self::$instances[$this->parent];
        }
        return null;
    }

    public function setParent($pI)
    {
        if(!is_null($pI) && $pI!==false) {
            if(is_object($pI) && $pI instanceof Api) {
                $this->parent = $pI->uid;
            } else if(is_numeric($pI) && isset(self::$instances[$pI])) {
                $this->parent = $pI;
            } else {
                $this->parent = null;
            }
        } else {
            $this->parent = null;
        }
        return $this;
    }

    public function setActions($actions=true, $expand=0)
    {
        if(!is_array($actions)) $actions=array();
        if(is_null($this->actions) && !$this->model) {
            $this->actions = array();
        } else if(is_null($this->actions)) {
            $this->actions = array();
            $cn = $this->model;
            if(is_null($this->auth)) $this->getAuth();
            $b = (isset($cn::$schema['ui-credentials']))?($cn::$schema['ui-credentials']):(array());

            $la = array();
            $actionsAvailable = $this->config('actionsAvailable');
            foreach($actionsAvailable as $an=>$a) {
                if($b && isset($b[$an]) && !$b[$an]) continue;
                else if(isset($actions[$an]) && !$actions[$an]) continue;

                if(isset($actions[$an])) {
                    $c = $actions[$an];
                    if(!is_array($c) || isset($c[0])) {
                        $a['auth']['credential'] = $c;
                    } else {
                        $a = array_merge($a, $c);
                    }
                    unset($c, $actions[$an]);
                }
                if(!isset($a['position'])) $a['position'] = 0.000;
                $p = $a['position'];
                while(isset($la[(string)$p])) $p = 0.001;
                $a['id'] = $an;
                $la[(string)$p] = $a;

                unset($actionsAvailable[$an], $b[$an], $an, $a, $p);
            }
            $additionalActions = $this->config('additionalActions');
            foreach($additionalActions as $an=>$a) {
                if($b && isset($b[$an]) && !$b[$an]) continue;
                else if(isset($actions[$an]) && !$actions[$an]) continue;

                if(isset($actions[$an])) {
                    $c = $actions[$an];
                    if(!is_array($c) || isset($c[0])) {
                        $a['auth']['credential'] = $c;
                    } else {
                        $a = array_merge($a, $c);
                    }
                    unset($c, $actions[$an]);
                }
                if(!isset($a['position'])) $a['position'] = 0.000;
                $p = $a['position'];
                while(isset($la[(string)$p])) $p += 0.001;
                $a['id'] = $an;
                $la[(string)$p] = $a;

                unset($additionalActions[$an], $b[$an], $an, $a, $p);
            }

            foreach($actions as $an=>$a) {
                if(isset($a['relation']) || isset($a['interface'])) $a += $this->config('relationAction');

                if(isset($a['expire']) && ($t=strtotime($a['expire'])) && $t<TDZ_TIME) {
                    continue;
                }

                if(!isset($a['action'])) continue;

                if(!isset($a['position'])) $a['position'] = 0.000;
                $p = $a['position'];
                while(isset($la[(string)$p])) $p += 0.001;
                $a['id'] = $an;

                $la[(string)$p] = $a;

                unset($an, $a, $p);
            }

            ksort($la, SORT_NUMERIC);
            foreach($la as $ap=>$a) {
                $this->actions[$a['id']] = $a;
                unset($la[$ap], $ap, $a);
            }
            unset($b, $cn, $actions, $la);
        }

        // should relations be expanded? why?
        /*
        $self = get_called_class();
        foreach($actions as $an=>$action) {
            if(!$action) continue;
            $this->actions[$an] = ($expand && !is_object($action) && (isset($action['model']) || isset($action['relation'])))?(new $self($action, $this, $expand-1)):($action);
        }
        */
        return $this;
    }

    public function getActions($an=null, $expand=0)
    {
        if(is_null($this->actions)) {
            $this->setActions($this->actions, $expand);
        }
        if(!is_null($an)) {
            return (isset($this->actions[$an]))?($this->actions[$an]):(false);
        }
        return $this->actions;
    }

    public function redirect($url=null, $oldurl=null)
    {
        if(is_null($url)) $url = $this->link();
        // ajax handlers
        if($oldurl && App::request('headers', 'z-action')=='Interface') {
            $this->message('<a data-action="unload" data-url="'.S::xml($this->link()).'"></a>');
        }
        S::redirect($url);
    }

    public function message($m=null)
    {
        $U = S::getUser();
        $clean = null;
        if(is_null(static::$msg)) {
            static::$msg = (string) $U->getMessage(null, true);
            $clean = true;
        }
        if($m) {
            static::$msg .= $m;
            if(!$clean) $U->deleteMessage();
            $U->setMessage(static::$msg);
        } else if($m===false) {
            unset($U, $clean);
            $msg = static::$msg;
            static::$msg = null;

            return $msg;
        }
        unset($U, $clean);

        return static::$msg;
    }

    public function getModel()
    {
        if($this->model && isset($this->options['view'])) {
            $cn = $this->model;
            $cn::$schema['view'] = $this->options['view'];
            unset($cn, $this->options['view']);
        }
        return $this->model;
    }

    public function getAuth($action=null)
    {
        if(is_null($this->auth)) {
            if(!is_null($this->parent)) {
                $this->auth = $this->getParent()->getAuth();
            } else {
                $this->auth = static::$authDefault;
            }
        }
        if(!is_null($action)) {
            if(isset($this->actions[$action])) {
                if($this->actions[$action] instanceof Api) return $this->actions[$action]->getAuth();
                else if(isset($this->actions[$action]['auth'])) return $this->actions[$action]['auth'];
            }
        }
        return $this->auth;
    }

    public function hasCredential($action=null)
    {
        $c = $this->getCredential($action);
        return (!$c || S::getUser()->hasCredential($c, false));
    }

    public function auth($action=null, $setStatus=null)
    {
        return static::checkAuth($this->getAuth($action), $setStatus);
    }

    public static function authHeaders($U=null, $h='private, no-cache')
    {
        S::cacheControl($h, static::$expires);
        self::$headers[static::H_CACHE_CONTROL] = $h;
    }

    public static function checkAuth($c, $setHeaders=null)
    {
        static $H, $U;
        if(is_null($U)) {
            $U = S::getUser();
        }
        if(!is_array($c)) {
            if(!$c) return true;
            if($U->isAuthenticated()) {
                if($setHeaders) {
                    self::authHeaders($U);
                }
                return true;
            } else {
                if($setHeaders) {
                    static::error(401, static::t('errorForbidden'));
                }
                return false;
            }
        }
        if(isset($c['user']) && is_array($c['user'])) {
            if($U->isAuthenticated() && ($uid=$U->getPk())) {
                if(in_array($uid, $c['user'])) return true;
            }
        }
        if(isset($c['host']) && is_array($c['host'])) {
            if($setHeaders) {
                self::authHeaders();
            }
            if(is_null($H)) {
                $H = (isset($_SERVER['REMOTE_ADDR']))?($_SERVER['REMOTE_ADDR']):(false);
            }
            if($H && in_array($H, $c['host'])) {
                return true;
            }
        }
        if(isset($c['credential'])) {
            if(!$c['credential']) {
                return true;
            } else {
                if($setHeaders) {
                    self::authHeaders($U);
                }
                if($U->hasCredential($c['credential'], false)) {
                    return true;
                }
            }
        }
        if($setHeaders) {
            static::error(($U->isAuthenticated()) ?403 :401, static::t('errorForbidden'));
        }
        return false;
    }


    public static function currentInterface($p, $I=null)
    {
        if(!isset(static::$base)) static::$base = S::scriptName();

        if(self::$className!=get_called_class()) self::$className = get_called_class();
        // first fetch any interface from the $p
        $n=null;
        if(is_null($I)) {
            $f=null;
            $rn = null;
            if($p) {
                $p0 = $p;
                $n = preg_replace('#[^a-z0-9\-\_\@]#i', '', array_shift($p));// find a file
                while(!($f=static::configFile($n)) && $p) {
                    $n .= '/'.rawurlencode(array_shift($p));
                    $f = null;
                }
            }
            if(!$f) {
                if(isset($p0)) $p = $p0;
                if(!$p && ($f=static::configFile(static::$indexFile))) {
                    $n = static::$indexFile;
                } else if($p) {
                    $n = preg_replace('#[^a-z0-9\-\_\@]#i', '', array_shift($p));
                    $rn = '/'.static::$indexFile;
                    while(!($f=static::configFile($n.'/'.static::$indexFile)) && $p) {
                        $n .= '/'.array_shift($p);
                    }
                    if($f) $n .= $rn;
                }
            } else {
                $sn = S::scriptName();
                $sn .= (substr($sn, -1)=='/') ?$n :'/'.$n;
                S::scriptName($sn);
            }
            unset($p0);
            if(!$f) {
                return static::error(404, static::t('errorNotFound'));
            }
            unset($f);
            $cn = self::$className;
            $I = new $cn($n);
            if(!$I->auth(null, true)) {
                return false;
            }
            if($rn) {
                $n = substr($n, 0, strlen($n) - strlen($rn));
            }
            $I->url = ($n)?(static::$base.'/'.$n):(static::$base);
            unset($cn);
        }

        //static::$urls[$I->link()] = array('title'=>$I->getTitle(),'action'=>$I->action);

        if($I->run) {
            return $I;
        }

        if(is_null($I->actions)) {
            $I->setActions(true, 1);
        }

        $a = $n = null;
        if($p) $n = array_shift($p);
        if($n) {
            if($a = $I->config('actionAlias', $n)) {
            } else if(isset($I->actions[$n]) && !in_array($n, $I->config('actionAlias'))) {
                $a = $n;
            } else {
                array_unshift($p, $n);
                $n = null;
            }
        }

        if($a) {
            $A=$I->setAction($a, $p);
        } else {
            // try default actions
            foreach($I->config('actionsDefault') as $a) {
                if($A=$I->setAction($a, $p)) {
                    break;
                }
                unset($a, $A);
            }
        }
        if(!isset($A) || !$A) {
            if($I->originalText) {
                return $I;
            } else {
                return static::error(404, static::t('errorNotFound'));
            }
        } else if(is_object($A) && $A instanceof Api) {
            static::$urls[$A->link()] = array('title'=>$A->getTitle(),'action'=>$A->action);
            if($p) {
                return static::currentInterface($p, $A);
            } else {
                return $A;
            }
        } else {
            return static::currentInterface($A);
        }
    }

    public function setId($id=null)
    {
        if(!$this->model) return false;
        $cn = $this->model;
        $pk = $cn::pk();
        if(is_array($pk)) {
            $pk = implode(',', $pk);
        }
        if($p=strrpos($pk, ' ')) $pk=substr($pk, 0, $p);
        $this->search[$pk] = $id;
        $this->id = $id;
        return $this;
    }

    public function getId()
    {
        return $this->id;
    }

    public function setUrl($url=null)
    {
        $this->url = $url;
        return $this;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setAction($a, &$p=null)
    {
        if(isset($this->actions[$a])) {
            static::$urls[$link=$this->link()] = [ 'title' => $this->getTitle(), 'interface' => false ];

            if(isset($this->actions[$a]['identified']) && $this->actions[$a]['identified']) {
                $n = ($p) ?array_shift($p) :null;
                if(!isset($this->id) && $n) {
                    if($n!=='') {
                        if(is_array($this->key)) {
                            $nc = explode(Model::$keySeparator, $n, count($this->key));
                            $add = array();
                            foreach($this->key as $k) {
                                $add[$k] = array_shift($nc);
                            }
                        } else {
                            if(strpos($n, ',')) $n = preg_split('/\s*\,\s*/', $n, -1, PREG_SPLIT_NO_EMPTY);
                            $add = array($this->key=>$n);
                        }
                    } else {
                        $add = array("`{$this->key}`!="=>$n);
                    }
                    $this->addSearch($add);
                    if($this->count()==1) {
                        $this->id = (is_array($n))?(implode(',',$n)):($n);
                    }
                } else if(!isset($this->id) && $this->search) {
                    if($this->count()==1) {
                        $this->model([], 1, false, true);
                    }
                } else if($n && $n!=$this->id) {
                    return false;
                }

                if(S::isempty($this->id)) {
                    if(isset($n)) {
                        array_unshift($p, $n);
                    }
                    return false;
                }
            } else if(!S::isempty($this->id)) {
                return false;
            }

            $this->action = $a;
            static::$urls[$link=$this->link($a)] = [ 'title' => $this->getTitle(), 'action' => $a ];

            if(!$this->auth($a, true)) {
                return false;
            }
            if((!isset($this->actions[$a]['additional-params']) || !$this->actions[$a]['additional-params']) && $p) {
                $n = array_shift($p);
                if(isset($this->actions[$n]['relation']) || isset($this->actions[$n]['interface'])) {
                    $this->action = $a;
                    return $this->relation($n, $p);
                }
                array_unshift($p, $n);
                return false;
            } else if($p) {
                $this->params = implode('/', $p);
                $p = array();
            }

            return $this;
        }
        return false;
    }

    public static function status($code=null)
    {
        if(!$code) {
            if(!static::$status) $code = 200;
            else return;
        }
        static::$status = $code;

        // add status headers
        static::$headers = array(
                static::H_STATUS=>App::status(static::$status, false),
                static::H_STATUS_CODE=>static::$status,
            ) + static::$headers;
        if(static::H_CACHE_CONTROL && !isset(static::$headers[static::H_CACHE_CONTROL])) {
            if($code==200) {
                static::$headers[static::H_CACHE_CONTROL] = 'public';
                S::cacheControl('public', static::$expires);
            } else {
                static::$headers[static::H_CACHE_CONTROL] = 'nocache';
                S::cacheControl('nocache', 0);
            }
        }
    }

    public static function error($code=500, $msg=null)
    {
        //for compatibility
        if(is_null(static::$format)) {
            $f = static::$formats;
            static::$format = array_shift($f);
            unset($f);
        }
        static::status($code);
        if($msg) {
            if(is_array($msg)) {
                if(isset($msg['error'])) static::$headers[static::H_MESSAGE] = $msg['error'];
                else if(isset($msg['message'])) static::$headers[static::H_MESSAGE] = $msg['message'];
                else static::$headers[static::H_MESSAGE] = implode(' ', $msg);
            } else {
                static::$headers[static::H_MESSAGE] = (string)$msg;
            }
        }

        if(static::$format!='html') {
            if($p=App::request('get', static::REQ_ENVELOPE)) {
                static::$envelope = (bool)S::raw($p);
            }
            if($p=App::request('get', static::REQ_PRETTY)) {
                static::$pretty = (bool)S::raw($p);
            }
            unset($p);
            $cn = self::$className;
            if(method_exists($cn, $m='to'.ucfirst(static::$format))) $msg = static::$m((is_array($msg))?($msg):(array()));

            App::response(array('headers'=>array('Content-Type'=>'application/'.static::$format.'; charset=utf-8')));
            App::end($msg, $code);
        }
        if(isset(static::$errorModule)) {
            $cn = static::$errorModule;
            static::$errorModule=null;
            return $cn::error($code);
        }
        S::getApp()->runError($code);
    }

    public static function toXml($ret)
    {
        $a = $b = '';
        $a = '<?xml version="1.0" encoding="utf-8" ?'.'>';
        $ln = "\n";
        $in = "  ";
        $ix = 1;
        if(!static::$pretty) {
            $a = '';
            $ln = '';
            $in = '';
        }

        if(!isset($ret[0])) $ret = array($ret);

        if(static::$envelope) {
            $a .= $ln.'<'.static::$xmlRoot;
            if(!static::$xmlPropertiesAsElements && static::$headers) {
                if(!is_array(static::$xmlRootAttributes)) static::$xmlRootAttributes=static::$headers;
                else static::$xmlRootAttributes += static::$headers;
            }
            if(static::$xmlRootAttributes) {
                foreach(static::$xmlRootAttributes as $k=>$v) {
                    $a .= ' '.$k.'="'.S::xml($v).'"';
                    unset($k, $v);
                }
            }
            $a .= '>';
            if(static::$xmlPropertiesAsElements) {
                foreach(static::$headers as $k=>$v) {
                    $s  = S::slug($k, '_');
                    $a .= $ln.str_repeat($in, $ix).'<'.$s.'>'.S::xml($v).'</'.$s.'>';
                    unset($s, $k, $v);
                }
            }
            $b = $ln.'</'.static::$xmlRoot.'>'.$b;
            $ix++;
        }

        $a .= $ln.str_repeat($in, $ix -1).'<'.static::$xmlContainer;
        if(static::$xmlContainerAttributes) {
            foreach(static::$xmlContainerAttributes as $k=>$v) {
                $a .= ' '.$k.'="'.S::xml($v).'"';
                unset($k, $v);
            }
        }
        $a .= '>';
        $b = $ln.str_repeat($in, $ix -1).'</'.static::$xmlContainer.'>'.$b;
        if($i=count($ret)) {
            while($i-- > 0) {
                $d=array_shift($ret);
                $a .= $ln.str_repeat($in, $ix).'<'.static::$xmlItem;
                if(static::$xmlItemAttributes) {
                    foreach(static::$xmlItemAttributes as $k=>$v) {
                        $a .= ' '.$k.'="'.S::xml($v).'"';
                        unset($k, $v);
                    }
                }
                $a .= '>';
                foreach($d as $k=>$v) {
                    if(!static::$xmlItemAttributesNode) {
                        $s = S::slug($k, '_');
                        if(is_array($v)) {
                            foreach($v as $x) {
                                $a .= $ln.str_repeat($in, $ix+1).'<'.$s.'>'.S::xml($x).'</'.$s.'>';
                            }
                        } else {
                            $a .= $ln.str_repeat($in, $ix+1).'<'.$s.'>'.S::xml($v).'</'.$s.'>';
                        }
                        unset($s);
                    } else {
                        if(is_array($v)) {
                            foreach($v as $x) {
                                $a .= $ln.str_repeat($in, $ix+1).'<'.static::$xmlItemAttributesNode.' name="'.S::xml($k).'" value="'.S::xml($x).'"/>';
                            }
                        } else {
                            $a .= $ln.str_repeat($in, $ix+1).'<'.static::$xmlItemAttributesNode.' name="'.S::xml($k).'" value="'.S::xml($v).'"/>';
                        }
                    }
                    unset($d[$k], $k, $v);
                }
                $a .= $ln.str_repeat($in, $ix).'</'.static::$xmlItem.'>';
                unset($d);
            }
        }
        $a .= $b.$ln;
        unset($b, $ret);
        return $a;
    }

    public static function toJson($ret)
    {
        if(static::$envelope) {
            $ret = static::envelope($ret);
        }

        $flags = (static::$pretty)?(JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE):(JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $b = $a = '';
        if(($p=App::request('get', static::REQ_CALLBACK)) && is_string($p)) {
            $b = preg_replace('/[^a-z0-9\_\.]+/i', '', $p).'(';
            $a = ');';
        }
        unset($p);
        return str_replace("\n\n", "\n", $b.json_encode($ret, $flags).$a)."\n";
    }

    public static function toYml($ret)
    {
        if(static::$envelope) {
            $ret = static::envelope($ret);
        }
        unset($p);
        return Yaml::dump($ret, 2, 80);
    }

    public static function toCsv($ret)
    {
        $r='';
        if(!isset($ret[0])) $ret = array($ret);
        if(!static::$pretty && !static::$envelope) {
            if($i=count($ret)) {
                while($i-- > 0) {
                    $d=array_shift($ret);
                    $r.="\n".static::csv($d, "\t", '');
                    unset($d);
                }
            }
            $r .= "\n";
        } else if(!static::$pretty) {

            if($i=count($ret)) {
                while($i-- > 0) {
                    $d=array_shift($ret);
                    if(!$r) {
                        $r.=static::csv(array_keys($d), static::$csvDelimiter, static::$csvEnclosure, static::$csvEncloseAll, static::$csvPrintNull);
                    }
                    $r.="\n".str_replace(array("\r", "\n"), array('\\r', '\\n'), static::csv($d, static::$csvDelimiter, static::$csvEnclosure, static::$csvEncloseAll, static::$csvPrintNull));
                    unset($d);
                }
            }
            $r .= "\n";
        } else {
            $w = array();
            foreach($ret as $i=>$d) {
                foreach($d as $k=>$v) {
                    if(!isset($w[$k])) $w[$k] = mb_strlen($k);
                    if(is_array($v)) $v = implode(',', $v);
                    $v = str_replace(array("\r", "\n"), array('\\r', '\\n'), $v);
                    $ret[$i][$k] = $v;
                    $l = mb_strlen($v, 'UTF8');
                    if($w[$k]<$l)$w[$k]=$l;
                    unset($d[$k], $k, $v, $l);
                }
                unset($d);
            }
            $h = array();
            foreach($w as $k=>$v) {
                $h[] = str_repeat(static::$csvFixedBorder, $v+2);
                unset($k, $v);
            }
            if(static::$csvFixedTopBorder) $r .= static::$csvFixedCorner.implode(static::$csvFixedCorner, $h).static::$csvFixedCorner."\n";
            foreach($w as $k=>$v) {
                $r .= static::$csvFixedDelimiter.' '.str_pad($k, $v+1, ' ', STR_PAD_RIGHT);
            }
            $r .= static::$csvFixedDelimiter."\n";
            if(static::$csvFixedHeaderBorder) $r .= static::$csvFixedCorner.implode(static::$csvFixedCorner, $h).static::$csvFixedCorner."\n";
            foreach($ret as $d) {
                foreach($d as $k=>$v) {
                    $r .= '| '.$v.str_repeat(' ', $w[$k]+1 - mb_strlen($v, 'UTF8'));
                }
                $r .= "|\n";
            }
            if(static::$csvFixedBottomBorder) $r .= static::$csvFixedCorner.implode(static::$csvFixedCorner, $h).static::$csvFixedCorner."\n";
        }

        unset($ret);
        return $r;
        //self::output($r, 'application/csv; charset=utf-8', false);
    }

    public static function csv(array $fields, $delimiter = ',', $enclosure = '"', $encloseAll = false, $nullToMysqlNull = false )
    {
        $delimiter_esc = preg_quote($delimiter, '/');
        $enclosure_esc = preg_quote($enclosure, '/');

        $output = array();
        foreach ( $fields as $field ) {
            if ($field === null && $nullToMysqlNull) {
                $output[] = 'NULL';
                continue;
            }
            if(is_array($field)) $field = implode(',', $field);

            // Enclose fields containing $delimiter, $enclosure or whitespace
            if ( $encloseAll || preg_match( "/(?:${delimiter_esc}|${enclosure_esc}|\s)/", $field ) ) {
                $output[] = $enclosure . str_replace($enclosure, $enclosure . $enclosure, $field) . $enclosure;
            }
            else {
                $output[] = $field;
            }
        }

        return implode( $delimiter, $output );
    }

    public static function ldif($a, $dn=false)
    {
        if(!is_array($a)) {
            if($dn) {
                return str_replace("\n", "\n ", wordwrap(preg_replace('/([,=+<>#;\\"])/', '\\\$1', $a)));
            } else {
                // do not escape base64 characters
                return str_replace("\n", "\n ", wordwrap(preg_replace('/([,<>#;\\"])/', '\\\$1', $a)));
            }
        } else {
            $s = '';
            foreach($a as $k=>$v) {
                $dn1 = ($k=='dn')?(true):($dn);
                if(is_int($k)) {
                    $s .= "\n".self::ldif($v, $dn1);
                } else if($k=='..') {
                    $s .= self::ldif($v, $dn1);
                } else if(is_array($v)) {
                    $s .= "\n{$k}: ";
                    foreach($v as $vk=>$vv) {
                        if(is_int($vk)) {
                            $s .= (($vk==0)?(''):("\n{$k}: ")).$vv;
                        } else if(is_array($vv)) {
                            $s .= "{$vk}=".implode(",{$vk}=",$vv).',';
                        } else {
                            $s .= "{$vk}=".self::ldif($vv, $dn1).',';
                        }
                    }
                    if(substr($s, -1)==',')
                        $s = substr($s, 0, strlen($s)-1);
                } else {
                    $s .= "\n{$k}: ".self::ldif($v, $dn1);
                }
            }
            return $s;
        }
    }

    public static function envelope($a)
    {
        $r = array();
        if(isset(static::$headers)) {
            foreach(static::$headers as $k=>$v) {
                if(static::$doNotEnvelope && in_array($k, static::$doNotEnvelope)) continue;
                $r[$k]=$v;
                unset($k, $v);
            }
        }
        $r[static::$envelopeProperty] = $a;
        return $r;
    }


    public function getTitle()
    {
        $cn = $this->getModel();
        $s = null;
        $xml = null;
        if(!S::isempty($this->id)) {
            $w = $this->search;
            if(!S::isempty($this->id) && !S::isempty($this->key)) {
                $w = [];
                if(is_string($this->key)) {
                    $w[$this->key] = $this->id;
                } else {
                    $id = (!is_array($this->id)) ?explode($cn::$keySeparator, $this->id, count($this->key)) :$this->id;
                    foreach($this->key as $k=>$v) {
                        if(isset($id[$k])) $w[$v] = $id[$k];
                        else if(isset($id[$v])) $w[$v] = $id[$v];
                        else if(isset($this->search[$v])) $w[$v] = $this->search[$v];
                    }
                }
            }
            if(!$w) $w = ($this->search) ?$this->search :$this->id;
            $r = $cn::find($w,null,'string',false,null,$this->groupBy);
            if($r) {
                if(method_exists($cn, 'renderTitle')) {
                    $s = '';
                    foreach($r as $i=>$o) {
                        $s .= (($s)?(', '):(''))
                            . $o->renderTitle();
                        unset($r[$i], $i, $o);
                    }
                    $xml = true;
                } else if($this->action && !in_array($this->action, $this->config('actionsDefault'))) {
                    $l = $this->action;
                    if(isset($this->actions[$l]['label']) && ($label = $this->actions[$l]['label'])) {
                        if(substr($label, 0, 1)==='*') $label = static::t(substr($label, 1));
                        $s = $label.': '.implode(', ', $r);
                    } else {
                        $s = static::t(S::camelize('label-'.$l), ucwords($l)).': '.implode(', ', $r);
                    }
                } else {
                    $s = implode(', ', $r);
                }
            }
        } else if($this->action && !in_array($this->action, $this->config('actionsDefault'))) {
            $l = $this->action;
            if(isset($this->actions[$l]['label']) && ($s = $this->actions[$l]['label'])) {
                if(substr($s, 0, 1)==='*') $s = static::t(substr($s, 1));
            } else {
                $s = static::t(S::camelize('label-'.$l), ucwords($l));
            }
        } else {
            if(!isset($this->text['title'])) {
                $s = $cn::label();
            } else if(substr($this->text['title'], 0, 1)=='*') {
                $s = static::t(substr($this->text['title'],1));
            } else {
                $s = $this->text['title'];
            }
        }

        if(!$s) {
            $s = static::t('Untitled');
        }

        if($s && !$xml) $s = S::xml($s);

        return $s;
    }

    public function setTitle($title)
    {
        $this->text['title'] = $title;
    }

    public function getSearch($relation=null)
    {
        if($relation) {
            $cn = $this->getModel();
            $rel =  $cn::$schema['relations'][$relation];
            $rcn = (isset($rel['className']))?($rel['className']):($relation);
            // try to figure ou which is the reverse relation
            $rr=null;
            foreach($rcn::$schema['relations'] as $rn=>$rd) {
                if($rn==$cn || (isset($rd['className']) && $rd['className']==$cn)) {
                    if($rd['local']==$rel['foreign'] && $rd['foreign']==$rel['local']) {
                        $rr = $rn;
                        unset($rn, $rd);
                        break;
                    }
                }
                unset($rn, $rd);
            }
            if(!$rr) {
                $rr = '_r_'.$cn;
                $rcn::$schema['relations'][$rr] = array('className'=>$cn, 'local'=>$rel['foreign'], 'foreign'=>$rel['local'], 'type'=>($rel['type']=='many')?('one'):('many'));
            }
            unset($rel, $rcn, $cn);
            $r = array();
            foreach($this->search as $k=>$v) {
                if(strpos($k, '`')!==false) $k = preg_replace('/\`([^\`]+)\`/', "`{$rr}.\$1`", $k);
                else $k = $rr.'.'.$k;
                $r[$k] = $v;
            }
            return $r;
        }
        return $this->search;
    }

    public function setSearch($arr)
    {
        $this->search = array();
        $this->addSearch($arr);
        return $this;
    }

    public function addSearch($arr)
    {
        if(!is_array($this->search)) $this->search = array();
        if(is_array($arr)) {
            foreach($arr as $fn=>$fv) {
                $this->search[$fn] = $fv;
            }
        }
        return $this;
    }

    public static function t($s, $alt=null)
    {
        $self = self::$className;
        if(property_exists($self, $s)) {
            $s = static::$$s;
        } else if($alt) {
            $s = $alt;
        }
        return (static::$translate || $alt===null)?(S::t($s, 'interface')):($s);
    }

    public static function template()
    {
        if(!in_array($d=TDZ_ROOT.'/data/templates', S::templateDir())) {
            S::$tplDir[] = $d;
        }
        unset($d);
        return S::templateFile(func_get_args());
    }

    public function referer()
    {
        if(($url=App::request('headers', 'z-referer')) || ($url=App::request('headers', 'referer'))) {
            $ref = parse_url($url);
            if($ref && (!isset($ref['host']) || $ref['host']==App::request('hostname')) && substr($ref['path'], 0, strlen($this::$base)+1)==$this::$base.'/') {
                return $url;
            }
        }

        return $this->link(false, true);
    }

    public function link($a=null, $id=null, $ext=true, $qs=null)
    {
        if(is_null($this->url)) {
            $this->url = static::$base.'/'.$this->text['interface'];
        }
        $url = $this->url;
        // add action to URL
        if(is_null($a)) $a = $this->action;
        $rel='';
        if(isset($this->actions[$a]['relation'])) {
            $rel = '/'.$a;
            $a = $this->action;
        }

        if(!$a || $a=='text') return $url;
        if(static::$actionAlias && in_array($a, static::$actionAlias)) {
            if($aa = array_search($a, static::$actionAlias)) {
                $url .= '/'.$aa;
            }
            unset($aa);
        } else {
            $url .= '/'.$a;
        }
        $A = (isset($this->actions[$a]))?($this->actions[$a]):(array());
        if(!is_array($A))$A=array();
        if($aa = $this->config('actionsAvailable', $a)) {
            $A += $aa;
        } else if($aa = $this->config('additionalActions', $a)) {
            $A += $aa;
        }
        unset($aa);
        if(!S::isempty($this->id) || !S::isempty($id)) {
            if($id===true || (S::isempty($id) && isset($A['identified']) && $A['identified'])) {
                $id = $this->id;
            }
            if(!S::isempty($id)) $url .= '/'.(!preg_match('/^\{[a-z0-9\-\_]+\}$/i', $id) ?rawurlencode($id) :$id);
        } else if(!S::isempty($this->params)) {
            $url .= '/'.$this->params;
        }
        if($rel) $url .= $rel;
        if($ext) $url .= static::$ext;
        if(isset($A['query']) && $A['query']) {
            if(is_null($qs)) $qs = $this->qs();
            if($qs) {
                $url .= '?'.str_replace(',', '%2C', $qs);
            }
        }
        unset($qs, $A, $rel);
        return $url;
    }

    public function isOne()
    {
        if(!S::isempty($this->id)) return true;

        if(static::$standalone && $this->search) {
            return ($this->count()==1);
        }
        if(!is_array($this->key)) {
            return isset($this->search[$this->key]);
        } else {
            $set = true;
            foreach ($this->key as $k) {
                if(!isset($this->search[$k])) {
                    $set = false;
                    break;
                }
            }
            return $set;
        }
        return false;
    }

    public function execute()
    {
        static::$currentAction = $this->action;
        if(!isset($this->text)) $this->text = array();
        $this->text['count'] = $this->count();
        $req = App::request('post') + $this->qs(true);
        if($req) {
            $noreq = array(static::REQ_LIMIT, static::REQ_OFFSET, static::REQ_ENVELOPE, static::REQ_PRETTY, static::REQ_CALLBACK, static::REQ_SCOPE, static::REQ_FIELDS, static::REQ_ORDER, static::REQ_PAGE);
            foreach($noreq as $k) {
                if(isset($req[$k])) unset($req[$k]);
            }
        }
        if(!$req) {
            if(isset($this->options[$this->action.'-filter'])) {
                $req = $this->options[$this->action.'-filter'];
            } else if(!$this->search && isset($this->options['default-filter'])) {
                $req = $this->options['default-filter'];
            }
        }

        if($p=App::request('get', static::REQ_ENVELOPE)) {
            $this->config['envelope'] = (bool)S::raw($p);
            static::$envelope = $this->config['envelope'];
        }
        if($p=App::request('get', static::REQ_PRETTY)) {
            $this->config['pretty'] = (bool)S::raw($p);
            static::$pretty = $this->config['pretty'];
        }
        unset($p);

        $cn = $this->getModel();
        if(isset($this->options['scope']) && is_array($this->options['scope'])) {
            $cn::$schema['scope'] = $this->options['scope'] + $cn::$schema['scope'];
        }

        // this should be deprecated
        if(isset($this->options['messages'])) {
            $this->config += $this->options['messages'];
        }

        if(is_null($this->parent) && $this->action!='list' && ($uid=App::request('get', '_uid')) && $uid!=$this->id) {
            if(!$this->search) $this->search=array();
            $pk = $cn::pk();
            $rq = (is_array($this->key)) ?explode(Model::$keySeparator, $uid, count($this->key)) :[$uid];
            if(is_array($pk) && count($pk)>1) {
                foreach($rq as $i=>$o) {
                    if(!isset($pk[$i])) {
                        $pk[$i]=$pk[$i-1];
                    }
                    $this->search[$pk[$i]] = $o;
                    unset($rq[$i], $i, $o);
                }
            } else if(is_array($pk)) {
                $this->search[array_shift($pk)] = $rq;
            } else {
                $this->search[$pk] = $rq;
            }
        }

        if(($this->action=='list' || !isset($this->id)) && $this->config('displaySearch') &&
            (
                (isset($this->options['search']) && $this->options['search'])
                || (isset($this->actions[$this->action]['query']) && $this->actions[$this->action]['query'])
            )) {
            $this->searchForm($req);
        }

        if(isset($this->options['group-by'])) $this->groupBy = $this->options['group-by'];

        $one = $this->isOne();
        $o = null;
        if($one && isset($this->options['redirect-by-property'])) {
            $redirect = null;
            $redirectKey = null;
            $o = $this->model();
            if(!$o) {
                $one = false;
            } else {
                $o->refresh(array_keys($this->options['redirect-by-property']));
                foreach($this->options['redirect-by-property'] as $fn=>$target) {
                    $v = $o->$fn;
                    if(isset($target[$v]) || ($v && isset($target[$v = '*']))) {
                        if(is_array($target[$v])) {
                            if(isset($target[$v]['action'])) {
                                $ta = $target[$v]['action'];
                                if(!is_array($ta)) $ta = [$ta];
                                if(in_array($this->action, $ta)) {
                                    if(isset($target[$v]['interface'])) {
                                        $redirect = $target[$v]['interface'];
                                        if(isset($target[$v]['key'])) {
                                            $redirectKey = $o[$target[$v]['key']];
                                        }
                                        break;
                                    }
                                }
                            }
                        } else {
                            $redirect = (string)$target[$v];
                            break;
                        }
                    }
                }
            }

            if($redirect) {
                if(!$redirectKey) $redirectKey = implode('-', $o->getPk(true));
                $actionAlias = $this->config('actionAlias');
                $redirect = static::$base
                   . '/'
                   . $redirect
                   . '/'
                   . (($actionAlias && ($aa=array_search($this->action, $actionAlias))) ?$aa :$this->action)
                   . '/'
                   .  urlencode($redirectKey)
                   ;

                $curr = $this->link();
                if($curr!=$redirect) {
                    $this->redirect($redirect, $this->link());
                }
            }
        }

        if($one && method_exists($cn, $m=$this->config('modelRenderPrefix').S::camelize($this->action, true))) {
            $this->getButtons();
            $this->scope((isset($cn::$schema->scope[$this->action]))?($this->action):('preview'));
            if(!$o) $o = $this->model([], 1, false, true);
            $this->text['preview'] = $o->$m($this);
            unset($o);
        } else if(method_exists($this, $m='render'.S::camelize($this->action, true))) {
            $this->getButtons();
            $this->text['preview'] = $this->$m();
        } else {
            $this->getButtons();
            $this->text['summary'] = $this->getSummary();
            $this->getList($req);
        }
        static::status(200);

        unset($req);
        unset($m, $cn);
    }

    public function executeMethod()
    {
        static::$currentAction = $this->action;
        if(!isset($this->text)) $this->text = array();
        $this->text['count'] = $this->count();
        $req = App::request('post') + $this->qs(true);
        if($req) {
            $noreq = array(static::REQ_LIMIT, static::REQ_OFFSET, static::REQ_ENVELOPE, static::REQ_PRETTY, static::REQ_CALLBACK, static::REQ_SCOPE, static::REQ_FIELDS, static::REQ_ORDER, static::REQ_PAGE);
            foreach($noreq as $k) {
                if(isset($req[$k])) unset($req[$k]);
            }
        }
        if(!$req) {
            if(isset($this->options[$this->action.'-filter'])) {
                $req = $this->options[$this->action.'-filter'];
            } else if(!$this->search && isset($this->options['default-filter'])) {
                $req = $this->options['default-filter'];
            }
        }

        $cn = $this->getModel();
        if(isset($this->options['scope']) && is_array($this->options['scope'])) {
            $cn::$schema->scope = $this->options['scope'] + $cn::$schema->scope;
        }
        if($rs=$this->requestScope()) {
            $scope = array('scope::'.$rs);
            unset($rs);
        } else if(isset($cn::$schema->scope[$this->action])) {
            $scope = $cn::$schema->scope[$this->action];
        } else {
            $scope = 'list';
        }
        $this->options['scope'] = $this->scope($scope);

        /*
        // this should be deprecated
        if(isset($this->options['messages'])) {
            $this->config += $this->options['messages'];
        }
        */

        if(($this->action=='list' || !isset($this->id)) && $this->config('displaySearch') &&
            (
                (isset($this->options['search']) && $this->options['search'])
                || (isset($this->actions[$this->action]['query']) && $this->actions[$this->action]['query'])
            )) {
            $this->searchForm($req);
        }

        if(isset($this->options['group-by'])) $this->groupBy = $this->options['group-by'];

        $r = null;
        if(method_exists($o=$this->model, $a='execute'.S::camelize($this->action, true))) {
            if($this->id) {
                // get object
                $o = $this->model([], 1, false, true);
                $r = $o->$a($this, $req);
            } else {
                $r = $o::$a($this, $req);
            }

            if(!isset($this->text['summary'])) {
                $this->text['summary'] = $this->getSummary();
            }
            static::status(200);

            return;
        }

        if(!isset($this->text['buttons'])) {
            $this->getButtons();
        }

        if(!isset($this->text['summary'])) {
            $this->text['summary'] = $this->getSummary();
        }

        if(is_string($r)) {
            $this->text['preview'] = $r;
        }

        static::status(200);

        unset($req);
        unset($m, $cn, $r);
    }

    public function executeInterface()
    {
        return $this->execute();
    }
    public function executeNew()
    {
        return $this->execute();
    }
    public function executeList()
    {
        return $this->execute();
    }
    public function executeReport()
    {
        return $this->execute();
    }
    public function executePreview()
    {
        return $this->execute();
    }
    public function executeUpdate()
    {
        return $this->execute();
    }
    public function executeDelete()
    {
        return $this->execute();
    }

    public function render()
    {
        S::$variables['Interface'] = $this;
        $title = $this->getTitle();
        $link = $this->link();
        if(!$this->action && !$this->run) {
            foreach(static::$actionsDefault as $a) {
                $p1 = S::urlParams(null, true);
                if($this->setAction($a, $p1)) {
                    break;
                }
                unset($a);
            }
            if(!isset(static::$urls[$link])) {
                static::$urls[$link] = array('title'=>$title,'action'=>$this->action);
            }
        }

        static::$currentAction = $this->action;
        if($this->run) {
            $o = $this->run;
            $call = array_shift($o);
            if($this->params) $o[] = $this->params;
            if(is_string($call[0]) && get_class($this)==$call[0]) $call[0] = $this;
            if(isset($this->text['title'])) App::response('title', $this->text['title']);

            $text = S::call($call, $o);
            $this->action = 'text';
            if(!static::$standalone) {
                $this->text['preview'] = $text;
                if(!isset(static::$urls[$link])) {
                    static::$urls[$link] = array('title'=>$title,'action'=>$this->action);
                }
            } else {
                return $text;
            }
        } else if (!$this->action && $this->originalText) {
            $data = null;
            $this->action = 'text';
        } else {
            if(static::$ext) $this->options['extension'] = static::$ext;
            if(isset($this->options['last-modified'])) $this->lastModified();
            if(isset($this->actions[$this->action]['action'])) {
                $action = $this->actions[$this->action]['action'];
                $data = null;
                if(is_array($action) && !isset($action[0]) && isset($action[$m=App::request('method')])) {
                    $action = $action[$m];
                }
                if(is_array($action) && count($action)==2) {
                    list($C, $m) = $action;
                    if(is_string($C) && $C==$this->model && !S::isempty($this->id)) {
                        $C = $this->model();
                    }
                    if(is_string($C)) {
                        $data = $C::$m($this, $this->options, $this->params);
                    } else if($C!=$this) {
                        $data = $C->$m($this, $this->options, $this->params);
                    } else {
                        $data = $this->$m($this->options, $this->params);
                    }
                    unset($C, $m);
                } else if(method_exists($this, $action)) {
                    $data = $this->$action($this->options, $this->params);
                } else {
                    $data = $this->execute($this->options, $this->params);
                }
            } else {
                $data = $this->execute($this->options);
            }
        }

        $f=static::template($this->template, 'api-'.static::$format, 'api-'.$this->action, 'api');
        $vars = $this->text;
        $vars['Interface'] = $this;
        $vars['title'] = $title;
        $vars['url'] = $this->link();//S::scriptName(true);
        $vars['response'] = $data;
        $vars['options'] = $this->options;

        return S::exec(array('script'=>$f, 'variables'=>$vars));
    }

    public static function headers()
    {
        $r = array();
        $headers = ($I=static::current()) ? $I->config('headers') : static::$headers;
        foreach($headers as $k=>$v) {
            $k = S::slug($k);
            $x = true;
            if($k===static::H_LAST_MODIFIED || $k==='location' || $k==='access-control-allow-origin') {
                header($k.': '.$v);
                if($x = defined($c='static::H_'.strtoupper(str_replace('-', '_', $k)))) {
                    $k=constant($c);
                }
            }
            if($x) {
                $v = preg_replace('/[\n\r\t]+/', '', strip_tags((is_array($v))?(implode(';',$v)):($v)));
                @header('x-'.$k.': '.$v);
                $r[] = 'x-'.$k.': '.$v;
            }
        }

        if(static::$status) {
            App::status(static::$status);
        }
    }

    public function lastModified($lmod=null)
    {
        if(!$lmod && isset($this->options['last-modified'])) {
            $def = $this->options['last-modified'];
            if(!is_array($def)) $def = array('field'=>$def);
            if(isset($def['query'])) {
                $R = S::query($def['query']);
                if($R) $lmod = array_shift($R[0]);
                unset($R);
            } else if(isset($def['field'])) {
                $cn = $this->getModel();
                $R = $cn::find($this->search,1,array('max(`'.$def['field'].'`) _m'),false,false,true);
                if($R) $lmod = strtotime($R->_m);
                unset($R, $cn);
            }
        }

        if(!$lmod) $lmod = time();
        if(!is_int($lmod)) $lmod = S::strtotime($lmod);
        if(S::env()!='dev' && isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $if = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            if($if >= $lmod) {
                if(static::$format!='html') {
                    header('content-type: application/'.static::$format.'; charset=UTF-8');
                }
                App::end('', 304);
            }
        }

        if(!isset($this->config['headers'])) $this->config['headers'] = [];
        $this->config['headers'][static::H_LAST_MODIFIED]=gmdate(static::$dateFormat, $lmod);
    }

    protected static $proc, $procTimeout=180;
    public static function workerProcess($proc=null)
    {
        if($proc) {
            self::$proc = $proc;
        } else if($proc===false && self::$proc) {
            Cache::delete(self::$proc);
            self::$proc = null;
        }
        return self::$proc;
    }
    public static function worker($msg=false, $f=false)
    {
        static $s;
        if(!$s && self::$proc) $s = Cache::get(self::$proc, static::$procTimeout);
        if($msg) {
            if(!$s || !is_array($s)) $s = array();
            $t = microtime(true);
            $s['s'] = $t;
            if(isset($s['m']) && $msg && substr($s['m'], 0, strlen($msg))==$msg) $msg = $s['m'].'.';
            $s['l'][$t] = $s['m'] = $msg;
            if($f) $s['f'] = $f;
            if(!Cache::set(self::$proc, $s, static::$procTimeout)) {
                $s = false;
            }
        }
        return $s;
    }

    public function backgroundWorker($m, $prefix='w/', $download=true, $redirect=null, $unload=null)
    {
        if(App::request('headers', 'z-action')==='Interface') {
            $uri = ($redirect && is_string($redirect)) ?$redirect :$this->link();
            $msg = '<a data-action="redirect" data-url="'.S::xml($uri).'"></a>';
            if(!($uid=App::request('headers', 'z-param'))) {
                $end = false;
                // send a status check variable
                $uid = S::compress64(uniqid(md5($uri)));
                static::workerProcess($prefix.$uid);
                $r = $prefix.$uid;
                $st = static::worker($m);
                if($st) {
                    ignore_user_abort(true);
                    $msg = '<a data-action="status" data-url="'.S::xml($uri).'" data-message="'.S::xml($m).'" data-status="'.$uid.'"></a>';

                    if($redirect) {
                        $curl = $this->link();
                        if($uri!=$curl) {
                            $msg = '<a data-action="unload" data-url="'.\S::xml(preg_replace('/\?.*/', '', $curl)).'"></a>'.$msg;
                        } else if(!$unload) {
                            $msg = '<a data-action="load" data-url="'.\S::xml($uri).'"></a>'.$msg;
                        }
                    } else if($unload) {
                        $curl = (is_string($unload)) ?$unload :$this->link();
                        $msg .= '<a data-action="unload" data-url="'.\S::xml(preg_replace('/\?.*/', '', $curl)).'"></a>';
                    }
                }
            } else if(($st=Cache::get($prefix.$uid))) {
                $end = true;
                static::workerProcess($prefix.$uid);
                if(isset($st['f'])) {
                    if($download) {
                        $uri .= ((strpos($uri, '?')!==false)?('&'):('?'))
                             .  '-bgd='.$uid;
                        $a = 'redirect';
                    } else {
                        $a = 'message';
                    }
                    $msg = '<a data-action="'.$a.'" data-url="'.S::xml($uri).'" data-message="'.S::xml($st['m']).'"></a>';
                    // process has ended
                    Cache::delete('sync-qbo/'.$uid);
                } else {
                    $msg = '<a data-action="status" data-url="'.S::xml($uri).'" data-message="'.S::xml($st['m']).'" data-status="'.$uid.'"></a>';
                }
            } else {
                $end = true;
                $msg = '<a data-action="error" data-message="'.S::xml(S::t('There was an error while processing your request. Please try again or contact support.', 'interface')).'"></a>';
            }
            S::output($msg, 'text/html; charset=utf8', $end);
            return $r;
        } else if($download && ($uid=App::request('get', '-bgd')) && ($st=Cache::get($prefix.$uid)) && isset($st['f'])) {
            Cache::delete($prefix.$uid);
            S::download($st['f'], null, preg_replace('/^[0-9]+\.[0-9]+\-/', '', basename($st['f'])), 0, true, false, false);
            unlink($st['f']);
            exit();
        }
    }

    public function renderShare($object = null, $scope = 'share', $class = null, $translate = false, $xmlEscape = true)
    {
        $this->options['scope'] = $this->scope($scope);
        $arguments = $this->source ?: array();

        $newInterface = [
            'base' => $this->text['interface'],
            'title' => $this->text['title'] . ' Shared',
            'owner' => (int)S::getUser()->id,
            'expires' => '',
            'auth' => [
                'credential' => [],
                'user' => [(int)S::getUser()->id],
            ],
            'search' => $this->search,
            'actions' => [
                'list' => true// He needs at least this
            ]
        ];

        $formConfig = [
            'method' => 'post',
            'buttons' => 'Save',
            'fields' => [
                'title' => [
                    'label' => '*Title',
                    'type' => 'text',
                    'value' => $newInterface['title']
                ],
                'expires' => [
                    'label' => '*Expire Date',
                    'type' => 'date',
                    'value' => (new \DateTime('30 days'))->format('Y-m-d')
                ],
            ],
        ];

        $availableCredentials = [];
        foreach($this->getAuth()['credential'] as $credential ) {
            $availableCredentials[$credential] = [
                'label' => ucfirst($credential),
                'value' => $credential
            ];
        }
        if (!empty($availableCredentials)) {
            $formConfig['fields']['auth_credential'] = [
                'label' => '*Credentials',
                'type' => 'checkbox',
                'multiple' => true,
                'choices' => $availableCredentials
            ];
        }

        $availableActions = [];
        foreach ($this->actions as $actionName => $actionConfig) {
            if ($actionName === 'list') {
                continue;
            }
            $availableActions[$actionName] = [
                'label' => $actionConfig['label'] ?: ucfirst($actionName),
                'value' => $actionName
            ];
        }
        if (!empty($availableActions)) {
            $formConfig['fields']['actions'] = [
                'label' => '*Actions',
                'type' => 'checkbox',
                'multiple' => true,
                'choices' => $availableActions
            ];
        }
        $form = new Form($formConfig);
        $this->text['summary'] = 'Sharing query';

        //$fo['c_s_r_f'] = new FormField(array('id'=>'c_s_r_f', 'type'=>'hidden', 'value'=>1234));
        try {
            $post = App::request('post');
            if ($post) {
                if (!$form->validate($post)) {
                    throw new Exception((!$post) ? static::t('errorNoInput') : $form->getError());
                }

                $newInterface['title'] = $post['title'];
                $newInterface['expires'] = $post['expires'];
                $newInterface['auth']['credential'] = array_filter($post['auth_credential']);
                $newInterface['auth'] = array_filter($newInterface['auth']);
                foreach (array_keys($availableActions) as $action) {
                    $newInterface['actions'][$action]= in_array($action, $post['actions'], true);
                }

                $fileName = $newInterface['base'] . date('-Y-m-d-') . S::salt(10);
                Yaml::save(TDZ_VAR . '/api-shared/' . $fileName . '.yml', ['all' => $newInterface]);
                $this->message('<div class="s-msg s-msg-success"><p>Shared interface /a/' . $fileName . ' created.</p></div>');
                $this->redirect("/a/$fileName");
            }
        } catch (Exception $e) {
            S::log('[INFO] User error while processing ' . __METHOD__ . ': ' . $e);
            $this->text['error'] = static::t('newError');
            $this->text['errorMessage'] = $e->getMessage();
            $this->text['summary'] .= '<div class="s-msg s-msg-error"><p>' . $this->text['error'] . '</p>' . $this->text['errorMessage'] . '</div>';
        }

        return $form;
    }

    public function renderReport($o=null, $scope=null, $class=null, $translate=false, $xmlEscape=true)
    {
        $pid = $this->backgroundWorker(S::t('Building report...','interface'), 'irs/', true, false, $this->link($this->action, null, true, false));

        unset($this->text['searchForm']);
        $r=array();
        foreach($this->text as $k=>$v) {
            if(!is_object($v) && !is_array($v))
                $r['{'.$k.'}'] = $v;
            unset($k, $v);
        }
        $this->text['r']=$r;
        unset($r);
        $this->getList();
        $this->text['listLimit']=50000;
    }

    public function download($f, $msg='Download...', $unload=null)
    {
        $fn = preg_replace('/^[0-9\.]+\-/', '', basename($f));
        if(App::request('headers', 'z-action')=='Interface') {
            if($f && file_exists($f)) {
                $uid = uniqid();
                $uri = $this->link(null, true);
                $uri .= (strpos($uri, '?'))?('&'):('?');
                $uri .= '-bgd='.$uid;
                Cache::set('bgd/'.$uid, $f);
                $msg = '<a data-action="download" data-download="'.S::xml($fn).'" data-url="'.S::xml($uri).'" data-message="'.S::xml($msg).'"></a>';
            } else {
                $msg = '<a data-action="error" data-message="'.S::xml(S::t('There was an error while processing your request. Please try again or contact support.','interface')).'"></a>';
            }

            if($unload) {
                $url = (is_string($unload)) ?$unload :$this->link($this->action, $this->id, true, false);
                $msg .= '<a data-action="unload" data-url="'.S::xml($url).'"></a>';
            }

            S::output($msg, 'text/html; charset=utf8', true);
        } else if(($uid=App::request('get', '-bgd')) && ($f=Cache::get('bgd/'.$uid)) && file_exists($f)) {
            Cache::delete('bgd/'.$uid);
            S::download($f, null, $fn, 0, true, false, false);
            unlink($f);
            //exit($f);
        }
    }

    public static function checkRequestScope($rs, $ps)
    {
        if(in_array($rs, $ps)) return true;

        foreach($ps as $s) {
            if(is_string($s) && strlen($s)>strlen($rs) && substr($s, 0, strlen($rs)+1)==$rs.':') {
                if(S::getUser()->hasCredentials(preg_split('/,+/', substr($s, strlen($rs)+1), -1, PREG_SPLIT_NO_EMPTY))) {
                    return true;
                }
            }
        }
    }

    public function requestScope()
    {
        if(($rs=S::slug(App::request('get', static::REQ_SCOPE))) && isset($this->options['scope'][$rs]) && !$this->config('actionsAvailable', $rs)) {
            // check if $this->options['scope'][$this->action] requires authentication
            $r = 'scope::'.$rs;

            $as = $this->action;
            $ad = $this->config('actionsDefault');
            while(!isset($this->options['scope'][$as]) && $ad) {
                $as = array_shift($ad);
            }
            if(isset($this->options['scope'][$as]) && !$this::checkRequestScope($r, $this->options['scope'][$as])) {
                $r = null;
            }
            return $rs;
        }
    }

    public function renderPreview($o=null, $scope=null, $class=null, $translate=false, $xmlEscape=true)
    {
        $cn = $this->getModel();

        if(!$scope) {
            if($rs=$this->requestScope()) {
                $scope = array('scope::'.$rs);
                unset($rs);
            }
        }
        $this->options['scope'] = $this->scope($scope);

        if(!$o) $o = $this->model([], 1, false, true);

        if(!$o) {
            if(static::$format!='html') {
                static::error(404, static::t('previewNoResult'));
            }
            $this->message('<div class="s-msg s-msg-error"><p>'.static::t('previewNoResult').'</p></div>');
            return $this->redirect($this->link(false, false), $this->link());
        }
        if(!$scope && isset($this->options['preview-scope-property']) && ($n=$this->options['preview-scope-property']) && ($rs=S::slug($o->$n)) && isset($cn::$schema->scope[$rs])) {
            $scope = array('scope::'.$rs);
            $this->options['scope'] = $this->scope($scope);
        }

        $this->text['class'] = $class;
        $this->text['xmlEscape'] = $xmlEscape;
        $this->text['summary'] = $this->getSummary();
        return $o;
    }

    public function renderNew($o=null, $scope=null)
    {
        $cn = $this->getModel();
        if(!$scope) {
            if(isset($cn::$schema['scope']['new'])) $scope = 'new';
            else $scope = 'preview';
        }
        //$scope = $this->scope($scope);
        $this->options['scope'] = $this->scope($scope);
        $a=($this->source)?($this->source):(array());
        if($this->config('newFromQueryString') && ($req=App::request('get'))) {
            foreach($req as $fn=>$v) {
                if(!isset($a[$fn]) && !S::isempty($v) && ($cn::$allowNewProperties || property_exists($cn, $fn))) {
                    $a[$fn] = $v;
                }
                unset($req[$fn], $fn, $v);
            }
            unset($req);
        }

        if(!$o) $o = new $cn($a, true, false);
        $fo = $this->getForm($o, $scope);
        //$fo['c_s_r_f'] = new FormField(array('id'=>'c_s_r_f', 'type'=>'hidden', 'value'=>1234));
        try {
            if(($post=App::request('post')) || static::$format!='html') {
                if(!$fo->validate($post) || !$post) {
                    $err = (!$post)?(static::t('errorNoInput')):($fo->getError());
                    if(static::$format!='html') {
                        throw new Exception($err);
                    }
                    if(static::$format!='html') $this->text['error'] = $err;
                    $msg = '<div class="s-msg s-msg-error">'.static::t('newError').'</div>';
                } else {
                    $oldurl = $this->link();
                    $o->save();
                    if(is_array($this->key)) {
                        $this->search = $o->asArray($this->key);
                        $this->id = implode('-', $this->search);
                    } else {
                        $pk = $this->key;
                        $this->id = $o->$pk;
                        $this->search = array($pk=>$this->id);
                    }
                    $next = null;
                    if(isset($this->options['next'])) {
                        if(is_array($this->options['next'])) {
                            if(isset($this->options['next'][$this->action])) {
                                $next = $this->options['next'][$this->action];
                            }
                        } else {
                            $next = $this->options['next'];
                        }
                    }
                    if(!$next && isset($this->actions[$this->action]['next'])) {
                        $next = $this->actions[$this->action]['next'];
                    }
                    if(!$next && ($next=App::request('get','next'))) {
                        if(!isset($this->actions[$next])) $next = null;
                    }
                    $this->text['success'] = sprintf(static::t('newSuccess'), $o::label(), $this->getTitle());
                    $msg = '<div class="s-msg s-msg-success">'.$this->text['success'].'</div>';
                    if($next) {
                        $this->action = $next;
                        $this->message($msg);
                        $this->redirect($this->link(), $oldurl);
                    }
                }
                $this->text['summary'] .= $msg;
            }
            unset($post);
        } catch(Exception $e) {
            S::log('[INFO] User error while processing '.__METHOD__.': '.$e->getMessage());
            $this->text['error'] = static::t('newError');
            $this->text['errorMessage'] = $e->getMessage();
            $this->text['summary'] .= '<div class="s-msg s-msg-error"><p>'.$this->text['error'].'</p>'.$this->text['errorMessage'].'</div>';
        }
        if(static::$standalone && isset($this->text['error']) && $this->text['error'] && is_object($fo)) {
            $fo->before = '<div class="s-msg s-msg-error">'.$this->text['error'].'</div>'.$fo->before;
            $this->text['error'] = null;
        }
        return $fo;
    }

    public function renderGraph()
    {
        if(!($displayGraph=$this->config('displayGraph'))) return;
        $cn = $this->getModel();
        if(!$this->graph && $displayGraph==='auto') {
            // autobuild graph based on index values
            $ckey = 'z-i-graph/'.$cn;
            $this->graph = Cache::get($ckey);
            if(is_array($this->graph)) {
                $columns = $cn::columns('search');
                $this->graph = [];
                foreach($columns as $label=>$fd) {
                    if(!is_array($fd) || !isset($fd['bind']) || preg_match('/^[^a-z_0-9]+$/i', $fd['bind'])) continue;
                    if(isset($fd['choices'])) {
                        $one = $rn = $rd = null;
                        foreach($cn::$schema->relations as $rn=>$rd) {
                            if($rd['local']===$fd['bind'] && !isset($rd['on'])) {
                                $one = ($rd['type']==='one');
                                $rlabel = null;
                                $rcn = (isset($rd['className'])) ?$rd['className'] :$rn;
                                if(isset($rcn::$schema->scope['string']) && ($rsc=$rcn::$schema->scope['string']) && ($rsc0=array_shift($rsc)) && !strpos($rsc0, ' ')) {
                                    $rlabel = $rn.'.'.$rsc0;
                                } else {
                                    foreach($rcn::$schema->properties as $rnk=>$rnd) {
                                        if(!$rnd->primary && $rnd->type=='string') {
                                            $rlabel = $rn.'.'.$rnk;
                                            unset($rnk, $rnd);
                                            break;
                                        }
                                        unset($rnk, $rnd);
                                    }
                                }
                                break;
                            }

                            $rn = $rd = null;
                        }
                        if(!$rn || !$rlabel || is_array($pk=$cn::pk(null, false))) continue;

                        if(isset($fd['label'])) $label = $fd['label'];
                        $n = S::slug($label);
                        if($one) {
                            $this->graph[$n] = [
                                'title'=>$label,
                                'type'=>'donut',
                                'pivot'=>$rlabel,
                                'options'=>['legend'=>['show'=>true]],
                                'axis'=>[
                                    '_x'=>['label'=>$rlabel, 'bind'=>$rlabel.' _x', 'type'=>'string'],
                                    '_y'=>['label'=>$label, 'bind'=>'count(`'.$pk.'`) _y', 'type'=>'number'],
                                ],
                                'group-by'=>$rlabel,
                                'order-by'=>[$rlabel=>'asc'],
                            ];
                        }
                        if(count($this->graph)>=static::$graphAutoMax) break;
                    }
                }
                if(($count=count($this->graph))>1) {
                    foreach($this->graph as $n=>$g) {
                        $this->graph[$n]['class'] = 'g-'.$count.' i1s'.$count;
                    }
                }
                Cache::set($ckey, $this->graph, S::$timeout);
            }
        }
        if(!$this->graph) return;
        $s = '';
        foreach($this->graph as $n=>$g) {
            $g['model'] = $cn;
            $g['id'] = S::slug($this->url).'-'.\S::slug($n);
            if($this->search) $g['where'] = (isset($g['where'])) ?$this->search + $g['where'] :$this->search;
            if(!isset($g['scope'])) $g['scope'] = $n;
            $s .= static::graph($g, $n);
        }

        return $s;
    }

    public static function graph($g, $n=null)
    {
        $cn = $g['model'];
        if(!$n) $n=uniqid('g');
        $s=null;
        $G=(isset($g['options']))?($g['options']) :[];
        $G['data']=[];
        $q = (isset($g['where'])) ?$g['where'] :[];
        $orderBy = (isset($g['order-by'])) ?$g['order-by'] :null;
        $groupBy = true;
        $label = (isset($g['label'])) ?$g['label'] :null;
        if(isset($g['group-by'])) {
            if(is_array($g['group-by'])) {
                if(count($g['group-by'])==1) {
                    $groupBy = implode('', $g['group-by']);
                    if(!$label) $label = implode('', array_keys($g['group-by']));
                } else {
                    $groupBy = array_values($g['group-by']);
                }
            } else {
                $groupBy = $g['group-by'];
            }
        }
        $scope = (isset($g['axis'])) ?$g['axis'] :$n;
        $x = null;
        $pivot = (isset($g['pivot'])) ?$g['pivot'] :null;
        if(is_array($scope) && is_string($groupBy)) {
            if(!$pivot) $G['data']['x']='_x';
            $scope['_x'] = (strpos($groupBy, ' ')) ?$groupBy :$groupBy.' _x';
            if($pivot && !isset($scope[$pivot])) {
                $pivot = '_x';
            }
        }
        $I = static::current();
        if($R=$cn::find($q,null,$scope,false,$orderBy,$groupBy)) {
            //$x = [];
            $cmap = [];
            $kcols = [];
            if(count($R) > $I->config('graphLegendMax') && isset($g['type']) && in_array($g['type'], ['donut','pie']) && isset($G['legend']['show'])) {
                $G['legend']['show'] = false;
            }
            foreach($R as $i=>$o) {
                $d = $o->asArray($scope, null, null);
                if($pivot) {
                    if(!isset($G['data']['columns'])) {
                        $G['data']['columns']=[];
                    }
                    $l = $d[$pivot];
                    if(is_null($l)) $l='';
                    unset($d[$pivot]);
                    /*
                    if(!$x) {
                        $x = array_keys($d);
                        array_unshift($x, '_x');
                    }
                    */
                    foreach($d as $k=>$v) {
                        if(!isset($G['data']['columns'][$i])) $G['data']['columns'][$i] = [$l];
                        $G['data']['columns'][$i][]=$v;
                    }
                } else if(isset($g['columns'])) {
                    foreach($g['columns'] as $ck=>$ca) {
                        if(!isset($d[$ck])) continue;
                        $coln = $d[$ck];
                        foreach($ca as $cak=>$cal) {

                            if(!isset($cmap[$coln])) {
                                $cmap[$coln] = count($cmap)+1;
                            }
                            $xn = $cmap[$coln];

                            $v0 = $cak;
                            $v1 = $cal;

                            $l0 = trim($coln);
                            $l1 = 'x'.$xn;

                            if(!isset($G['data']['xs'][$l0])) $G['data']['xs'][$l0]=$l1;

                            if(isset($d[$v0])) {
                                if(!isset($kcols[$l0])) $kcols[$l0] = [$l0];
                                $kcols[$l0][] = $d[$v0];
                            }
                            if(isset($d[$v1])) {
                                if(!isset($kcols[$l1])) $kcols[$l1] = [$l1];
                                $kcols[$l1][] = $d[$v1];
                            }
                        }
                    }
                } else {
                    if(!isset($G['data']['columns'])) {
                        $G['data']['columns']=[];
                        $h = array_keys($d);
                        foreach($h as $k=>$v) $G['data']['columns'][$k]=[$v];
                    }
                    $h = array_values($d);
                    foreach($h as $k=>$v) $G['data']['columns'][$k][]=$v;
                }
                unset($R[$i], $i, $o, $h);
            }

            if($kcols) {
                if(!isset($G['data']['columns'])) {
                    $G['data']['columns']=array_values($kcols);
                } else {
                    $G['data']['columns']=array_merge($G['data']['columns'], array_values($kcols));
                }
            }
            //if($x) $G['data']['columns'][] = $x;

            $a = [
                'id'=>'z-graph-'.S::slug($g['id']),
                'class'=>'z-graph z-graph--'.$n,
                'data-type'=>(isset($g['type'])) ?$g['type'] :'line',
                'data-label'=>$label,
            ];
            $G['data']['type'] = $a['data-type'];
            $G['bindto'] = '#'.$a['id'];
            if(isset($g['title'])) $a['data-title'] = $g['title'];
            if(isset($g['style'])) $G += $g['style'];
            $a['data-g'] = base64_encode(json_encode($G, JSON_UNESCAPED_SLASHES));
            if(isset($g['class'])) $a['class'] .= ' '.$g['class'];
            $s .= '<div';
            foreach($a as $an=>$av) $s .= ' '.$an.'="'.S::xml($av).'"';
            $s .= '>'
                . '</div>';
            unset($a, $R, $G);
        }

        return $s;
    }

    public function renderUpdate($o=null, $scope=null)
    {
        $cn = $this->getModel();
        if(!$o) $o = $this->model([], 1, false, true);
        if(!$scope) {
            if(($rs=S::slug(App::request('get', static::REQ_SCOPE))) && (isset($this->options['scope'][$rs]) || isset($o::$schema->scope[$rs])) && !$this->config('actionsAvailable', $rs)) {
                $scope = $rs;
                unset($rs);
            } else if(isset($this->options['update-scope-property']) && ($n=$this->options['update-scope-property']) && ($rs=S::slug($o->$n)) && isset($cn::$schema->scope[$rs])) {
                $scope = array('scope::'.$rs);
            } else if(isset($this->options['scope'][$this->action])) {
                $scope = $this->action;
            } else {
                $scope = 'preview';
            }
        }
        //$scope = $this->scope($scope);
        $this->options['scope'] = $this->scope($scope);
        $fo = $this->getForm($o, $scope);
        //$fo['c_s_r_f'] = new FormField(array('id'=>'c_s_r_f', 'type'=>'hidden', 'value'=>1234));
        try {
            // prevent these actions from being marked as updates
            $dontpost = (($za=App::request('headers', 'z-action')) && in_array($za, array('Upload','choices')));

            if(($post=App::request('post')) || static::$format!='html') {
                $msg = '';
                if(!$fo->validate($post) || !$post) {
                    $err = (!$post)?(static::t('errorNoInput')):($fo->getError());
                    if(static::$format!='html') {
                        throw new Exception($err);
                    }
                    $this->text['error'] = static::t('updateError');
                    $msg = '<div class="s-msg s-msg-error">'.$this->text['error'].'</div>';
                } else {
                    $oldurl = $this->link();
                    $pk = implode('-', $o->getPk(true));
                    $o->save();
                    $newpk = implode('-', $o->getPk(true));
                    // success message
                    $this->text['success'] = sprintf(static::t('updateSuccess'), $o::label(), $this->getTitle());
                    $msg = '<div class="s-msg s-msg-success">'.$this->text['success'].'</div>';

                    $next = $url = null;
                    if(!($url=App::request('headers', 'z-interface')) || substr($url, 0, strlen(static::$base)+1)!=static::$base.'/') {
                        if(isset($this->options['next'])) {
                            if(is_array($this->options['next'])) {
                                if(isset($this->options['next'][$this->action])) {
                                    $next = $this->options['next'][$this->action];
                                }
                            } else {
                                $next = $this->options['next'];
                            }
                        }
                        if(!$next && isset($this->actions[$this->action]['next'])) {
                            $next = $this->actions[$this->action]['next'];
                        }
                        if(!$next && ($next=App::request('get','next'))) {
                            if(!isset($this->actions[$next])) $next = null;
                        }

                        if($newpk!=$pk) {
                            $this->id = $newpk;
                            if(!$next) $next = $this->action;
                        }
                        if($next) {
                            $this->action = $next;
                            $url = $this->link();
                        }
                    }

                    if($url) {
                        $this->message($msg);
                        $this->redirect($url, $oldurl);
                    }
                }
                $this->text['summary'] .= $msg;
            } else {
                $this->text['summary'] .= $this->message();
            }
            unset($post);
        } catch(Exception $e) {
            S::log('[INFO] User error while processing '.__METHOD__.': '.$e);
            $this->text['error'] = static::t('updateError');
            $this->text['errorMessage'] = $e->getMessage();
            $this->text['summary'] .= '<div class="s-msg s-msg-error"><p>'.$this->text['error'].'</p>'.$this->text['errorMessage'].'</div>';
        }

        if(static::$standalone && isset($this->text['error']) && $this->text['error'] && is_object($fo)) {
            $fo->before = '<div class="s-msg s-msg-error">'.$this->text['error'].'</div>'.$fo->before;
            $this->text['error'] = null;
        }
        return $fo;
    }

    public function renderDelete($o=null, $scope=null)
    {
        try {
            if(($M = $this->model([], 1, false, true))) {
                $oldurl = $this->link();
                $s = $this->getTitle();
                $M->delete(true);
                $this->text['success'] = sprintf(static::t('deleteSuccess'), $M::label(), $s);
                $msg = '<div class="s-msg s-msg-success"><p>'.$this->text['success'].'</p></div>';

                $next = null;
                if(isset($this->options['next'])) {
                    if(is_array($this->options['next'])) {
                        if(isset($this->options['next'][$this->action])) {
                            $next = $this->options['next'][$this->action];
                        }
                    } else {
                        $next = $this->options['next'];
                    }
                }
                if(!$next) {
                    $next=App::request('get','next');
                }
                if(!$next && isset($this->actions[$this->action]['next'])) {
                    $next = $this->actions[$this->action]['next'];
                }
                if($next && !isset($this->actions[$next])) {
                    if(App::request('headers', 'z-action')=='Interface') {
                        // remove record preview, if  it exists
                        $this->message('<a data-action="unload" data-url="'.S::xml($this->link('preview', true)).'"></a>');
                    }
                    $this->message($msg);
                    $this->redirect($next, $oldurl);
                } else if($next) {
                    if(App::request('headers', 'z-action')=='Interface') {
                        // remove record preview, if  it exists
                        $this->message('<a data-action="unload" data-url="'.S::xml($this->link('preview', true)).'"></a>');
                    }
                    $this->action = $next;
                    $this->message($msg);
                    $this->redirect($this->link($next, false), $oldurl);
                }
                $this->text['summary'] .= $msg;

                return $this->redirect($this->link(false, false), $oldurl);
            }
        } catch(Exception $e) {
            S::log('[INFO] User error while processing '.__METHOD__.': '.$e);
        }
        $msg = static::t('deleteError');
        if(static::$format!='html') {
            $this->message($msg);
            static::error(422, array('error'=>$msg));
        } else {
            $this->message('<div class="s-msg s-msg-error"><p>'.$msg.'</p></div>');
        }
        return $this->redirect($this->link(false, false));
    }

    public function renderSchema($o=null, $scope=null)
    {
        $qs = null;
        if(!$scope) {
            if(($o=S::slug(App::request('get', static::REQ_SCOPE))) && isset($this->options['scope'][$o]) && substr($o, 0, 1)!='_') {
                $qs = '?'.static::REQ_SCOPE.'='.$o;
                $scope = (!$this->config('actionsAvailable', $o)) ?$this->scope($o, false, false, true) :$scope = $this->scope($o);
                unset($o);
            } else if(isset($this->options['scope'][$this->action])) {
                $scope = $this->scope($this->action);
                $o = $this->action;
            } else {
                $scope = $this->scope('review');
                $o = null;
            }
            if(!$scope) {
                return static::error(404, static::t('errorNotFound'));
            }
        }

        $actionAlias = $this->config('actionAlias');
        $a = (in_array($this->params, $actionAlias))?(array_search($this->params, $actionAlias)):($this->params);
        $identified = $this->config('actionsAvailable', $a, 'identified');
        $envelope=App::request('get', static::REQ_ENVELOPE);
        if($envelope) $envelope = ($envelope==='false') ?false :(bool)$envelope;

        if(!is_null($envelope)) {
            $qs = (($qs) ?'&' :'?').static::REQ_ENVELOPE.'='.var_export($envelope, true);
        } else {
            $envelope = $this->config('envelope');
        }
        self::$envelope = false;

        // move this to a Schema::dump($scope, $properties=array($id, title) ) method
        // available scopes might form full definitions (?)
        $cn = $this->getModel();
        $so = $cn::schema($cn, array(), true)->toJsonSchema($scope);
        $S = array(
            '$schema'=>'http://json-schema.org/draft-07/schema#',
            '$id'=>S::buildUrl($this->link().$qs),
            'title'=>(isset($this->text['title']))?($this->text['title']):($cn::label()),
        );
        if(isset($this->text['description'])) {
            $S['description'] = $this->text['description'];
        }
        $S['type']='object';

        if($envelope) {
            $S['properties']=array(
                'status'=>array('type'=>'string'),
                'status-code'=>array('type'=>'number'),
            );
            $S['required']=array('status', 'status-code');
            // add headers
            if($headers = $this->config('headers')) {
                $doNotEnvelope = $this->config('doNotEnvelope');
                foreach($headers as $k=>$v) {
                    if($doNotEnvelope && in_array($k, $doNotEnvelope)) continue;
                    $S['properties'][$k]=array(
                        'type'=>'string',
                    );
                    if(isset($this->options['headers'][$k])) {
                        $S['required'][] = $k;
                        if(!is_string($v)) {
                            $S['properties'][$k]['type']='number';
                        }
                    }
                    unset($k, $v);
                }
            }

            $S['properties'][$this->config('envelopeProperty')] = array(
                'type'=>'array',
                'items'=>$so,
            );
            $S['required'][] = $this->config('envelopeProperty');

        } else if(!$identified) {
            $S['type'] = 'array';
            $S['items'] = $so;
        } else {
            $S += $so;
        }
        S::output($this->toJson($S), 'application/schema+json', false);
        App::end();
    }

    protected function getForm($o=null, $scope=null)
    {
        if(!$o || !($o instanceof Model)) {
            $o = $this->model();
            if(!$o) {
                $this->message('<div class="s-msg s-msg-error"><p>'.static::t('previewNoResult').'</p></div>');
                return $this->redirect($this->link(false, false));
            }
        }
        if(is_null($scope) && $this->action) {
            $scope = $this->action;
        }
        if(!S::isempty($this->id)) {
            if(is_string($scope)) $ss=$scope;
            else if(count($scope)==1 && isset($scope[0]) && substr($scope[0], 0, 7)=='scope::') $ss=substr($scope[0], 7);
        }

        $cn = get_class($o);
        $d = $cn::columns($scope);
        if(!$d) $d = array_keys($cn::$schema['columns']);
        $o->refresh($d);
        $link = $this->link();
        //static::$urls[$link] = static::t('labelUpdate', 'Update').': '.static::$urls[$link];
        if(!isset(static::$urls[$link])) {
            static::$urls[$link] = ['title'=>$this->getTitle()];
        }

        if(!(S::$variables['form-field-template'] = $this->config('updateTemplate'))) {
            S::$variables['form-field-template'] = $this->config('previewTemplate');
        }

        if(($itemreq=App::request('item')) && ($label=array_search($itemreq, $d))!==false) {
            if(is_integer($label)) $label = $cn::fieldLabel($itemreq, false);
            static::$urls[$link]['title'] .= ' ('.$label.')';
            $this->text['title'] = static::$urls[$link]['title'];
            $d = array($label=>$itemreq);
            S::$variables['form-field-template'] = '$INPUT$ERROR';
        }

        $this->text['summary'] = $this->getSummary(static::$urls[$link]['title']);

        $sep = $this->config('headingTemplate');
        foreach($d as $i=>$fn) {
            if(is_array($fn)) {

            } else if(substr($fn, 0, 2)=='--' && substr($fn, -2)=='--') {
                $label = substr($fn, 2, strlen($fn)-4);
                $d[$i] = str_replace(array('$LABEL', '$ID', '$INPUT', '$CLASS', '$ERROR'), array($label, $fn, $label, $i, ''), $sep);
            } else if(strpos($fn, '::')) {
                list($id, $n) = explode('::', $fn, 2);

                if(!isset($this->actions[$n]['relation'])) {
                    unset($d[$i]);
                    continue;
                }
                $I = $this->relation($fn);
                $a = (isset($I->options[$I->action]))?($I->action):('preview');

                $d[$i] = array(
                    'type'=>'form',
                    'bind'=>$this->actions[$n]['relation'],
                    'id'=>$id,
                    'scope'=>$I->scope($a),
                );
                if(isset($this->actions[$n]['title'])) {
                    $d[$i]['label'] = $this->actions[$n]['title'];
                }
                /*

                if(!$I->isOne()) {
                    $r = $I->expand();
                } else {
                    $r = array($I);
                }
                unset($I);
                if($r) {
                    foreach($r as $k=>$v) {
                        $d[$i]
                        $o = $v->model();
                    }
                }
                */
            }
            unset($i, $fn, $label);
        }
        $fo = $o->getForm($d);
        if(preg_match('/\&ajax=[0-9]+\b/', $fo->action)) $fo->action = preg_replace('/\&ajax=[0-9]+\b/', '', $fo->action);
        $fo->id = S::slug($this->text['interface']).'--'.(($o->isNew())?('n'):(S::slug(@implode('-',$o->getPk(true)))));
        $fo->attributes['class'] = $this->config('attrFormClass');
        if($this->action=='update' || $this->action=='new') {
            $fo->buttons['submit']=static::t('button'.ucwords($this->action), ucwords($this->action));
        }
        if(!static::$standalone && isset($ss)) {
            $fo->buttons['button']=array(
                'label'=>static::t('buttonClose', 'Close'),
                'attributes'=>array(
                    'data-action-scope'=>$ss,
                ),
            );
        }
        return $fo;
    }

    public function preview()
    {
        if(static::$format && static::$format!='html') {
            App::response(array('headers'=>array('Content-Type'=>'application/'.static::$format.'; charset=utf-8')));
            App::end($this->render());
        }
        $this->template = 'api-standalone';
        return $this->render();
    }

    protected function renderSub($r)
    {
        $I = $this->relation($r);
        $I->template = 'api-sub';

        if($I->actions[$I->action]['identified'] && !$I->isOne()) {
            $r = $I->expand('render');
            if($r) $s = implode('', $r);
            else $s = false;
            unset($r);
        } else {
            $s = $I->render();
            unset($I);
        }

        return $s;
    }

    protected function relation($r)
    {
        if(strpos($r, '::')!==false) {
            list($id, $n) = explode('::', $r, 2);
        } else {
            $id = $n = $r;
        }
        if(!isset($this->actions[$n]['relation']) && !isset($this->actions[$n]['interface'])) {
            return '';
        }

        $url = $this->url;
        $ic = get_class($this);

        static::$urls[$this->link()] = array('title'=>$this->getTitle(),'action'=>$this->action);

        if(isset($this->actions[$n]['relation'])) {
            $f = $this->search;
            $cn = $this->getModel();
            $rcn = $cn::relate($this->actions[$n]['relation'], $f);
            $a = array(
                'interface'=>(isset($this->actions[$n]['interface']))?($this->actions[$n]['interface']):($rcn::$schema['tableName']),
                'model'=>$rcn,
                'url'=>$this->link($n, $this->id),
                //'action'=>$this->action,
                'relation'=>($this->relation)?($this->relation.'['.$id.']'):($id),
                'enable'=>true,
            );
            unset($rcn, $cn);
        } else {
            $a = array(
                'interface'=>$this->actions[$n]['interface'],
                'url'=>$this->link($n, $this->id),
                'relation'=>(!S::isempty($this->id))?($cn.'#'.$this->id):($cn),
                'enable'=>true,
            );
        }
        $I = new $ic($a, $this);
        unset($a);
        if(isset($f)) {
            $I->source = $f;
            if($I->search) {
                $I->search = $f + $I->search;
            } else {
                $I->search = $f;
            }
        }
        return $I;
    }

    public function expand($call=null)
    {
        if($this->isOne()) {
            return array($this);
        }
        $r = array();

        if($cn=$this->getModel()) {
            $pk = $cn::pk();
            if(!is_array($pk)) $pk=array($pk=>$pk);
            $R = $cn::find($this->search,0,$pk,false,null,$this->groupBy);
            if($R) {
                foreach($R as $k=>$o) {
                    $I = clone $this;
                    $I->search = $o->asArray($pk);
                    $I->id = $o[$I->key];
                    if($call) {
                        if(is_array($call)) {
                            list($c, $m)=$call;
                            if(is_object($c)) $r[] = $c->$m($I);
                            else $r[] = $c::$m($I);
                            unset($I, $c, $m);
                        } else if(method_exists($I, $call)) {
                            $r[] = $I->$call();
                            unset($I);
                        }
                    }
                    if(isset($I)) {
                        $r[] = $I;
                        unset($I);
                    }
                    unset($R[$k], $k, $o);
                }
            }
            unset($R);
        }
        return $r;
    }

    public function getSummary($title=null)
    {
        $cn = $this->getModel();
        if(!$title && ($l=$this->link()) && isset(static::$urls[$l]['title'])) {
            $title = static::$urls[$l]['title'];
        }
        unset($l);
        $a = ucfirst($this->action);
        $s = '<p class="z-i-labels">'
           . ((isset($this->text['title'])) ?'<span class="z-i-label-model">'.S::xml($this->text['title']).'</span>' :'')
           . '<span class="z-i-label-action">'.static::t('label'.$a, $a).'</span>'
           . (($this->id) ?'<span class="z-i-label-uid"><span class="z-i-label-key">'.$cn::fieldLabel($this->key).'</span><span class="z-i-label-id">'.S::xml($this->id).'</span></span>' :'')
           . '<span class="z-i-label-title">'.S::xml($title).'</span>'
           . '</p>';

        if(isset($this->options['summary-'.$this->action]) && $this->options['summary-'.$this->action]) $s .= S::markdown($this->options['summary-'.$this->action]);
        else if(isset($this->options['summary']) && $this->options['summary']) $s .= S::markdown($this->options['summary']);
        unset($cn);
        return $s;

    }

    public function hasAction($n)
    {
        return isset($this->actions[$n]);
    }

    public function getButtons()
    {
        $s = '';
        if(!isset($this->options)) $this->options = array();
        $this->options['checkbox'] = false;
        $this->options['radio'] = false;
        $sn = $this->url;
        // strip id
        if(!S::isempty($this->id)) $sn = substr($sn, 0, strrpos($sn, '/'));
        // strip current action, leave only the model
        $sn = substr($sn, 0, strrpos($sn, '/'));
        $attrButtonClass = $this->config('attrButtonClass');
        if($attrButtonClass) $attrButtonClass = ' '.$attrButtonClass;

        foreach($this->getActions() as $an=>$action) {
            $qs=$this->qs();
            if(!isset($action['position']) || (!$action['position'] && $action['position']!==0)) continue;
            if(!$this->auth($an)) {
                unset($action, $an);
                continue;
            }
            if($an == $this->action) {
                $qs='';
            }
            $An = S::camelize($an, true);
            $label = null;

            if(isset($action['relation'])) {
                $id = true;
                $bt = false;
                if(isset($action['text']['title'])) $label = $action['text']['title'];
            } else {
                $bt = (isset($action['batch']))?($action['batch']):(false);
                $id  = (isset($action['identified']))?($action['identified']):(false);
                if(isset($action['label'])) $label = $action['label'];
            }
            if($label && substr($label, 0, 1)=='*') $label = static::t(substr($label, 1));
            else if(!$label) $label = static::t('label'.$An, $An);
            if($bt && !$this->options['checkbox']) $this->options['checkbox']=true;
            if($id && !$this->options['radio']) $this->options['radio']=true;
            if(!($aa = $this->config('actionAlias', $an))) {
                $aa = $an;
            }
            //$href = ($bt||$id)?('data-url="'.$sn.'/'.$aa.'/{id}'.'"'):('href="'.$sn.'/'.$aa.'"');
            if($id && !S::isempty($this->id)) $sid = $this->id;
            else if($id) $sid = '{id}';
            else $sid = false;

            if(isset($action['on']) && is_array($action['on'])) {
                if(S::isempty($this->id) || !$this->model([], 1, false, true) || !$this->model()->checkObjectProperties($action['on'])) {
                    continue;
                }
            }

            $ac = (isset($action['icon'])) ?'z-i-a '.$action['icon'] :'z-i-a z-i--'.$aa;
            if(static::$standalone) {
                if(preg_match('/^\{[a-z0-9\_\-]+\}$/i', $sid) || $an==$this->action) continue;
                if(!S::isempty($this->id)) {// only show batch or identifiable buttons
                    if(!$id && !$bt) continue;
                } else {
                    if($id) continue;
                }
                if(isset($action['attributes']['target']) && (!$id || !S::isempty($this->id))) {
                    if(isset($action['query']) && $action['query'] && $qs) {
                        $qs = str_replace(',', '%3A', S::xml($qs));
                    } else {
                        $qs = '';
                    }
                    $href = 'href="'.$this->link($an, $sid).$qs.'"';
                } else {
                    $href = 'href="'.S::xml($this->link($an, ($id)?($sid):(false), true, $qs)).'"';
                }
                $s .= '<a '.$href.' class="'.$ac.'">'
                    . '<span class="s-api-label">'
                    . $label
                    . '</span>'
                    . '</a>';
                continue;
            }
            if(isset($action['attributes']['target']) && (!$id || !S::isempty($this->id))) {
                if(isset($action['query']) && $action['query'] && $qs) {
                    $qs = str_replace(',', '%3A', S::xml($qs));
                } else {
                    $qs = '';
                }
                $href = 'href="'.$this->link($an, $sid).$qs.'"';
            } else {
                $href = 'data-url="'.S::xml($this->link($an, ($id)?($sid):(false), true, $qs)).'"';
                if(isset($action['query']) && $action['query'] && $qs) {
                    $href .= ' data-qs="'.str_replace(',', '%3A', S::xml($qs)).'"';
                }
                if(isset($action['attributes']['target'])) {
                    $action['attributes']['data-target']=$action['attributes']['target'];
                    unset($action['attributes']['target']);
                }
            }
            if(isset($action['attributes'])) {
                foreach($action['attributes'] as $k=>$v) {
                    $href .= ' '.S::xml($k).'="'.S::xml($v).'"';
                }
            }

            $ac .= $attrButtonClass
                . (($bt)?(' z-i-a-many'):(''))
                . (($id)?(' z-i-a-one'):(''))
            ;
            $s .= '<a '.$href.' class="'.trim($ac).'">'
                . '<span class="s-api-label">'
                . $label
                . '</span>'
                . '</a>';
            unset($action, $qs, $an, $aa, $An, $bt, $id, $action, $href);
        }
        $this->text['buttons'] = $s;

        unset($s, $sn);
    }

    public function qs($asArray=false)
    {
        static $qs;
        if(is_null($qs)) {
            $qs = array_diff_key(App::request('get'), $this->config('removeQueryString'));
        }

        return ($asArray) ?$qs :http_build_query($qs);
    }

    public function getList($req=array())
    {
        if(!isset($this->text['count'])) $this->text['count']=$this->count();
        if(!isset($this->text['error'])) $this->text['error']=array();
        if(!$this->text['count']) {
            $this->text['error'][] = static::t('listNoResults');
            //$error = '<div class="s-msg s-msg-error"><p>'.static::t('listNoResults').'</p></div>';
            $found = false;
        } else if(isset($this->text['searchCount']) && !$this->text['searchCount']) {
            // $error = '';
            //$error = '<div class="s-msg s-msg-error"><p>'.static::t('listNoSearchResults').'</p></div>';
            $found = false;
        }
        if(!isset($found)) {
            $cn = $this->getModel();
            if(($rs=S::slug(App::request('get', static::REQ_SCOPE))) && isset($this->options['scope'][$rs]) && !isset(static::$actionsAvailable[$rs])) {
                $scope = $this->scope($rs, false, false, true);
                unset($rs);
            } else if(isset($this->options['scope'][$this->action])) {
                $scope = $this->scope($this->action);
            } else {
                $scope = $this->scope('review');
            }
            $order = App::request('get', static::REQ_ORDER);
            if($scope && (isset($scope[0]) || substr(array_keys($scope)[0], 0, 1)=='*') && static::$translate) {
                $nscope = [];
                foreach($scope as $i=>$o) {
                    $fn = $o;
                    if(is_array($fn)) {
                        if(isset($fn['label'])) $i = $fn['label'];
                        if(isset($fn['bind'])) $fn = $fn['bind'];
                        else continue;
                    }
                    if(is_int($i)) {
                        $i = $cn::fieldLabel($fn);
                    } else if(substr($i, 0, 1)=='*') {
                        $i = S::t(substr($i, 1), 'model-'.$cn::$schema->tableName);
                    }
                    $nscope[$i] = $o;
                    unset($scope[$i], $i, $o, $fn);
                }
                $scope = $nscope;
                unset($nscope);
            }
            if($order && preg_match('/^(\!)?(.+)$/', $order, $m) && isset($scope[$m[2]])) {
                $fn = $scope[$m[2]];
                if(is_array($fn)) $fn=$fn['bind'];
                if(strpos($fn, ' ')!==false) $fn = preg_replace('/(\s+as)?\s+[^\s]+$/', '', $fn);
                $order=array($fn=>($m[1])?('desc'):('asc'));
                unset($m, $fn);
            } else if(isset($this->orderBy)) {
                $order=$this->orderBy;
            } else if(isset($this->options['order'])) {
                $order=$this->options['order'];
            } else {
                $order = null;
            }
            $found = $cn::find($this->search,null,$this->scope(null,true,true),true,$order,$this->groupBy);
        }
        if(!$found) {
            $count = 0;
        } else {
            //$error = '';
            $count = $found->count();
            $start = 0;
        }

        if(!isset($this->config['headers'])) $this->config['headers'] = [];
        $this->config['headers'][static::H_TOTAL_COUNT] = $count;
        $listAction = $this->config('listAction');
        $this->options['link'] = ($this->hasAction($listAction))?($this->link($listAction, false, false)):(false);
        $this->text['listLimit'] = (isset($this->options['list-limit']) && is_numeric($this->options['list-limit']))?($this->options['list-limit']):($this->config('hitsPerPage'));
        $p=App::request('get', static::REQ_LIMIT);
        if($p!==null && is_numeric($p) && $p >= 0) {
            $p = (int) $p;
            $this->text['listLimit'] = $p;
            $this->config['headers']['limit'] = $p;
        } else if($count > $this->config('hitsPerPage')) {
            $this->config['headers']['limit'] = $this->config('hitsPerPage');
        }
        if($this->text['listLimit']>static::MAX_LIMIT) {
            $this->text['listLimit'] = static::MAX_LIMIT;
            $this->config['headers']['limit'] = static::MAX_LIMIT;
        }

        $this->text['listOffset'] = 0;
        $p=App::request('get', static::REQ_OFFSET);
        if($p!==null && is_numeric($p)) {
            $p = (int) $p;
            if($p < 0) {
                $p = $p*-1;
                if($p > $count) $p = $p % $count;
                if($p) $p = $count - $p;
            }
            $this->text['listOffset'] = $p;
            $this->config['headers']['offset'] = $p;
        } else if(($pag=App::request('get', static::REQ_PAGE)) && is_numeric($pag)) {
            if(!$pag) $pag=1;
            $this->text['listOffset'] = (($pag -1)*$this->text['listLimit']);
            if($this->text['listOffset']>$count) $this->text['listOffset'] = $count;
            $this->config['headers']['offset'] = $this->text['listOffset'];
        } else if(isset($this->config['headers']['limit'])) {
            $this->config['headers']['offset'] = $this->text['listOffset'];
        }
        if(isset($scope)) {
            if($f=App::request('get', static::REQ_FIELDS)) {
                if(!is_array($f)) $f=explode(',',$f);
                foreach($scope as $k=>$v) {
                    if(!in_array($k, $f)) unset($scope[$k]);
                    unset($v, $k);
                }
            }
            $this->options['scope'] = $scope;
            unset($scope);
        }
        $this->text['list'] = $found;
        if(isset($this->options['list-key'])) $this->text['key'] = $this->options['list-key'];
        unset($found, $s, $start, $count, $cn, $order, $error);
        return $this->text['list'];
    }

    public function model($req=array(), $max=1, $collection=false, $setId=null)
    {
        static $current=[];
        $cn = $this->getModel();
        $order = null;
        if(isset($req['o']) && preg_match('/^[a-z0-9\.\_]+$/i', $req['o'])) {
            $order=array($req['o']=>(isset($req['d']) && $req['d']=='desc')?('desc'):('asc'));
        } else if(isset($this->orderBy)) {
            $order=$this->orderBy;
        } else if(isset($this->options['order'])) {
            $order=$this->options['order'];
        }
        if(isset($this->options['scope'][$this->action])) {
            $a = $this->action;
        } else if($max==1) {
            $a = 'preview';
        } else {
            $a = 'review';
        }

        if($max==1 && !S::isempty($this->id)) {
            $key = $cn.'::'.$this->id.'::'.$a;
            if(isset($current[$key])) {
                return $current[$key];
            }
        }

        $r = $cn::find($this->search,$max,$this->scope($a,true,true),$collection,$order,$this->groupBy);

        if($max==1 && S::isempty($this->id) && $setId && $r) {
            $this->id = implode(',', $r->getPk(true));
        }
        if($max==1 && !S::isempty($this->id)) {
            $current[$cn.'::'.$this->id.'::'.$a] = $r;
        }

        return $r;
    }

    public function scope($a=null, $clean=false, $pk=false, $expand=null)
    {
        if(!is_null($a)) {
            if(is_array($a)) {
                $this->scope = $a;
                $a = null;
            }
        }
        $cn = $this->getModel();
        if(is_null($this->scope) || $a) {
            if(!$a) $a = $this->action;
            $this->scope = $a;
            if(!is_array($a)) {
                if(isset($this->options['scope'][$a.'-'.static::$format])) {
                    $scope = $a . '-' . static::$format;
                    $this->scope = $this->options['scope'][$a.'-'.static::$format];
                } else if(isset($this->options['scope'][$a])) {
                    $scope = $a;
                    $this->scope = $this->options['scope'][$a];
                } else if(isset($cn::$schema->scope[$a])) {
                    $scope = $a;
                    $this->scope = $cn::$schema->scope[$a];
                } else {
                    $scope = null;
                }
                if($scope && !isset($cn::$schema->scope[$scope])) {
                    $cn::$schema->scope += $this->options['scope'];
                }
                unset($scope);
            }
            if(!is_array($this->scope)) $this->scope = $cn::columns($this->scope);
        }
        if(($rs=S::slug(App::request('get', static::REQ_SCOPE))) && substr($rs, 0, 1)!='_' && is_array($this->scope)) {
            if(in_array('scope::'.$rs, $this->scope) || in_array('sub::'.$rs, $this->scope)) {
                $scope = array('scope::'.$rs);
            }
        }
        if(is_array($this->scope)) {
            static $propMap=array(
                'groupBy'=>true,
                'orderBy'=>true,
                'search'=>true,
                'format'=>false,
                'hitsPerPage'=>false,
            );
            foreach($this->scope as $k=>$v) {
                if(is_array($v) && isset($v['credential'])) {
                    if(!isset($U)) $U=S::getUser();
                    if(!$U || !$U->hasCredentials($v['credential'], false)) continue;
                }
                if(substr($k, 0, 10)=='Interface:') {
                    $p = substr($k, 10);
                    if(isset($propMap[$p])) {
                        if($propMap[$p]) {
                            if(is_array($this->$p) && is_array($v)) {
                                $this->$p = array_merge($this->$p, $v);
                            } else {
                                $this->$p = $v;
                            }
                        } else {
                            $this->config[$p] = $v;
                        }
                    }
                    unset($this->scope[$k], $p, $k, $v);
                }
            }
        }
        if($pk && $this->groupBy) {
            $pk = $cn::pk();
            if(!is_array($pk)) $pk = array($pk);
            if(!isset($scope)) $scope = $this->scope;
            foreach($pk as $k) {
                if(!in_array($k, $scope)) array_unshift($scope, $k);
            }
        }
        if(isset($scope)) return $scope;

        if($expand && is_array($this->scope)) {
            $r = array();
            foreach($this->scope as $k=>$v) {
                if(is_string($v) && substr($v, 0, 7)=='scope::') {
                    $v = substr($v, 7);
                    if(strpos($v, ':')) {
                        $c = preg_split('/[\s\,\:]+/', substr($v, strpos($v, ':')+1), -1, PREG_SPLIT_NO_EMPTY);
                        $v = substr($v, 0, strpos($v, ':'));
                        if(!isset($U)) $U = S::getUser();
                        if($c && (!$U || !$U->hasCredential($c, false))) continue;
                    }
                    if(isset($this->options['scope'][$v])) {
                        $r += $this->options['scope'][$v];
                    }
                    continue;
                }
                $r[$k] = $v;
                unset($k, $v);
            }
            $this->scope = $r;
            return $r;
        }
        /*
        if($clean) {
            $scope = $this->scope;
            foreach($scope as $k=>$v) {
                if(!is_string($v) || strpos($v, '::')!==false || (substr($v, 0, 2)=='--' && substr($v, -2)=='--')) {
                    unset($scope[$k]);
                }
                unset($k, $v);
            }
            return $scope;
        }
        */
        return $this->scope;
    }

    public function count()
    {
        $r = 0;
        if(!$this->searchError && ($cn=$this->getModel())) {
            $Q = null;
            if(method_exists($cn, 'queryHandler')) {
                $Q = $cn::queryHandler();
            }

            if(!S::isempty($this->id) && !S::isempty($this->key)) {
                if(is_string($this->key)) {
                    $this->search[$this->key] = $this->id;
                } else {
                    foreach($this->key as $k) {
                        if(isset($this->id[$k])) $this->search[$k] = $this->id[$k];
                    }
                }
            }

            if($Q && method_exists($Q, 'count')) {
                if($this->search) {
                    $Q->reset();
                    $Q->where($this->search);
                }
                $r = $Q->count();
            } else {
                $pk = $cn::pk(null, true);
                $R = $cn::find($this->search,null,$pk,true,false,true);
                if($R) $r = $R->count();
                unset($R);
            }
            unset($Q);
        }
        return $r;
    }

    public function searchForm($post=array(), $render=true)
    {
        foreach(array(static::REQ_ORDER, static::REQ_PAGE) as $p) {
            if(isset($post[$p])) unset($post[$p]);
        }
        if(!$post) {
            if(isset($this->options[$this->action.'-filter'])) {
                $post = $this->options[$this->action.'-filter'];
            }
        }
        $cn = $this->getModel();
        $scope = (isset($cn::$schema['scope']['search']))?($cn::$schema['scope']['search']):('review');
        if(!is_array($scope)) $scope = $cn::columns($scope);
        $fns = array();
        $ff=array();
        $dest = (isset($this->actions[$this->action]['query']) && $this->actions[$this->action]['query'])?($this->action):('list');
        $fo=array(
            'class'=>$this->config('attrFormClass').' tdz-auto '.$this->config('attrSearchFormClass').' tdz-no-empty tdz-simple-serialize',
            'method'=>'get',
            'limits'=>false,
            'action'=>$this->link($dest, false),
            'buttons'=> false,
            //'model' => new $cn,
            'fields'=> [
                '_omnibar' => [
                    'type' => 'search',
                    'template' => 'input',
                    'attributes'=>['data-omnibar'=>'q', 'name'=>'','class' => 'z-omnibar',],
                ],
            ],
        );
        $model = new $cn;
        $fieldset = static::t('Search options');
        $active = false;
        $noq = false;
        $scopes = 1;
        if(isset($this->searchError)) $this->searchError = null;
        if(isset($scope['q']) && is_array($scope['q'])) {
            $scopes++;
            $addScope = array($scope);
            $scope = $scope['q'];
            unset($addScope[0]['q']);
        }
        while($scopes-- > 0) {
            foreach($scope as $k=>$fn) {
                $label = $k;
                $fd0 = null;
                if(is_array($fn)) {
                    $fd0 = $fn;
                    if(isset($fd0['bind'])) {
                        $fn = $fd0['bind'];
                        unset($fd0['bind']);
                    } else {
                        $fn=null;
                    }
                    if(isset($fd0['label'])) $label = $fd0['label'];
                }

                if(strpos($fn, ' ')) {
                    $fn = preg_replace('/\s+_[a-z0-9\_]+$/', '', $fn);
                    $scope[$k]=$fn;
                }

                if(substr($label, 0, 1)=='*' && static::$translate) {
                    $label = S::t(substr($label, 1), 'model-'.$cn::$schema->tableName);
                } else if(is_int($label)) {
                    $label = $cn::fieldLabel($fn);
                }
                $fd = $cn::column($fn, true, true);
                if(!$fd) {
                    $fd = array('type'=>'text');
                }
                if(isset($fd0)) {
                    if(isset($fd0['type']) && !isset($fd0['format'])) $fd0['format'] = $fd0['type'];
                    $fd = $fd0 + (array)$fd;
                }
                unset($fd0);
                $slug=S::slug($label);
                $fns[$slug]=$fn;
                if(!isset($fd['type'])) $fd['type']='string';
                if(!isset($fd['format'])) $fd['format'] = $fd['type'];
                $type = $fd['format'];

                if(substr($type,0,4)=='date') {
                    $fo['fields'][$slug.'-0']=array(
                        'type'=>'string',
                        'format'=>$type,
                        'label'=>$label,
                        'id'=>$slug.'-0',
                        'placeholder'=>static::t('From'),
                        'fieldset'=>$fieldset,
                        'class'=>'tdz-search-input tdz-date tdz-date-from tdz-'.$type.'-input',
                    );
                    $fo['fields'][$slug.'-1']=array(
                        'type'=>'string',
                        'format'=>$type,
                        'label'=>'',
                        'id'=>$slug.'-1',
                        'placeholder'=>static::t('To'),
                        'fieldset'=>$fieldset,
                        'class'=>'tdz-search-input tdz-date tdz-date-to tdz-'.$type.'-input',
                    );
                    $ff[$slug]='date';
                    if(isset($post[$slug.'-0']) || isset($post[$slug.'-1'])) $active = true;
                    else if(isset($post[$slug])) {
                        if(is_array($post[$slug])) {
                            if(implode('', $post[$slug])) {
                                $active = true;
                                if(isset($post[$slug][0])) {
                                    $post[$slug.'-0'] = $post[$slug][0];
                                    unset($post[$slug][0]);
                                }
                                if(isset($post[$slug][1])) {
                                    $post[$slug.'-1'] = $post[$slug][1];
                                    unset($post[$slug][1]);
                                }
                            }
                        } else if($post[$slug]) {
                            $active = true;
                            @list($post[$slug.'-0'], $post[$slug.'-1']) = preg_split('/\s*[\,\~]\s*/', $post[$slug], 2);
                            unset($post[$slug]);
                        }
                    }
                } else if(isset($fd['choices'])) {
                    $ff[$slug]='choices';
                    if(!in_array($type, ['select', 'checkbox', 'radio'])) $type = 'checkbox';
                    if($fd['choices'] && is_string($fd['choices']) && isset($cn::$schema['relations'][$fd['choices']]['className'])) {
                        $fd['choices'] = $cn::$schema['relations'][$fd['choices']]['className'];
                    } else if(is_string($fd['choices']) && ($m=$fd['choices']) && method_exists($model, $m)) {
                        $fd['choices']=$model->$m();
                    } else if(is_string($fd['choices']) && isset($fd['className']) && $fd['className']!=get_class($model) && method_exists($fd['className'], $m)) {
                        $fd['choices']=(new $fd['className'])->$m();
                    }

                    $fo['fields'][$slug]=array(
                        'format'=>$type,
                        'choices'=>$fd['choices'],
                        'multiple'=>((isset($fd['multiple']) && $fd['multiple']) || $type=='checkbox'),
                        'label'=>$label,
                        'placeholder'=>$label,
                        'fieldset'=>$fieldset,
                        'class'=>'tdz-search-input tdz-'.$type.'-input',
                    );
                    if(isset($fd['attributes'])) $fo['fields'][$slug]['attributes'] = $fd['attributes'];
                    if(isset($post[$slug])) $active=true;
                } else if($type=='bool' || $fd['type']=='bool' || (isset($fd['foreign']) || (($fdo = $cn::column($fn)) && isset($fdo['type']) && $fdo['type']=='bool'))) {
                    if(!isset($cb))
                        $cb=array('1'=>static::t('Yes'), '-1'=>static::t('No'));
                    $fo['fields'][$slug]=array(
                        'format'=>'checkbox',
                        'choices'=>$cb,
                        'label'=>$label,
                        'multiple'=>true,
                        'fieldset'=>$fieldset,
                        'class'=>'tdz-search-input tdz-check-input',
                    );
                    $ff[$slug]='bool'.((isset($fdo) && $fdo['type']!='bool')?('-rel-'.$fd['className']):('bool'));
                    if(isset($post[$slug])) {
                        $active = true;
                        if(!is_array($post[$slug]) && !isset($cb[$post[$slug]])) {
                            $post[$slug] = (S::raw($post[$slug]))?('1'):('-1');
                        }
                    }
                } else if($noq || (isset($fd['filter']) && $fd['filter'])) {
                    $ff[$slug]='choices';
                    $fo['fields'][$slug]=array(
                        'format'=>'text',
                        'size'=>'200',
                        'label'=>$label,
                        'fieldset'=>$fieldset,
                        'class'=>'tdz-search-input'
                    );
                    if(isset($fd['multiple']) && $fd['multiple']) $fo['fields'][$slug]['multiple']=true;
                    if(isset($post[$slug])) $active = true;
                } else {
                    $ff['q'][$slug]=$label;
                    if(!isset($fo['fields']['q'])) {
                        if(count($fo['fields'])>1) {
                            $fo['fields'] = ['_omnibar'=>$fo['fields']['_omnibar'], 'q'=>[],'w'=>[]] + $fo['fields'];
                        }
                        $fo['fields']['q']=array(
                            'format'=>'text',
                            'size'=>'200',
                            'label'=>'',
                            'placeholder'=>static::t('Search for'),
                            'fieldset'=>$fieldset,
                            'class'=>'tdz-search-input',
                            'attributes'=>['data-always-send'=>1],
                        );
                    } else {
                        if(!isset($fo['fields']['w'])) {
                            if(count($fo['fields'])>2) {
                                $fo['fields'] = ['_omnibar'=>$fo['fields']['_omnibar'], 'q'=>$fo['fields']['q'],'w'=>[]] + $fo['fields'];
                            }
                            $fo['fields']['w']=array(
                                'type'=>'string',
                                'format'=>'checkbox',
                                'choices'=>$ff['q'],
                                'label'=>static::t('Search at'),
                                'multiple'=>true, 'fieldset'=>$fieldset,
                                'class'=>'tdz-search-input tdz-check-input',
                                'attributes'=>['data-omnibar-alias'=>'in'],
                            );
                        } else {
                            $fo['fields']['w']['choices'][$slug]=$label;
                        }
                    }
                    if(isset($post['q'])) $active = true;
                }
                unset($scope[$k], $k, $fd, $fdo, $label, $fn, $slug);
            }

            if(isset($fo['fields']['w']) && !$fo['fields']['w']) unset($fo['fields']['w']);
            if($scopes > 0) {
                if(isset($addScope) && $addScope) {
                    $scope = array_shift($addScope);
                    $noq = true;
                } else {
                    break;
                }
            }
        }
        $islug = S::slug($this->text['interface']);
        $fo['fields'] += [
            '_submit' => [
                'type' => 'submit',
                'value'=> '<span class="i-label">'.static::t('Search').'</span>',
                'html_labels'=>true,
                'class' => 'z-i--search',
            ],
            '_options' => [
                'type' => 'button',
                'value'=> '<span class="i-label">'.static::t('Search options').'</span>',
                'html_labels'=>true,
                'class' => 'z-i--filter',
                'attributes'=>[ 'data-display-switch'=>'#q-'.$islug.' .z-omnibar|#q-'.$islug.' fieldset,#q-'.$islug.' button .i-label'],
            ],
        ];
        $F = new Form($fo);
        if(!$F->id) $F->id = 'q-'.$islug;
        if($active && $F->validate($post)) {
            $d=$F->getData();
            $this->text['searchTerms'] = '';
            $S = [];
            if(is_null($this->search)) $this->search = array();
            foreach($d as $k=>$v) {
                if($v==='' || is_null($v)) continue;
                if($k=='q') {
                    if(!$v) continue;
                    $w = (isset($d['w']))?($d['w']):(array_keys($ff['q']));
                    if(!is_array($w)) $w = preg_split('/\,/', $w, -1, PREG_SPLIT_NO_EMPTY);
                    $ps = $S;
                    $S = array();
                    foreach($w as $slug) {
                        $S['|'.$fns[$slug].'%=']=$v;
                        $this->text['searchTerms'] .= (($this->text['searchTerms'])?(' '.static::t('or').' '):(''))
                                        . '<span class="'.$this->config('attrParamClass').'">'.$ff['q'][$slug].'</span>';
                    }
                    $S += $ps;
                    unset($ps);
                    if($this->text['searchTerms']) $this->text['searchTerms'] .= ': <span class="'.$this->config('attrTermClass').'">'.S::xml($post['q']).'</span>';
                    continue;
                } else if($k=='w') continue;

                if(!isset($ff[$k]) && substr($k, -2, 1)=='-' && isset($ff[$k1=substr($k, 0, strrpos($k, '-'))])) {
                    $type = substr($ff[$k1], 0, 4);
                    $k0 = $k;
                    $k = $k1;
                    unset($k1);
                } else if(!isset($ff[$k])) {
                    continue;
                } else {
                    $k0 = $k;
                    $type = substr($ff[$k], 0, 4);
                }

                if(is_object($F[$k0]) && $F[$k0]->multiple && !is_array($v)) {
                    $v = explode(',', $v);
                } else if($type=='date' && preg_match('/[\<\>=]+$/', $fns[$k])) {
                    $ff[$k] = $type = 'choices';
                    if(preg_match('/^[\-\+]/', $v) && ($dt=strtotime($v))) {
                        $v = date('Y-m-d\TH:i:s', $dt);
                        unset($dt);
                    }
                }

                if($type=='bool') {
                    $c0=(in_array('-1', $v))?(true):(false);
                    $c1=(in_array('1', $v))?(true):(false);
                    if($c0 && $c1) { // do nothing
                    } else if($c0||$c1) {
                        if((substr($ff[$k], 0, 8)=='bool-rel-' && ($rcn=substr($ff[$k], 8)))||(isset($cn::$schema['relations'][$fns[$k]]) && ($rcn=$cn::$schema['relations'][$fns[$k]]))) {
                            if(is_array($rcn)) {
                                $rcn = (isset($rcn['className']))?($rcn['className']):($fns[$k]);
                            }
                            $fk=$rcn::pk();
                            if(is_array($fk)) $fk=array_shift($fk);
                            if($c1) $fk.='!=';
                            $S[$fns[$k].'.'.$fk]='';
                        } else {
                            if($c1) $S[$fns[$k].'!=']='';
                            else $S[$fns[$k]]='';
                        }
                        $this->text['searchTerms'] .= (($this->text['searchTerms'])?('; '):(''))
                                    . '<span class="'.$this->config('attrParamClass').'">'.$F[$k0]->label.'</span>: '
                                    . '<span class="'.$this->config('attrTermClass').'">'.(($c1)?(static::t('Yes')):(static::t('No'))).'</span>';
                    }
                } else if($ff[$k]=='choices') {
                    if(S::isempty($v) || !is_object($F[$k0])) continue;
                    $S[$fns[$k]] = $v;
                    $this->text['searchTerms'] .= (($this->text['searchTerms'])?('; '):(''))
                                . '<span class="'.$this->config('attrParamClass').'">'.$F[$k0]->label.'</span>: '
                                . '<span class="'.$this->config('attrTermClass').'">'.$cn::renderAs($v, $fns[$k], ((isset($fo['fields'][$k]))?($fo['fields'][$k]):(null))).'</span>';
                } else if($type=='date') {
                    $t0=$t1=false;
                    if(isset($d[$k.'-0']) && $d[$k.'-0']) {
                        $t0 = S::strtotime($d[$k.'-0']);
                        unset($d[$k.'-0']);
                    }
                    if(isset($d[$k.'-1']) && $d[$k.'-1']) {
                        $t1 = S::strtotime($d[$k.'-1']);
                        unset($d[$k.'-1']);
                    }
                    if($ff[$k]=='datetime') {
                        $df = 'Y-m-d\TH:i:s';
                        $dt = true;
                    } else {
                        $df = 'Y-m-d';
                        $dt = false;
                    }
                    if ($t0 && $t1) { // $cn::renderAs($v, $fns[$k])
                        $S[$fns[$k].'~']=array(date($df, $t0), date($df, $t1));
                        $this->text['searchTerms'] .= (($this->text['searchTerms'])?('; '):(''))
                                . '<span class="'.$this->config('attrParamClass').'">'.$F[$k.'-0']->label.'</span>: '
                                . '<span class="'.$this->config('attrTermClass').'">'.S::dateDiff($t0, $t1, $dt).'</span>';
                    } else if($t0) {
                        $S[$fns[$k].'>=']=date($df, $t0);
                        $this->text['searchTerms'] .= (($this->text['searchTerms'])?('; '):(''))
                                . '<span class="'.$this->config('attrParamClass').'">'.$F[$k.'-0']->label.'</span>: '
                                . '<span class="'.$this->config('attrTermClass').'">' . static::t('from').' '.S::date($t0, $dt).'</span>';
                    } else if($t1) {
                        $S[$fns[$k].'<=']=date($df, $t1);
                        $this->text['searchTerms'] .= (($this->text['searchTerms'])?('; '):(''))
                                . '<span class="'.$this->config('attrParamClass').'">'.$F[$k.'-0']->label.'</span>: '
                                . '<span class="'.$this->config('attrTermClass').'">' . static::t('to').' '.S::date($t1, $dt).'</span>';
                    }
                }
            }

            $this->search[] = $S;
            $this->text['searchCount'] = $this->count();
        } else if($active) {
            $this->searchError = $F->getError(true);
            $this->text['searchCount'] = 0;
        }
        $this->text['searchForm'] = $F;
        return (isset($this->text['searchCount']))?($this->text['searchCount']):($this->text['count']);
    }

    public static function listInterfaces($base=null, $array=false, $checkAuth=true)
    {
        if(!is_null($base)) static::$base = $base;
        else if(is_null(static::$base)) static::$base = S::scriptName();
        $Is = static::find(null, $checkAuth);
        $ul = array();
        $pp=array();
        $pl=array();
        foreach($Is as $k=>$I) {
            if(isset($I['options']['navigation']) && !$I['options']['navigation']) continue;
            if(!isset($I['title']) || !$I['title']) {
                $m = $I['model'];
                $I['title'] = $m::label();
                unset($m);
            } else if(static::$translate && substr($I['title'], 0, 1)=='*') {
                $I['title'] = static::t(substr($I['title'], 1));
            }
            $p = str_pad((isset($I['options']['priority']))?($I['options']['priority']):(''), 5, '0', STR_PAD_LEFT).S::slug($I['title']);
            if(isset($I['options']['list-parent'])) {
                if(!$I['options']['list-parent']) {
                    unset($Is[$k], $I, $k, $p, $m);
                    continue;
                }
                $pl[$p] = $I['options']['list-parent'];
            }
            $pp[$k] = $p;
            $ul[$p][0]='<a href="'.static::$base.'/'.$I['interface'].'">'.S::xml($I['title']).'</a>';
            if(isset($I['options']['list-parent'])) {
                $pl[$p] = $I['options']['list-parent'];
            }
            unset($Is[$k], $I, $k, $p, $m);
        }
        ksort($ul);
        ksort($pl);
        foreach($pl as $p=>$k) {
            if(isset($pp[$k])) {
                $ul[$pp[$k]][1][$p]=&$ul[$p];
                $ul[$pp[$k]][2]=$k;
            }
            unset($p, $k);
        }
        $s = '';
        foreach($ul as $k=>$v) {
            if(!isset($pl[$k])) {
                $s .= self::_li($v);
            }
            unset($ul[$k], $v, $k);
        }
        unset($ul, $pl);
        if($s) {
            $s = '<ul>'.$s.'</ul>';
            return $s;
        } else {
            return false;
        }
    }

    protected static function _li($o)
    {
        if(isset($o[1])) {
            $s = '<li id="z-nav-'.S::slug($o[2]).'" class="z-children z-toggle-active" data-toggler-options="child,storage">'.$o[0].'<ul>';
            foreach ($o[1] as $k=>$v) {
                $s .= self::_li($v);
                unset($o[1][$k], $k, $v);
            }
            $s .= '</ul>';
        } else {
            $s = '<li>'.$o[0];
        }
        $s .= '</li>';
        return $s;
    }

    public static function find($q=null, $checkAuth=true)
    {
        if($q) {
            if(is_string($q)) return array(static::loadInterface($q));
        }
        $Is = array();
        $dd = App::config('app', 'data-dir');
        $da = (!static::$authDefault)?(true):(static::checkAuth(static::$authDefault));
        $base = static::base();
        foreach(static::$dir as $d) {
            $b0 = ((substr($d, 0, 1)!='/')?($dd.'/'):('')).$d;
            $b = $b0.$base.'/*.yml';
            $L = glob($b);
            if(static::$baseMap && isset(static::$baseMap[$base])) {
                $bm=static::$baseMap[$base];
                foreach($bm as $nbase) {
                    $b = $b0.$nbase.'/*.yml';
                    $nL = glob($b);
                    if($nL) {
                        if(!$L) $L = $nL;
                        else $L = array_merge($L, $nL);
                    }
                    unset($nbase, $nL);
                }
                if($L) $L = array_unique($L);
            }
            if(!$L) continue;
            foreach($L as $i) {
                $a = basename($i, '.yml');
                $I = static::loadInterface($a);
                if(isset($I['enable']) && !$I['enable']) {
                    $I = null;
                } else if($checkAuth) {
                    if(isset($I['auth'])) {
                        if(!static::checkAuth($I['auth'])) {
                            $I = null;
                        }
                    } else if(!$da) {
                        $I=null;
                    }
                }
                if($I) {
                    $Is[$a] = $I;
                }
                unset($I, $i, $a);
            }
        }
        if(Studio::config('enable_interface_index')) {
            if($L = Interfaces::find($q,null,null,false)) {
                foreach($L as $i=>$o) {
                    if($o->indexed) {
                        if($f = $o->cacheFile()) {
                            $a = S::config($f, S::env());
                        }
                        $oid = basename($f, '.yml');
                        if(isset($Is[$oid])) {
                            $Is[$oid] = $a + $Is[$oid];
                        } else {
                            $Is[$oid] = $a;
                        }
                    }
                    unset($L[$i], $i, $o);
                }
            }
        }

        return $Is;
    }

    public static function configFile($s, $skip=[])
    {
        static $dd;
        if(is_null($dd)) $dd = App::config('app', 'data-dir');

        if(Studio::config('enable_interface_index') && ($r=Interfaces::findCacheFile($s)) && !in_array($r, $skip)) {
            return $r;
        }

        $s = S::slug($s, '/_',true);
        if(!is_array($skip)) $skip = [];
        $base = static::base();
        $bm = (static::$baseMap && isset(static::$baseMap[$base])) ?static::$baseMap[$base] :null;
        foreach(static::$dir as $d) {
            $b0 = ((substr($d, 0, 1)!='/')?($dd.'/'):('')).$d;
            /*
            if(file_exists($f=$b0.'/'.$s.'.yml')) {
                return $f;
            }
            */
            if (file_exists($f=$b0.$base.'/'.$s.'.yml') && !in_array($f, $skip)) {
                return $f;
            }
            if($bm) {
                foreach($bm as $nbase) {
                    if(file_exists($f=$b0.$nbase.'/'.$s.'.yml') && !in_array($f, $skip)) {
                        return $f;
                    }
                    unset($nbase);
                }
            }
            unset($d, $b0);
        }

        unset($s);
    }

    public static function loadInterface($a=array(), $prepare=true)
    {
        if(!is_array($a) && $a) {
            $a = array('interface'=>$a);
        }
        if(isset($a['interface']) && $a['interface']) {
            $not = [];
            if($f = static::configFile($a['interface'])) {
                $not[] = $f;
                $cfg = S::config($f, S::env());
                if($cfg) $a += $cfg;
                unset($cfg);
            }
            if(isset($a['base'])) {
                $i=3;
                while(isset($a['base']) && ($f=static::configFile($a['base'], $not))) {
                    $not[] = $f;
                    unset($a['base']);
                    $na = S::config($f, S::env());
                    if($na) $a = S::mergeRecursive($a, $na);
                    if($i-- <=0) break;
                }
            }

            static $r;
            if(!$r) {
                $r = array('$DATE'=>date('Y-m-d\TH:i:s'), '$TODAY'=>date('Y-m-d'), '$NOW'=>date('H:i:s'));
                $U = S::getUser();
                if($U->isAuthenticated()) {
                    $r['$UID'] = $U->getPk();
                }
                unset($U);
            }
            $a = S::replace($a, $r);
            unset($f);
        }
        if($prepare && isset($a['model']) && isset($a['prepare'])) {
            list($c,$m) = (is_array($a['prepare']))?($a['prepare']):(array($a['model'],$a['prepare']));
            if(is_string($c)) $a = $c::$m($a);
            else $a = $c->$m($a);
            unset($c, $m);
        }

        $re = '/^(Tecnodesign_Studio_|Studio\\\Model\\\)/';
        if(isset($a['model']) && preg_match($re, $a['model'])) {
            $n = preg_replace($re, '', $a['model']);
            if(!Studio::enabledModels($a['model'])) {
                $a['options']['navigation'] = null;
                $a['options']['list-parent'] = false;
                $a['options']['priority'] = null;
            }
        } else {
            $n = S::camelize($a['interface'], true);
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
                } else if(!is_null($c = Studio::credential($an.'Interface'.$n))) {
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
            if(!is_null($c = Studio::credential('interface'.$n))
                || !is_null($c = Studio::credential('interface'))
                || !is_null($c = Studio::credential('edit'))
            ) {
                $a['credential'] = $c;
            }
        }

        return $a;
    }

    /**
     * ArrayAccess abstract method. Gets stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return mixed the stored value, or method results
     */
    #[\ReturnTypeWillChange]
    public function offsetGet($name)
    {
        if (method_exists($this, $m='get'.S::camelize($name, true))) {
            return $this->$m();
        } else if (isset($this->$name)) {
            return $this->$name;
        }
        return null;
    }
    /**
     * ArrayAccess abstract method. Sets parameters to the PDF.
     *
     * @param string $name  parameter name, should start with lowercase
     * @param mixed  $value value to be set
     *
     * @return void
     */
    public function offsetSet($name, $value): void
    {
        if (method_exists($this, $m='set'.S::camelize($name, true))) {
            $this->$m($value);
        } else if(!property_exists($this, $name)) {
            throw new Exception(array(S::t('Column "%s" is not available at %s.','exception'), $name, get_class($this)));
        } else {
            $this->$name = $value;
        }
        unset($m);
    }

    /**
     * ArrayAccess abstract method. Searches for stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return bool true if the parameter exists, or false otherwise
     */
    public function offsetExists($name): bool
    {
        return isset($this->$name);
    }

    /**
     * ArrayAccess abstract method. Unsets parameters to the PDF. Not yet implemented
     * to the PDF classes — only unsets values stored in $_vars
     *
     * @param string $name parameter name, should start with lowercase
     */
    public function offsetUnset($name): void
    {
        $this->offsetSet($name, null);
    }	
}