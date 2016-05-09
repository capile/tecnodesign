<?php
/**
 * E-Studio Core Routing
 *
 * @package       tdzEStudioPlugin
 * @author        Tecnodesign <ti@tecnodz.com>
 * @link          http://tecnodz.com/
 * @version       SVN: $Id: tdzEStudioRouting.php 508 2010-10-27 12:41:08Z capile $
 */

class tdzEStudioRouting
{
  static public function listenToRoutingLoadConfigurationEvent(sfEvent $event)
  {
    sfConfig::set('sf_enabled_modules', array_merge(sfConfig::get('sf_enabled_modules'), array('tdz_entries','tdz_contents')));
    $helpers=sfConfig::get('sf_standard_helpers');
    if(!in_array('I18N',$helpers)) $helpers[]='I18N';
    sfConfig::set('sf_standard_helpers', $helpers);
    sfConfig::set('sf_default_culture', 'en');

    $routing = $event->getSubject();
    // add plug-in routing rules on top of the existing ones

    $routing->prependRoute('tdz_js_loader', new sfRoute(
     sfConfig::get('app_e-studio_assets_url').'/js/loader.js', array(
      'module'=>'tdz_entries',
      'action'=>'ui_loader',
      'sf_format'=>'js',
    )));
    $routing->prependRoute('tdz_cms_entry', new sfRoute(
     '/e-studio/e/:action/:id', array(
      'module'=>'tdz_entries',
    )));
    $routing->prependRoute('tdz_cms_entry_list', new sfRoute(
     '/e-studio/e', array(
      'action'=>'list',
      'module'=>'tdz_entries',
    )));
    $routing->prependRoute('tdz_cms_entry_files', new sfRoute(
     '/e-studio/e/files', array(
      'action'=>'files',
      'module'=>'tdz_entries',
    )));
    $routing->prependRoute('tdz_cms_content', new sfRoute(
     '/e-studio/c/:slot/:action/:id', array(
      'module'=>'tdz_contents',
    )));
    /*
    $routing->appendRoute('tdz_preview_tecnodesign', new sfRoute(
     '/tecnodesign/*', array(
      'module'=>'tdz_entries',
      'action'=>'preview',
      'tdz/slots'=>'tecnodesign',
    )));
     */
    $routing->appendRoute('tdz_preview', new sfRoute(
     '/*', array(
      'module'=>'tdz_entries',
      'action'=>'preview',
    )));
 }
}