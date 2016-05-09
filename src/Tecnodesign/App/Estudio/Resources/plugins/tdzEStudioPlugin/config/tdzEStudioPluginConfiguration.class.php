<?php
/**
 * E-Studio Configuration initializer
 *
 * PHP version 5.2
 *
 * @category  Core
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id$
 * @link      http://tecnodz.com/
 */

/**
 * E-Studio Symfony Configuration Initializer
 *
 * @category  EStudio
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */
class tdzEStudioPluginConfiguration extends sfPluginConfiguration
{
    /**
     * Initializes E-Studio
     *
     * Should look for cached information first. For additional scripts/modules to
     * be loaded, use the app_e-studio_onload config.
     *
     * @return void
     */
    public function initialize() {

        $app_root = sfConfig::get('sf_root_dir');
        require_once($app_root.'/lib/vendor/tecnodesign/tdz.php');

        $onload = sfConfig::get('app_e-studio_onload');
        if (!empty($onload)) {
            if (!is_array($onload)) {
                $onload = array($onload);
            }
            foreach ($onload as $app) {
                if (function_exists($app)) {
                    $app();
                } else {
                    eval($app);
                }
            }
        }
        $modules = sfConfig::get('sf_enabled_modules');
        if (!is_array($modules)) {
            $modules = array();
        }
        $modules[] = 'tdz_entries';
        $modules[] = 'tdz_contents';
        sfConfig::set('sf_enabled_modules', $modules);
        if(tdz::get('disable-routing')) {
            return false;
        }
        $this->dispatcher->connect(
            'routing.load_configuration',
            array('tdzEStudioRouting', 'listenToRoutingLoadConfigurationEvent')
        );
    }

}
