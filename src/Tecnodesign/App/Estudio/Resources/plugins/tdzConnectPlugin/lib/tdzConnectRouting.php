<?php
/**
 * Tecnodesign Connect Routing
 *
 * @package       tdzConnectPlugin
 * @author        Tecnodesign <ti@tecnodz.com>
 * @link          http://tecnodz.com/
 * @version       SVN: $Id$
 */

class tdzConnectRouting
{
  static public function listenToRoutingLoadConfigurationEvent(sfEvent $event)
  {
    sfConfig::set('sf_enabled_modules', array_merge(sfConfig::get('sf_enabled_modules'), array('tdz_connect')));
    $helpers=sfConfig::get('sf_standard_helpers');
    if(!in_array('I18N',$helpers)) $helpers[]='I18N';
    sfConfig::set('sf_standard_helpers', $helpers);
    sfConfig::set('sf_default_culture', 'en');

    $routing = $event->getSubject();
    // add plug-in routing rules on top of the existing ones

    $r=sfConfig::get('app_connect_signin_route');
    $ro=sfConfig::get('app_connect_signout_route');
    if($r)
    {
      $routing->prependRoute('tdz_connect_signin', new sfRoute(
       $r, array(
        'module'=>'tdz_connect',
        'action'=>'signIn',
      )));
      $routing->prependRoute('tdz_connect_signout', new sfRoute(
       $ro, array(
        'module'=>'tdz_connect',
        'action'=>'signOut',
      )));
      $ns=sfConfig::get('app_connect_ns');
      foreach($ns as $provider=>$options)
      {
        if(!($options['enabled'] && $options['signin']))continue;
        $url=$provider;
        if(is_string($options['signin']) && strlen($options['signin'])>1)$url=$options['signin'];
        $routing->prependRoute('tdz_connect_signin_'.$provider, new sfRoute(
         $r.'/'.$url, array(
          'module'=>'tdz_connect',
          'action'=>'signIn',
          'provider'=>$provider,
        )));
        $routing->prependRoute('tdz_connect_signout_'.$provider, new sfRoute(
         $ro.'/'.$url, array(
          'module'=>'tdz_connect',
          'action'=>'signOut',
          'provider'=>$provider,
        )));
      }
    }
 }
}