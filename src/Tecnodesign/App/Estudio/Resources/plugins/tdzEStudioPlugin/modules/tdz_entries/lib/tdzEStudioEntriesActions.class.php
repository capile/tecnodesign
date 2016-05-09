<?php
/**
 * e-Studio actions related to page loading and UI
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: tdzEStudioEntriesActions.class.php 1193 2013-03-06 19:41:26Z capile $
 */
class tdzEStudioEntriesActions extends sfActions
{

  public function setLanguage(sfWebRequest $request, $language=false)
  {
    if(!$language)
    {
      $languages=sfConfig::get('app_e-studio_languages');
      if(!is_array($languages) || count($languages)==0)
        $language=sfConfig::get('app_e-studio_default_language');
      else
      {
        $languages = array_keys($languages);
        $language  = (count($languages)>1)?($request->getPreferredCulture($languages)):(array_shift($languages));
      }
    }
    $this->getUser()->setCulture($language);
    $this->getContext()->getI18n()->setCulture($language);
    sfConfig::set('sf_default_culture', $language);
    if(!Tecnodesign_Translate::$apiKey) {
        $to=sfConfig::get('app_tecnodesign_translate');
        if($to) {
            foreach($to as $tk=>$tv) {
                Tecnodesign_Translate::$$tk=$tv;
            }
        }
    }
    tdz::$lang = str_replace('_', '-', $language);
    return $language;
  }
  /**
  * Executes index action
  *
  * @param sfRequest $request A request object
  */
  public function executePreview(sfWebRequest $request)
  {
    $this->setLanguage($request);
    $url=tdz::scriptName(true, true);
    $user=$this->getUser();
    if (count($_POST) > 0) {
        tdz::cacheControl('private, must-revalidate', 0);
    } else if (!$user->isAuthenticated()) {
        tdz::set('cache-control', 'public');
    }
    $credentials=$user->getCredentials();
    if(is_array($credentials))$credentials=implode(',',$credentials);
    $ckey=md5($url).'-'.md5($credentials);
    $co=new sfFileCache(array('cache_dir'=>sfConfig::get('sf_app_cache_dir').'/pages'));
    $updated=tdzEntries::lastModified(true,array('entries','permissions'));
    $this->entry=false;
    $this->ajax=false;
    $cmod=$co->getLastModified($ckey);
    $this->download=false;
    $layout=false;
    $ci=false;
    if ($cmod>$updated) {
      $ci=unserialize($co->get($ckey));
      if(file_exists("{$layout}.php"))
        $layout=$ci['layout'];
      $this->entry=$ci['entry'];
      if(isset($ci['download'])) {
          $this->download=$ci['download'];
      }
    }
    if($layout && !file_exists("{$layout}.php")) $layout=false;
    if(!$this->entry) {
        $this->entry = tdzEntries::match($url);
    }
    if(!$this->entry && $credentials && $request->getParameter('id')!='')
    {
      $this->entry = tdzEntries::find($request->getParameter('id'));
      if($this->entry->type=='entry' && $this->entry->link)$url=$this->entry->link;
    }
    if($this->entry && !$this->entry->hasPermission('preview')) {
        $this->forward('tdz_entries','error403');
    }
    tdz::set('entry',$this->entry);
    tdz::scriptName($this->entry->link);
    if($this->entry && $request->isXmlHttpRequest())
    {
      $json=$this->ajaxPreview($request);
      if($json)
      {
        if(!$ci)
          $co->set($ckey,serialize(array('layout'=>false,'entry'=>$this->entry)),sfConfig::get('app_e-studio_cache_timeout'));
        sfConfig::set('sf_web_debug',false);
        sfConfig::set('sf_escaping_strategy',false);
        $this->setLayout(false);
        @header('Content-Type: application/json; charset=utf-8');
        @header('Content-Length: '.strlen($json));
        exit($json);
      }
    }
    if(!$layout)
    {
      if($this->entry)
      {
        if($this->entry->type=='feed')
          $layout=$this->atomPreview($request);
        else if($this->entry->type=='file')
        {
          $o=$request->getParameter('optimize');
          $layout=$this->entry->filePreview($o);
          $this->download=$this->entry->format;
          if(!$this->download)$this->download=true;
        }
        else if($this->entry->type=='entry')
          $layout=$this->entryPreview($request);
        else
          $layout=$this->entry->getLayout($request->getParameter('layout'));
      }
      else if(sfConfig::get('app_e-studio_assets_optimize') && strpos($url,sfConfig::get('app_e-studio_assets_prefix'))===0) {
        $layout=$this->previewAsset($request);
      }

      if($layout)
        $co->set($ckey,serialize(array('layout'=>$layout,'entry'=>$this->entry)),sfConfig::get('app_e-studio_cache_timeout'));
    }
    $prefix_url = sfConfig::get('app_e-studio_prefix_url');
    //$this->getResponse()->setHttpHeader('Last-Modified', $this->getResponse()->getDate($timestamp));
    $response = $this->getResponse();
    //$response->addStyleSheet(sfConfig::get('app_e-studio_assets_url').'/css/e-studio.css');
    if($this->entry)
      $response->addJavaScript($prefix_url.sfConfig::get('app_e-studio_assets_url').'/js/loader.js?link='.urlencode($this->entry->link));
    else
      $response->addJavaScript($prefix_url.sfConfig::get('app_e-studio_assets_url').'/js/loader.js?referer');
    if($layout)
    {
      $vars=array('layout'=>$layout,'entry'=>$this->entry);
      if($this->download)$vars['download']=$this->download;
      $co->set($ckey,serialize($vars),sfConfig::get('app_e-studio_cache_timeout'));
      //$this->message=$this->getUser()->getFlash('message');
      if($this->download)
      {
        $this->download=$ci['download'];
        $format=$ci['download'];
        if(strlen($format)<2)$format=false;
        sfConfig::set('sf_web_debug',false);
        sfConfig::set('sf_escaping_strategy',false);
        tdz::download($layout, $format, basename($url));
        exit();
      }
      $user = $this->getUser();
      $this->message=$user->getFlash('message');
      if($this->message=='' && file_exists($layout.'.php'))
      {
        $fl=fopen($layout.'.php','r');
        $fh=fread($fl,14);
        fclose($fl);
        if(substr($fh,6)=='//static')
        {
          $etag=md5_file($layout.'.php');
          tdz::getBrowserCache($etag, filemtime($layout.'.php'),sfConfig::get('app_e-studio_cache_timeout'));
        }
      } else if($this->message) {
        $user->setFlash('message', false);
      }
      if($this->entry)
        tdz::scriptName($this->entry->link);
      $this->setLayout($layout);
      return sfConfig::get('app_e-studio_ui_view');
    }
    $this->forward('tdz_entries','error404');
    //throw new sfError404Exception();
  }

  public function ajaxPreview(sfWebRequest $request)
  {
    if(!isset($this->entry) && $this->entry->type!='page') return false;
    if(isset($_SERVER['HTTP_TDZ_SLOTS']) && preg_match('/[1-9][0-9]{9,}$/',$_SERVER['QUERY_STRING']))
      $this->ajax=true;
    else
      return false;
    $lastmod = tdzEntries::lastModified(true);

    $result=array();
    if(isset($_SERVER['HTTP_TDZ_SLOTS']))
    {
      $s = array();
      parse_str($_SERVER['HTTP_TDZ_SLOTS'],$s);
      if(!is_array($s)) return false;
      $w = array();
      $wp=array();
      $i=1;
      $result['page']['updated']=$lastmod;
      if(!isset($s['page']) && (!$this->getUser()->isAuthenticated() || count($this->getUser()->getCredentials())==0))
        return "{\"page\":{\"updated\":{$lastmod}}}";
      if(isset($s['page']) && $s['page']>=$lastmod)
        return '{}';
      $edit=$this->entry->hasPermission('edit');
      $new=$this->entry->hasPermission('new');
      $publish=$this->entry->hasPermission('publish');
      $editable=($this->getUser()->isAuthenticated() && count($this->getUser()->getCredentials())>0 && ($edit||$new||$publish));

      if($editable)
      {
        $prop=array();
        if($new) $prop[]='new';
        if($this->entry->id>0 && $edit)$prop[]='edit';
        if($this->entry->hasPermission('search'))
        {
          $prop[]='search';
          $prop[]='files';
        }
        if($this->entry->hasPermission('users'))
          $prop[]='users';
        else
          $prop[]='user';
        if($this->entry->id>0 && $publish)
        {
          $prop[]='publish';
          if($this->entry->published!='') $prop[]='unpublish';
        }
        $prop=implode(' ',$prop);
        if($prop!='') $result['page']['prop']=$prop;
      }

      $result['slots']=array();
      foreach($s as $id=>$lastupdated)
      {
        $w[] = "c.slot=:slot{$i}";
        $wp['slot'.$i]=$id;
        $i++;
        if($new) $result['slots'][$id]=array('prop'=>'new');
      }
      $contents = $this->entry->getAllContents(implode(' or ', $w),$wp);
      foreach($contents as $content)
      {
        $slot = $content->getSlot();
        if(!isset($result['slots'][$slot]))$result['slots'][$slot]=array();
        if(!isset($result['slots'][$slot]['contents']))$result['slots'][$slot]['contents']=array();
        $id='c'.$content->getId();
        $result['slots'][$slot]['contents'][]=$id;

        $updated=strtotime($content->getUpdated());
        $o=array('updated'=>$updated);
        if(!(isset($s[$slot]) && is_array($s[$slot]) && isset($s[$slot][$id]) && $updated<=$s[$slot][$id])) $o['html']=$this->getComponent('tdz_contents','preview',array('id'=>$content->id));

        if($editable)
        {
          $o['prop']=array();
          if($new && $content->hasPermission('new')) $o['prop'][]='new';
          if($edit && $content->hasPermission('edit'))$o['prop'][]='edit';
          if($publish && $content->hasPermission('publish'))
          {
            $pub=$content->published;
            if($pub) $pub=strtotime($pub);
            if(!$pub || $pub<$updated) $o['prop'][]='publish';
            if($pub) $o['prop'][]='unpublish';
          }
          $o['prop']=implode(' ',$o['prop']);
          if($o['prop']=='') unset($o['prop']);
        }
        if(count($o)>1)
        {
          if(!isset($result['contents']))$result['contents']=array();
          $result['contents'][$id]=$o;
        }
      }
    }
    return json_encode($result);
  }

  public function atomPreview(sfWebRequest $request)
  {
    if(!isset($this->entry) && $this->entry->type!='feed') return false;
    $this->entry->getLatestVersion();
    $cachefile=sfConfig::get('sf_app_cache_dir').'/tdzEntries/e'.$this->entry->id.'.'.$this->entry->version;
    $php='<'."?php\n//static\n";
    $php .= "sfConfig::set('sf_web_debug',false);\n";
    $php .= "sfConfig::set('sf_escaping_strategy',false);\n";
    $xml=tdzEntries::feedPreview($this->entry,'tdz_atom');
    $php .= "@header('Content-Type: application/atom+xml;charset=UTF-8');\n";
    $php .= "@header('Content-Length: ".strlen($xml)."');\n";
    $php .= 'echo '.var_export($xml,true).";\n";
    if(!is_dir(dirname($cachefile))) mkdir(dirname($cachefile),0777,true);
    file_put_contents("{$cachefile}.php", $php);
    @chmod("{$cachefile}.php",0666);
    return $cachefile;
  }

  public function entryPreview(sfWebRequest $request)
  {
    if(!isset($this->entry) && $this->entry->type!='entry') return false;
    $this->entry->getLatestVersion();
    $cachefile=sfConfig::get('sf_app_cache_dir').'/tdzEntries/e'.$this->entry->id.'.'.$this->entry->version;
    $php='<'."?php\n//static\n";
    $php .= "sfConfig::set('sf_web_debug',false);\n";
    $php .= "sfConfig::set('sf_escaping_strategy',false);\n";
    $xml=tdzEntries::entryPreview($this->entry,'tdz_entry');
    $php .= 'echo '.var_export($xml,true).";\n";
    if(!is_dir(dirname($cachefile))) mkdir(dirname($cachefile),0777,true);
    file_put_contents("{$cachefile}.php", $php);
    @chmod("{$cachefile}.php",0666);
    return $cachefile;


    sfConfig::set('sf_web_debug',false);
    sfConfig::set('sf_escaping_strategy',false);
    tdzEntries::entryPreview($this->id,$this->template);
    $this->setLayout(false);
    return $this->renderComponent('tdz_entries', 'entryPreview', array('id'=>$request->getParameter('id'),'template'=>'tdz_entry'));
  }
  /**
  * Submodule for rendering assets (static files, or image entries with custom adjustments)
  *
  * @param sfRequest $request A request object
  */
  public function previewAsset(sfWebRequest $request)
  {
    $url=$request->getPathInfo();
    // possible files
    $dir=dirname($url);
    $file=basename($url);
    $root=sfConfig::get('app_e-studio_document_root');
    $cache=sfConfig::get('sf_app_cache_dir').'/assets';
    $parts=preg_split('/\.+/',$file, 0,PREG_SPLIT_NO_EMPTY);
    $ext='';$dext='';$action='';
    $pn=count($parts);
    if($pn>1 && strlen($parts[$pn -1])<=4)
    {
      $ext=array_pop($parts);
      $dext = ($ext!='')?('.'.$ext):('');
      $pn=count($parts);
    }
    $optimize=sfConfig::get('app_e-studio_assets_optimize');
    $action=false;
    $data='';
    $lastmod=false;
    $original=false;
    $final=false;
    $proc=array();
    $params=array();
    $entry=false;
    if($pn>1 && $optimize)
    {
      $actions=sfConfig::get('app_e-studio_assets_optimize_actions');
      if(isset($actions[$parts[$pn -1]]) && in_array(strtolower($ext),$actions[$parts[$pn -1]]['extensions']))
      {
        $action=array_pop($parts);
        $options=$actions[$action];
        if(isset($options['params']))$params=$options['params'];
        if((!isset($options['combine']) || !$options['combine']) && count($parts)>1)
          $parts=array(implode('.',$parts));
        
        $method=$options['method'];
        $ppart = ''; // previous part -- if doesn't match
        foreach($parts as $part)
        {
          $f=false;
          $part = $ppart.$part;
          $entry=tdzEntries::match($dir.'/'.$part.$dext,true);
          if($entry && $entry->type=='file')
          {
            $file = $entry->filePreview($action);
            if(!$file) return false;
            $this->download=$entry->format;
            if(!$this->download)$this->download=true;
            return $file;
          }

          if(file_exists($root.$dir.'/'.$part.$dext))
            $f=$root.$dir.'/'.$part.$dext;
          else if(file_exists($root.$dir.'/'.$part))
            $f=$root.$dir.'/'.$part;
          
          if($f)
          {
            $ppart = '';
            $flastmod=filemtime($f);
            $original=$f;
            if($flastmod>$lastmod)$lastmod=$flastmod;
            $proc[]=array('file'=>$f,'function'=>$method);
            if(!$options['combine']) break;
          } else {
              $ppart .= $part.'.';
          }
        }
      }
      // search for files that match parts (if combine is available)
    }
    if(!$original)
    {
      if(file_exists($root.$dir.'/'.$file))
        $original=$root.$dir.'/'.$file;
      else if(file_exists($root.$dir.'/'.implode('.',$parts)))
        $original=$root.$dir.'/'.implode('.',$parts);
      if($original)
        $lastmod=filemtime($original);
    }
    if(!$original) return false;
    $file=false;

    $cachefile=$cache.$url;
    if(file_exists($cachefile) && filemtime($cachefile)>$lastmod)
    {
      $file=$cachefile;
    }
    else if(count($proc)>0)
    {
      $data='';
      foreach($proc as $p)
      {
        $method=$p['function'];
        if(method_exists($this, $method))
          $data.=$this->$method($p['file'],$params);
        else if(method_exists('tdz', $method))
          $data.=tdz::$method($p['file'],$params);
        else if(function_exists($method))
          $data.=$method($p['file'],$params);
        else
          $data.=file_get_contents($f);
      }

      if($data!='')
      {
        if(!is_dir(dirname($cachefile))) mkdir(dirname($cachefile),0777,true);
        file_put_contents($cachefile, $data);
        @chmod($cachefile,0666);
        $file=$cachefile;
      }
    }
    else
    {
      $file=$original;
    }
    if(!$file) return false;
    $ct=true;
    $response=$this->getResponse();
    $ext = strtolower(preg_replace('/.*\.([a-z0-9]{1,5})$/i','$1',$original));
    if(isset($params['content-type']))
      $ct=$params['content-type'];
    else if($request->getMimeType($ext))
      $ct=$request->getMimeType($ext);
    else if(class_exists('finfo'))
    {
      $finfo=new finfo(FILEINFO_MIME_TYPE);
      $ct=$finfo->file($original);
    }
    else if(function_exists('mime_content_type'))
      $ct=mime_content_type($original);
    if($ct=='')$ct=true;
    $this->download=$ct;
    return $file;
  }

  public function processForm(sfWebRequest $request, $action=null)
  {
    if(!isset($this->entry) || !$this->entry)
      $this->entry = tdzEntries::find($request->getParameter('id'),array('expired'=>true));
    if(!$this->entry)
      $this->forward('tdz_entries','error403');
    if ($request->isXmlHttpRequest())
    {
      $this->setLayout(false);
      sfConfig::set('sf_web_debug',false);
      sfConfig::set('sf_escaping_strategy',false);
      if(isset($_SERVER['HTTP_TDZ_TAGS']))
      {
        $tags=tdzTags::search($_SERVER['HTTP_TDZ_TAGS']);
        $json=json_encode(array_values($tags));
        @header('Content-Type: application/json; charset=utf-8');
        @header('Content-Length: '.strlen($json));
        exit($json);
      }
    }

    sfWidgetFormSchema::setDefaultFormFormatterName('list');
    if(!isset($this->form) || !$this->form)
      $this->form = new tdzEntriesForm($this->entry);

    $this->delete=(!$this->form->isNew() && $this->entry->hasPermission('delete'));
    $this->expired = ($this->entry->expired!='');
    if($this->expired && !$this->delete)
      $this->forward('tdz_entries','error403');
    $this->publish=false;
    $this->unpublish=false;
    if($this->entry->hasPermission('publish'))
    {
      if($this->entry['published']!='') $this->unpublish=true;
      $this->publish=true;
    }
    $this->js=false;

    $this->img = sfConfig::get('app_e-studio_assets_url').'/images/icons.png';
    $this->url = sfConfig::get('app_e-studio_prefix_url').$request->getPathInfo();

    $this->type = $this->entry->type;
    if($this->type=='')$this->type='entry';

    if($this->form->isNew() && $request->isMethod('post'))
    {
      $post = $request->getPostParameter('tdze');
      $this->form->setDefaults($post);
    }
    $this->form->configure_cms();
    $this->message=$this->getUser()->getFlash('message');
    $this->js="tdz.load('e-studio_ui.js');tdz.init_form();";
    if(is_null($action))
    {
      if($this->form->isNew()) $action='new';
      else if($this->entry->expired!='') $action='undelete';
      else $action='edit';
    }
    if(!isset($this->run))
      $this->run=false;
    $url=false;
    if($request->isMethod('post') && !isset($_SERVER['HTTP_TDZ_NOT_FOR_UPDATE']))
    {
      $post = $request->getPostParameter('tdze');

      // fix sorting
      if(isset($post['contents']) && count($post['contents'])>1)
      {
        $pos=array();
        foreach($post['contents'] as $k=>$val)
        {
          if($val['slot']=='')$val['slot']='body';
          $slot=$val['slot'];
          if(!isset($pos[$slot]))$pos[$slot]=0;
          $pos[$slot]++;
          $val['position']=$pos[$slot];
          $post['contents'][$k]=$val;
        }
      }

      $files= $request->getFiles('tdze');
      $this->form->bind($post, $files);
      if($this->form->isValid())
      {
        $this->form->save();
        $url=sfConfig::get('app_e-studio_prefix_url').$request->getPathInfo();
        $this->run=true;
      }
      else
      {
        $this->message = $this->getContext()->getI18N()->__('There was a problem while updating this entry. Please try again.');
      }
    }
    if($this->run)
    {
      if($action=='new')
      {
        $this->message = $this->getContext()->getI18N()->__('Successfully created a new entry!');
        $url=sfConfig::get('app_e-studio_prefix_url').sfConfig::get('app_e-studio_ui_url').'/e/edit/'.$this->form->getObject()->id;
      }
      else if($action=='publish')
      {
        $this->message = $this->getContext()->getI18N()->__('Successfully published entry!');
        $this->entry->published=date('Y-m-d H:i:s');
        $this->entry->save();
        $this->cleanCache();
        if($this->entry->Contents->count()>0)
        {
          foreach($this->entry->Contents as $c)
          {
            $c->published=date('Y-m-d H:i:s');
            $c->save();
            $c->cleanCache();
          }
        }

        if(!$url)
          $url=sfConfig::get('app_e-studio_prefix_url').$request->getPathInfo();
        $url = str_replace('/publish/','/edit/', $url);
      }
      else if($action=='unpublish')
      {
        $this->message = $this->getContext()->getI18N()->__('Successfully unpublished entry!');
        $this->entry->published=null;
        $this->entry->save();
        if(!$url)
          $url=sfConfig::get('app_e-studio_prefix_url').$request->getPathInfo();
        $url = str_replace('/unpublish/','/edit/', $url);
      }
      else if($action=='undelete')
      {
        $this->message = $this->getContext()->getI18N()->__('Successfully restored entry!');
        $this->entry->published=null;
        $this->entry->expired=null;
        $this->entry->save();
      }
      else
        $this->message = $this->getContext()->getI18N()->__('Successfully updated entry!');

      if($action!='new')
        $this->cleanCache();
      if($url)
      {
        $this->getUser()->setFlash('message',$this->message);
        $this->redirect($url);
      }
      $this->js.="tdz.update_slots();";
    }
    $this->js .="tdz.load('e-studio-ui.js');";
  }

  
  public function executeNew(sfWebRequest $request)
  {
    $this->setLanguage($request);
    tdzEntriesForm::setCSRFFieldName('_tdz');
    $this->url = sfConfig::get('app_e-studio_prefix_url').$request->getPathInfo();
    //$this->entry = Doctrine_Core::getTable('tdzEntries')->findById($request->getParameter('id'))->getFirst();
    $this->entry = new tdzEntries();
    $this->title = $this->entry;
    $this->form=false;
    $this->js=false;
    if ($request->isXmlHttpRequest())
      $this->setLayout(false);
    if(!$this->entry->hasPermission('new'))
      $this->forward('tdz_entries','error403');

    $this->img = sfConfig::get('app_e-studio_assets_url').'/images/icons.png';
    $this->url = sfConfig::get('app_e-studio_prefix_url').$request->getPathInfo();
    $response = $this->getResponse();
    $t=$this->getContext()->getI18N();
    $response->setTitle($t->__('New entry'));
    $this->entry->type='entry';
    if ($type==$request->getGetParameter('type')) {
      $types = sfConfig::get('app_e-studio_entry_types');
      if(isset($types[$type]))
      {
        $this->entry->type=$type;
      }
    }
    if ($link==$request->getGetParameter('link')) {
      $this->entry->link=tdz::validUrl($link);
    }
    $this->form = new tdzEntriesForm($this->entry);
    if($request->getGetParameter('tdze')!='')
    {
      $get = $request->getGetParameter('tdze');
      $this->form->setDefaults($get);
    }

    $this->processForm($request);
    return sfConfig::get('app_e-studio_ui_view');
  }

 /**
  * Updates an entry
  *
  * @param sfRequest $request A request object
  */
  public function executeEdit(sfWebRequest $request)
  {
    $this->setLanguage($request);
    tdzEntriesForm::setCSRFFieldName('_tdz');
    sfWidgetFormSchema::setDefaultFormFormatterName('list');
    $this->url = sfConfig::get('app_e-studio_prefix_url').$request->getPathInfo();
    $this->entry = tdzEntries::find($request->getParameter('id'),array('expired'=>true));
    $this->title = $this->entry;
    if(!$this->entry || !$this->entry->hasPermission('edit'))
      $this->forward('tdz_entries','error403');
    $response = $this->getResponse();
    $t=$this->getContext()->getI18N();
    $response->setTitle($t->__('Edit').' '.lcfirst($t->__(ucfirst($this->type))).': '.$this->entry->title);
    $this->processForm($request);
    tdz::cacheControl('private, no-cache', 0);
    return sfConfig::get('app_e-studio_ui_view');
  }

 /**
  * Removes an entry
  *
  * @param sfRequest $request A request object
  */
  public function executeDelete(sfWebRequest $request)
  {
    $this->setLanguage($request);
    tdzEntriesForm::setCSRFFieldName('_tdz');
    sfWidgetFormSchema::setDefaultFormFormatterName('list');
    $this->url = sfConfig::get('app_e-studio_prefix_url').$request->getPathInfo();
    $this->entry = tdzEntries::find($request->getParameter('id'));
    if(!$this->entry)
      $this->forward('tdz_entries','error404');

    if(!$this->entry->hasPermission('delete'))
      $this->forward('tdz_entries','error403');

    $this->form = new tdzEntriesForm($this->entry);
    $this->form->configure_cms();
    $this->message='';
    $valid = false;
    $id = $this->entry->id;
    if($id!='' && $request->isMethod('post'))
    {
      $post = $request->getPostParameter('tdze');
      $csrf=$this->form->getCSRFFieldName();
      if(isset($post[$csrf]) && $post[$csrf]==$this->form->getCSRFToken())
      {
        $valid = true;
        $this->entry->delete();
        $this->cleanCache();
        $this->message=$this->getContext()->getI18N()->__('Ok, entry removed.');
      }
    }
    if(!$valid && count($post)>0)
    {
      $this->message = $this->getContext()->getI18N()->__('There was a problem while updating this entry. Please try again.');
    }

    $url=sfConfig::get('app_e-studio_prefix_url').sfConfig::get('app_e-studio_ui_url').'/e/edit/'.$this->entry->id;
    $this->getUser()->setFlash('message',$this->message);
    tdz::cacheControl('private, no-cache', 0);
    $this->redirect($url);
  }

  public function cleanCache($id=null)
  {
    if(is_null($id) && isset($this->entry)) $id=$this->entry->id;
    tdz::cleanCache('tdzEntries/e'.$id);
    //$co=new sfFileCache(array('cache_dir'=>sfConfig::get('sf_app_cache_dir').'/tdzEntries'));
    //if($co->has('e'.$id))$co->remove('e'.$id);
  }

 /**
  * Publishes an entry
  *
  * @param sfRequest $request A request object
  */
  public function executePublish(sfWebRequest $request)
  {
    $this->setLanguage($request);
    tdzEntriesForm::setCSRFFieldName('_tdz');
    sfWidgetFormSchema::setDefaultFormFormatterName('list');
    $this->entry = tdzEntries::find($request->getParameter('id'));

    if(!$this->entry || !$this->entry->hasPermission('publish'))
      $this->forward('tdz_entries','error403');

    $this->message='';
    $id = $this->entry->id;
    $this->run=true;
    $this->processForm($request, 'publish');
    $url=sfConfig::get('app_e-studio_prefix_url').sfConfig::get('app_e-studio_ui_url').'/e/edit/'.$this->entry->id;
    $this->getUser()->setFlash('message',$this->message);
    tdz::cacheControl('private, no-cache', 0);
    $this->redirect($url);
    //$this->forward('tdz_entries','edit');
  }

 /**
  * Publishes an entry
  *
  * @param sfRequest $request A request object
  */
  public function executeUnpublish(sfWebRequest $request)
  {
    $this->setLanguage($request);
    tdzEntriesForm::setCSRFFieldName('_tdz');
    sfWidgetFormSchema::setDefaultFormFormatterName('list');
    $this->entry = Doctrine_Core::getTable('tdzEntries')->findById($request->getParameter('id'))->getFirst();

    if(!$this->entry->hasPermission('publish'))
      $this->forward('tdz_entries','error403');

    $this->form = new tdzEntriesForm($this->entry);
    $this->form->configure_cms();
    $this->message='';
    $id = $this->entry->id;
    if($id!='' && $request->isMethod('post'))
    {
      $this->processForm($request, 'unpublish');
    }
    else
    {
      if($this->entry->published=='')
        $this->message=$this->getContext()->getI18N()->__('This entry was already unpublished. Nothing done.');
      else
      {
        $this->entry->published=null;
        $this->entry->save();
        $this->cleanCache();
        if($this->entry->published=='')
          $this->message=$this->getContext()->getI18N()->__('This entry was successfully unpublished.');
        else
          $this->message=$this->getContext()->getI18N()->__('It wasn\'t possible to unpublish the entry. Please try again or contact support.');
      }

    }
    $url=sfConfig::get('app_e-studio_prefix_url').sfConfig::get('app_e-studio_ui_url').'/e/edit/'.$this->entry->id;
    $this->getUser()->setFlash('message',$this->message);
    tdz::cacheControl('private, no-cache', 0);
    $this->redirect($url);
    //$this->forward('tdz_entries','edit');
  }

  public function renderLayout(sfWebRequest $request)
  {
    $layout=sfConfig::get('app_e-studio_default_layout');
    $action=$this->actionName;
    if(substr($this->actionName,0,5)=='error')
    {
        $this->actionName='error';
        $tpldir=sfConfig::get('app_e-studio_template_dir');
        if(file_exists($layout)){}
        else if(file_exists("{$tpldir}/{$layout}.php"))
          $layout="{$tpldir}/{$layout}";
    }


    $prefix_url = sfConfig::get('app_e-studio_prefix_url');
    $response = $this->getResponse();
    //$response->addStyleSheet(sfConfig::get('app_e-studio_assets_url').'/css/e-studio.css');
    $this->entry=new tdzEntries();
    $this->entry->link=sfConfig::get('app_e-studio_ui_url')."/{$action}";
    $response->addJavaScript($prefix_url.sfConfig::get('app_e-studio_assets_url').'/js/loader.js?link='.urlencode($this->entry->getLink()));
    $layout=$this->entry->getLayout($request->getParameter('layout'));
    $this->setLayout($layout);
    return sfConfig::get('app_e-studio_ui_view');
  }

  /**
  * Executes list of records
  *
  * @param sfRequest $request A request object
  */
  public function executeList(sfWebRequest $request)
  {
    $this->setLanguage($request);
    if(!tdzEntries::hasStaticPermission('search')){
      $this->forward('tdz_entries','error403');
    }
    $this->message='';
    $this->query=$request->getGetParameter('q');
    // replace textual entries (surrounded by " or ') by variables
    $q = $this->query;
    $sp=array();
    if(preg_match_all('/"[^"]+"|\'[^\']+\'/', $q, $m))
    {
      $uid=uniqid();
      foreach($m[0] as $k=>$v)
      {
        $sp[$uid.$k]=substr($v,1,strlen($v)-2);
        $q = str_replace(array(':'.$v,$v),array(":{$uid}{$k} "," {$uid}{$k} "),$q);
      }
    }
    $q = preg_split('/[\s\n\r]+/', $q, null, PREG_SPLIT_NO_EMPTY);
    // search for keywords
    // valid keywords
    $keys=array(
     ':'=>array('title','link','summary','created','updated'),//array('title','link','summary','filename','label','status','created','updated'),
     '<>'=>array('created','updated'),
    );
    $qf=array();
    $es=array_keys($sp);
    $er=array_values($sp);
    $w =array();
    foreach($q as $qk=>$qp)
    {
      if(strpos($qp,':')>0 && in_array(substr($qp,0,strpos($qp,':')),$keys[':']))
      {
        $k=substr($qp,0,strpos($qp,':'));
        $qf[$k][]=substr($qp,strpos($qp,':')+1);
        unset($q[$qk]);
        continue;
      }
      else if(strpos($qp,'<')>0 && in_array(substr($qp,0,strpos($qp,'<')),$keys['<>']))
      {
        $k=substr($qp,0,strpos($qp,'<')).'<';
        $qf[$k]['<']=substr($qp,strpos($qp,'<')+1);
        unset($q[$qk]);
        continue;
      }
      else if(strpos($qp,'>')>0 && in_array(substr($qp,0,strpos($qp,'>')),$keys['<>']))
      {
        $k=substr($qp,0,strpos($qp,'>'));
        $qf[$k][]=substr($qp,strpos($qp,'>')+1);
        unset($q[$qk]);
        continue;
      }
      $qp=str_replace($es, $er, $qp);
      $q[$qk];
    }
    $w=$q;
    foreach($qf as $k=>$vv)
    {
      foreach($vv as $v)
      {
        if(substr($k,0,7)=='created' || substr($k,0,7)=='updated')
        {
          $v=strtotime($v);
          $op=(substr($k,-1)=='<')?($k):($k.'>');
          if($v)
            $w["{$op}= ?"][]=date('Y-m-d H:i:s',$v);
        }
        else
        {
          $w["locate(?,e.{$k})>0"][]=$v;
        }
      }
    }

    $this->img = sfConfig::get('app_e-studio_assets_url').'/images/icons.png';
    $this->url = sfConfig::get('app_e-studio_prefix_url').$request->getPathInfo();
    $this->ui_url = sfConfig::get('app_e-studio_prefix_url').sfConfig::get('app_e-studio_ui_url');
    $this->entries=array();

    //$q = tdzEntries::query();
    $q = new sfDoctrinePager('tdzEntries', 25);
    $q->getQuery()//->select('e.*')
      ->from("tdzEntries e");
      //->addComponent('e', 'tdzEntries')

    $this->status=false;
    if(tdzEntries::hasStaticPermission('delete','Entry'))
    {
      $get = $request->getGetParameters();
      $sent = (isset($get['q']));
      $status=array('published'=>$request->getGetParameter('published',!$sent),'unpublished'=>$request->getGetParameter('unpublished',!$sent),'expired'=>$request->getGetParameter('expired',false));
      $ws = array();
      $bool = ' and ';
      if($status['published'])
        $ws['published'][]= 'e.published is not null';
      if($status['unpublished'])
        $ws['published'][]= 'e.published is null';
      if($status['expired'])
      {
        $bool = ' or ';
        $ws['expired'][]= 'e.expired is not null';
      }
      else
        $ws['expired'][]= 'e.expired is null';

      if(count($ws)>0)
      {
        foreach($ws as $wsk=>$wsv)
          if(count($wsv)>1)
            $ws[$wsk]='('.implode(' or ',$wsv).')';
          else
            $ws[$wsk]=implode('',$wsv);

        $q->getQuery()->where(implode($bool,$ws));
      }
      $this->status=$status;
    }
    else
      $q->getQuery()->where('e.expired is null');


    foreach($w as $wk=>$where)
    {
      if(is_int($wk))
      {
        $wk="locate(?, concat(ifnull(e.title,''), ifnull(e.summary,''), ifnull(e.link,'')))>0";
        $q->getQuery()->andWhere($wk,$where);
      }
      else
      {
        foreach($where as $wherel)
          $q->getQuery()->andWhere($wk,$where);
      }
    }
    $this->types=sfConfig::get('app_e-studio_entry_types');
    $typeq = $request->getGetParameter('t');
    if(!is_array($typeq) || count($typeq)==0) $typeq=false;
    $typesel=array();
    foreach($this->types as $k=>$v)
    {
      if((is_array($typeq) && in_array($k, $typeq)) || (!$typeq && $v['display']))
      {
        $this->types[$k]['display']=true;
        $typesel[]=$k;
      }
      else
      {
        $this->types[$k]['display']=false;
      }
    }
    if(in_array('entry',$typesel))
      $q->getQuery()->andWhereIn('e.type is null or e.type',$typesel);
    else
      $q->getQuery()->andWhereIn('e.type',$typesel);


    
    $q->getQuery()->orderBy('e.updated desc');
    $this->page=$request->getGetParameter('p',1);
    $q->setPage($this->page);
    $q->init();

    $this->entries = $q;//->execute();

    $this->js=false;
    $this->js="tdz.load('e-studio_ui.js');tdz.init_form();";
    if ($request->isXmlHttpRequest())
      $this->setLayout(false);
    else
      $this->js .= 'tdz.cms_reload();';

    $response = $this->getResponse();
    $t=$this->getContext()->getI18N();
    $response->setTitle($t->__('Search for').': '.$this->query);
    //$fc = $this->entry;
    //if($fc->hasPermission('delete')) $this->delete=true;
    tdz::cacheControl('private, no-cache', 0);
    return sfConfig::get('app_e-studio_ui_view');
  }

  public function executeUi_loader(sfWebRequest $request)
  {
    $this->language=$this->setLanguage($request);
    $link = $request->getGetParameter('link');
    if($link=='') {
      $referer = parse_url($_SERVER['HTTP_REFERER']);
      $link = $referer['path'];
    }
    $this->entry = false;
    $prefix_url = sfConfig::get('app_e-studio_prefix_url');
    $this->ui_url = $prefix_url.sfConfig::get('app_e-studio_ui_url');
    if(false && preg_match('#^'.$prefix_url.'/e-studio/c/([a-z0-9\-_]+)/([a-z_]+)/([0-9]+)$#', $link, $m))
    {
      if($m[2]=='new')$this->entry = Doctrine_Core::getTable('tdzEntries')->findBy ('id', $m[2]);
      else
      {
        $fid = Doctrine_Core::getTable('tdzContents')->findBy('id', $m[3]);
        if($fid->count()>0)$this->entry = Doctrine_Core::getTable('tdzEntries')->findBy ('id', $fid->getFirst()->getEntries());
      }
    }
    else
    {
      $this->entry = tdzEntries::match($link);
    }
    $eid=($this->entry)?($this->entry->id):('0');
    if($this->entry && tdzEntries::hasStaticPermission('search'))
    {
      $this->a=array('e'=>array(),'c'=>array());
      $urls=array(
       'cnew'=>'<a class="btn [action]"  href="'.$this->ui_url.'/c/[slot]/new/'.$eid.'?before=[id]" title="[icon]"></a>',
       'cedit'=>true,
       'cpublish'=>true,
       'enew'=>true,
       'eedit'=>true,
       'esearch'=>'<a class="btn search" href="'.$this->ui_url.'/e" title="[icon]"></a><a class="btn files" href="'.$this->ui_url.'/e/files" title="[icon]"></a>',
       'epublish'=>true,
      );
      $objs=array('e'=>'Entry','c'=>'Content');
      foreach($urls as $k=>$v)
      {
        $action=substr($k,1);
        $obj=substr($k,0,1);
        if(tdzEntries::hasStaticPermission($action,$objs[$obj]))
          $this->a[$obj][$action]=$v;
      }
      if($this->a['c']['publish'])$this->a['c']['unpublish']=$this->a['c']['publish'];
      if($this->a['e']['publish'])$this->a['e']['unpublish']=$this->a['e']['publish'];
    }
    else
    {
      $this->a=array();
    }
    $this->user=false;
    $U = $this->getUser();
    if($U && $U->isAuthenticated()) {
      $this->user=$U->getAttribute('name');
      if(!$this->user) {
        $this->user=(string)$U;
      }
      if(!$this->user) {
        $this->user=$U->getAttribute('id');
      }
    }
    unset($U);
    $this->poll=sfConfig::get('app_e-studio_poll');
    $this->assets=$prefix_url.sfConfig::get('app_e-studio_assets_url');
    tdz::cacheControl('private, must-revalidate', false);
    return sfConfig::get('app_e-studio_ui_view');
  }

  /**
  * File manager
  *
  * @param sfRequest $request A request object
  */
  public function executeFiles(sfWebRequest $request)
  {
    $this->setLanguage($request);
    if(!tdzEntries::hasStaticPermission('search')){
      $this->forward('tdz_entries','error403');
    }
    $lastmod=tdzEntries::lastModified(true);
    //tdz::getBrowserCache(md5($lastmod.$_SERVER['REQUEST_URI']),$lastmod);
    $this->message='';
    $this->js=false;
    $this->js="tdz.load('e-studio-ui.js');";
    $d=$request->getGetParameter('d','').'/';
    $this->current_folder=tdz::validUrl($d);
    if(tdzEntries::hasStaticPermission('new')) {
        $upload = isset($_FILES['upload']) ? $_FILES['upload'] : false;
        if ($upload) {
            if (is_array($upload['tmp_name']) && count($upload['tmp_name']) > 1) {
                $info = array();
                foreach ($upload['tmp_name'] as $index => $value) {
                    $info[] = $this->handle_file_upload(
                        $upload['tmp_name'][$index],
                        $d.$upload['name'][$index],
                        $upload['size'][$index],
                        $upload['type'][$index],
                        $upload['error'][$index]
                    );
                }
            } else {
                if (is_array($upload['tmp_name'])) {
                    $upload = array(
                        'tmp_name' => $upload['tmp_name'][0],
                        'name' => $d.$upload['name'][0],
                        'size' => $upload['size'][0],
                        'type' => $upload['type'][0],
                        'error' => $upload['error'][0]
                    );
                }
                $info = $this->handle_file_upload(
                    $upload['tmp_name'],
                    isset($_SERVER['HTTP_X_FILE_NAME']) ?
                        $_SERVER['HTTP_X_FILE_NAME'] : $upload['name'],
                    isset($_SERVER['HTTP_X_FILE_SIZE']) ?
                        $_SERVER['HTTP_X_FILE_SIZE'] : $upload['size'],
                    isset($_SERVER['HTTP_X_FILE_TYPE']) ?
                        $_SERVER['HTTP_X_FILE_TYPE'] : $upload['type'],
                    $upload['error']
                );
            }
            if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                    $_SERVER['HTTP_X_REQUESTED_WITH'] === 'XMLHttpRequest') {
                header('Content-type: application/json');
            } else {
                header('Content-type: text/plain');
            }
            
            $this->setLayout(false);
            sfConfig::set('sf_web_debug',false);
            sfConfig::set('sf_escaping_strategy',false);
            $json=json_encode($info);
            @header('Content-Type: application/json; charset=utf-8');
            @header('Content-Length: '.strlen($json));
            tdz::cacheControl('private, no-cache', 0);
            exit($json);
        }
        $this->js="tdz.load('fileupload.js','e-studio-ui.js','fileupload.css');";
    }
    
    
    
    
    
    
    
    
    
    
    $auto_update = false;//sfConfig::get('app_e-studio_auto_update_files');
    if($auto_update)
    {
      if(is_bool($auto_update))
        tdzEntries::updateFiles();
      else
        tdzEntries::updateFiles($auto_update);
    }
    $this->toolbar=(!isset($_GET['editor']));
    if($request->isXmlHttpRequest())
    {
      $this->toolbar=false;
      $this->setLayout(false);
    }

    $this->img = sfConfig::get('app_e-studio_assets_url').'/images/icons.png';
    $this->url = sfConfig::get('app_e-studio_prefix_url').$request->getPathInfo();
    $this->ui_url = sfConfig::get('app_e-studio_prefix_url').sfConfig::get('app_e-studio_ui_url');
    $this->entries=array();
    $this->action='';

    $options=array();
    if($this->format == $request->getGetParameter('f','')) {
      $this->format=preg_replace('/[^a-z0-9]+/','',$this->format);
      $options['format']=$this->format;
    }
    $options['hydrate']=Doctrine::HYDRATE_ARRAY;
    $this->folders=tdzEntries::getFolders($d, $options);
    $this->files = tdzEntries::getLinks($d, $options);
    $response = $this->getResponse();
    $t=$this->getContext()->getI18N();
    $response->setTitle($t->__('Files at').': '.$this->current_folder);
    //$fc = $this->entry;
    //if($fc->hasPermission('delete')) $this->delete=true;
    tdz::cacheControl('private, no-cache', 0);
    return sfConfig::get('app_e-studio_ui_view');
  }

    private function handle_file_upload($uploaded_file, $name, $size, $type, $error) {
        $file = new stdClass();
        $file->name = basename(stripslashes($name));
        $file->size = intval($size);
        $file->type = $type;
        $e = new tdzEntries();
        $e->title = basename(stripslashes($name));
        $e->type = 'file';
        $e->link = tdz::validUrl($name);
        $e->source = sha1_file($uploaded_file);
        $ext = array_search($type, tdz::$formats);
        if($ext) {
            $e->source .= '.'.$ext;
        }
        $e->format = $type;
        $e->created=date('Y-m-d H:i:s');
        $e->updated = $e->created;
        if (!$error && $file->name) {
            if ($file->name[0] === '.') {
                $file->name = substr($file->name, 1);
            }
            $file_path = sfConfig::get('app_e-studio_upload_dir').'/'.$e->source;
            $append_file = false;//is_file($file_path) && $file->size > filesize($file_path);
            clearstatcache();
            if ($uploaded_file && is_uploaded_file($uploaded_file)) {
                // multipart/formdata uploads (POST method uploads)
                if ($append_file) {
                    file_put_contents(
                        $file_path,
                        fopen($uploaded_file, 'r'),
                        FILE_APPEND
                    );
                } else {
                    move_uploaded_file($uploaded_file, $file_path);
                }
            } else {
                // Non-multipart uploads (PUT method support)
                file_put_contents(
                    $file_path,
                    fopen('php://input', 'r'),
                    $append_file ? FILE_APPEND : 0
                );
            }
            $file_size = filesize($file_path);
            if ($file_size === $file->size) {
                $file->url = $this->upload_url.rawurlencode($file->name);
                //$file->thumbnail = $this->create_thumbnail($file->name) ?
                //    $this->thumbnails_url.rawurlencode($file->name) : null;
            }
            $file->size = $file_size;
        } else {
            $file->error = $error;
        }
        if ($e->save());
        $file->id=$e->id;
        $file->link=$e->link;
        $file->url = '/e-studio/e/preview/'.$e->id.'?optimize=thumb&t='.time();
        return $file;
    }
    
  public function executeError403(sfWebRequest $request)
  {
    $this->setLanguage($request);
    $layout=$this->errorResponse($request,'403');
    $this->setLayout($layout);
    return sfConfig::get('app_e-studio_ui_view');
  }
  public function executeError404(sfWebRequest $request)
  {
    $this->setLanguage($request);
    $layout=$this->errorResponse($request,'404');
    $this->setLayout($layout);
    return sfConfig::get('app_e-studio_ui_view');
  }

  public function errorResponse(sfWebRequest $request, $err='404')
  {
    $m = sfConfig::get('app_e-studio_error'.$err);
    $response = $this->getResponse();
    $context=$this->getContext();
    $t=$context->getI18N();
    $this->title = $t->__($m['title']);
    $this->message=$t->__($m['message']);
    $response->setTitle($this->title);
    if(isset($m['link']))
    {
      $https = (isset($_SERVER['HTTPS']))?('https://'):('http://');
      $link=sprintf($m['link'],urlencode($https.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']));
      $context->getUser()->setFlash('message',$this->message);
      if ($err == '403') {
        //Para não dar problema com o IE, não emite o erro 403 no redirect
        $this->redirect($link);
      } else {
        $this->redirect($link, $err);      
      }
    }
    if(!$request->isXmlHttpRequest())
    {
      $response->setStatusCode($err);
    }

    $errurl=(isset($m['link']))?($m['link']):('/error'.$err);
    $entry=tdzEntries::match($errurl);
    if(!$entry)
    {
      $entry=new tdzEntries();
      $entry->link=$errurl;
      $entry->title=$this->title;
    }
    $layout=$entry->getLayout();
    $this->setLayout($layout);
    $this->actionName='error';
    return $layout;
  }

}
