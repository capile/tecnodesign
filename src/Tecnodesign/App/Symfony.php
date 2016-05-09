<?php
/**
 * Tecnodesign Symfony loader
 *
 * This package enables Tecnodesign applications to run Symfony modules and 
 * components.
 *
 * PHP version 5.2
 *
 * @category  App
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id: Symfony.php 835 2011-07-18 19:49:35Z capile $
 * @link      http://tecnodz.com/
 */

/**
 * Tecnodesign Symfony loader
 *
 * This package enables Tecnodesign applications to run Symfony modules and 
 * components.
 *
 * @category  App
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */
class Tecnodesign_App_Symfony
{
    protected static $_staticInstance = null;
    protected $_instance = null;
    protected $_configuration = null;
    protected $_symfonyApp = null;
    protected $_env = null;
    protected $_sfDir = null;
    
    public function __construct($config, $env='prod')
    {
        $this->_env = $env;
        if(isset($config['app'])) {
            $this->_symfonyApp = $config['app'];
        }
        if (strpos(TDZ_ROOT, '/lib/vendor/')!==false) {
            $this->_sfDir = substr(TDZ_ROOT, 0, strpos(TDZ_ROOT, '/lib/vendor/'));
        } else {
            $this->_sfDir = substr(TDZ_ROOT, 0, strpos(TDZ_ROOT, '/lib/'));
        }
    }
    
    public static function getInstance($app='symfony', $env='prod')
    {
        $app = Tecnodesign_App::getInstance();
        $sf = false;
        if (!is_null(Tecnodesign_App_Symfony::$_staticInstance)) {
            $sf = Tecnodesign_App_Symfony::$_staticInstance;
        } else if (isset($app->addons['symfony'])) {
            $sf = $app->addons['symfony'];
        } else {
            $sf = new Tecnodesign_App_Symfony(array('app'=>$app), $env);
        }
        if ($sf && !class_exists('sfContext')) {
            $sf->initialize();
        }
        Tecnodesign_App_Symfony::$_staticInstance=$sf;
        return $sf;
    }
    
    public function initialize()
    {
        if(is_null($this->_configuration)) {
            if(!class_exists('ProjectConfiguration')) require_once($this->_sfDir.'/config/ProjectConfiguration.class.php');
            $this->_configuration = ProjectConfiguration::getApplicationConfiguration($this->_symfonyApp, $this->_env, false);
        }
        if(is_null($this->_instance)) {
            //require_once $this->_sfDir.'/lib/vendor/symfony/lib/autoload/sfCoreAutoload.class.php';
            //sfCoreAutoload::register();
            $this->_instance = sfContext::createInstance($this->_configuration,$this->_symfonyApp);
        }
    }

    public function run()
    {
        $this->initialize();
        $controller = $this->_instance->getController();
        $controller->dispatch();
    }
}