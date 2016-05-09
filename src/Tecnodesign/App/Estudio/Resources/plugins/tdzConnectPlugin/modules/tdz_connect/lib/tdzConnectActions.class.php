<?php
/**
 * Tecnodesign Connect actions related to authentication & authorization
 *
 * @package      tdzConnectPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id$
 */
class tdzConnectActions extends sfActions
{
  /**
  * Sign in action
  *
  * used for authentication requests -- should always redirect back to where
  * the request was sent, or return a JSON/Javascript string
  *
  * @param sfRequest $request A request object
  */
  public function executeSignIn(sfWebRequest $request)
  {
    $provider=$request->getParameter('provider');
    $ns=sfConfig::get('app_connect_ns');
    if(isset($ns[$provider]) && $ns[$provider]['enabled'])
    {
      $m=$provider.'SignIn';
      $this->getUser()->$m($ns[$provider]['options'], $request);
    }
    tdz::debug('{}');
    //$url=$request->getPathInfo();
    //$credentials=$this->getUser()->getCredentials();
    return false;
  }
  /**
  * Sign out action
  *
  * used for authentication requests -- should always redirect back to where
  * the request was sent, or return a JSON/Javascript string
  *
  * @param sfRequest $request A request object
  */
  public function executeSignOut(sfWebRequest $request)
  {
    $user=$this->getUser();
    $provider=$request->getParameter('provider');
    if($provider=='')
      list($provider,$id)=preg_split('/\:/',$user->getAttribute('id'),2);
    $ns=sfConfig::get('app_connect_ns');
    if(isset($ns[$provider]) && $ns[$provider]['enabled'])
    {
      $m=$provider.'SignOut';
      $user->$m($ns[$provider]['options'], $request);
    }
    tdz::debug('{}');
    //$url=$request->getPathInfo();
    //$credentials=$this->getUser()->getCredentials();
    return false;
  }
}
