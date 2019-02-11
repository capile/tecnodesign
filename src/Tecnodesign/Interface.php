<?php
/**
 * Tecnodesign Automatic Interfaces
 *
 * This is an action for managing interfaces for all available models
 *
 * PHP version 5.2
 *
 * @category  Ui
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */
class Tecnodesign_Interface implements ArrayAccess
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
        $listResult         = 'There\'s only one record available.',
        $listResults        = 'There are %s records available.',
        $listSearchResult   = 'There\'s only one record in %s that match your query for %s.',
        $listSearchResults  = 'There are %s records in %s that match your query for %s.',
        $listNoSearchResults= 'There are no records in %s that match your query for %s. Please try again using different parameters.',
        $listCounter        = 'Showing from %s to %s.',
        $previewNoResult    = 'This record is not available.',
        $listNoResults      = 'There are no records available.',
        $updateSuccess      = '%s ―%s― successfully updated.',
        $newSuccess         = '%s ―%s― successfully created.',
        $deleteSuccess      = '%s ―%s― successfully removed.',
        $updateError        = 'It wasn\'t possible to update the record. Please verify the error messages and try again.',
        $newError           = 'It wasn\'t possible to create the record. Please verify the error messages and try again.',
        $deleteError        = 'It wasn\'t possible to remove the record. Please verify the error messages and try again.',
        $errorNotSupported  = 'Format not supported.',
        $errorConflictFormat= 'The format requested as the filename extension conflicts with the header information.',
        $errorForbidden     = 'You don\'t have the credentials required to access this interface.',
        $errorNotFound      = 'Not found or permanently disabled.',
        $errorNoInput       = 'No input was provided.',
        $labelFilter        = 'Filter',
        $labelActions       = 'Actions',
        $breadcrumbs        = true,
        $displaySearch      = true,
        $displayList        = true,
        $listPagesOnTop     = true,
        $listPagesOnBottom  = true,
        $translate          = true,
        $hitsPerPage        = 25,
        $attrListClass      = 'tdz-i-list',
        $attrPreviewClass   = 'tdz-i-preview',
        $attrParamClass     = 'tdz-i-param',
        $attrTermClass      = 'tdz-i-term',
        $attrFooterClass    = 'tdz-i-footer',
        $attrErrorClass     = 'tdz-err tdz-msg',
        $attrCounterClass   = 'tdz-counter',
        $attrButtonsClass   = '',
        $attrButtonClass    = '',
        $actionAlias        = array(),
        $actionsAvailable   = array(
                                'list'      => array('position'=>0,  'identified'=>false, 'batch'=>false, 'query'=>true,  'additional-params'=>true, ),
                                'report'    => array('position'=>10, 'identified'=>false, 'batch'=>false, 'query'=>true,  'additional-params'=>true,   'renderer'=>'renderReport',),
                                'new'       => array('position'=>20, 'identified'=>false, 'batch'=>false, 'query'=>false, 'additional-params'=>false,  'renderer'=>'renderNew', 'next'=>'preview'),
                                'preview'   => array('position'=>30, 'identified'=>true,  'batch'=>true,  'query'=>false, 'additional-params'=>false,  'renderer'=>'renderPreview',),
                                'update'    => array('position'=>40, 'identified'=>true,  'batch'=>true,  'query'=>false, 'additional-params'=>false,  'renderer'=>'renderUpdate', 'next'=>'preview'),
                                'delete'    => array('position'=>50, 'identified'=>true,  'batch'=>true,  'query'=>false, 'additional-params'=>false,  'renderer'=>'renderDelete', 'next'=>'list'),
                                'schema'    => array('position'=>false, 'identified'=>false, 'batch'=>true,  'query'=>false, 'additional-params'=>true,  'renderer'=>'renderSchema', 'next'=>'list'),
                            ),
        $relationAction     =                  array('position'=>60,    'action' => 'executeInterface','identified'=>true,  'batch'=>false, 'query'=>false, 'renderer'=>'renderInterface'),
        $additionalActions  = array(),
        $listAction         = 'preview',
        $actionsDefault     = array( 'preview', 'list' ),
        $share              = null,
        $boxTemplate        = '<div class="tdz-i-scope-block scope-$ID" data-action-scope="$ID">$INPUT</div>',
        $headingTemplate    = '<hr /><h3>$LABEL</h3>',
        $previewTemplate    = '<dl class="if--$ID tdz-i-field $CLASS"><dt>$LABEL$ERROR</dt><dd data-action-item="$ID">$INPUT</dd></dl>',
        $updateTemplate,
        $newTemplate,
        $renderer           = 'renderPreview',
        $dir                = array('interface'),
        $urls               = array(),
        $baseInterface      = array(
            'interface'     => 'index',
            'run'           => 'listInterfaces',
        ),
        $authDefault        = false,
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
        $className='Tecnodesign_Interface';


    protected $uid, $model, $action, $id, $search, $groupBy, $orderBy, $key, $url, $options, $parent, $relation, $scope, $auth, $actions, $text, $template, $run, $params, $source;
    protected static 
        $instances=array(), 
        $is=0,
        $base,
        $formats=array( 'html', 'json', 'xls', 'xlsx', 'csv', 'yml', 'xml' ),
        $format,
        $ext,
        $statusCodes = array(
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            301 => 'Moved Permanently',
            302 => 'Found',
            304 => 'Not Modified',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            412 => 'Precondition Failed',
            422 => 'Unprocessable Entity',
            500 => 'Internal Server Error',
        );


    /**
     * Interface creator: load a interface configuration, checking its contents and authentication
     *
     * Each interface definition should follow the syntax:
     *
     *   (string) model:        instanceof Tecnodesign_Model that should be loaded
     *   (string) key:          key to use for URLs and links, if not the $model::pk()
     *   (string) relation:     for sub-interfaces, which relation this interface refers to — might replace model information
     *    (array) search:       $model::find() parameters for restricting the scope of this interface.
     *                          this parameter is automatically filled when sub-interfaces are called
     *    (array) options:      different options for controlling the interface, is also set as the action parameter[0]
     * (callable) action:       action definition. If it's a string, then it's checked for a method of $model 
     *                          or a Tecnodesign_Interface action. Arrays might contain the className/Object in the first parameter.
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
        if(isset($d['action'])) {
            $this->action = $d['action'];
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
        } else if(isset($_SERVER['HTTP_TDZ_INTERFACE_MODE'])) {
            if($_SERVER['HTTP_TDZ_INTERFACE_MODE']=='standalone') $this->template = 'interface-standalone';
        }
        if(isset($d['formats']) && is_array($d['formats'])) {
            static::$formats = $d['formats'];
            unset($d['formats']);
            if(static::$format && !in_array(static::$format, static::$formats)) return static::error(400, static::t('errorNotSupported'));
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
                if(isset($this->options[$opt]) && $this->options[$opt]!=static::$$opt) static::$$opt = (bool) $this->options[$opt];
            }
            unset($d['options']);
        }

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
                        ) + static::$relationAction;
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
        if(($r=Tecnodesign_App::response('route')) && isset($r['url'])) tdz::scriptName($r['url']);
        return static::run();
    }

    public static function format($format=null)
    {
        if($format && in_array($format, static::$formats)) static::$format = $format;
        return static::$format;
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
        if(isset($_SERVER['HTTP_TDZ_ACTION']) && $_SERVER['HTTP_TDZ_ACTION']=='choices') {
            unset($_SERVER['HTTP_ACCEPT']);
        }
        if(self::$className!=get_called_class()) self::$className = get_called_class();
        try {
            if(!is_null($url)) tdz::scriptName($url);
            else if(($route=Tecnodesign_App::response('route')) && isset($route['url']) && strpos($route['url'], '*')===false) tdz::scriptName($route['url']);
            static::$request = tdz::requestUri();
            $p = tdz::urlParams(null, true);
            $l = count($p) -1;

            // load extension first, then only check formats after the interface was loaded
            $ext = null;
            if(isset($p[$l]) && preg_match('/\.([a-z0-9]{3,4})$/', $p[$l], $m)) {
                $ext = $m[1];
                $p[$l] = substr($p[$l], 0, strlen($p[$l]) - strlen($m[0]));
            } else if(($m=Tecnodesign_App::request('extension')) && in_array($m, static::$formats)) {
                static::$ext = '.'.$m;
                $format = static::$format = $m;
            }
            unset($m);

            static::$base = tdz::scriptName();
            if($n) {
                array_unshift($p, $n);
                if(substr(static::$base, -1*strlen('/'.$n))=='/'.$n) {
                    static::$base = substr(static::$base, 0, strlen(static::$base) - strlen($n) -1);
                }
            } else if(static::$base=='/') static::$base='';

            if(static::$share) {
                $sf = (static::$share===true || static::$share===1)?('interface-shared'):(tdz::slug(static::$share, '_', true));
                if(!in_array($sf, static::$dir)) static::$dir[] = $sf;
            }

            $I = static::currentInterface($p);
            if($ext && !in_array($ext, static::$formats)) {
                return static::error(400, static::t('errorNotSupported'));
            } else if($ext) {
                static::$ext = '.'.$ext;
                $format = static::$format = $ext;
            }
            unset($ext);

            if(isset($_SERVER['HTTP_ACCEPT']) && preg_match('#^application/([a-z]+)#', $_SERVER['HTTP_ACCEPT'], $m)) {
                if($m[1]=='yaml') $m[1]='yml';
                if(!in_array($m[1], static::$formats)) {
                    if(!in_array('*', static::$formats)) {
                        return static::error(400, static::t('errorNotSupported'));
                    }
                } else if(static::$ext && static::$ext!='.'.$m[1]) {
                    return static::error(400, static::t('errorConflictFormat'));
                } else if($m[1]!='*') {
                    static::$format = $m[1];
                }
                unset($m);
            }

            if(!$I) return false;

            Tecnodesign_App::$assets[] = 'Z.Interface';
            Tecnodesign_App::$assets[] = '!'.Tecnodesign_Form::$assets;
            //Tecnodesign_App::$assets[] = '!Z.Graph,Chart';

            //if($I && $I->auth) tdz::cacheControl('private, no-store, no-cache, must-revalidate',0);
            if(is_null(static::$format)) {
                $f = static::$formats;
                static::$format = array_shift($f);
                unset($f);
            }
            if(!$I) {
                $cn = self::$className;
                $I = new $cn(static::$baseInterface);
                $I->url = static::$base.'/'.$I->text['interface'];
                if(!$I->auth()) {
                    static::error(403, static::t('errorForbidden'));
                }
                $p = null;
            } else {
                $sn = tdz::scriptName();
                tdz::scriptName($I->url);
            }
            return $I->output($p);

        } catch(Tecnodesign_App_End $e) {
            if(static::$headers) static::headers();
            throw $e;
        } catch(Exception $e) {
            tdz::log('[ERROR] '.__METHOD__.'->'.get_class($e).':'.$e);
            static::error(500);
        }
    }

    public function output($p=null)
    {
        $s = $this->render($p);
        if(static::$headers) static::headers();
        if(static::$format!='html') {
            Tecnodesign_App::response(array('headers'=>array('Content-Type'=>'application/'.static::$format.'; charset=utf-8')));
            Tecnodesign_App::end($s);
        }
        $base = ($this::$base && $this::P_REAL_BASE)?($this::$base):($this->link(''));
        $s = '<div class="tdz-i-box" base-url="'.$base.'">'.$s.'</div>';

        if(isset($_SERVER['HTTP_TDZ_ACTION']) && $_SERVER['HTTP_TDZ_ACTION']=='Interface') {
            Tecnodesign_App::end($s);
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
        $id = array_pop(array_keys(self::$instances));
        return self::$instances[$id];
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
            if(is_object($pI) && $pI instanceof Tecnodesign_Interface) {
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
            foreach(static::$actionsAvailable as $an=>$a) {
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
                while(isset($la[$p])) $p += 0.001;
                $a['id'] = $an;
                $la[$p] = $a;

                unset($b[$an], $an, $a, $p);
            }
            foreach(static::$additionalActions as $an=>$a) {
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
                while(isset($la[$p])) $p += 0.001;
                $a['id'] = $an;
                $la[$p] = $a;

                unset($b[$an], $an, $a, $p);
            }

            foreach($actions as $an=>$a) {
                if(isset($a['relation']) || isset($a['interface'])) $a += static::$relationAction;
                if(!isset($a['action'])) continue;

                if(!isset($a['position'])) $a['position'] = 0.000;
                $p = (float) $a['position'];
                while(isset($la[$p])) $p += 0.001;
                $a['id'] = $an;

                $la[$p] = $a;

                unset($an, $a, $p);
            }

            ksort($la);
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
        if($oldurl && isset($_SERVER['HTTP_TDZ_ACTION']) && $_SERVER['HTTP_TDZ_ACTION']=='Interface') {
            $this->message('<a data-action="unload" data-url="'.tdz::xmlEscape($this->link()).'"></a>');
        }
        tdz::redirect($url);
    }

    public function message($m=null)
    {
        static $msg='',$got=false;
        if($m) {
            tdz::getUser()->setMessage($m);
            $msg .= $m;
        } else if(!$got) {
            $msg .= tdz::getUser()->getMessage(false, true);
            $got=true;
        }
        return $msg;
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
                if($this->actions[$action] instanceof Tecnodesign_Interface) return $this->actions[$action]->getAuth();
                else if(isset($this->actions[$action]['auth'])) return $this->actions[$action]['auth'];
            }
        }
        return $this->auth;
    }

    public function hasCredential($action=null)
    {
        $c = $this->getCredential($action);
        return (!$c || tdz::getUser()->hasCredential($c, false));
    }

    public function auth($action=null)
    {
        return static::checkAuth($this->getAuth($action));
    }

    public static function authHeaders($U=null, $h='private')
    {
        tdz::cacheControl($h, static::$expires);
        self::$headers[static::H_CACHE_CONTROL] = $h;
    }

    public static function checkAuth($c)
    {
        static $H, $U;
        if(!is_array($c)) {
            if(!$c) return true;
            if(is_null($U)) {
                $U = tdz::getUser();
            }
            if($U->isAuthenticated()) {
                self::authHeaders($U);
                return true;
            } else {
                return false;
            }
        }
        if(isset($c['user']) && is_array($c['user'])) {
            if(is_null($U)) {
                $U = tdz::getUser();
            }
            if($U->isAuthenticated() && ($uid=$U->getPk())) {
                if(in_array($uid, $c['user'])) return true;
            }
        }
        if(isset($c['host']) && is_array($c['host'])) {
            self::authHeaders();
            if(is_null($H)) {
                $H = (isset($_SERVER['REMOTE_ADDR']))?($_SERVER['REMOTE_ADDR']):(false);
            }
            if($H && in_array($H, $c['host'])) {
                return true;
            }
        }
        if(isset($c['credential'])) {
            if(is_null($U)) {
                $U = tdz::getUser();
            }
            if(!$c['credential']) {
                return true;
            } else {
                self::authHeaders($U);
                return $U->hasCredential($c['credential'], false);
            }
        }
        return false;
    }


    public static function currentInterface($p, $I=null)
    {
        if(!isset(static::$base)) static::$base = tdz::scriptName();

        if(self::$className!=get_called_class()) self::$className = get_called_class();
        // first fetch any interface from the $p
        $n=null;
        if(is_null($I)) {
            $f=null;
            $rn = null;
            if($p) {
                $p0 = $p;
                $n = preg_replace('#[^a-z0-9\-\_\@]#i', '', array_shift($p));// find a file
                while(!file_exists($f=static::configFile($n)) && $p) {
                    $n .= '/'.rawurlencode(array_shift($p));
                    $f = null;
                }
            }
            if(!$f) {
                if(isset($p0)) $p = $p0;
                if($f=static::configFile('index')) {
                    $n = 'index';
                    $rn = 'index';
                } else if($p) {
                    $n = preg_replace('#[^a-z0-9\-\_\@]#i', '', array_shift($p));
                    $rn = '/index';
                    while(!file_exists($f=static::configFile($n.'/index')) && $p) {
                        $n .= '/'.array_shift($p);
                    }
                    if($f) $n .= $rn;
                }
            } else {
                tdz::scriptName(tdz::scriptName().'/'.$n);
            }
            unset($p0);
            if(!$f) {
                return static::error(404, static::t('errorNotFound'));
            }
            unset($f);
            $cn = self::$className;
            $I = new $cn($n);
            if(!$I->auth()) {
                return static::error(403, static::t('errorForbidden'));
            }
            if($rn) {
                $n = substr($n, 0, strlen($n) - strlen($rn));
            }
            $I->url = ($n)?(static::$base.'/'.$n):(static::$base);
            unset($cn);
        }

        //Tecnodesign_Interface::$urls[$I->link()] = array('title'=>$I->getTitle(),'action'=>$I->action);

        if($I->run) {
            return $I;
        }

        if(is_null($I->actions)) {
            $I->setActions(true, 1);
        }


        $a = $n = null;
        if($p) $n = array_shift($p);
        if($n) {
            if(isset(static::$actionAlias[$n])) {
                $a = static::$actionAlias[$n];
            } else if(isset($I->actions[$n]) && !in_array($n, static::$actionAlias)) {
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
            foreach(static::$actionsDefault as $a) {
                if($A=$I->setAction($a, $p)) {
                    break;
                }
                unset($a, $A);
            }
        }
        if(!isset($A) || !$A) {
            return static::error(404, static::t('errorNotFound'));
        } else if(is_object($A) && $A instanceof Tecnodesign_Interface) {
            Tecnodesign_Interface::$urls[$A->link()] = array('title'=>$A->getTitle(),'action'=>$A->action);
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

            if(isset($this->actions[$a]['identified']) && $this->actions[$a]['identified']) {
                if(!isset($this->id) && $p) {
                    $n = array_shift($p);
                    if($n!=='') {
                        if(is_array($this->key)) {
                            $nc = explode(Tecnodesign_Model::$keySeparator, $n, count($this->key));
                            $add = array();
                            foreach($this->key as $k) {
                                $add[$k] = array_shift($nc); 
                            }
                        } else {
                            if(strpos($n, ',')) $n = preg_split('/\s*\,\s*/', $n, null, PREG_SPLIT_NO_EMPTY);
                            $add = array($this->key=>$n);
                        }
                    } else {
                        $add = array("`{$this->key}`!="=>$n);
                    }
                    $this->addSearch($add);
                    $this->id = (is_array($n))?(implode(',',$n)):($n);
                } else if(!isset($this->id) && $this->search) {
                    if($this->isOne()) {
                        $this->id = $this->model()->getPk();
                    }
                }
                if(tdz::isempty($this->id)) {
                    if(isset($n)) {
                        array_unshift($p, $n);
                    }
                    return false;
                }
            } else if(!tdz::isempty($this->id)) {
                return false;
            }
            $this->action = $a;
            if(!$this->auth($a)) {
                static::error(403, static::t('errorForbidden'));
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
                static::H_STATUS=>static::$statusCodes[static::$status],
                static::H_STATUS_CODE=>static::$status,
            ) + static::$headers;
        if(static::H_CACHE_CONTROL && !isset(static::$headers[static::H_CACHE_CONTROL])) {
            if($code==200) {
                static::$headers[static::H_CACHE_CONTROL] = 'public';
                tdz::cacheControl('public', static::$expires);
            } else {
                static::$headers[static::H_CACHE_CONTROL] = 'nocache';
                tdz::cacheControl('nocache', 0);
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
            if($p=Tecnodesign_App::request('get', static::REQ_ENVELOPE)) {
                static::$envelope = (bool)tdz::raw($p);
            }
            if($p=Tecnodesign_App::request('get', static::REQ_PRETTY)) {
                static::$pretty = (bool)tdz::raw($p);
            }
            unset($p);
            $cn = self::$className;
            if(method_exists($cn, $m='to'.ucfirst(static::$format))) $msg = static::$m((is_array($msg))?($msg):(array()));

            Tecnodesign_App::response(array('headers'=>array('Content-Type'=>'application/'.static::$format.'; charset=utf-8')));
            Tecnodesign_App::end($msg, $code);
        }
        if(isset(static::$errorModule)) {
            $cn = static::$errorModule;
            static::$errorModule=null;
            return $cn::error($code);
        }
        tdz::getApp()->runError($code);
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
                    $a .= ' '.$k.'="'.tdz::xmlEscape($v).'"';
                    unset($k, $v);
                }
            }
            $a .= '>';
            if(static::$xmlPropertiesAsElements) {
                foreach(static::$headers as $k=>$v) {
                    $s  = tdz::slug($k, '_');
                    $a .= $ln.str_repeat($in, $ix).'<'.$s.'>'.tdz::xmlEscape($v).'</'.$s.'>';
                    unset($s, $k, $v);
                }
            }
            $b = $ln.'</'.static::$xmlRoot.'>'.$b;
            $ix++;
        }

        $a .= $ln.str_repeat($in, $ix -1).'<'.static::$xmlContainer;
        if(static::$xmlContainerAttributes) {
            foreach(static::$xmlContainerAttributes as $k=>$v) {
                $a .= ' '.$k.'="'.tdz::xmlEscape($v).'"';
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
                        $a .= ' '.$k.'="'.tdz::xmlEscape($v).'"';
                        unset($k, $v);
                    }
                }
                $a .= '>';
                foreach($d as $k=>$v) {
                    if(!static::$xmlItemAttributesNode) {
                        $s = tdz::slug($k, '_');
                        if(is_array($v)) {
                            foreach($v as $x) {
                                $a .= $ln.str_repeat($in, $ix+1).'<'.$s.'>'.tdz::xmlEscape($x).'</'.$s.'>';
                            }
                        } else {
                            $a .= $ln.str_repeat($in, $ix+1).'<'.$s.'>'.tdz::xmlEscape($v).'</'.$s.'>';
                        }
                        unset($s);
                    } else {
                        if(is_array($v)) {
                            foreach($v as $x) {
                                $a .= $ln.str_repeat($in, $ix+1).'<'.static::$xmlItemAttributesNode.' name="'.tdz::xmlEscape($k).'" value="'.tdz::xmlEscape($x).'"/>';
                            }
                        } else {
                            $a .= $ln.str_repeat($in, $ix+1).'<'.static::$xmlItemAttributesNode.' name="'.tdz::xmlEscape($k).'" value="'.tdz::xmlEscape($v).'"/>';
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
        if(($p=Tecnodesign_App::request('get', static::REQ_CALLBACK)) && is_string($p)) {
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
        return Tecnodesign_Yaml::dump($ret, 2, 80);
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
        if(!tdz::isempty($this->id)) {
            $r = $cn::find($this->search,0,'string',false,null,$this->groupBy);
            if($r) {
                if(method_exists($cn, 'renderTitle')) {
                    $s = '';
                    foreach($r as $i=>$o) {
                        $s .= (($s)?(', '):(''))
                            . $o->renderTitle();
                        unset($r[$i], $i, $o);
                    }
                    return $s;
                } else {
                    return tdz::xml(implode(', ', $r));
                }
            }
        }
        if(!isset($this->text['title'])) {
            return $cn::label();
        }
        return $this->text['title'];
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
        return (static::$translate)?(tdz::t($s, 'interface')):($s);
    }

    public static function template() {
        $tpls = func_get_args();
        $td = tdz::getApp()->tecnodesign['templates-dir'];
        foreach($tpls as $tpl) {
            if(!$tpl) continue;
            if(file_exists($f=$tpl.'.php') 
                || file_exists($f=$td.'/'.$tpl.'.php')
                || file_exists($f=TDZ_ROOT.'/src/Tecnodesign/App/Resources/templates/'.$tpl.'.php')) break;
            unset($f, $tpl);
        }
        return (isset($f))?($f):(false);
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

        if(!$a) return $url;
        if(static::$actionAlias && ($aa=array_search($a, static::$actionAlias))) {
            $url .= '/'.$aa;
            unset($aa);
        } else {
            $url .= '/'.$a;
        }
        $A = (isset($this->actions[$a]))?($this->actions[$a]):(array());
        if(!is_array($A))$A=array();
        if(isset(static::$actionsAvailable[$a])) {
            $A += static::$actionsAvailable[$a];
        } else if(isset(static::$additionalActions[$a])) {
            $A += static::$additionalActions[$a];
        }
        if(!tdz::isempty($this->id) || !tdz::isempty($id)) {
            if($id===true || (tdz::isempty($id) && isset($A['identified']) && $A['identified'])) {
                $id = $this->id;
            }
            if(!tdz::isempty($id)) $url .= '/'.$id;
        } else if(!tdz::isempty($this->params)) {
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
        if(!tdz::isempty($this->key)) {
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
        }
        return false;
    }

    public function execute()
    {
        static::$currentAction = $this->action;
        if(!isset($this->text)) $this->text = array();
        $this->text['count'] = $this->count();
        $req = Tecnodesign_App::request('post') + Tecnodesign_App::request('get');
        if(isset($req['ajax'])) unset($req['ajax']);
        if($req) {
            $noreq = array(static::REQ_LIMIT, static::REQ_OFFSET, static::REQ_ENVELOPE, static::REQ_PRETTY, static::REQ_CALLBACK, static::REQ_SCOPE, static::REQ_FIELDS, static::REQ_ORDER);
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

        if($p=Tecnodesign_App::request('get', static::REQ_ENVELOPE)) {
            static::$envelope = (bool)tdz::raw($p);
        }
        if($p=Tecnodesign_App::request('get', static::REQ_PRETTY)) {
            static::$pretty = (bool)tdz::raw($p);
        }
        unset($p);

        $cn = $this->getModel();
        if(isset($this->options['scope']) && is_array($this->options['scope'])) {
            $cn::$schema['scope'] = $this->options['scope'] + $cn::$schema['scope'];
        }

        if(is_null($this->parent) && $this->action!='list' && ($uid=Tecnodesign_App::request('get', '_uid'))) {
            if(!$this->search) $this->search=array();
            $pk = $cn::pk();
            $rq = explode(',', $uid);
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

        $m='render'.ucfirst($this->action);
        if(($this->action=='list' || !isset($this->id)) && static::$displaySearch && 
            (
                (isset($this->options['search']) && $this->options['search'])
                || (isset($this->actions[$this->action]['query']) && $this->actions[$this->action]['query'])
            )) {
            $this->searchForm($req);
        }

        if(isset($this->options['group-by'])) $this->groupBy = $this->options['group-by'];

        if($this->isOne() && method_exists($cn, $m)) {
            $this->getButtons();
            $this->scope((isset($cn::$schema['scope'][$this->action]))?($this->action):('preview'));
            $o = $this->model();
            $this->text['preview'] = $o->$m($this);
            unset($o);
        } else if(method_exists($this, $m)) {
            $this->getButtons();
            $this->text['preview'] = $this->$m();
        } else {
            $this->getButtons();
            $this->getList($req);
        }
        static::status(200);

        unset($req);
        unset($m, $cn);
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

    public function render($p=array())
    {
        tdz::$variables['Interface'] = $this;
        if(!$this->action) {
            foreach(static::$actionsDefault as $a) {
                if($this->setAction($a, tdz::urlParams(null, true))) {
                    break;
                }
                unset($a);
            }
            if(!Tecnodesign_Interface::$urls) {
                Tecnodesign_Interface::$urls[$this->link()] = array('title'=>$this->getTitle(),'action'=>$this->action);
            }
        }
        static::$currentAction = $this->action;

        if($this->run) {
            $o = $this->run;
            $call = array_shift($o);
            if($this->params) $o[] = $this->params;
            if(is_string($call[0]) && get_class($this)==$call[0]) $call[0] = $this;
            if(isset($this->text['title'])) Tecnodesign_App::response('title', $this->text['title']);
            return tdz::call($call, $o);
        }
        if(static::$ext) $this->options['extension'] = static::$ext;
        if(isset($this->options['last-modified'])) $this->lastModified();
        if(isset($this->actions[$this->action]['action'])) {
            $action = $this->actions[$this->action]['action'];
            $data = null;
            if(is_array($action) && !isset($action[0]) && isset($action[$m=Tecnodesign_App::request('method')])) {
                $action = $action[$m];
            }
            if(is_array($action) && count($action)==2) {
                list($C, $m) = $action;
                if(is_string($C) && $C==$this->model && !tdz::isempty($this->id)) {
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

        $f=static::template($this->template, 'interface-'.static::$format, 'interface-'.$this->action, 'interface');
        $vars = $this->text;
        $vars['Interface'] = $this;
        $vars['url'] = $this->link();//tdz::scriptName(true);
        $vars['response'] = $data;
        $vars['options'] = $this->options;

        return tdz::exec(array('script'=>$f, 'variables'=>$vars));
    }

    public static function headers()
    {
        $r = array();
        foreach(static::$headers as $k=>$v) {
            $k = tdz::slug($k);
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
            Tecnodesign_App::status(static::$status);
        }
    }

    public function lastModified($lmod=null)
    {
        if(!$lmod && isset($this->options['last-modified'])) {
            $def = $this->options['last-modified'];
            if(!is_array($def)) $def = array('field'=>$def);
            if(isset($def['query'])) {
                $R = tdz::query($def['query']);
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
        if(!is_int($lmod)) $lmod = tdz::strtotime($lmod);
        if(tdz::env()!='dev' && isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $if = strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE']);
            if($if >= $lmod) {
                if(static::$format!='html') {
                    header('content-type: application/'.static::$format.'; charset=UTF-8');
                }
                Tecnodesign_App::end('', 304);
            }
        }
        static::$headers[static::H_LAST_MODIFIED]=gmdate(static::$dateFormat, $lmod);
    }

    protected static $proc, $procTimeout=180;
    public static function workerProcess($proc=null)
    {
        if($proc) {
            self::$proc = $proc;
        } else if($proc===false && self::$proc) {
            Tecnodesign_Cache::delete(self::$proc);
            self::$proc = null;
        }
        return self::$proc;
    }
    public static function worker($msg=false, $f=false)
    {
        static $s;
        if(!$s && self::$proc) $s = Tecnodesign_Cache::get(self::$proc, static::$procTimeout);
        if($msg) {
            if(!$s || !is_array($s)) $s = array();
            $t = microtime(true);
            $s['s'] = $t;
            if(isset($s['m']) && $msg && substr($s['m'], 0, strlen($msg))==$msg) $msg = $s['m'].'.';
            $s['l'][$t] = $s['m'] = $msg;
            if($f) $s['f'] = $f;
            if(!Tecnodesign_Cache::set(self::$proc, $s, static::$procTimeout)) {
                $s = false;
            }
        }
        return $s;
    }

    public function backgroundWorker($m, $prefix='w/', $download=true)
    {
        if(isset($_SERVER['HTTP_TDZ_ACTION']) && $_SERVER['HTTP_TDZ_ACTION']=='Interface') {
            $uri = $this->link();
            $msg = '<a data-action="redirect" data-url="'.tdz::xml($uri).'"></a>';
            if(!isset($_SERVER['HTTP_TDZ_PARAM'])) {
                $end = false;
                // send a status check variable
                $uid = tdz::compress64(uniqid(md5($uri)));
                static::workerProcess($prefix.$uid);
                $r = $prefix.$uid;
                $st = static::worker($m);
                if($st) {
                    ignore_user_abort(true);
                    $msg = '<a data-action="status" data-url="'.tdz::xml($uri).'" data-message="'.tdz::xml($m).'" data-status="'.$uid.'"></a>';
                }
            } else if(($uid=$_SERVER['HTTP_TDZ_PARAM']) && ($st=Tecnodesign_Cache::get($prefix.$uid))) {
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
                    $msg = '<a data-action="'.$a.'" data-url="'.tdz::xml($uri).'" data-message="'.tdz::xml($st['m']).'"></a>';
                    // process has ended
                    Tecnodesign_Cache::delete('sync-qbo/'.$uid);
                } else {
                    $msg = '<a data-action="status" data-url="'.tdz::xml($uri).'" data-message="'.tdz::xml($st['m']).'" data-status="'.$uid.'"></a>';
                }
            } else {
                $end = true;
                $msg = '<a data-action="error" data-message="'.tdz::xml(tdz::t('There was an error while processing your request. Please try again or contact support.', 'interface')).'"></a>';
            }
            tdz::output($msg, 'text/html; charset=utf8', $end);
            return $r;
        } else if($download && ($uid=Tecnodesign_App::request('get', '-bgd')) && ($st=Tecnodesign_Cache::get($prefix.$uid)) && isset($st['f'])) {
            Tecnodesign_Cache::delete($prefix.$uid);
            tdz::download($st['f'], null, preg_replace('/^[0-9]+\.[0-9]+\-/', '', basename($st['f'])), 0, true, false, false);
            unlink($st['f']);
            exit();
        }
    }


    public function renderReport($o=null, $scope=null, $class=null, $translate=false, $xmlEscape=true)
    {
        $pid = $this->backgroundWorker(tdz::t('Building report...','interface'), 'irs/', true);

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

    public function download($f, $msg='Download...')
    {
        $fn = preg_replace('/^[0-9\.]+\-/', '', basename($f));
        if(isset($_SERVER['HTTP_TDZ_ACTION']) && $_SERVER['HTTP_TDZ_ACTION']=='Interface') {
            if($f && file_exists($f)) {
                $uid = uniqid();
                $uri = $this->link(null, true);
                $uri .= (strpos($uri, '?'))?('&'):('?');
                $uri .= '-bgd='.$uid;
                Tecnodesign_Cache::set('bgd/'.$uid, $f);
                $msg = '<a data-action="download" data-download="'.tdz::xml($fn).'" data-url="'.tdz::xml($uri).'" data-message="'.tdz::xml($msg).'"></a>';
            } else {
                $msg = '<a data-action="error" data-message="'.tdz::xml(tdz::t('There was an error while processing your request. Please try again or contact support.','interface')).'"></a>';
            }
            tdz::output($msg, 'text/html; charset=utf8', true);
        } else if(($uid=Tecnodesign_App::request('get', '-bgd')) && ($f=Tecnodesign_Cache::get('bgd/'.$uid)) && file_exists($f)) {
            Tecnodesign_Cache::delete('bgd/'.$uid);
            tdz::download($f, null, $fn, 0, true, false, false);
            unlink($f);
            //exit($f);
        }
    }

    public static function checkRequestScope($rs, $ps)
    {
        if(in_array($rs, $ps)) return true;

        foreach($ps as $s) {
            if(is_string($s) && strlen($s)>strlen($rs) && substr($s, 0, strlen($rs)+1)==$rs.':') {
                if(tdz::getUser()->hasCredentials(preg_split('/,+/', substr($s, strlen($rs)+1), null, PREG_SPLIT_NO_EMPTY))) {
                    return true;
                }
            }
        }


    }

    public function requestScope()
    {
        if(($rs=tdz::slug(Tecnodesign_App::request('get', static::REQ_SCOPE))) && isset($this->options['scope'][$rs]) && !isset(static::$actionsAvailable[$rs])) {
            // check if $this->options['scope'][$this->action] requires authentication
            $r = 'scope::'.$rs;

            $as = $this->action;
            $ad = static::$actionsDefault;
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
        if(!$o) $o = $this->model();
        if(!$o) {
            if(static::$format!='html') {
                static::error(404, static::t('previewNoResult'));
            }
            $this->message('<div class="tdz-i-msg tdz-i-error"><p>'.static::t('previewNoResult').'</p></div>');
            return $this->redirect($this->link(false, false), $this->link());
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
        if(!$o) $o = new $cn($a, true, false);
        $fo = $this->getForm($o, $scope);
        //$fo['c_s_r_f'] = new Tecnodesign_form_Field(array('id'=>'c_s_r_f', 'type'=>'hidden', 'value'=>1234));
        try {
            if(($post=Tecnodesign_App::request('post')) || static::$format!='html') {
                if(!$fo->validate($post) || !$post) {
                    throw new Tecnodesign_Exception((!$post)?(static::t('errorNoInput')):($fo->getError()));
                }
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
                $next = (static::$format!='html')?(null):('preview');
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
                if(!$next && ($next=Tecnodesign_App::request('get','next'))) {
                    if(!isset($this->actions[$next])) $next = null;
                }
                $this->text['success'] = sprintf(static::t('newSuccess'), $o::label(), $this->getTitle());
                $msg = '<div class="tdz-i-msg tdz-i-success"><p>'.$this->text['success'].'</p></div>';
                if($next) {
                    $this->action = $next;
                    $this->message($msg);
                    $this->redirect($this->link(), $oldurl);
                }
                $this->text['summary'] .= $msg;
            }
            unset($post);
        } catch(Exception $e) {
            tdz::log('[INFO] User error while processing '.__METHOD__.': '.$e);
            $this->text['error'] = static::t('newError');
            $this->text['errorMessage'] = $e->getMessage();
            $this->text['summary'] .= '<div class="tdz-i-msg tdz-i-error"><p>'.$this->text['error'].'</p>'.$this->text['errorMessage'].'</div>';
        }
        return $fo;
    }

    public function renderUpdate($o=null, $scope=null)
    {
        if(!$o) $o = $this->model();
        if(!$scope) {
            if(($rs=tdz::slug(Tecnodesign_App::request('get', static::REQ_SCOPE))) && isset($this->options['scope'][$rs]) && !isset(static::$actionsAvailable[$rs])) {
                $scope = $rs;
                unset($rs);
            } else if(isset($this->options['scope'][$this->action])) {
                $scope = $this->action;
            } else {
                $scope = 'preview';
            }
        }
        //$scope = $this->scope($scope);
        $this->options['scope'] = $this->scope($scope);
        $fo = $this->getForm($o, $scope);
        //$fo['c_s_r_f'] = new Tecnodesign_form_Field(array('id'=>'c_s_r_f', 'type'=>'hidden', 'value'=>1234));
        try {
            // prevent these actions from being marked as updates
            $dontpost = (isset($_SERVER['HTTP_TDZ_ACTION']) && in_array($_SERVER['HTTP_TDZ_ACTION'], array('Upload','choices')));

            if(($post=Tecnodesign_App::request('post')) || static::$format!='html') {
                if(!$fo->validate($post) || !$post) {
                    throw new Tecnodesign_Exception((!$post)?(static::t('errorNoInput')):($fo->getError()));
                }
                $oldurl = $this->link();
                $pk = implode('-', $o->getPk(true));
                $o->save();
                $newpk = implode('-', $o->getPk(true));
                // success message
                $this->text['success'] = sprintf(static::t('updateSuccess'), $o::label(), $this->getTitle());
                $msg = '<div class="tdz-i-msg tdz-i-success"><p>'.$this->text['success'].'</p></div>';

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
                if(!$next && ($next=Tecnodesign_App::request('get','next'))) {
                    if(!isset($this->actions[$next])) $next = null;
                }

                if($newpk!=$pk) {
                    $this->id = $newpk;
                    if(!$next) $next = $this->action;
                }
                if($next) {
                    $this->action = $next;
                    $this->message($msg);
                    $this->redirect($this->link(), $oldurl);
                }
                $this->text['summary'] .= $msg;

            }
            unset($post);
        } catch(Exception $e) {
            tdz::log('[INFO] User error while processing '.__METHOD__.': '.$e);
            $this->text['error'] = static::t('updateError');
            $this->text['errorMessage'] = $e->getMessage();
            $this->text['summary'] .= '<div class="tdz-i-msg tdz-i-error"><p>'.$this->text['error'].'</p>'.$this->text['errorMessage'].'</div>';
        }
        return $fo;
    }

    public function renderDelete($o=null, $scope=null)
    {
        try {
            if(($M = $this->model())) {
                $oldurl = $this->link();
                $s = $this->getTitle();
                $M->delete(true);
                $this->text['success'] = sprintf(static::t('deleteSuccess'), $M::label(), $s);
                $msg = '<div class="tdz-i-msg tdz-i-success"><p>'.$this->text['success'].'</p></div>';

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
                if(!$next && ($next=Tecnodesign_App::request('get','next'))) {
                    if(!isset($this->actions[$next])) $next = null;
                }
                if($next) {
                    if(isset($_SERVER['HTTP_TDZ_ACTION']) && $_SERVER['HTTP_TDZ_ACTION']=='Interface') {
                        // remove record preview, if  it exists
                        $this->message('<a data-action="unload" data-url="'.tdz::xmlEscape($this->link('preview', true)).'"></a>');
                    }
                    $this->action = $next;
                    $this->message($msg);
                    $this->redirect($this->link($next, false), $oldurl);
                }
                $this->text['summary'] .= $msg;

                return $this->redirect($this->link(false, false), $oldurl);
            }
        } catch(Exception $e) {
            tdz::log('[INFO] User error while processing '.__METHOD__.': '.$e);
        }
        $msg = static::t('deleteError');
        if(static::$format!='html') {
            $this->message($msg);
            static::error(422, array('error'=>$msg));
        } else {
            $this->message('<div class="tdz-i-msg tdz-i-error"><p>'.$msg.'</p></div>');
        }
        return $this->redirect($this->link(false, false));
    }

    public function renderSchema($o=null, $scope=null)
    {
        if(!$scope) {
            if(($o=tdz::slug(Tecnodesign_App::request('get', static::REQ_SCOPE))) && isset($this->options['scope'][$o]) && !isset(static::$actionsAvailable[$o]) && substr($o, 0, 1)!='_') {
                $scope = $this->scope($o, false, false, true);
                unset($rs);
            } else if($o && isset($this->options['scope'][$o]) && substr($o, 0, 1)!='_') {
                $scope = $this->scope($o);
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

        $a = (in_array($this->params, static::$actionAlias))?(array_search($this->params, static::$actionAlias)):($this->params);
        $identified = false;
        if(isset(static::$actionsAvailable[$a]['identified'])) {
            $identified = static::$actionsAvailable[$a]['identified'];
        }

        $qs = null;
        if($p=Tecnodesign_App::request('get', static::REQ_ENVELOPE)) {
            $qs = '?'.static::REQ_ENVELOPE.'='.var_export((bool)self::$envelope, true);
        }

        // move this to a Tecnodesign_Schema::dump($scope, $properties=array($id, title) ) method
        // available scopes might form full definitions (?)
        $cn = $this->getModel();
        $so = $cn::schema($cn, array(), true)->toJsonSchema($scope);
        $S = array(
            '$schema'=>'http://json-schema.org/draft-07/schema#',
            '$id'=>tdz::buildUrl($this->link().$qs),
            'title'=>(isset($this->text['title']))?($this->text['title']):($cn::label()),
        );
        if(isset($this->text['description'])) {
            $S['description'] = $this->text['description'];
        }
        $S['type']='object';

        if(static::$envelope) {
            $S['properties']=array(
                'status'=>array('type'=>'string'),
                'status-code'=>array('type'=>'number'),
            );
            $S['required']=array('status', 'status-code');
            // add headers
            if(isset(static::$headers)) {
                foreach(static::$headers as $k=>$v) {
                    if(static::$doNotEnvelope && in_array($k, static::$doNotEnvelope)) continue;
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

            $S['properties'][static::$envelopeProperty] = array(
                'type'=>'array',
                'items'=>$so,
            );
            $S['required'][] = static::$envelopeProperty;

        } else if(!$identified) {
            $S['type'] = 'array';
            $S['items'] = $so;
        } else {
            $S += $so;
        }
        tdz::output($this->toJson($S), 'application/schema+json', false);
        Tecnodesign_App::end();
    }

    protected function getForm($o, $scope=null)
    {
        if(!$o || !($o instanceof Tecnodesign_Model)) {
            $o = $this->model();
            if(!$o) {
                $this->message('<div class="tdz-i-msg tdz-i-error"><p>'.static::t('previewNoResult').'</p></div>');
                return $this->redirect($this->link(false, false));
            }
        }
        if(!tdz::isempty($this->id)) {
            if(is_string($scope)) $ss=$scope;
            else if(count($scope)==1 && isset($scope[0]) && substr($scope[0], 0, 7)=='scope::') $ss=substr($scope[0], 7);
        }
        $cn = get_class($o);
        $d = $cn::columns($scope);
        if(!$d) $d = array_keys($cn::$schema['columns']);
        $o->refresh($d);
        $link = $this->link();
        //Tecnodesign_Interface::$urls[$link] = static::t('labelUpdate', 'Update').': '.Tecnodesign_Interface::$urls[$link];

        tdz::$variables['form-field-template'] = (static::$updateTemplate)?(static::$updateTemplate):(static::$previewTemplate);


        if(isset($_GET['item']) && ($label=array_search($_GET['item'], $d))!==false) {
            if(is_integer($label)) $label = $cn::fieldLabel($_GET['item'], false);
            Tecnodesign_Interface::$urls[$link]['title'] .= ' ('.$label.')';
            $this->text['title'] = Tecnodesign_Interface::$urls[$link]['title'];
            $d = array($label=>$_GET['item']);
            tdz::$variables['form-field-template'] = '$INPUT$ERROR';
        }

        $this->text['summary'] = $this->getSummary(Tecnodesign_Interface::$urls[$link]['title']);

        $sep = static::$headingTemplate;
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
        $fo->id = $this->text['interface'].'--'.(($o->isNew())?('n'):(@implode('-',$o->getPk(true))));
        $fo->attributes['class']='z-form';
        if(isset($ss)) {
            $fo->buttons['button']=array(
                'label'=>'*Close',
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
            Tecnodesign_App::response(array('headers'=>array('Content-Type'=>'application/'.static::$format.'; charset=utf-8')));
            Tecnodesign_App::end($this->render());
        }
        $this->template = 'interface-standalone';
        return $this->render();
    }

    protected function renderSub($r)
    {
        $I = $this->relation($r);
        $I->template = 'interface-sub';

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
                'relation'=>(!tdz::isempty($this->id))?($cn.'#'.$this->id):($cn),
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
        if(!$title && ($l=$this->link()) && isset(Tecnodesign_Interface::$urls[$l]['title'])) {
            $title = Tecnodesign_Interface::$urls[$l]['title'];
        }
        unset($l);
        $a = ucfirst($this->action);
        $s = '<p>'
           . '<span class="tdz-i-label-action">'.static::t('label'.$a, $a).'</span>'
           . '<span class="tdz-i-label-key">'.$cn::fieldLabel($this->key).'</span>'
           . '<span class="tdz-i-label-id">'.$this->id.'</span>'
           . '<span class="tdz-i-label-title">'.tdz::xmlEscape($title).'</span>'
           . '</p>';
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
        if(!tdz::isempty($this->id)) $sn = substr($sn, 0, strrpos($sn, '/'));
        // strip current action, leave only the model
        $sn = substr($sn, 0, strrpos($sn, '/'));
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
            $An = tdz::camelize($an, true);

            if(isset($action['relation'])) {
                $id = true;
                $bt = false;
                $label = (isset($action['text']['title']))?($action['text']['title']):(static::t('label'.$An, $An));
            } else {
                $bt = (isset($action['batch']))?($action['batch']):(false);
                $id  = (isset($action['identified']))?($action['identified']):(false);
                $label = (isset($action['label']))?($action['label']):(static::t('label'.$An, $An));
            }

            if($bt && !$this->options['checkbox']) $this->options['checkbox']=true;
            if($id && !$this->options['radio']) $this->options['radio']=true;

            $aa = (static::$actionAlias && isset(static::$actionAlias[$an]))?(static::$actionAlias[$an]):($an);

            //$href = ($bt||$id)?('data-url="'.$sn.'/'.$aa.'/{id}'.'"'):('href="'.$sn.'/'.$aa.'"');
            if($id && !tdz::isempty($this->id)) $sid = $this->id;
            else if($id) $sid = '{id}';
            else $sid = false;
            if(isset($action['attributes']['target']) && (!$id || !tdz::isempty($this->id))) {
                if(isset($action['query']) && $action['query'] && $qs) {
                    $qs = str_replace(',', '%3A', tdz::xmlEscape($qs));
                } else {
                    $qs = '';
                }
                $href = 'href="'.$this->link($an, $sid).$qs.'"';
            } else {
                $href = 'data-url="'.tdz::xmlEscape($this->link($an, ($id)?($sid):(false), true, $qs)).'"';
                if(isset($action['query']) && $action['query'] && $qs) {
                    $href .= ' data-qs="'.str_replace(',', '%3A', tdz::xmlEscape($qs)).'"';
                }
                if(isset($action['attributes']['target'])) {
                    $action['attributes']['data-target']=$action['attributes']['target'];
                    unset($action['attributes']['target']);
                }
            }
            if(isset($action['attributes'])) {
                foreach($action['attributes'] as $k=>$v) {
                    $href .= ' '.tdz::xmlEscape($k).'="'.tdz::xmlEscape($v).'"';
                }
            }
            $ac = 'tdz-i-a tdz-i--'.$aa
                . ((static::$attrButtonClass)?(' '.static::$attrButtonClass):(''))
                . (($bt)?(' tdz-i-a-many'):(''))
                . (($id)?(' tdz-i-a-one'):(''))
            ;
            $s .= '<a '.$href.' class="'.$ac.'">'
                . '<span class="tdz-i-label">'
                . $label
                . '</span>'
                . '</a>';
            unset($action, $qs, $an, $aa, $An, $bt, $id, $action, $href);
        }
        $this->text['buttons'] = $s;

        unset($s, $sn);
    }

    public function qs()
    {
        if($this->action!='list') {
            $rm = 'ajax';
        } else {
            $rm = 'ajax|_uid';
        }
        return preg_replace('/\&?\b('.$rm.')(=[^\&]*)?/', '', Tecnodesign_App::request('query-string'));
        return $qs;
    }

    public function getList($req=array())
    {
        if(!isset($this->text['count'])) $this->text['count']=$this->count();
        if(!isset($this->text['error'])) $this->text['error']=array();
        if(!$this->text['count']) {
            $this->text['error'][] = static::t('listNoResults');
            //$error = '<div class="tdz-i-msg tdz-i-error"><p>'.static::t('listNoResults').'</p></div>';
            $found = false;
        } else if(isset($this->text['searchCount']) && !$this->text['searchCount']) {
            // $error = '';
            //$error = '<div class="tdz-i-msg tdz-i-error"><p>'.static::t('listNoSearchResults').'</p></div>';
            $found = false;
        }
        if(!isset($found)) {
            $cn = $this->getModel();
            if(($rs=tdz::slug(Tecnodesign_App::request('get', static::REQ_SCOPE))) && isset($this->options['scope'][$rs]) && !isset(static::$actionsAvailable[$rs])) {
                $scope = $this->scope($rs, false, false, true);
                unset($rs);
            } else if(isset($this->options['scope'][$this->action])) {
                $scope = $this->scope($this->action);
            } else {
                $scope = $this->scope('review');
            }
            $order = null;
            if(($order=Tecnodesign_App::request('get', static::REQ_ORDER)) && preg_match('/^(\!)?(.+)$/', $order, $m) && isset($scope[$m[2]])) {
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
            $found = $cn::find($this->search,0,$this->scope(null,true,true),true,$order,$this->groupBy);
        }
        if(!$found) {
            $count = 0;
        } else {
            //$error = '';
            $count = $found->count();
            $start = 0;
        }
        static::$headers[static::H_TOTAL_COUNT] = $count;
        $this->options['link'] = ($this->hasAction(static::$listAction))?($this->link(static::$listAction, false, false)):(false);
        $this->text['listLimit'] = (isset($this->options['list-limit']) && is_numeric($this->options['list-limit']))?($this->options['list-limit']):(static::$hitsPerPage);
        $p=Tecnodesign_App::request('get', static::REQ_LIMIT);
        if($p!==null && is_numeric($p) && $p >= 0) {
            $p = (int) $p;
            $this->text['listLimit'] = $p;
            static::$headers['limit'] = $p;
        } else if($count > static::$hitsPerPage) {
            static::$headers['limit'] = static::$hitsPerPage;
        }
        if($this->text['listLimit']>static::MAX_LIMIT) {
            $this->text['listLimit'] = static::MAX_LIMIT;
            static::$headers['limit'] = static::MAX_LIMIT;
        }

        $this->text['listOffset'] = 0;
        $p=Tecnodesign_App::request('get', static::REQ_OFFSET);
        if($p!==null && is_numeric($p)) {
            $p = (int) $p;
            if($p < 0) {
                $p = $p*-1;
                if($p > $count) $p = $p % $count;
                if($p) $p = $count - $p;
            }
            $this->text['listOffset'] = $p;
            static::$headers['offset'] = $p;
        } else if(isset($req['p']) && is_numeric($req['p'])) {
            $pag = (int) $req['p'];
            if(!$pag) $pag=1;
            $this->text['listOffset'] = (($pag -1)*$this->text['listLimit']);
            if($this->text['listOffset']>$count) $this->text['listOffset'] = $count;
            static::$headers['offset'] = $this->text['listOffset'];
        } else if(isset(static::$headers['limit'])) {
            static::$headers['offset'] = $this->text['listOffset']; 
        }
        if(isset($scope)) {
            if($f=Tecnodesign_App::request('get', static::REQ_FIELDS)) {
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

    public function model($req=array(), $max=1, $collection=false)
    {
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
        return $cn::find($this->search,$max,$this->scope($a,true,true),$collection,$order,$this->groupBy);
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
                    $scope = $a.'-'.static::$format;
                    $this->scope = $this->options['scope'][$a.'-'.static::$format];
                } else if(isset($this->options['scope'][$a])) {
                    $scope = $a;
                    $this->scope = $this->options['scope'][$a];
                } else {
                    $scope = null;
                }
                if($scope && !isset($cn::$schema['scope'][$scope])) {
                    $cn::$schema['scope'] += $this->options['scope'];
                }
                unset($scope);
            }
            if(!is_array($this->scope)) $this->scope = $cn::columns($this->scope);
        }
        if(($rs=tdz::slug(Tecnodesign_App::request('get', static::REQ_SCOPE))) && substr($rs, 0, 1)!='_' && is_array($this->scope)) {
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
                    if(!isset($U)) $U=tdz::getUser();
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
                            $this::$$p = $v;
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
                        $c = preg_split('/[\s\,\:]+/', substr($v, strpos($v, ':')+1), null, PREG_SPLIT_NO_EMPTY);
                        $v = substr($v, 0, strpos($v, ':'));
                        if(!isset($U)) $U = tdz::getUser();
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
        if($cn=$this->getModel()) {
            $sql = true;
            $Q = null;
            if(method_exists($cn, 'queryHandler')) {
                $Q = $cn::queryHandler();
                if($Q::TYPE!='sql') {
                    $sql = false;
                }
            }
            if($sql) {
                $pk = $cn::pk();
                if(is_array($pk) || strpos($pk, ' ')) $pk='`*`';
                else $pk = 'distinct `'.$pk.'`';
                $R = $cn::find($this->search,1,array('count('.$pk.') _count'),true,false,true);
                if($R) $r = (int) $R->_count;
                unset($R);
            } else if($Q && method_exists($Q, 'count')) {
                if($this->search) {
                    $Q->where($this->search);
                }
                $r = $Q->count();
            } else {
                $pk = $cn::pk(null, true);
                $R = $cn::find($this->search,0,$pk,true,false,true);
                if($R) $r = $R->count();
                unset($R);
            }
            unset($Q);
        }
        return $r;
    }

    public function searchForm($post=array(), $render=true)
    {
        foreach(array('o', 'd', 'p') as $p) {
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
            'class'=>'tdz-auto tdz-search tdz-no-empty tdz-simple-serialize',
            'method'=>'get',
            'action'=>$this->link($dest, false),
            'buttons'=>array('submit'=>tdz::t('Search', 'interface'),
            'cleanup'=>tdz::t('Cleanup', 'interface')),
            'fields'=>array()
        );
        $fieldset = tdz::t('Search options', 'interface');
        $active = false;
        $noq = false;
        $scopes = 1;
        if(isset($scope['q']) && is_array($scope['q'])) {
            $scopes++;
            $addScope = array($scope);
            $scope = $scope['q'];
            unset($addScope[0]['q']);
        }
        while($scopes-- > 0) {
            foreach($scope as $k=>$fn) {
                $label = $k;
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
                    $scope[$label]=$fn;
                }
                if(is_int($label)) $label = $cn::fieldLabel($fn);
                $fd = $cn::column($fn, true, true);
                if(!$fd) {
                    if(isset($fd0)) {
                        $fd = $fd0;
                        unset($fd0);
                    } else {
                        $fd = array('type'=>'text');
                    }
                } else if(isset($fd0)) {
                    $fd = array_merge($fd, $fd0);
                    unset($fd0);
                }
                if(!isset($fd['type'])) $fd['type']='text';
                $slug=tdz::slug($label);
                $fns[$slug]=$fn;

                if(substr($fd['type'],0,4)=='date') {
                    $fo['fields'][$slug.'-0']=array(
                        'type'=>$fd['type'],
                        'label'=>$label,
                        'id'=>$slug.'-0',
                        //'attributes'=>array('onchange'=>'$(\'#'.$fn.'1\').datepicker(\'option\',\'minDate\', $(this).val());'), 
                        'placeholder'=>tdz::t('From', 'interface'),
                        'fieldset'=>$fieldset,
                        'class'=>'tdz-search-input tdz-date tdz-date-from tdz-'.$fd['type'].'-input', 
                    );
                    $fo['fields'][$slug.'-1']=array(
                        'type'=>$fd['type'],
                        'label'=>'',
                        'id'=>$slug.'-1',
                        'placeholder'=>tdz::t('To', 'interface'),
                        'fieldset'=>$fieldset,
                        'class'=>'tdz-search-input tdz-date tdz-date-to tdz-'.$fd['type'].'-input', 
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
                    if(!isset($fd['type']))$fd['type']='checkbox';
                    $type = ($fd['type']!='select')?($fd['type']):('select');

                    if($fd['choices'] && is_string($fd['choices']) && isset($cn::$schema['relations'][$fd['choices']]['className'])) 
                        $fd['choices'] = $cn::$schema['relations'][$fd['choices']]['className'];
                    else if(is_string($fd['choices']) && ($m=$fd['choices']) && method_exists($cn, $m)) $fd['choices']=$cn::$m();

                    $fo['fields'][$slug]=array(
                        'type'=>$type,
                        'choices'=>$fd['choices'],
                        'multiple'=>((isset($fd['multiple']) && $fd['multiple']) || $type=='checkbox'),
                        'label'=>$label,
                        'placeholder'=>$label,
                        'fieldset'=>$fieldset,
                        'class'=>'tdz-search-input tdz-'.$type.'-input',
                    );
                    if(isset($post[$slug])) $active=true;
                } else if($fd['type']=='bool' || (isset($fd['foreign']) || (($fdo = $cn::column($fn)) && isset($fdo['type']) && $fdo['type']=='bool'))) {
                    if(!isset($cb))
                        $cb=array('1'=>tdz::t('Yes', 'interface'), '-1'=>tdz::t('No', 'interface'));
                    $fo['fields'][$slug]=array(
                        'type'=>'checkbox',
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
                            $post[$slug] = (tdz::raw($post[$slug]))?('1'):('-1');
                        }
                    }
                } else if($noq || (isset($fd['filter']) && $fd['filter'])) {
                    $ff[$slug]='choices';
                    $fo['fields'][$slug]=array(
                        'type'=>'text',
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
                        $fo['fields']['q']=array(
                            'type'=>'text',
                            'size'=>'200',
                            'label'=>'',
                            'placeholder'=>tdz::t('Search for', 'interface'),
                            'fieldset'=>$fieldset,
                            'class'=>'tdz-search-input'
                        );
                    } else {
                        if(!isset($fo['fields']['w'])) {
                            $fo['fields']['w']=array(
                                'type'=>'checkbox',
                                'choices'=>$ff['q'],
                                'label'=>tdz::t('Search at', 'interface'),
                                'multiple'=>true, 'fieldset'=>$fieldset,
                                'class'=>'tdz-search-input tdz-check-input',
                            );
                        } else {
                            $fo['fields']['w']['choices'][$slug]=$label;
                        }
                    }
                    if(isset($post['q'])) $active = true;
                }
                unset($scope[$k], $k, $fd, $fdo, $label, $fn, $slug);
            }
            if($scopes > 0) {
                if(isset($addScope) && $addScope) {
                    $scope = array_shift($addScope);
                    $noq = true;
                } else {
                    break;
                }
            }
        }
        $F = new Tecnodesign_Form($fo);
        if(!$F->id) $F->id = 'q-'.$this->text['interface'];
        if($active && $F->validate($post)) {
            $d=$F->getData();
            $this->text['searchTerms'] = '';
            if(is_null($this->search)) $this->search = array();
            foreach($d as $k=>$v) {
                if($v==='' || is_null($v)) continue;
                if($k=='q') {
                    if(!$v) continue;
                    $w = (isset($d['w']))?($d['w']):(array_keys($ff['q']));
                    if(!is_array($w)) $w = preg_split('/\,/', $w, null, PREG_SPLIT_NO_EMPTY);
                    $ps = $this->search;
                    $this->search = array();
                    foreach($w as $slug) {
                        $this->search['|'.$fns[$slug].'%=']=$v;
                        $this->text['searchTerms'] .= (($this->text['searchTerms'])?(' '.tdz::t('or', 'interface').' '):(''))
                                        . '<span class="'.static::$attrParamClass.'">'.$ff['q'][$slug].'</span>';
                    }
                    $this->search += $ps;
                    unset($ps);
                    if($this->text['searchTerms']) $this->text['searchTerms'] .= ': <span class="'.static::$attrTermClass.'">'.tdz::xmlEscape($post['q']).'</span>';
                    continue;
                } else if($k=='w') continue;

                if(!isset($ff[$k]) && substr($k, -2, 1)=='-' && isset($ff[$k1=substr($k, 0, strrpos($k, '-'))])) {
                    $type = substr($ff[$k1], 0, 4);
                    $k0 = $k;
                    $k = $k1;
                    unset($k1);
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
                            $this->search[$fns[$k].'.'.$fk]='';
                        } else {
                            if($c1) $this->search[$fns[$k].'!=']='';
                            else $this->search[$fns[$k]]='';
                        }
                        $this->text['searchTerms'] .= (($this->text['searchTerms'])?('; '):(''))
                                    . '<span class="'.static::$attrParamClass.'">'.$F[$k0]->label.'</span>: '
                                    . '<span class="'.static::$attrTermClass.'">'.(($c1)?(tdz::t('Yes', 'interface')):(tdz::t('No', 'interface'))).'</span>';
                    }
                } else if($ff[$k]=='choices') {
                    if(!$v || !is_object($F[$k0])) continue;
                    $this->search[$fns[$k]] = $v;
                    $this->text['searchTerms'] .= (($this->text['searchTerms'])?('; '):(''))
                                . '<span class="'.static::$attrParamClass.'">'.$F[$k0]->label.'</span>: '
                                . '<span class="'.static::$attrTermClass.'">'.$cn::renderAs($v, $fns[$k], ((isset($fo['fields'][$k]))?($fo['fields'][$k]):(null))).'</span>';
                } else if($type=='date') {
                    $t0=$t1=false;
                    if(isset($d[$k.'-0']) && $d[$k.'-0']) {
                        $t0 = tdz::strtotime($d[$k.'-0']);
                        unset($d[$k.'-0']);
                    }
                    if(isset($d[$k.'-1']) && $d[$k.'-1']) {
                        $t1 = tdz::strtotime($d[$k.'-1']);
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
                        $this->search[$fns[$k].'~']=array(date($df, $t0), date($df, $t1));
                        $this->text['searchTerms'] .= (($this->text['searchTerms'])?('; '):(''))
                                . '<span class="'.static::$attrParamClass.'">'.$F[$k.'-0']->label.'</span>: '
                                . '<span class="'.static::$attrTermClass.'">'.tdz::dateDiff($t0, $t1, $dt).'</span>';
                    } else if($t0) {
                        $this->search[$fns[$k].'>=']=date($df, $t0);
                        $this->text['searchTerms'] .= (($this->text['searchTerms'])?('; '):(''))
                                . '<span class="'.static::$attrParamClass.'">'.$F[$k.'-0']->label.'</span>: '
                                . '<span class="'.static::$attrTermClass.'">'.tdz::t('from', 'interface').' '.tdz::date($t0, $dt).'</span>';
                    } else if($t1) {
                        $this->search[$fns[$k].'<=']=date($df, $t1);
                        $this->text['searchTerms'] .= (($this->text['searchTerms'])?('; '):(''))
                                . '<span class="'.static::$attrParamClass.'">'.$F[$k.'-0']->label.'</span>: '
                                . '<span class="'.static::$attrTermClass.'">'.tdz::t('to', 'interface').' '.tdz::date($t1, $dt).'</span>';
                    }
                }
            }
            $this->text['searchCount'] = $this->count();
        }
        $this->text['searchForm'] = $F;
        return (isset($this->text['searchCount']))?($this->text['searchCount']):($this->text['count']);
    }

    public static function listInterfaces($base=null)
    {
        if(!is_null($base)) static::$base = $base;
        else if(is_null(static::$base)) static::$base = tdz::scriptName();
        $Is = static::find();
        $ul = array();
        $pp=array();
        $pl=array();
        foreach($Is as $k=>$I) {
            if(!isset($I['title']) || !$I['title']) {
                $m = $I['model'];
                $I['title'] = $m::label();
                unset($m);
            }
            $p = str_pad((isset($I['options']['priority']))?($I['options']['priority']):(''), 5, '0', STR_PAD_LEFT).tdz::slug($I['title']);
            if(isset($I['options']['list-parent'])) {
                if(!$I['options']['list-parent']) {
                    unset($Is[$k], $I, $k, $p, $m);
                    continue;
                }
                $pl[$p] = $I['options']['list-parent'];
            }
            $pp[$k] = $p;
            $ul[$p][0]='<a href="'.static::$base.'/'.$I['interface'].'">'.tdz::xmlEscape($I['title']).'</a>';
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
        $s = '<li>'.$o[0];
        if(isset($o[1])) {
            $s .= '<ul>';
            foreach ($o[1] as $k=>$v) {
                $s .= self::_li($v);
                unset($o[1][$k], $k, $v);
            }
            $s .= '</ul>';
        }
        $s .= '</li>';
        return $s;
    }

    public static function find($q=null)
    {
        if($q) {
            if(is_string($q)) return array(static::loadInterface($q));
        }
        $Is = array();
        $dd = tdz::getApp()->tecnodesign['data-dir'];
        $da = (!static::$authDefault)?(true):(static::checkAuth(static::$authDefault));
        foreach(static::$dir as $d) {
            foreach(glob(((substr($d, 0, 1)!='/')?($dd.'/'):('')).$d.'/*.yml') as $i) {
                $a = basename($i, '.yml');
                $I = static::loadInterface($a);
                if(isset($I['enable']) && !$I['enable']) {
                    $I = null;
                } else if(isset($I['auth'])) {
                    if(!static::checkAuth($I['auth'])) {
                        $I = null;
                    }
                } else if(!$da) {
                    $I=null;
                }
                if($I) {
                    $Is[$a] = $I;
                }
                unset($I, $i, $a);
            }
        }
        return $Is;
    }

    public static function configFile($s)
    {
        static $dd;
        if(is_null($dd)) $dd = tdz::getApp()->tecnodesign['data-dir'];
        $s = tdz::slug($s, '/_');
        foreach(static::$dir as $d) {
            if(file_exists( $f=((substr($d, 0, 1)!='/')?($dd.'/'):('')).$d.'/'.$s.'.yml') ) {
                return $f;
            } elseif (file_exists( $f=((substr($d, 0, 1)!='/')?($dd.'/'):('')).$d.static::$base.'/'.$s.'.yml') ) {
                return $f;
            }
            unset($d, $f);
        }
        unset($s);
    }

    public static function loadInterface($a=array(), $prepare=true)
    {
        if(!is_array($a) && $a) {
            $a = array('interface'=>$a);
        }
        if(isset($a['interface']) && $a['interface']) {
            if($f = static::configFile($a['interface'])) {
                $cfg = tdz::config($f, tdz::env());
                if($cfg) $a += $cfg;
                unset($cfg);
            }
            if(isset($a['base'])) {
                $i=3;
                while(isset($a['base']) && ($f=static::configFile($a['base']))) {
                    unset($a['base']);
                    $a += tdz::config($f, tdz::env());
                    if($i-- <=0) break;
                }
            }

            static $r;
            if(!$r) {
                $r = array('$DATE'=>date('Y-m-d\TH:i:s'), '$TODAY'=>date('Y-m-d'), '$NOW'=>date('H:i:s'));
            }
            $a = tdz::replace($a, $r);
            unset($f);
        }
        if($prepare && isset($a['model']) && isset($a['prepare'])) {
            list($c,$m) = (is_array($a['prepare']))?($a['prepare']):(array($a['model'],$a['prepare']));
            if(is_string($c)) $a = $c::$m($a);
            else $a = $c->$m($a);
            unset($c, $m);
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
    public function  offsetGet($name)
    {
        if (method_exists($this, $m='get'.ucfirst(tdz::camelize($name)))) {
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
    public function  offsetSet($name, $value)
    {
        if (method_exists($this, $m='set'.tdz::camelize($name))) {
            $this->$m($value);
        } else if(!property_exists($this, $name)) {
            throw new Tecnodesign_Exception(array(tdz::t('Column "%s" is not available at %s.','exception'), $name, get_class($this)));
        } else {
            $this->$name = $value;
        } 
        unset($m);
        return $this;
    }

    /**
     * ArrayAccess abstract method. Searches for stored parameters.
     *
     * @param string $name parameter name, should start with lowercase
     *
     * @return bool true if the parameter exists, or false otherwise
     */
    public function offsetExists($name)
    {
        return isset($this->$name);
    }

    /**
     * ArrayAccess abstract method. Unsets parameters to the PDF. Not yet implemented
     * to the PDF classes — only unsets values stored in $_vars
     *
     * @param string $name parameter name, should start with lowercase
     */
    public function offsetUnset($name)
    {
        return $this->offsetSet($name, null);
    }
}