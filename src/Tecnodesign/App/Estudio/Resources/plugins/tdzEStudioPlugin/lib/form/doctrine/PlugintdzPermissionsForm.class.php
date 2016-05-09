<?php

/**
 * PlugintdzPermissions form.
 *
 * @package    ##PROJECT_NAME##
 * @subpackage form
 * @author     ##AUTHOR_NAME##
 * @version    SVN: $Id: PlugintdzPermissionsForm.class.php 967 2011-12-08 09:45:41Z capile $
 */
abstract class PlugintdzPermissionsForm extends BasetdzPermissionsForm
{
  public $reloaded=false;
  public $blocks=array();
  public $values=array();
  public $use_fields=array();
  public function render_cms()
  {
    return tdz::renderForm($this);
  }
  public function configure_cms($options=array(), $values=null)
  {
    $embedded=(isset($options['embedded']) && $options['embedded']);


    $this->widgetSchema->setNameFormat('tdzfp[%s]');
    $this->setCSRFFieldName('_tdz');

    $action=sfContext::getInstance()->getActionName();
    $user=sfContext::getInstance()->getUser();
    $o=$this->getObject();

    $bk = ($embedded)?('Permissions'):('_');

    $this->blocks[$bk]=array();
    $get = array();
    $ff = false;
    $request=sfContext::getInstance()->getRequest();
    $o=$this->getObject();
    $post = ($request->getPostParameter('tdzfp')!='')?($request->getPostParameter('tdzfp')):(array());
    $get = ($request->getGetParameter('tdzfp')!='')?($request->getGetParameter('tdzfp')):(array());
    if(is_null($values))
      $values=array();
    $values += $post + $get + $o->getData() + $this->getValues() + $this->getTaintedValues();

    if(isset($values['credentials']) && $values['credentials']=='1')
      $values['credentials']=array();
    else if(isset($values['credentials']) && is_string($values['credentials']))
      $values['credentials']=preg_split('/\s*\,\s*/', $values['credentials'], null, PREG_SPLIT_NO_EMPTY);
    $credentials=tdzUser::getAllCredentials();
    $this->blocks[$bk]['entry']=array('type'=>'hidden');
    $this->blocks[$bk]['role']=array('type'=>'hidden');
    $label='Credentials';
    if(isset($options['label'])) $label=$options['label'];
    $this->blocks[$bk]['credentials']=array('type'=>'choice','choices'=>$credentials, 'callback'=>'validateCredentials','multiple'=>true,'options'=>array('multiple'=>true,'expanded'=>true),'required'=>false, 'label'=>$label);
    tdz::form($this);
    foreach($values as $fn=>$v)
      if(!isset($this->blocks[$bk][$fn])) unset($values[$fn]);
    $values[$this->getCSRFFieldName()]=$this->getCSRFToken();

    $this->values=$values;
    if(count($post) > 0 || $this->isBound())
      $this->bind($values);
    else
      $this->setDefaults($values);
  }
  public static function fieldValidatorCredentials($validator, $str, $arguments=array())
  {
    //tdz::debug('fieldValidatorCredentials', $str);
    if(is_array($str))
    {
      asort($str);
      $str = implode(',',$str).',';
    }
    return $str;//self::validUrl($str);
  }

  public function updateEntryColumn($value)
  {
    if(!$value)$value=tdzEntriesForm::$entry;
    return $value;
  }


}
