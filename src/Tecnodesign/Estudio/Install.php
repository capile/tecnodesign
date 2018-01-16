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
 * @link      http://tecnodz.com/
 */
class Tecnodesign_Estudio_Install
{
    protected $errors=array();
    protected $classes=array('Entry','Content', 'Tag', 'Permission', 'Relation', 'User', 'Group', 'Credential', '');
    
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
            'package'=>'Estudio',
            'author'=>'Guilherme Capilé, Tecnodesign <ti@tecnodz.com>',
            'copyright'=>date('Y').' Tecnodesign',
            'link'=>'https://tecnodz.com/',
        );
        $cns=array();
        foreach($this->classes as $cn) {
            $cns[]='Tecnodesign_Estudio_'.$cn;
        }
        Tecnodesign_Database::syncronizeModels($cns, TDZ_ROOT.'/src', $meta);
        echo("ok!\n");
        return 'cli';
    }

    public static function upgrade($version=1.0)
    {
        // check and run SQL upgrades
        foreach(glob(dirname(__FILE__).'/Resources/install/e-studio-*.php') as $p) {
            if(preg_match('#/e-studio-([0-9]+\.[0-9]+)-[0-9]+\.php$#', $p, $m) && (float)$m[1]>$version) {
                tdz::exec(array('script'=>$p));
            }
        }
    } 

}