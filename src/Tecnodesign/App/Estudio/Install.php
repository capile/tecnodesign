<?php
/**
 * Tecnodesign e-Studio Installer
 *
 * This package implements a installer for the e-Studio CMS
 *
 * PHP version 5.3
 *
 * @category  App
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @version   SVN: $Id$
 * @link      http://tecnodz.com/
 */

/**
 * Tecnodesign e-Studio Installer
 *
 * This package implements a installer for the e-Studio CMS
 *
 * @category  App
 * @package   Tecnodesign
 * @author    Guilherme Capilé, Tecnodesign <ti@tecnodz.com>
 * @copyright 2011 Tecnodesign
 * @license   http://creativecommons.org/licenses/by/3.0  CC BY 3.0
 * @link      http://tecnodz.com/
 */
class Tecnodesign_App_Studio_Install
{
    protected $errors=array();
    protected $classes=array('Entry','Content', 'Tag', 'Permission', 'Relation');
    
    public function __construct()
    {
    }

    public function install()
    {
        $data = dirname(__FILE__).'/Resources/sql/e-studio-install.yml';
        Tecnodesign_Database::import($data);
        tdz::debug(tdzEntry::find()->asArray());
    }
    
    public function updateSchema()
    {
        $app = tdz::getApp();
        $req = $app->request();
        $meta = array(
            'category'=>'Model',
            'package'=>'Studio',
            'author'=>'Guilherme Capilé, Tecnodesign <ti@tecnodz.com>',
            'copyright'=>date('Y').' Tecnodesign',
            'link'=>'http://tecnodz.com/',
        );
        $cns=array();
        foreach($this->classes as $cn) {
            $cns[]='Tecnodesign_App_Studio_'.$cn;
        }
        Tecnodesign_Database::syncronizeModels($cns, TDZ_ROOT.'/src', $meta);
        echo("ok!\n");
        return 'cli';
    }
}