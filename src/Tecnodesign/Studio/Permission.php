<?php
/**
 * Resources (pages/contents) required credentials
 * 
 * PHP version 5.6+
 * 
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.3
 */
class Tecnodesign_Studio_Permission extends Tecnodesign_Studio_Model
{
    public static $schema;
    protected $id, $entry, $role, $credentials, $version, $created, $updated, $expired, $Permission;


    public function choicesCredentials($check=null)
    {
        static $c;
        if(is_null($c)) $c = tdz::getApp()->user['credentials'];
        return $c;
    }

    /*
    public function validateCredentials($v)
    {
        return $v;
    }
    */

    public function choicesRole()
    {
        static $roles;
        if(is_null($roles)) {
            $roles = [
                'edit'=>tdz::t('Edit', 'model-tdz_permission'),
                'previewPublished'=>tdz::t('Preview', 'model-tdz_permission'),
                'publish'=>tdz::t('Publish', 'model-tdz_permission'),
            ];
        }

        return $roles;
    }
}
