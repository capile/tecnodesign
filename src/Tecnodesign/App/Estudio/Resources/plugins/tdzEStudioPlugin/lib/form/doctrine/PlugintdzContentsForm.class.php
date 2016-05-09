<?php
/**
 * PlugintdzContents form.
 *
 * @package    ##PROJECT_NAME##
 * @subpackage form
 * @author     ##AUTHOR_NAME##
 * @version    SVN: $Id: PlugintdzContentsForm.class.php 1211 2013-05-05 17:43:12Z capile $
 */
abstract class PlugintdzContentsForm extends BasetdzContentsForm
{
  public $reloaded=false;
  public $blocks=array();
  public $values=array();
  public $use_fields=array();

  public function xrender()
  {
    return tdz::renderForm($this);
  }
  public function render_cms()
  {
    return tdz::renderForm($this);
  }
  public function configure_cms($options=array(), $values=null)
  {
    $embedded=(isset($options['embedded']) && $options['embedded']);

    $this->widgetSchema->setNameFormat('tdzfc[%s]');
    $this->setCSRFFieldName('_tdz');

    $action=sfContext::getInstance()->getActionName();
    $user=sfContext::getInstance()->getUser();
    $o=$this->getObject();

    $ct=tdzContents::getContentTypes();
    $ct_choices = false;
    if($user->hasAttribute($action.'ContentTypes'))
        $ct_choices=$user->getAttribute($action.'ContentTypes',false);
    if(!is_array($ct_choices))
    {
      $d=sfConfig::get('app_e-studio_permissions');
      $ct_choices = array();

      foreach($ct as $id=>$value)
      {
        $pid=$action.'ContentType'.ucfirst($id);
        if(!isset($d[$pid]) || $d[$pid]=='*' || $user->hasCredential($d[$pid]))
          $ct_choices[$id]=(isset($value['title']))?($value['title']):($id);
      }
      $user->setAttribute($action.'ContentTypes',$ct_choices);
      if(!isset($ct[''])) $ct_choices['']='Plain Text';
    }

    $bk = ($embedded)?('Conteúdo'):('_');

    $this->blocks[$bk]=array();
    $get = array();
    $ff = false;
    $request=sfContext::getInstance()->getRequest();
    $o=$this->getObject();
    if($o->id=='1') $o->slot='body';
    $post = ($request->getPostParameter('tdzfc')!='')?($request->getPostParameter('tdzfc')):(array());
    $get = ($request->getGetParameter('tdzfc')!='')?($request->getGetParameter('tdzfc')):(array());
    // get current content-type
    //tdz::debug("from \$o: ",$o->getContent(),"from \$this: ",$this->getValues());
    if(is_null($values))
      $values=array();
    $values += $post + $get + $o->getData() + $this->getValues() + $this->getTaintedValues();

    //foreach($values as $k=>$v)
    //  if($v=='')unset($values[$k]);
    $oct='';
    if($this->isNew() && !isset($values['content_type']) || $values['content_type']=='' || !isset($ct_choices[$values['content_type']]))
      $oct=sfConfig::get('app_e-studio_default_content_type');
    else if(isset($values['content_type']) && isset($ct[$values['content_type']]))
      $oct = $values['content_type'];
    $values['content_type']=$oct;

    $oc=(isset($values['content']))?($values['content']):(array());//$o->getContent();
    if(!is_array($oc))
      $oc = sfYaml::load("{$oc}\n");
    if(!is_array($oc)) $oc=array();

    //tdz::debug($values, $post, $get, $o->getData(), $this->getValues(), $this->getTaintedValues(), false);

    $this->blocks[$bk]['entry']=array('type'=>'hidden');
    $this->blocks[$bk]['content_type']=array('type'=>'choice','default'=>$oct,'choices'=>$ct_choices,'expanded'=>false,'required'=>true,'class'=>'preview','attributes'=>array('onchange'=>'tdz.cms_reload()'));
    if($oct !='' && isset($ct[$oct]['fields']))
    {
      $this->blocks[$bk]['content']=array('type'=>'hidden');
      foreach($oc as $fn=>$v)
        if(isset($ct[$oct]['fields'][$fn]) && !isset($values["content_{$fn}"])) $values["content_{$fn}"]=$v;
      foreach($ct[$oct]['fields'] as $fn=>$fd)
        $this->blocks[$bk]["content_{$fn}"]=$fd;
    }
    else
    {
      $this->blocks[$bk]['content']=array('type'=>'textarea','label'=>'Plain Text');
    }
    
    if(!$embedded && $o->hasPermission('edit','Template'))
    {
      $this->blocks[$bk]['show_at']=array('type'=>'textarea','class'=>'preview 2columns smallest');
      $this->blocks[$bk]['hide_at']=array('type'=>'textarea','class'=>'preview 2columns smallest');
    }
    if($embedded)
    {
      $dslots=sfConfig::get('app_e-studio_slots');
      $slots=array();
      $dsn=sfConfig::get('app_e-studio_default_slotname');
      if(!isset($dslots[$dsn])) $dsn='default';
      foreach($dslots[$dsn] as $sn=>$v)
        if(isset($v['label'])) $slots[$sn]=$v['label'];
        else $slots[$sn]=ucwords($sn);
      $this->blocks[$bk]['slot']=array('type'=>'choice','default'=>'body','choices'=>$slots,'expanded'=>false,'required'=>true,'class'=>'preview');
      //$this->blocks[$bk]['position']=array('type'=>'text','class'=>'preview sortorder');
    }
    else
    {
    $this->blocks[$bk]['slot']=array('type'=>'hidden');
    }
    $this->blocks[$bk]['position']=array('type'=>'hidden','class'=>'sortorder');
    $this->blocks[$bk]['entry']=array('type'=>'hidden');
    tdz::form($this);
    //tdz::debug($values);
    foreach($values as $fn=>$v)
      if(!isset($this->blocks[$bk][$fn])) unset($values[$fn]);
    $values[$this->getCSRFFieldName()]=$this->getCSRFToken();

    $this->values=$values;
    if((count($post) > 0 && !isset($_SERVER['HTTP_TDZ_NOT_FOR_UPDATE'])) || $this->isBound())
      $this->bind($values);
    else
      $this->setDefaults($values);
  }

  public function updateContentColumn($value, $a='')
  {
    // check for posted relations
    $post = $this->getTaintedValues();
    //tdz::debug('content!', $value, $post, $this->values, $_POST);
    if(count($post)==0)$post=$this->values;
    $ct=tdzContents::getContentTypes();
    $oct=$post['content_type'];
    if(isset($ct[$oct]))
    {
      $value=array();
      foreach($ct[$oct]['fields'] as $fn=>$fd)
      {
        if(isset($post["content_{$fn}"]))
          $value[$fn]=$post["content_{$fn}"];
      }
      $value = sfYaml::dump($value);
      $this->getObject()->cleanCache();
    }
    return $value;
  }
  public function updateEntryColumn($value)
  {
    if(!$value)$value=tdzEntriesForm::$entry;
    return $value;
  }

}
