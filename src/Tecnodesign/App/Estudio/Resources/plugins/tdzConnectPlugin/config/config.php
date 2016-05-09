<?php
/**
 * Tecnodesign Connect Core Controller
 *
 * @package      tdzEStudioPlugin
 * @author       Tecnodesign <ti@tecnodz.com>
 * @link         http://tecnodz.com/
 * @copyright    Tecnodesign (c) 2010
 * @version      SVN: $Id: config.php 505 2010-10-24 15:13:38Z capile $
 */
if(!tdz::get('disable-routing') && sfConfig::get('app_connect_register_routes') ) {
  $this->dispatcher->connect('routing.load_configuration', array('tdzConnectRouting', 'listenToRoutingLoadConfigurationEvent'));
}
