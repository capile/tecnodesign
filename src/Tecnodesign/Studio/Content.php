<?php
/**
 * Page contents
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Studio_Content extends Tecnodesign_Studio_Model
{
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
        $disableExtensions=array(),                  // disable the preview of selected extensions
        $allowMarkdownExtensions=true;

    public static $schema;
    protected $id, $entry, $slot, $content_type, $source, $attributes, $content, $position, $published, $version=false, $created, $updated=false, $expired, $show_at, $hide_at, $ContentDisplay, $Entry;
    protected static $content_types=null;

    public function __toString()
    {
        if($this->source) {
            $s = $this->source;
        } else {
            if($this->_url) {
                $s = $this->_url;
            } else {
                $s = '(#'.$this->id.')';
            }
        }

        return $s;
    }

    public static function choicesContentType()
    {
        return static::$contentType;
    }

    public function previewContentType()
    {
        if(isset(static::$contentType[$this->content_type])) {
            return static::$contentType[$this->content_type];
        }
        return $this->content_type;
    }

    public static function choicesSlot()
    {
        $r = array();
        foreach(tdzEntry::$slots as $n=>$c) {
            $r[$n] = Tecnodesign_Studio::t($n, ucfirst($n));
        }
        return $r;
    }

    public function previewSlot()
    {
        if($this->slot) {
            return Tecnodesign_Studio::t($this->slot, ucfirst($this->slot));
        }
    }

    public static function find($q=null, $limit=0, $scope=null, $collection=true, $orderBy=null, $groupBy=null)
    {
        if((is_string($q) && ($page=tdz::decrypt($q, null, 'uuid'))) 
            || (isset($q['id']) && is_string($q['id'])&& ($page=tdz::decrypt($q['id'], null, 'uuid')))) {
            $C = Tecnodesign_Studio::content(Tecnodesign_Studio_Entry::file($page), false);
            if($limit==1) return $C;
            else return array($C);
        }
        if(!Tecnodesign_Studio::$connection) {
            return false;
            //Tecnodesign_Studio::$connection = static::$schema['database'];
            //Tecnodesign_Studio::indexDb();
        }
        return parent::find($q, $limit, $scope, $collection, $orderBy, $groupBy);
    }

    
    public static function preview($c)
    {
        if(!($c instanceof self)) {
            $c = self::find($c,1);
        }
        if($c) {
            return $c->render(true);
        }
        return false;
    }

    public function getContents()
    {
        if(substr($this->content, 0,1)=='{') {
            $r = json_decode($this->content, true);
        } else if(preg_match('#^\n*(---[^\n]*\n)?[a-z0-9\- ]+\:#i', $this->content)) {
            $r = str_replace(array('\r\n', "\\r\n"), "\n", Tecnodesign_Yaml::load($this->content));
            //$r = Tecnodesign_Yaml::load($this->content);
        } else {
            $r = $this->content;
        }
        if(!is_array($r)) {
            $r = array($r);
        }
        return $r;
    }

    public static function entry($page)
    {
        $url = preg_replace('#(/[^\.]+)(\.[^\.]+)*(\.[^\.]+)$#', '$1$3', $page);
        if(tdzEntry::file($url)) {
            return $url;
        }
    }

    public function getEntry()
    {
        if(!$this->entry && $this->source && ($e=static::entry($this->source))) {
            return tdz::hash($e, null, 'uuid');
        }
        return $this->entry;
     }

    public function getPosition()
    {
        if(isset($this->_position)) {
            return preg_replace('/[^0-9]+/', '', $this->_position);
        }
        return $this->position;
    }

    public static function attributes($A=array())
    {
        if($A) {
            $r=array();
            foreach($A as $n=>$v) {
                if(is_string($n)) {
                    $r[] = '<li><strong>'.tdz::xmlEscape($n).'</strong>: '.tdz::xmlEscape($v).'</li>';
                } else {
                    $r[] = '<li><strong>'.tdz::xmlEscape($v['name']).'</strong>: '.tdz::xmlEscape($v['value']).'</li>';
                }
            }
            if($r) {
                return '<ul>'.implode('', $r).'</ul>';
            }
        }
    }

    public function save($beginTransaction=null, $relations=null, $conn=false)
    {
        if(!Tecnodesign_Studio::$connection) {
            if($this->isNew() || ($this->source && ($f=tdzEntry::file($this->source)))) {

                $m = array();
                if($this->attributes) {
                    $m['attributes'] = $this->attributes;
                }

                if($m) {
                    $c = "<!--\n".Tecnodesign_Yaml::dump($m)."\n-->\n{$this->content}";
                } else {
                    $c = $this->content;
                }

                // check if it is a different file
                $slotpos = '.'.$this->slot.(($this->position)?('.'.substr('0000'.$this->position, -4)):(''));
                if($slotpos=='.body.0000' || $slotpos=='.body') $slotpos = '';
                $page = $this->getEntry().$slotpos.'.'.$this->content_type;
                $rename = ($this->source && $page != $this->source);

                if(!tdz::save(tdzEntry::file($page, false), $c)) {
                    throw new Exception("Could not save [{$this->source}]");
                    return false;
                }
                if($rename) {
                    unlink($f);
                }
                // git add && git commit && git push
                // force reindex
                return true;
            } else if(!$this->id && !is_numeric($this->id) && $this->isNew()) {
                $this->id=null;
            }
            throw new Tecnodesign_Exception('We could not find the source content to update.', 1);

        }
        return parent::save($beginTransaction, $relations, $conn);
    }

    public function render($display=true)
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
        if($a=$this['attributes.attributes']) {
            $attr += $a;
        }
        $a = '';
        foreach($attr as $n=>$v) {
            $a .= ' '.$n.'="'.tdz::xmlEscape($v).'"';
            unset($attr[$n], $n, $v);
        }
        unset($attr);
        if($tpl=\tdz::templateFile('tdz-contents-'.$type)) {
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
                } else if(isset($r['exec'])) {
                    $result .= tdz::exec($r['exec']);
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
        } else {
            $r = "<div{$a}>{$r}</div>";
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
        return tdz::markdown(static::renderText($code), !static::$allowMarkdownExtensions);
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
        if(Tecnodesign_Studio::$staticCache) {
            return $code;
        }
        return tdz::exec($code);
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

        $eid = null;
        $E = null;
        if(is_object($e) && ($e instanceof Tecnodesign_Studio_Entry)) {
            $eid = (int)$e->id;
            $E = $e;
        } else if(isset($code['entry']) && is_numeric($code['entry'])) {
            $eid = (int)$code['entry'];
            $q = ['id'=>$eid];
        } else {
            $q = $e;
        }

        if(!$E) $E = tdzEntry::find($q,1);
        $o['variables']['entry']=$E;
        if($E) {
            $o['variables'] += $E->asArray();
            $f = array('Relation.parent'=>$E->id);
            if(!(Tecnodesign_Studio::$private && !Tecnodesign_Studio::$cacheTimeout)) {
                $f['published<']=date('Y-m-d\TH:i:s');
            }
            $o['variables']['entries'] = tdzEntry::find($f,null,'preview',(isset($code['hpp']) && $code['hpp']),($E->type=='page')?(array('Relation.position'=>'asc','published'=>'desc')):(array('published'=>'desc')));
        }
        /*
        if(!Tecnodesign_Studio::$cacheTimeout || (!isset($code['hpp']) || !$code['hpp'])) {
            return tdz::exec($o);
        }
        */
        unset($code);
        return tdz::exec($o);
        //return array('export'=>'tdz::exec('.var_export($o,true).')');
    }

    public function renderSource()
    {
        static $doNotList = ['template'];
        $ct = $slot = null;
        if($this->content_type) {
            $ct = (isset(static::$contentType[$this->content_type])) ?static::$contentType[$this->content_type] :null;
            if(substr($ct, 0, 1)=='*') $ct = Tecnodesign_Studio::t(substr($ct, 1), ucfirst(substr($ct, 1)));
        }
        if($this->slot) {
            $slot = (isset(Tecnodesign_Studio_Entry::$slots[$this->slot])) ?Tecnodesign_Studio_Entry::$slots[$this->slot] :null;
            if($slot) $slot = Tecnodesign_Studio::t($slot, ucfirst($slot));
        }

        $s = '';
        $C = $this->getContents();
        if($C) {
            foreach($C as $i=>$o) {
                if(!$o) continue;
                $n = '_'.$i;
                $label = (isset(static::$schema->overlay[$n]['label'])) ?static::$schema->overlay[$n]['label'] :$n;
                if(substr($label, 0, 1)=='*') $label = Tecnodesign_Studio::t(substr($label, 1), ucfirst(substr($label, 1)));

                $c = null;
                if($i=='entry') {
                    $c = $o;
                } else if($i=='src') {
                    $c = '<img src="'.\tdz::xml($o).'" />';
                } else if($i=='html' && ($c = tdz::xml(trim(strip_tags($o))))) {
                } else if(is_array($o) || in_array($i, $doNotList)) {
                    continue;
                } else {
                    $c = '<code>'.tdz::xml($o).'</code>';
                }
                if($c) $s .= '<span class="z-tag">'.\tdz::xml($label).' </span>'.$c."\n<br />";
            }
        }

        if($slot) $s = '<em>'.tdz::xml($slot).' </em> '.$s;
        if($ct) $s = '<strong>'.tdz::xml($ct).' </strong>'.$s;

        return $s;

    }

    public static function studioIndex($a, $icn=null, $scope='preview', $keyFormat=true, $valueFormat=true, $serialize=true)
    {
        static $pages, $root;

        if(is_null($pages)) {
            $pages = [];
            $root = preg_replace('#/+$#', '', tdzEntry::file(''));
            if(!$root) return false;
            $pages = glob($root.'/*');
        }

        if(!$pages) {
            return;
        }

        $page = array_shift($pages);
        $basename = basename($page);
        if(substr($basename, 0, 1)=='.') {
            // do nothing
        } else if(is_dir($page)) {
            $pages = array_merge($pages, glob($page.'/*'));
        } else if($o=Tecnodesign_Studio::content($page, false, false, false)) {
            // process $page
            $pk = $o->getPk();
            $P = [];
            if($preview=$o->asArray($scope, $keyFormat, $valueFormat, $serialize)) {
                foreach($preview as $n=>$v) {
                    $P[] = ['interface'=>$a['interface'], 'id'=>$pk, 'name'=>$n, 'value'=>$v];
                }
            }

            try {
                Tecnodesign_Studio_Index::replace([
                    'interface'=>$a['interface'],
                    'id'=>$pk,
                    'summary'=>(string) $o,
                    'indexed'=>TDZ_TIMESTAMP,
                    'IndexProperties'=>$P,
                ]);
            } catch (\Exception $e) {
                tdz::log('[ERROR] There was an issue while indexing contents: '.$e->getMessage());
            }
        }

        return static::studioIndex($a, $icn, $scope, $keyFormat, $valueFormat, $serialize);
    }
}
