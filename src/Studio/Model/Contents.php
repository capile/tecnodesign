<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
namespace Studio\Model;

use Studio\Model as Model;
use Studio\Model\Entries as Entries;
use Studio\Studio as Studio;
use Tecnodesign_App as App;
use Tecnodesign_Cache as Cache;
use Studio\Api as Api;
use Tecnodesign_Collection as Collection;
use Tecnodesign_Yaml as Yaml;
use Tecnodesign_Exception as Exception;
use Studio as S;

class Contents extends Model
{
    public static 
        $schema,
        $schemaClass='Studio\\Schema\\Model',
        $contentType = [
            'feed'=>'*Feed',
            'html'=>'*HTML',
            'md'=>'*Markdown',
            'media'=>'*Media file',
            'php'=>'*PHP script',
            'txt'=>'*Plain text',
        ],
        $widgets = [],
        $multiviewContentType=['widget','php','md'], // which entry types can be previewed within multiple urls
        $disableExtensions=[],                  // disable the preview of selected extensions
        $previewContentType=['html', 'md', 'media'],
        $allowMarkdownExtensions=true;

    protected static $content_types=null;

    protected $id, $entry, $slot, $content_type, $source, $attributes, $content, $position, $published, $version=false, $created, $updated=false, $expired, $show_at, $hide_at, $ContentDisplay, $Entry;

    public function __toString()
    {
        if($this->source) {
            $s = $this->source;
        } else {
            if(isset($this->_url) && $this->_url) {
                $s = $this->_url;
            } else if(isset($this->_title)) {
                $s = trim($this->_title).'#'.$this->id;
            } else {
                $s = '(#'.$this->id.')';
            }
        }

        return $s;
    }

    public static function choicesContentType($choice=null)
    {
        static $checked;
        if(!$checked) {
            $checked = true;
            static::$contentType = S::checkTranslation(static::$contentType, 'model-tdz_contents');
            asort(static::$contentType);
        }
        if($choice) {
            return (isset(static::$contentType[$choice])) ?static::$contentType[$choice] :null;
        }
        return static::$contentType;
    }

    public function previewContentType()
    {
        if($this->content_type && ($r=$this->choicesContentType($this->content_type))) {
            return $r;
        }

        return $this->content_type;
    }

    public static function choicesSlot()
    {
        $r = array();
        foreach(Entries::$slots as $n=>$c) {
            $r[$n] = Studio::t($n, ucfirst($n));
        }
        return $r;
    }

    public function previewSlot()
    {
        if($this->slot) {
            return Studio::t($this->slot, ucfirst($this->slot));
        }
    }

    public function previewEntry()
    {
        if(!isset($this->entry)) {
            $this->refresh(['entry']);
        }

        if($this->entry) {
            $E = Entries::find(['id'=>$this->entry],1,['title','type']);
            if($E) {
                if(substr(S::scriptName(), 0, strlen(Studio::$home)+1)==Studio::$home.'/') {
                    $link = $E->getStudioLink().'/preview/'.$E->id;
                    return S::xml((string)$E).' <a href="'.$link.'" class="z-i-link z-i--preview"></a>';
                } else {
                    return S::xml((string)$E);
                }
            }
        }

    }

    public static function find($q=null, $limit=0, $scope=null, $collection=true, $orderBy=null, $groupBy=null)
    {
        if((is_string($q) && ($page=S::decrypt($q, null, 'uuid'))) 
            || (isset($q['id']) && is_string($q['id'])&& ($page=S::decrypt($q['id'], null, 'uuid')))) {
            $C = Studio::content(Entries::file($page), false);
            if($limit==1) return $C;
            else return array($C);
        //} else if(is_array($q) && isset($q['source']) && count($q)==1) {
        //    $C = Studio::content(S_VAR.'/'.$q['source'], false, false, false);
        }
        if(!Studio::$connection) {
            return false;
            //Studio::$connection = static::$schema['database'];
            //Studio::indexDb();
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

    public static function parseContent($r, $contentType=null)
    {
        if($r && is_string($r)) {
            $r = trim(str_replace(["\r", '\\r'], '', $r));
            if(substr($r, 0, 1)=='{') {
                $r = S::unserialize($r, 'json');
            } else if(preg_match('#^\n*(---[^\n]*\n)?[a-z0-9\- ]+\:#i', $r)) {
                $r = S::unserialize($r, 'yaml');
            }
        }

        if(!is_array($r)) {
            if($contentType && isset(static::$schema->scope[$s='u-'.$contentType])) {
                foreach(static::$schema->scope[$s] as $fn) {
                    $fn = preg_replace('/^content\./', '', $fn);
                    $r = [[ $fn => $r ]];
                    unset($fn);
                    break;
                }
            } else {
                $r = array($r);
            }
        }
        return $r;

    }

    public function getContents()
    {
        return self::parseContent($this->content);
    }

    public static function entry($page)
    {
        $url = preg_replace('#(/[^\.]+)(\.[^\.]+)*(\.[^\.]+)$#', '$1$3', $page);
        if(Entries::file($url)) {
            return $url;
        }
    }

    public function getEntry()
    {
        if(!$this->entry && $this->source && ($e=static::entry($this->source))) {
            return S::hash($e, null, 'uuid');
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
                    $r[] = '<li><strong>'.S::xml($n).'</strong>: '.S::xml($v).'</li>';
                } else {
                    $r[] = '<li><strong>'.S::xml($v['name']).'</strong>: '.S::xml($v['value']).'</li>';
                }
            }
            if($r) {
                return '<ul>'.implode('', $r).'</ul>';
            }
        }
    }

    public function save($beginTransaction=null, $relations=null, $conn=false)
    {
        if(!Studio::$connection) {
            if($this->isNew() || ($this->source && ($f=Entries::file($this->source)))) {

                $m = array();
                if($this->attributes) {
                    $m['attributes'] = $this->attributes;
                }

                if($m) {
                    $c = "<!--\n".Yaml::dump($m)."\n-->\n{$this->content}";
                } else {
                    $c = $this->content;
                }

                // check if it is a different file
                $slotpos = '.'.$this->slot.(($this->position)?('.'.substr('0000'.$this->position, -4)):(''));
                if($slotpos=='.body.0000' || $slotpos=='.body') $slotpos = '';
                $page = $this->getEntry().$slotpos.'.'.$this->content_type;
                $rename = ($this->source && $page != $this->source);

                if(!S::save(Entries::file($page, false), $c)) {
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
            throw new Exception('We could not find the source content to update.', 1);

        }
        return parent::save($beginTransaction, $relations, $conn);
    }

    public function previewContent()
    {
        //$c = S::xml($this->content);
        if(!isset($this->content_type) && $this->id) $this->refresh(['content_type']);
        $scope = null;
        if($this->content_type) {
            $scope = 'u-'.$this->content_type;
            if(!isset(static::$schema->scope[$scope])) {
                $scope = null;
            }
        }

        if($this->content_type && in_array($this->content_type, static::$previewContentType)) {
            $c = $this->render(true);
        } else if(!$scope) {
            $c = '<div class="z-inner-block">'.S::xml($this->content).'</div>';
        } else {
            if(is_string($scope)) $scope = static::columns($scope, null, true);
            if(count($scope)>2) {
                array_pop($scope);
                array_pop($scope);
            }
            $c = $this->renderScope($scope, true, null, '<dl class="$CLASS"><dt>$LABEL</dt><dd>$INPUT</dd></dl>');
        }

        return $c;
    }

    public function previewContentEntry()
    {
        if(($e=$this['content.entry']) && ($E=Entries::find(['id'=>$e],1,'string'))) {
            if(Api::format()=='html') {
                return S::xml((string)$E)
                        . ' <a class="z-i-a z-i-link z-i--list" href="'.Studio::$home.'/entry/q?'.S::slug(S::t('Newsfeed', 'model-tdz_entries')).'='.$E->id.'"></a>'
                        . ' <a class="z-i-a z-i-link z-i--new" href="'.Studio::$home.'/entry/n?'.S::slug(S::t('Newsfeed', 'model-tdz_entries')).'='.$E->id.'"></a>'
                        ;
            } else {
                return (string) $E;
            }
        }
    }

    public function getContent($p=null)
    {
        if(!isset($this->content)) {
            $this->refresh(['content']);
        }
        if($this->content) {
            if(is_string($this->content)) {
                $this->content = self::parseContent($this->content, $this->content_type);
            }
            if($p) {
                if(isset($this->content[$p])) {
                    return $this->content[$p];
                }
                return;
            }
        }

        return $this->content;
    }

    public function setContent($v)
    {
        if($v) {
            if(is_string($v)) {
                if(!S::unserialize($v)) {
                    $v = ['txt'=>$v];
                }
            } else if(is_array($v)) {
                if(isset($v[0]) && count($v)==1) $v = array_shift($v);
                foreach($v as $k=>$d) {
                    if(preg_match('/^(0\.)?content[\._]/', $k, $m)) {
                        unset($v[$k]);
                        $v[substr($k, strlen($m[0]))] = $d;
                    }
                    unset($m, $k, $d);
                }
            }
        }

        $this->content = $v;
    }

    public static function prepareContentTypes($a)
    {
        static $methods = ['u','v'];
        if(($p=S::urlParams()) && count($p)>=2 && in_array($p[0], $methods) && is_numeric($p[1]) && ($E=self::find(['id'=>$p[1]],1,['content_type']))) {
            $s = 'u-'.$E->content_type;
            if(isset(static::$schema->scope[$s])) {
                $scope = static::$schema->scope[$s];
            } else if(isset($a['options']['scope'][$s])) {
                static::$schema->scope[$s] = $scope = $a['options']['scope'][$s];
            } else {
                S::log('[ERROR] Please prepare the scope: Tecnodesign_Studio_Content::$schema->scope['.$s.']', $E->getContent());
            }
            $a['options']['scope'][$s] = $a['options']['scope']['c'] = $scope;
        }

        return $a;
    }

    public function render($display=true)
    {
        /*
        if(!$this->hasPermission('preview')) {
            return false;
        }
        */
        $id = Entries::$s++;
        $code = $this->getContents();
        $code['slot']=$this->slot;
        $type = $this->content_type;
        $attr = array('id'=>'c'.$id);
        if(Studio::$webInterface) {
            if(!$this->id && $this->source && ($C=Contents::find(['source'=>$this->source],1,['id']))) {
                $this->id = $C->id;
                unset($C);
            }
            $attr['data-studio'] = Studio::interfaceId('content/preview/'.$this->id);
        }
        if($p=Studio::config('content_class_name')) {
            $attr['class'] = $p;            
        }
        if($a=$this['attributes.attributes']) {
            $attr += $a;
        }
        $a = '';
        foreach($attr as $n=>$v) {
            $a .= ' '.$n.'="'.S::xml($v).'"';
            unset($attr[$n], $n, $v);
        }
        unset($attr);
        if($tpl=S::templateFile('tdz-contents-'.$type)) {
            if(!isset($code['txt']) && isset($code[0])) {
                $code['txt']=$code[0];
                unset($code[0]);
            }
            $s = "<div{$a}>"
                . S::exec(array('script'=>$tpl, 'variables'=>$code))
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
                    $result .= S::exec($r['exec']);
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
            $code['format']=S::fileFormat($code['src']);
        }
        $s='';
        if(preg_match('/(image|pdf|flash|download|video|audio)/', strtolower($code['format']), $m)) {
            $f=$m[1];
        } else {
            $f='download';
        }
        $code['format'] = $f;
        foreach($code as $k=>$v) if($v===null || $v==='') unset($code[$k]);
        $tpl = Studio::templateFile('tdz_media_'.$f, 'tdz_media');

        return S::exec(array('script'=>$tpl, 'variables'=>$code));
    }


    public static function renderHtml($code=null, $e=null)
    {
        if(is_array($code)) {
            $code = isset($code['html'])?($code['html']):(array_shift($code));
        }
        return trim($code);
    }

    public static function renderTxt($code=null)
    {
        if(is_array($code)) {
            $code = isset($code['txt'])?($code['txt']):(array_shift($code));
        }
        return $code;
    }

    public static function renderText($code=null)
    {
        return self::renderTxt($code);
    }

    public static function renderMd($code=null)
    {
        if(is_array($code)) {
            $code = isset($code['txt'])?($code['txt']):(array_shift($code));
        }
        return S::markdown($code, !static::$allowMarkdownExtensions);
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
            if($code['script'] && file_exists($f=Studio::$app->tecnodesign['apps-dir'].'/'.$code['script'])) {
                $code['script']=$f;
            } else {
                unset($code['script']);
            }
        }
        if(isset($code['pi'])) {
            $code['pi']=trim($code['pi']);
            if(substr($code['pi'], 0,5)=='<'.'?php') $code['pi'] = trim(substr($code['pi'], 5));
        }
        if(Studio::$staticCache) {
            return $code;
        }
        return S::exec($code);
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
                if(!Studio::$cacheTimeout || (isset($app['cache']) && $app['cache'])) {
                    return call_user_func($call, $e);
                } else if(is_string($call[0])) {
                    return array('export'=>$call[0].'::'.$call[1].'('.var_export($e, true).')');
                } else {
                    return array('export'=>'call_user_func('.var_export($call, true).', '.var_export($e, true).')');
                }
            }
        }
    }

    public static function choicesMaster($check=null)
    {
        static $master;

        if(!$master && Studio::$indexTimeout) {
            $master = Cache::get('studio/content/master', Studio::$indexTimeout);
        }

        if(!$master) {
            $master = [];
            $g = Studio::templateDir();
            while($g) {
                $d = array_shift($g);
                if(is_dir($d) && ($nd=glob($d.'/*'))) {
                    $g = array_merge($g, $nd);
                } else if(is_file($d) && substr($d, -4)=='.php') {
                    $n = $l = basename($d, '.php');
                    if(!isset($master[$n])) {
                        $c = file_get_contents($d);
                        if(strpos($c, '<html')===false) {
                            if(preg_match('#^\<\?php\s*\n/\*\*?\s*\n\s*\*?([^\n]+)#s', $c, $m)) {
                                $l = trim($m[1]).' ('.$n.')';
                            }
                            $master[$n] = $l;
                        }
                        unset($c);
                    }
                    unset($n, $l);
                }
                unset($d, $nd);
            }
            if($master && Studio::$indexTimeout) {
                Cache::set('studio/content/master', $master, Studio::$indexTimeout);
            }
        }

        if($check) {
            return (isset($master[$check])) ?$master[$check] :false;
        }

        return $master;

        //if(!$this->content_type && $this->id) $this->refresh(['content_type']); 
        //return Studio::templateFiles($this->content_type);
    }

    public function previewContentMaster()
    {
        if($k=$this['content.master']) {

            if($r = $this->choicesMaster($k)) return $r;

            if(!$this->content_type && $this->id) $this->refresh(['content_type']);
            $p = 'tdz_'.$this->content_type;
            $shift = strlen($p);
            if(substr($k, 0, $shift)==$p) $k = substr($k, $shift);
            return ucfirst(str_replace(['-', '_'], ' ', trim($k, '-_')));
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
        $o['script'] = Studio::templateFile((isset($code['master']))?($code['master']):(null), 'tdz_feed');

        $eid = null;
        $E = null;
        if(is_object($e) && ($e instanceof Entries)) {
            $eid = (int)$e->id;
            $E = $e;
        } else if(isset($code['entry']) && is_numeric($code['entry'])) {
            $eid = (int)$code['entry'];
            $q = ['id'=>$eid];
        } else {
            $q = $e;
        }

        if(!$E) $E = Entries::find($q,1);
        $o['variables']['entry']=$E;
        if($E) {
            $o['variables'] += $E->asArray();
            $f = array('Related.parent'=>$E->id);
            if(!(Studio::$private && !Studio::$cacheTimeout)) {
                $f['published<']=date('Y-m-d\TH:i:s');
            }
            $o['variables']['entries'] = Entries::find($f,null,'preview',(isset($code['hpp']) && $code['hpp']),($E->type=='page')?(array('Related.position'=>'asc','published'=>'desc')):(array('published'=>'desc')));
        }
        /*
        if(!Studio::$cacheTimeout || (!isset($code['hpp']) || !$code['hpp'])) {
            return S::exec($o);
        }
        */
        unset($code);
        return S::exec($o);
        //return array('export'=>'S::exec('.var_export($o,true).')');
    }

    public function renderSource()
    {
        static $doNotList = ['template'];
        $ct = $slot = null;
        if($this->content_type) {
            $ct = (isset(static::$contentType[$this->content_type])) ?static::$contentType[$this->content_type] :null;
            if(substr($ct, 0, 1)=='*') $ct = Studio::t(substr($ct, 1), ucfirst(substr($ct, 1)));
        }
        if($this->slot) {
            $slot = (isset(Entries::$slots[$this->slot])) ?Entries::$slots[$this->slot] :null;
            if($slot) $slot = Studio::t($slot, ucfirst($slot));
        }

        $s = '';
        $C = $this->getContents();
        if($C) {
            foreach($C as $i=>$o) {
                if(!$o) continue;
                $n = '_'.$i;
                $label = (isset(static::$schema->overlay[$n]['label'])) ?static::$schema->overlay[$n]['label'] :$n;
                if(substr($label, 0, 1)=='*') $label = Studio::t(substr($label, 1), ucfirst(substr($label, 1)));

                $c = null;
                if($i=='entry') {
                    $c = $o;
                } else if($i=='src') {
                    $c = '<img src="'.S::xml($o).'" />';
                } else if($i=='html' && ($c = S::xml(trim(strip_tags($o))))) {
                } else if(is_array($o) || in_array($i, $doNotList)) {
                    continue;
                } else {
                    $c = '<code>'.S::xml($o).'</code>';
                }
                if($c) $s .= '<span class="z-tag">'.S::xml($label).' </span>'.$c."\n<br />";
            }
        }

        if($slot) $s = '<em>'.S::xml($slot).' </em> '.$s;
        if($ct) $s = '<strong>'.S::xml($ct).' </strong>'.$s;

        return $s;

    }

    public function showAt($url)
    {
        $r = false;
        if($C = $this->getRelation('ContentDisplay', null, ['link', 'display'], false)) {
            foreach($C as $i=>$o) {
                if($o->matchUrl($url)) {
                    if($o->display>0) {
                        $r = true;
                    } else {
                        $r = false;
                        break;
                    }
                }
            }
        }
        return $r;
    }

    public static function fromFile($file, $attr=[])
    {
        if($C=Studio::content($file, false, true, false, $attr)) {
            return $C;
        }
    }

    public function prepareContentFormField(&$arg, $Field=null)
    {
        $this->refresh(['content_type', 'entry']);
        if(isset(static::$scope->scope['u-'.$this->content_type])) {
            static::$scope->overlay['content']['scope']='u-'.$this->content_type;
        }
    }

    public function updateSource()
    {
        if(!isset($this->source)) $this->refresh(['source']);
        if(!$this->source) return true;

        if($f=Studio::sourceFile($this->source)) {
            // keep comments at the header and update contents
            $M = [];
            $a = null;
            if($c = file_get_contents($f)) {
                if($m = Entries::meta($c)) {
                    $m = trim(preg_replace('/\.\.\.[\s\n]*$/', '', $m));
                    $M = Yaml::load($m);
                }
            }

            if($C = $this->getContent()) {
                if(isset($C['txt'])) {
                    $a = $C['txt'];
                    unset($C['txt']);
                } else if(isset($C['html'])) {
                    $a = $C['html'];
                    unset($C['html']);
                }

                if($M) $C+=$M;
            }

            S::save($f, (($C) ?"<!--\n".Yaml::dump($C)."...\n-->\n" :'') . $a);

            if(strpos($f, S_REPO_ROOT)===0) {
                // update reposiroty
                $rn = preg_replace('#^/*([^/]+)/.*#', '$1', substr($f, strlen(S_REPO_ROOT)+1));
                S::log('[INFO] Update repository: '.$rn);
            }
        }

        return true;
    }
}