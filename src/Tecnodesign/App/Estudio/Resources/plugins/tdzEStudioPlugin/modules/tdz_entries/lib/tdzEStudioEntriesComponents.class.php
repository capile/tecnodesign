<?php
/**
 * Entry components
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: tdzEStudioEntriesComponents.class.php 543 2010-12-16 13:17:23Z capile $
 */

class tdzEStudioEntriesComponents extends sfComponents
{
  public function executeEntryPreview(sfWebRequest $request)
  {
    $this->preview=tdzEntries::entryPreview($this->id,$this->template);
    return true;
  }

  
  public function executeFeedPreview(sfWebRequest $request)
  {
    $this->preview=tdzEntries::feedPreview($this->id,$this->template);
    return true;
  }
}
