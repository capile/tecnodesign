<?php
/**
 * Page contents
 *
 * PHP version 5.3
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2014 Tecnodesign
 * @link      https://tecnodz.com/
 */
class Tecnodesign_Studio_Content extends Tecnodesign_Model
{
   /**
     * Configurable behavior
     * This is only available for customizing Studio, please use the tdzContent class
     * within your lib folder (not TDZ_ROOT!) or .ini files
     */

    public static 
        $contentType = array(
            'html'=>'HTML',
            'md'=>'Markdown',
            'feed'=>'Feed',
            'media'=>'Media file',
            'php'=>'PHP script',
        ),
        $widgets = array(
        ),
        $multiviewContentType=array('widget','php','md'), // which entry types can be previewed
        $disableExtensions=array();                  // disable the preview of selected extensions

    /**
     * Tecnodesign_Model schema
     */
    //--tdz-schema-start--2014-12-27 18:32:23
    public static $schema = array (
      'database' => 'studio',
      'tableName' => 'tdz_contents',
      'label' => '*Contents',
      'className' => 'tdzContent',
      'columns' => array (
        'id' => array ( 'type' => 'string', 'increment' => 'auto', 'null' => false, 'primary' => true, ),
        'entry' => array ( 'type' => 'int', 'null' => true, ),
        'slot' => array ( 'type' => 'string', 'size' => '50', 'null' => true, ),
        'content_type' => array ( 'type' => 'string', 'size' => '100', 'null' => true, ),
        'content' => array ( 'type' => 'string', 'size' => '', 'null' => true, ),
        'position' => array ( 'type' => 'string', 'size'=>250, 'null' => true, ),
        'published' => array ( 'type' => 'datetime', 'null' => true, ),
        'version' => array ( 'type' => 'int', 'null' => true, ),
        'created' => array ( 'type' => 'datetime', 'null' => false, ),
        'updated' => array ( 'type' => 'datetime', 'null' => false, ),
        'expired' => array ( 'type' => 'datetime', 'null' => true, ),
      ),
      'relations' => array (
        'ContentDisplay' => array ( 'local' => 'id', 'foreign' => 'content', 'type' => 'many', 'className' => 'Tecnodesign_Studio_ContentDisplay', ),
        'Entry' => array ( 'local' => 'entry', 'foreign' => 'id', 'type' => 'one', 'className' => 'Tecnodesign_Studio_Entry', ),
      ),
      'scope' => array (
        'string'=>array('id', 'slot', 'Entry.link _url'),
        'preview' =>array('content_type', 'content'),
      ),
      'order' => array (
        'slot' => 'asc',
        'position' => 'asc',
        'version' => 'desc',
      ),
      'events' => array (
        'before-insert' => array ( 'actAs', ),
        'before-update' => array ( 'actAs', ),
        'before-delete' => array ( 'actAs', ),
        'after-insert' => array ( 'actAs', ),
        'after-update' => array ( 'actAs', ),
        'after-delete' => array ( 'actAs', ),
        'active-records' => 'expired is null',
      ),
      'form' => array (
        'content_type' => array ( 'bind' => 'content_type', 'type' => 'select', 'class' => 'studio-field-content-type s-inline', 'attributes'=>array('data-callback'=>'contentType')),
        'content' => array ( 'bind' => 'content', 'type' => 'textarea', 'class' => 'studio-field-content' ),
      ),
      'actAs' => array (
        'before-insert' => array ( 'auto-increment' => array ( 'id', ), 'timestampable' => array ( 'created', 'updated', ), 'sortable' => array ( 'position', ), ),
        'before-update' => array ( 'auto-increment' => array ( 'version', ), 'timestampable' => array ( 'updated', ), 'sortable' => array ( 'position', ), ),
        'before-delete' => array ( 'auto-increment' => array ( 'version', ), 'timestampable' => array ( 'updated', ), 'soft-delete' => array ( 'expired', ), 'sortable' => array ( 'position', ), ),
        'after-insert' => array ( 'versionable' => array ( 'version', ), ),
        'after-update' => array ( 'versionable' => array ( 'version', ), ),
        'after-delete' => array ( 'versionable' => array ( 'version', ), ),
      ),
    );
    protected $id, $entry, $slot, $content_type, $content, $position, $published, $version=false, $created, $updated=false, $expired, $ContentDisplay, $Entry;
    //--tdz-schema-end--
    protected static $content_types=null;
    protected $show_at, $hide_at, $modified;
    public $pageFile, $attributes, $subposition;


    public function __toString()
    {
        if($this->pageFile) {
            $s = ($this->pageFile && substr($this->pageFile, 0, strlen(tdzEntry::$pageDir))==tdzEntry::$pageDir)?(substr($this->pageFile, strlen(tdzEntry::$pageDir))):($this->pageFile);
        } else {
            if($this->_url) {
                $s = $this->_url;
            } else {
                $s = '(#'.$this->id.')';
            }
        }
        if($this->slot) {
            $s .= '#'.$this->slot;
        }
        return $s;
    }

    public static function choicesContentType()
    {
        return static::$contentType;
    }

    public static function find($q=null, $limit=0, $scope=null, $collection=true, $orderBy=null, $groupBy=null)
    {
        if((is_string($q) && ($page=tdz::decrypt($q, null, 'uuid'))) 
            || (isset($q['id']) && is_string($q['id'])&& ($page=tdz::decrypt($q['id'], null, 'uuid')))) {
            $C = Tecnodesign_Studio::content(TDZ_VAR.'/'.$page, false);
            if($limit==1) return $C;
            else return array($C);
        }
        if(!Tecnodesign_Studio::$connection) {
            Tecnodesign_Studio::$connection = static::$schema['database'];
            Tecnodesign_Studio::indexDb();
        }
        return parent::find($q, $limit, $scope, $collection, $orderBy, $groupBy);
    }

    
    public static function preview($c)
    {
        if(!($c instanceof self)) {
            $c = self::find($c);
        }
        if($c) {
            return $c->render(true);
        }
        return false;
    }

    public function getModified()
    {
        if(is_null($this->modified)) $this->modified = strtotime($this->updated);
        return $this->modified;
    }
    public function setModified($t)
    {
        $this->modified = $t;
    }
    /*
    public function getContent()
    {
        // should be valid json
        if(substr($this->content, 0,1)!='{') {
            tdz::debug(__METHOD__, var_Export($this, true));
            $this->content = json_encode(Tecnodesign_Yaml::load($this->content),true);
        }
        return $this->content;
    }
    */


    public function getContents()
    {
        if(substr($this->content, 0,1)=='{') {
            $r = json_decode($this->content, true);
        } else if(preg_match('#^\n*(---[^\n]*\n)?[a-z0-9\- ]+\:#i', $this->content)) {
            $r = str_replace('\r\n', "\n", Tecnodesign_Yaml::load($this->content));
            //$r = Tecnodesign_Yaml::load($this->content);
        } else {
            $r = $this->content;
        }
        if(!is_array($r)) {
            $r = array($r);
        }
        return $r;
    }

    /*
    public function getForm($scope)
    {
        $cn = get_called_class();
        if(!isset($cn::$schema['e-studio-configured'])) {
            $cn::$schema['e-studio-configured']=true;
            $cfg = Tecnodesign_Studio::config('content_types');
            $cn::$schema['scope']['e-studio']=array('content_type','content');
            foreach($cfg as $tn=>$d) {
                foreach($d['fields'] as $fn=>$fd) {
                    if(isset($fd['model'])) {
                        $fd['model']=str_replace(array('tdzEntries'), array('tdzEntry'), $fd['model']);
                        $fd['choices']=$fd['model'];
                        unset($fd['model']);
                        if(isset($fd['method'])) {
                            $fd['choices'].='::'.$fd['method'].'()';
                            unset($fd['method']);
                        }
                    }
                    if(isset($fd['options'])) {
                        $fd['attributes']=$fd['options'];
                        unset($fd['options']);
                    }
                    if(isset($fd['required'])) {
                        if(!isset($fd['attributes'])) $fd['attributes']=array();
                        $fd['attributes']['required']=$fd['required'];
                        unset($fd['required']);
                    }
                    $n='content-'.$tn.'-'.$fn;
                    $cn::$schema['form'][$n]=$fd;
                    if(!isset($cn::$schema['form'][$n]['class'])) $cn::$schema['form'][$n]['class']='studio-field-disabled studio-field-contents studio-content-'.$tn;
                    else $cn::$schema['form'][$n]['class']='studio-field-disabled studio-field-contents studio-content-'.$tn.' '.$cn::$schema['form'][$n]['class'];
                    $cn::$schema['scope']['e-studio'][]=$n;
                }
            }
            $cn::$schema['scope']['e-studio'][]='show_at';
            $cn::$schema['scope']['e-studio'][]='hide_at';
        }
        $cn::$schema['scope'][$scope]=$cn::$schema['scope']['e-studio'];
        tdz::debug($cn::$schema);
        return parent::getForm($scope);
    }

    public static function contentTypes()
    {
        return static::$contentType;
        if(is_null(self::$content_types)) {
            self::$content_types=Tecnodesign_Studio::$app->studio['content_types'];
            self::$widgets=Tecnodesign_Studio::$app->studio['widgets'];
            if(is_array(self::$widgets) && count(self::$widgets)>0) {
                $wg=array();
                foreach(self::$widgets as $wk=>$w) {
                    $wg[$wk]=$w['label'];
                    unset($wk, $w);
                }
                self::$content_types['widget']=array('title'=>'Widgets','fields'=>array('app'=>array('label'=>'Widget','type'=>'choice','required'=>true,'choices'=>$wg)));
            } else {
                self::$widgets = array();
            }
        }
        return self::$content_types;
    }
    */

    public function save($beginTransaction=null, $relations=null, $conn=false)
    {
        if($this->pageFile && ($f=tdzEntry::file($this->pageFile))) {
            $p = file_get_contents($f, false, null, 0, 40960);
            if($p && ($m=tdzEntry::meta($p))) {
                $c = "<!--{$m}-->{$this->content}";
            } else {
                $c = $this->content;
            }
            if(!tdz::save($f, $c)) {
                throw new Exception("Could not save [{$this->pageFile}]");
                return false;
            }
            // will this be removed if we index?
            // force reindex
            return true;
        } else if(!$this->id && !is_numeric($this->id) && $this->isNew()) {
            $this->id=null;
        }
        return parent::save($beginTransaction, $relations, $conn);
    }

    public function render($display=false)
    {
        /*
        if(!$this->hasPermission('preview')) {
            return false;
        }
        */
        $id = Tecnodesign_Studio_Entry::$s++;
        $code = $this->getContents();
        $code['slot']=$this->slot;
        $type = $this->content_type;
        $attr = array('id'=>'c'.$id, 'data-studio-c'=>$this->id);
        if(isset($this->attributes)) {
            $attr += $this->attributes;
        }
        $a = '';
        foreach($attr as $n=>$v) {
            $a .= ' '.$n.'="'.tdz::xmlEscape($v).'"';
            unset($attr[$n], $n, $v);
        }
        unset($attr);
        if(file_exists($tpl=Tecnodesign_Studio::$app->tecnodesign['templates-dir'].'/tdz-contents-'.$type.'.php')) {
            if(!isset($code['txt']) && isset($code[0])) {
                $code['txt']=$code[0];
                unset($code[0]);
            }
            $s = "<div{$a}>"
                . tdz::exec(array('script'=>$tpl, 'variables'=>$code))
                . '</div>';
            return $s;
        }
        $ct = (isset(static::$contentType[$type]))?(static::$contentType[$type]):(array());
        $call = (isset($ct['class']) && class_exists($ct['class']))?(array($ct['class'])):(array($this));
        if(isset($ct['method']) && method_exists($call[0], $ct['method'])) {
            $call[1] = $ct['method'];
        } else {
            $call[1] = 'render'.ucfirst($type);
        }
        $r = call_user_func($call, $code, $this);
        unset($call[0], $call);
        if($display) {
            $result = '';
            if(is_array($r)) {
                if(isset($r['before'])) {
                    $result .= $r['before'];
                }
                if(isset($r['export'])) {
                    $result .= eval("return {$r['export']};");
                } else {
                    $result .= (isset($r['content']))?($r['content']):('');
                }
            } else {
                $result .= $r;
            }
            unset($r);
            if($this->slot=='meta') return $result;
            $result = "<div{$a}>{$result}</div>";
            return $result;
        }
        if(is_array($r)) {
            $r['before'] = "<div{$a}>";
            $r['after'] = "</div>";
        }
        return $r;
    }

    public static function renderMedia($code=null, $e=null)
    {
        if(!isset($code['src'])||$code['src']=='') {
            return '';
        }
        if(!isset($code['format'])||$code['format']=='') {
            $code['format']=tdz::fileFormat($code['src']);
        }
        $s='';
        if(preg_match('/(image|pdf|flash|download|video|audio)/', strtolower($code['format']), $m)) {
            $f=$m[1];
        } else {
            $f='download';
        }
        $code['format'] = $f;
        foreach($code as $k=>$v) if($v===null || $v==='') unset($code[$k]);
        $tpl = Tecnodesign_Studio::templateFile('tdz_media_'.$f, 'tdz_media');

        return tdz::exec(array('script'=>$tpl, 'variables'=>$code));
    }


    public static function renderHtml($code=null, $e=null)
    {
        if(is_array($code)) {
            $code = isset($code['html'])?($code['html']):(array_shift($code));
        }
        return trim($code);
    }

    public static function renderText($code=null)
    {
        if(is_array($code)) {
            $code = isset($code['txt'])?($code['txt']):(array_shift($code));
        }
        return $code;
    }

    public static function renderMd($code=null)
    {
        return tdz::markdown(static::renderText($code));
    }

    public static function renderPhp($code=null, $e=null)
    {
        if(!is_array($code)) {
            $code = array('pi'=>$code);
        } else if(isset($code[0])) {
            $code['pi']=$code[0];
            unset($code[0]);
        }
        if(isset($code['script'])) {
            if($code['script'] && file_exists($f=Tecnodesign_Studio::$app->tecnodesign['apps-dir'].'/'.$code['script'])) {
                $code['script']=$f;
            } else {
                unset($code['script']);
            }
        }
        if(isset($code['pi'])) {
            $code['pi']=trim($code['pi']);
            if(substr($code['pi'], 0,5)=='<'.'?php') $code['pi'] = trim(substr($code['pi'], 5));
        }
        return tdz::exec($code);
        /*
        if(Tecnodesign_Studio::$cacheTimeout===false) {
            return tdz::exec($code);
        }
        return array('export'=>'tdz::exec('.var_export($code,true).')');
        */
    }

    public static function renderWidget($code=null, $e=null)
    {
        if(!is_array($code) || !isset($code['app']) || !isset(self::$widgets[$code['app']])) {
            return false;
        }
        $app=self::$widgets[$code['app']];
        $call = array();
        if(isset($app['model']) && class_exists($app['model'])) {
            $call[0] = $app['model'];
            if(isset($app['method']) && method_exists($call[0], $app['method'])) {
                $call[1] = $app['method'];
                if(!Tecnodesign_Studio::$cacheTimeout || (isset($app['cache']) && $app['cache'])) {
                    return call_user_func($call, $e);
                } else if(is_string($call[0])) {
                    return array('export'=>$call[0].'::'.$call[1].'('.var_export($e, true).')');
                } else {
                    return array('export'=>'call_user_func('.var_export($call, true).', '.var_export($e, true).')');
                }
            }
        }
    }

    public static function renderFeed($code=null, $e=null)
    {
        if(!is_array($code)) {
            $code = array('entry'=>$code);
        }
        $o = array('variables'=>$code);
        /**
         * $code should contain:
         *
         *   entry  (mandatory) integer  The feed id
         *   master (optional) string   The template to use
         *
         * If the entry is not found, it should use current feed as a parameter
         */
        $o['script'] = Tecnodesign_Studio::templateFile((isset($code['master']))?($code['master']):(null), 'tdz_feed');
        if(!is_numeric($o['variables']['entry'])) {
            $o['variables']['entry']=$e;
        }
        $E = ($e instanceof tdzEntry)?($e):(tdzEntry::find($o['variables']['entry']));
        if($E) {
            $o['variables'] += $E->asArray();
            $f = array('Relation.parent'=>$E->id);
            if(!(Tecnodesign_Studio::$private && !Tecnodesign_Studio::$cacheTimeout)) {
                $f['published<']=date('Y-m-d\TH:i:s');
            }
            $o['variables']['entries'] = tdzEntry::find($f,0,'preview',(isset($code['hpp']) && $code['hpp']),($E->type=='page')?(array('Relation.position'=>'asc','published'=>'desc')):(array('published'=>'desc')));
        }
        /*
        if(!Tecnodesign_Studio::$cacheTimeout || (!isset($code['hpp']) || !$code['hpp'])) {
            return tdz::exec($o);
        }
        */
        unset($code);
        return array('export'=>'tdz::exec('.var_export($o,true).')');
    }
    
}
