<?php
/**
 * Content actions
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: tdzEStudioContentsActions.class.php 1152 2013-01-08 14:39:38Z capile $
 */

class tdzEStudioContentsActions extends sfActions
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
    return $language;
  }

  public function executePreview(sfWebRequest $request)
  {
    $this->setLanguage($request);
    $this->id=$request->getParameter('id');
    sfConfig::set('sf_web_debug',false);
    sfConfig::set('sf_escaping_strategy',false);
    $this->setLayout(false);
    tdz::cacheControl('private, no-cache', 0);
    return $this->renderComponent('tdz_contents', 'preview', array('id'=>$request->getParameter('id'),'slot'=>$request->getParameter('slot')));
  }



 /**
  * New content form
  */
  public function executeNew(sfWebRequest $request)
  {
    $this->setLanguage($request);
    $this->entry = tdzEntries::find($request->getParameter('id'));
    $this->title = $this->entry;
    if(!$this->entry || !$this->entry->hasPermission('new','Content')){
      $this->forward('tdz_entries','error403');
    }
    $response = $this->getResponse();
    $response->setTitle('New content at _'.$this->entry.'_');
    $this->processForm($request,'new');
    tdz::cacheControl('private, no-cache', 0);
    return sfConfig::get('app_e-studio_ui_view');
  }
  public function cleanCache($id=null)
  {
    if(is_null($id) && isset($this->content)) $id=$this->content->id;
    if($id)
    {
      tdz::cleanCache('tdzContents/c'.$id);
    }
  }

  public function processForm(sfWebRequest $request, $action=null)
  {
    tdzEntriesForm::setCSRFFieldName('_tdz');
    tdz::cacheControl('private, no-cache', 0);
    $this->setLanguage($request);
    sfWidgetFormSchema::setDefaultFormFormatterName('list');
    $this->slot = preg_replace('/[^a-z0-9\-\_]/i','',$request->getParameter('slot'));
    $this->img = sfConfig::get('app_e-studio_assets_url').'/images/icons.png';
    $this->url = sfConfig::get('app_e-studio_prefix_url').$request->getPathInfo();
    $this->js=false;
    $this->toolbar = true;

    if($request->isXmlHttpRequest())
    {
      $this->toolbar = false;
      $this->setLayout(false);
    }

    if($action=='new' && !isset($this->content))
    {
      $this->content = new tdzContents();
      if(!isset($this->entry))
        $this->entry = tdzEntries::find($request->getParameter('id'));
      $ct = sfConfig::get('app_e-studio_default_content_type');
      if($this->entry)
      {
        $this->content->setEntry($this->entry->id);
        $ref=false;$reftype=false;
        if($request->getGetParameter('before')!='' && is_numeric($request->getGetParameter('before')))
        {
          $ref = $request->getGetParameter('before');
          $reftype='before';
        }
        else if($request->getGetParameter('after')!='' && is_numeric($request->getGetParameter('after')))
        {
          $ref = $request->getGetParameter('after');
          $reftype='after';
        }
        if($ref)
        {
          $ref=$this->entry->getAllContents('c.id=:content',array('content'=>$ref));
          if($ref->count()>0)
          {
            $ref = $ref[0];
            if($reftype=='before')
              $this->content->setPosition((int)$ref->getPosition());
            else if($reftype=='after')
              $this->content->setPosition((int)$ref->getPosition() +1);

            $ct=$ref->getContentType();
          }
        }
      }
      $this->content->setContentType($ct);
    }
    else if(!isset($this->content))
    {
      $this->content=tdzContents::find($request->getParameter('id'));
    }
    if(!$this->content)
    {
      $this->message = $this->getContext()->getI18N()->__('This content is no longer available. Has it been deleted?');
      $this->forward('tdz_entries','error404');
    }
    $this->entry = $this->content->getEntries();
    if(!$this->entry)
      $this->entry=new tdzEntries();

    $actionName=$action;
    if($action=='unpublish')$actionName='publish';
    if(!$this->entry || !$this->entry->hasPermission($actionName,'Content'))
      $this->forward('tdz_entries','error403');

    $this->content->setSlot($this->slot);

    $this->delete=($action!='new' && $this->content->hasPermission('delete'));
    $this->publish=($action!='new' && $this->content->hasPermission('publish'));
    $this->unpublish=($this->publish && $this->content->published);
    $this->expired=($this->content->expired!='');

    $this->message='';
    $this->js="tdz.load('e-studio_ui.js');tdz.init_form();";
    if(!isset($this->form)) $this->form = new tdzContentsForm($this->content);
    if(!isset($this->run))
      $this->run=false;
    if($this->form)
    {
      $this->form->configure_cms();
      if(is_null($action))
      {
        if($this->form->isNew()) $action='new';
        else if($this->content->expired!='') $action='undelete';
        else $action='edit';
      }
      if($request->isMethod('post') && !isset($_SERVER['HTTP_TDZ_NOT_FOR_UPDATE']))
      //if ($request->isMethod('post'))
      {
        $post = $request->getPostParameter('tdzfc');
        $this->form->bind($post);
        if($this->form->isValid())
        {
          $this->run=true;
          /*
          $values = $this->form->getValues();
          if(isset($values['contents']))
            $post['content']=sfYaml::dump($values['contents']);
          $this->form->bind($post);
           */
          $this->js.="tdz.update_slots();";
          $this->content = $this->form->save();
          $this->content->cleanCache();
        }
      }
      else if($request->getGetParameter('tdzfc')!='')
      {
        $get = $request->getGetParameter('tdzfc');
        if(isset($get['contents'])) unset($get['contents']);
        $this->form->setDefaults($get);
      }
    }
    $this->message=$this->getUser()->getFlash('message');
    $url=sfConfig::get('app_e-studio_prefix_url').$request->getPathInfo();
    $forward=false;
    if($this->run)
    {
      if($action=='new')
      {
        $this->message = $this->getContext()->getI18N()->__('Successfully created contents!');
        $url=sfConfig::get('app_e-studio_prefix_url').sfConfig::get('app_e-studio_ui_url').'/c/'.$this->slot.'/edit/'.$this->form->getObject()->id;
        if($request->isXmlHttpRequest())
        {
          $this->form=false;
          //$this->actionName='edit';
        }
      }
      else if($action=='publish' || $action=='unpublish')
      {
        $this->content->published=($action=='publish')?(date('Y-m-d H:i:s')):(null);
        $this->content->save();
        $this->content->cleanCache();
        $this->js.="tdz.update_slots('{$this->slot}');";
        if(($action=='publish' && $this->content->published!='')||($action=='unpublish' && $this->content->published==''))
          $this->message=$this->getContext()->getI18N()->__('Successfully '.$action.'ed contents!');
        else
          $this->message=$this->getContext()->getI18N()->__('It wasn\'t possible to '.$action.' the content. Please try again or contact support.');
        if(!$this->form)
          $url=false;
        else
          $url = str_replace('/'.$action.'/','/edit/', $url);
      }
      else if($action=='delete')
      {
        $this->content->delete();
        $this->content->cleanCache();
        $this->message=$this->getContext()->getI18N()->__('Ok, contents removed.');
      }
      else
      {
        $this->message = $this->getContext()->getI18N()->__('Successfully saved contents!');
      }
      if(!$request->isXmlHttpRequest() && $url)
      {
        $this->getUser()->setFlash('message',$this->message);
        $this->redirect($url);
      }
    }
  }

  public function executeEdit(sfWebRequest $request)
  {
    if(!tdzEntries::hasStaticPermission('edit','Content'))
      $this->forward('tdz_entries','error403');
    $this->setLanguage($request);
    $this->message='';
    $response = $this->getResponse();
    $this->processForm($request,'edit');
    $this->title = $this->entry;
    $response->setTitle('Edit content at _'.$this->entry.'_');
    return sfConfig::get('app_e-studio_ui_view');
  }

  public function executeDelete(sfWebRequest $request)
  {
    if(!tdzEntries::hasStaticPermission('delete','Content'))
      $this->forward('tdz_entries','error403');
    $this->setLanguage($request);

    $this->run=true;
    if(!$request->isMethod('post'))
      $this->form=false;
    $this->processForm($request, 'delete');
    $this->title = $this->entry;
    $response = $this->getResponse();
    $response->setTitle('Delete content');
    return sfConfig::get('app_e-studio_ui_view');
  }

  public function executePublish(sfWebRequest $request)
  {
    if(!tdzEntries::hasStaticPermission('publish','Content'))
      $this->forward('tdz_entries','error403');
    $this->setLanguage($request);

    $this->run=true;
    if(!$request->isMethod('post'))
      $this->form=false;
    $this->processForm($request, 'publish');
    $this->title = $this->entry;
    $response = $this->getResponse();
    $response->setTitle('Publish content');
    return sfConfig::get('app_e-studio_ui_view');
  }

  public function executeUnpublish(sfWebRequest $request)
  {
    if(!tdzEntries::hasStaticPermission('publish','Content'))
      $this->forward('tdz_entries','error403');
    $this->setLanguage($request);

    $this->run=true;
    if(!$request->isMethod('post'))
      $this->form=false;
    $this->processForm($request, 'unpublish');
    $this->title = $this->entry;
    $response = $this->getResponse();
    $response->setTitle('Unpublish content');
    return sfConfig::get('app_e-studio_ui_view');
  }

  public function executeDeleteOld(sfWebRequest $request)
  {
    if(!tdzEntries::hasStaticPermission('delete','Content'))
      $this->forward('tdz_entries','error403');
    // i18n
    $languages = array('pt_BR','en');
    $language  = $request->getPreferredCulture($languages);
    $this->getUser()->setCulture($language);
    $this->title='';

    if($request->isXmlHttpRequest())
      $this->setLayout(false);
    $this->url = $request->getPathInfo();
    $fc = tdzContents::find($request->getParameter('id'));
    if(!$fc)
    {
      $this->message = $this->getContext()->getI18N()->__('This content is no longer available. Has it been deleted?');
    }
    else if(!$fc->hasPermission('delete')){
      $this->message=$this->getContext()->getI18N()->__('You don\'t have the appropriate credentials to access this resource.');
    }
    else
    {
      if(!$fc->hasPermission('delete'))
        $this->forward('tdz_entries','error403');
      $id=$fc->id;
      $this->entry = $fc->getEntries();
      $this->slot = preg_replace('/[^a-z0-9\-\_]/i','',$request->getParameter('slot'));
      $response = $this->getResponse();
      $this->title = $this->entry;
      $response->setTitle('Delete content at _'.$this->entry.'_');
      $this->form = new tdzContentsForm($fc);
      $this->form->configure_cms();
      $this->content=$fc;
      $this->message='';
      $this->js="tdz.load('e-studio_ui.js');";
      $valid = false;
      if($request->isMethod('post'))
      {
        $post = $request->getPostParameter('tdzfc');
        $this->form->bind($post);
        // check csrf only
        if(true || $this->form->isValid())
        {
          $valid= true;
          $this->content->delete();
          $this->content->cleanCache();
          $this->message=$this->getContext()->getI18N()->__('Ok, contents removed.');
          $this->js.="tdz.update_slots('{$this->slot}');";
        }
      }
      if(!$valid)
      {
        $this->message=$this->getContext()->getI18N()->__('It wasn\'t possible to remove the selected contents.');
      }
    }

    return sfConfig::get('app_e-studio_ui_view');
  }
}
