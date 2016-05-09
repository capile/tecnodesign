<?php
/**
 * Content components
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: tdzEStudioContentsComponents.class.php 967 2011-12-08 09:45:41Z capile $
 */

class tdzEStudioContentsComponents extends sfComponents
{

  public function executePreview(sfWebRequest $request)
  {
    /**
     * should cache:
     * {
     *   credentials: string|array
     *   code: {
     *     before:
     *     execute:
     *     content:
     *     after:
     *   }
     */
    $this->preview=false;
    $cvar=false;
    $timeout=tdzEntries::lastModified(true);
    $co=new sfFileCache(array('cache_dir'=>sfConfig::get('sf_app_cache_dir').'/tdzContents'));
    $ckey="c{$this->id}.{$this->version}";
    if(false && $this->version!='' && $co->getLastModified($ckey)>$timeout)
    {
      $cvar=$co->get($ckey);
      if($cvar)
        $cvar=unserialize($cvar);
    }
    else
    {
      if(isset($this->content))
        $fc=$this->content;
      else
      {
        $fc = tdzContents::find($this->id);
        if($fc) {
            $fc->getLatestVersion();
        }
      }
      if(!$fc || $fc->expired!='') return false;

      $ckey='c'.$fc->id;
      $ckey.= '.'.$fc->version;
      $cvar=false;
      $lifetime=sfConfig::get('app_e-studio_cache_timeout');
      //$timeout=time() - $lifetime;
      if(false && $co->getLastModified($ckey)>$timeout)
      {
        $cvar=$co->get($this->id);
        if($cvar)
          $cvar=unserialize($cvar);
      }
    }
    if(is_array($cvar))
    {
      foreach($cvar as $k=>$v)
      {
        $this->$k=$v;
      }
    }
    else
    {
      $this->code='';
      $this->slot='';
      if($fc)
      {
        $this->credentials=$fc->getPermission('preview');
        $this->slot=$fc->slot;
        $this->code=$fc->render();
      }
      $cvar=array('credentials'=>$this->credentials,'slot'=>$this->slot,'code'=>$this->code);
      $co->set($ckey,serialize($cvar),$lifetime);
    }
    //tdzToolKit::debug($this->credentials, "nonono",sfContext::getInstance()->getUser()->hasCredential($this->credentials,false), $this->code);

    if(!isset($this->credentials)||(is_bool($this->credentials)&&$this->credentials==false)||(!is_bool($this->credentials)&&!sfContext::getInstance()->getUser()->hasCredential($this->credentials,false)))
      $this->preview=false;
    else
      $this->preview=true;
    if(is_array($this->code))
    {
      foreach($this->code as $k=>$v)
        $this->$k=$v;
    }
    else $this->content=(string)$this->code;
  }
}
