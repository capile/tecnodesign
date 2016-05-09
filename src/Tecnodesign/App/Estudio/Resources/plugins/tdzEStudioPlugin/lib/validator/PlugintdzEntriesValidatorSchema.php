<?php
/**
 * Entry actions
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id$
 */

class PlugintdzEntriesValidatorSchema extends sfValidatorSchema
{

  protected function configure($options = array(), $messages = array())
  {
    //$this->addMessage('caption', 'The caption is required.');
    //$this->addMessage('filename', 'The filename is required.');
  }

  protected function doClean($values)
  {
    $errorSchema = new sfValidatorErrorSchema($this);
    //tdz::debug($values, $this->form->getObject()->type);
    return $values;

    foreach($values as $key => $value)
    {
      $errorSchemaLocal = new sfValidatorErrorSchema($this);


      // filename is filled but no caption
      if ($value['filename'] && !$value['caption'])
      {
        $errorSchemaLocal->addError(new sfValidatorError($this, 'required'), 'caption');
      }

      // caption is filled but no filename
      if ($value['caption'] && !$value['filename'])
      {
        $errorSchemaLocal->addError(new sfValidatorError($this, 'required'), 'filename');
      }

      // no caption and no filename, remove the empty values
      if (!$value['filename'] && !$value['caption'])
      {
        unset($values[$key]);
      }

      // some error for this embedded-form
      if (count($errorSchemaLocal))
      {
        $errorSchema->addError($errorSchemaLocal, (string) $key);
      }
    }

    // throws the error for the main form
    if (count($errorSchema))
    {
      throw new sfValidatorErrorSchema($this, $errorSchema);
    }

    return $values;
  }
}