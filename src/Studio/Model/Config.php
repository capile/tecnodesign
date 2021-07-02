<?php
/**
 * Configuration files updater
 *
 * @package     capile/tecnodesign
 * @author      Tecnodesign <ti@tecnodz.com>
 * @license     GNU General Public License v3.0
 * @link        https://tecnodz.com
 * @version     2.5
 */

namespace Studio\Model;

use Studio\Model;
use Studio\User;
use Studio\Crypto;
use tdz as S;


class Config extends Model
{
    public static $schema;

    protected $studio, $tecnodesign, $user;

    public function choicesStudioVersion()
    {
        return ["2.5"=>"2.5"];
    }

    public function choicesLanguage()
    {
        return ["en"=>"English", "pt"=>"Português"];
    }

    public function renderTitle()
    {
        return $this->__uid;
    }

    public function validateAdminPassword($s)
    {
        if($s) {
            return Crypto::hash($s, null, User::$hashType);
        }
    }

    public function reloadConfiguration()
    {
        // reload config
        @touch(S_ROOT.'/app.yml');

        $cmd = S_ROOT.'/studio';

        // check database tables
        S::exec(['shell'=>$cmd.' :check']);

        // import admin password (if set)
        if(isset($this->_admin_password)) {
            $import = [
                'Studio\Model\Users!'=>[[
                    '__key' => [ 'username' ],
                    '__set' => [ 'USERID' => 'id' ],
                    'username' => 'admin',
                    'password' => $this->_admin_password,
                    'name' => 'Administrator',
                ]],
                'Studio\Model\Groups!' => [[
                    '__key' => [ 'name' ],
                    '__set' => [ 'GROUPID' => 'id' ],
                    'name' => 'Administrators',
                    'priority' => 1,
                ]],
                'Studio\Model\Credentials!' => [[
                    '__key' => [ 'userid', 'groupid' ],
                    'userid' => '$USERID',
                    'groupid' => '$GROUPID',
                ]],
            ];
            S::exec(['shell'=>$cmd.' :import '.escapeshellarg(S::serialize($import, 'json'))]);
        }
        return true;
    }
}