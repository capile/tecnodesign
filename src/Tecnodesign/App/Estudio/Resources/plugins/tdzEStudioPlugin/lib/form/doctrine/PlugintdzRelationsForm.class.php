<?php

/**
 * PlugintdzRelations form.
 *
 * @package    ##PROJECT_NAME##
 * @subpackage form
 * @author     ##AUTHOR_NAME##
 * @version    SVN: $Id: PlugintdzRelationsForm.class.php 564 2011-01-24 21:21:54Z capile $
 */
abstract class PlugintdzRelationsForm extends BasetdzRelationsForm
{
  public $blocks=array();
  public $use_fields=array();

  public function configure_cms($format='hidden')
  {
    $fields=array('id'=>true);

    if($format=='hidden')
    {
      $this->blocks['Relations']['entry']=array('type'=>'hidden');
      $this->blocks['Relations']['position']=array('type'=>'hidden');
      $this->blocks['Relations']['parent']=array('type'=>'hidden');
    }
    else
    {
      $this->widgetSchema->setNameFormat('tdze[%s]');
    }
    tdz::form($this);
  }
  public function doSave($con = null)
  {
    tdz::debug('relations');
  }
  public function updateParentColumn($value)
  {
    if($value=='')$value=null;
    return $value;
  }
  public function updateEntryColumn($value)
  {
    if(!$value)$value=tdzEntriesForm::$entry;
    return $value;
  }

}
