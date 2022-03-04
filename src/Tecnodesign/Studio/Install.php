<?php
/**
 * Tecnodesign e-Studio Installer
 * 
 * This package implements a installer for the e-Studio CMS
 * 
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.7
 */
class Tecnodesign_Studio_Install
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
            'link'=>'https://tecnodz.com/',
        );
        $cns=array();
        foreach($this->classes as $cn) {
            $cns[]='Tecnodesign_Studio_'.$cn;
        }
        Tecnodesign_Database::syncronizeModels($cns, TDZ_ROOT.'/src', $meta);
        echo("ok!\n");
        return 'cli';
    }

    public static function upgrade($version=1.0)
    {
        // check and run SQL upgrades
        foreach(glob(TDZ_ROOT.'/src/Tecnodesign/Resources/studio/studio-*.php') as $p) {
            if(preg_match('#studio-([0-9]+\.[0-9]+)-[0-9]+\.php$#', $p, $m) && (float)$m[1]>$version) {
                tdz::exec(array('script'=>$p));
            }
        }
    } 

}