<?php
/**
 * Tecnodesign_Studio_Content table description
 *
 * PHP version 5.3
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @link      http://tecnodz.com/
 * @version   SVN: $Id$
 */

/**
 * Tecnodesign_Studio_Content table description
 *
 * @category  Model
 * @package   Studio
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @link      http://tecnodz.com/
 */
class Tecnodesign_Studio_ContentDisplay extends Tecnodesign_Model
{
    /**
     * Tecnodesign_Model schema
     *
     * Remove the comment below to disable automatic schema updates
     */
    //--tdz-schema-start--2012-02-29 19:44:01
    public static $schema = array (
      'database' => 'studio',
      'tableName' => 'tdz_contents_display',
      'className' => 'tdzContentDisplay',
      'columns' => array (
        'content' => array ( 'type' => 'int', 'null' => false, 'primary' => true, ),
        'link' => array ( 'type' => 'string', 'size'=>200, 'null' => false, 'primary' => true, ),
        'version' => array ( 'type' => 'int', 'null' => false, 'primary' => true, ),
        'display' => array ( 'type' => 'bool', 'null' => false, ),
        'created' => array ( 'type' => 'datetime', 'null' => false, ),
        'updated' => array ( 'type' => 'datetime', 'null' => false, ),
        'expired' => array ( 'type' => 'datetime', 'null' => true, ),
      ),
      'relations' => array (
        'Content' => array ( 'local' => 'content', 'foreign' => 'id', 'type' => 'one', 'className' => 'tdzContent', ),
      ),
      'scope' => array (
      ),
      'order' => array(
        'version'=>'desc',
      ),
      'events' => array (
        'before-insert' => array ( 'actAs', ),
        'before-update' => array ( 'actAs', ),
        'before-delete' => array ( 'actAs', ),
        'active-records' => '`expired` is null',
      ),
      'form' => array (
        'content_type'=>array('bind'=>'content_type', 'type'=>'select', 'choices'=>'Tecnodesign_Studio::config(\'content_types\')', 'class'=>'studio-field-content-type'),
        'content'=>array('bind'=>'content', 'type'=>'hidden', 'class'=>'studio-field-content'),
      ),
      'actAs' => array (
        'before-insert' => array ( 'timestampable' => array ( 'created', 'updated' ), ),
        'before-update' => array ( 'timestampable' => array ( 'updated', ), ),
        'before-delete' => array ( 'timestampable' => array ( 'updated', ), 'soft-delete' => array ( 'expired', ), ),
      ),
    );
    protected $id, $created, $entry, $slot, $content_type, $content, $position, $published, $show_at, $hide_at, $expired, $Entry;
    //--tdz-schema-end--
    protected static $content_types=null;
    protected $subposition;
    
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

    public function getContent()
    {
        // should be valid json
        if(substr($this->content, 0,1)!='{') {
            $this->content = json_encode(Tecnodesign_Yaml::load($this->content),true);
        }
        return $this->content;
    }
    public function getContents()
    {
        $content = $this->content;
        if(substr($content, 0,1)=='{') {
            $content = json_decode($content);
        } else {
            $content = Tecnodesign_Yaml::load($content);
        }
        return $content;
    }

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
        return parent::getForm($scope);
    }

    public static function getContentTypes()
    {
        if(is_null(self::$content_types)) {
            $app = tdz::getApp();
            $ct=$app->studio['content_types'];
            $widgets=$app->studio['widgets'];
            if(is_array($widgets) && count($widgets)>0) {
                $wg=array();
                foreach($widgets as $wk=>$w) {
                    $wg[$wk]=$w['label'];
                }
                $ct['widget']=array('title'=>'Widgets','fields'=>array('app'=>array('label'=>'Widget','type'=>'choice','required'=>true,'choices'=>$wg)));
            }
            self::$content_types=$ct;
        }
        return self::$content_types;
    }

    public function render($display=false)
    {
        /*
        if(!$this->hasPermission('preview')) {
            return false;
        }
        */
        $code = $this->content;
        $type = $this->content_type;
        $content_types = self::getContentTypes();
        $ct = (isset($content_types[$type]))?($content_types[$type]):(array());
        if(!file_exists($code)) {
            $code = str_replace('\r\n', "\n", Tecnodesign_Yaml::load($code));
        }
        $code['slot']=$this->slot;
        $app = tdz::getapp()->tecnodesign;
        $tpl = $app['templates-dir'].'/tdz-contents-'.$type.'.php';
        if(file_exists($tpl)) {
            $s = "<div id=\"c{$this->id}\" class=\"tdzc\">"
                . tdz::exec(array('script'=>$tpl, 'variables'=>$code))
                . '</div>';
            return $s;
        }
        $class = $this;
        $method = 'render'.ucfirst($type);
        $component = '';
        if(isset($ct['class']) && class_exists($ct['class'])) {
            $class = $ct;
        }
        if(isset($ct['method']) && method_exists($class, $ct['method'])) {
            $method = $ct;
        }
        if(isset($ct['component'])) {
            $component = $ct['component'];
        }
        if($component != '') {
          // render component
        } else if(is_object($class) && method_exists($class, $method)) {
            $code = $class->$method($code, $this->getEntry());
        } else if(is_string($class)) {
            $code = $class::$method($code, $this->getEntry());
        }

        $class='tdzc';

        if(!is_array($code)) {
            $code = array('content'=>$code);
        }
        /*
        if(!isset($code['before']))$code['before']='';
        $code['before'].='<div class="'.$class.'" id="c'.$this->getId().'">';
        if(!isset($code['after']))$code['after']='';
        $code['after'].='</div>';
         */
        if($display) {
            //if(!function_exists('tdz_eval')) require_once sfConfig::get('app_e-studio_helper_dir').'/tdzEStudioHelper.php';
            $result='';
            if(is_array($code) && isset($code['before'])) {
                $result .= $code['before'];
            }
          
            if(is_array($code) && isset($code['export'])) {
                $result .= eval("return {$code['export']};");
            } else if(is_array($code)) {
                $result .= (isset($code['content']))?($code['content']):('');
            } else {
                $result .= $code;
            }
            
            $result = "<div id=\"c{$this->id}\" class=\"tdzc\">{$result}</div>";
            return $result;
        }
        //$code['before'] .= '<h1>Position: '.$this->getPosition().'</h1>';
        return $code;
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
        if($f=='image') {
            $s = '<img src="'.tdz::xmlEscape($code['src']).'"';
            if(isset($code['alt']) && $code['alt']) {
                $s .= ' alt="'.tdz::xmlEscape($code['alt']).'"';
            }
            if(isset($code['title']) && $code['title']) {
                $s .= ' title="'.tdz::xmlEscape($code['title']).'"';
            }
            if(isset($code['id']) && $code['id']) {
                $s .= ' id="'.tdz::xmlEscape($code['id']).'"';
            }
            $s .= ' />';
            if(isset($code['href']) && $code['href']) {
                $s = '<a href="'.tdz::xmlEscape($code['href']).'">'.$s.'</a>';
            }
        } else if($f=='video') {
            $s = '<video src="'.tdz::xmlEscape($code['src']).'"';
            if(isset($code['alt']) && $code['alt']) {
                $s .= ' alt="'.tdz::xmlEscape($code['alt']).'"';
            }
            if(isset($code['title']) && $code['title']) {
                $s .= ' title="'.tdz::xmlEscape($code['title']).'"';
            }
            if(isset($code['id']) && $code['id']) {
                $s .= ' id="'.tdz::xmlEscape($code['id']).'"';
            }
            $s .= ' autobuffer="true" controls="true">alternate part';
            // alternate -- using flash?
            $s .= '</video>';
        } else if($f=='flashzzzz') {
            $s = '<div src="'.tdz::xmlEscape($code['src']).'"';
            if(isset($code['alt']) && $code['alt']) {
                $s .= ' alt="'.tdz::xmlEscape($code['alt']).'"';
            }
            if(isset($code['title']) && $code['title']) {
                $s .= ' title="'.tdz::xmlEscape($code['title']).'"';
            }
            if(isset($code['id']) && $code['id']) {
                $s .= ' id="'.tdz::xmlEscape($code['id']).'"';
            }
            $s .= ' autobuffer="true" controls="true">alternate part';
            // alternate -- using flash?
            $s .= '</video>';
        } else {
            $s = '<p';
            if(isset($code['id']) && $code['id']) {
                $s .= ' id="'.tdz::xmlEscape($code['id']).'"';
            }
            $s .= '><a href="'.tdz::xmlEscape($code['src']).'">';
            $s .= (isset($code['title']) && $code['title'])?(tdz::xmlEscape($code['title'])):(basename($code['src']));
            $s .= '</a></p>';
        }
        return $s;
    }


    public static function renderHtml($code=null, $e=null)
    {
        if(is_null($code) && isset($this)) {
            $code = $this->content;
            if(!file_exists($code)) 
              $code = Tecnodesign_Yaml::load($code);
        }
        if(is_array($code)) {
            $code = $code['html'];
        }
        return $code;
    }

    public static function renderText($code=null, $e=null)
    {
        if(is_null($code) && isset($this)) {
            $code = $this->content;
            if(!file_exists($code)) 
              $code = Tecnodesign_Yaml::load($code);
        }
        if(is_array($code)) {
            $code = $code['txt'];
        }
        return $code;
    }

    public static function renderWidget($code=null, $e=null)
    {
        $widgets=tdz::getApp()->studio['widgets'];
        if(!is_array($code) || !isset($code['app']) || !isset($widgets[$code['app']])) {
            return false;
        }
        $app=$widgets[$code['app']];
        $class=$method=false;
        if(isset($app['model']) && class_exists($app['model'])) {
            $class = $app['model'];
        }
        if(isset($app['method']) && method_exists($class, $app['method'])) {
            $method = $app['method'];
        }
        $s='problema';
        if(!$class || !$method) {
        } else if(isset($app['cache']) && $app['cache']) {
            if(is_object($class) && method_exists($class, $method)) {
                $s = $class->$method($e);
            } else if(is_string($class)) {
                $s = $class::$method($e);
            }
            $code=$s;
        } else {
            if(is_object($class) && method_exists($class, $method)) {
                $s = '$class='.var_export($class,true).';return $class->'.$method.'('.var_export($e,true).');';
            } else if(is_string($class)) {
                $s = "return {$class}::{$method}(".var_export($e,true).');';
            }
            $code=array('export'=>'tdz::exec(array(\'pi\'=>"'.$s.'"))');
        }
        return $code;
    }

    public static function renderPhp($code=null, $e=null)
    {
        if(is_null($code) && isset($code)) {
            $code = $this->content;
            if(!file_exists($code)) {
                $code = Tecnodesign_Yaml::load($code);
            }
        }
        if(!is_array($code)) {
            $code = array('pi'=>$code);
        }
        $app = tdz::getApp();
        if(isset($code['script'])) {
            if(file_exists($app->tecnodesign['apps-dir'].'/'.$code['script'])) {
                $code['script']=$app->tecnodesign['apps-dir'].'/'.$code['script'];
            } else {
                unset($code['script']);
            }
        }
        return array('export'=>'tdz::exec('.var_export($code,true).')');
    }

    public static function renderFeed($code=null, $e=null)
    {
        if(is_null($code) && isset($this)){
            $code = $this->content;
            if(!file_exists($code)) {
                $code = Tecnodesign_Yaml::load($code);
            }
        }
        
        if(!is_array($code)) {
            $code = array('entry'=>$code);
        }
        /**
         * $code should contain:
         *
         *   entry  (mandatory) integer  The feed id
         *   master (optional) string   The template to use
         *
         * If the entry is not found, it should use current feed as a parameter
         */
        $feed = false;
        $app = tdz::getApp();
        if(isset($code['master']) && file_exists($app->tecnodesign['templates-dir'].'/'.$code['master'].'.php')) {
            $code['master']=$app->tecnodesign['templates-dir'].'/'.$code['master'].'.php';
        } else if(isset($code['master']) && file_exists(TDZ_ROOT.'/src/Tecnodesign/App/Resources/templates/'.$code['master'].'.php')) {
            $code['master']=TDZ_ROOT.'/src/Tecnodesign/App/Resources/templates/'.$code['master'].'.php';
        } else if(file_exists($app->tecnodesign['templates-dir'].'/tdz_feed.php')) {
            $code['master']=$app->tecnodesign['templates-dir'].'/tdz_feed.php';
        } else if(file_exists(TDZ_ROOT.'/src/Tecnodesign/App/Resources/templates/tdz_feed.php')) {
            $code['master']=TDZ_ROOT.'/src/Tecnodesign/App/Resources/templates/tdz_feed.php';
        } else {
            unset($code['master']);
        }
        
        if(!is_numeric($code['entry'])) {
            $code['entry']=$e;
        }
        //return array('export'=>'tdz_feed('.var_export($code,true).')');
        return array('export'=>'tdzEntry::feedPreview('.var_export($code, true).')');
    }
    
}
