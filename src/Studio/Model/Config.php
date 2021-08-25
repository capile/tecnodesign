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
use Tecnodesign_Yaml as Yaml;
use tdz as S;


class Config extends Model
{
    public static $schema;

    protected $app, $studio, $user;

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

    public function checkConfiguration()
    {
        if(is_null($this->app)) $this->app = [];
        else if(isset($this->app['api-dir'])) unset($this->app['api-dir']);

        if(isset($this->app['languages']) && $this->app['languages']) {
            if(count($this->app['languages'])==1) {
                $this->app['language'] = array_shift($this->app['languages']);
                unset($this->app['languages']);
            } else {
                $langs = $this->choicesLanguage();
                $l = [];
                foreach($this->app['languages'] as $lang) {
                    if(isset($langs[$lang])) $l[$langs[$lang]] = $lang;
                }
                $this->app['languages'] = $l;
            }
        }

        $this->user = [];
        $cfgs = [];
        if(isset($this->studio['enable_interface_credential']) && $this->studio['enable_interface_credential']) {
            $cfgs = Yaml::load(S_ROOT.'/data/config/config-credential.yml-example');
        }
        if(isset($this->studio['enable_interface_index']) && $this->studio['enable_interface_index']) {
            $n = Yaml::load(S_ROOT.'/data/config/config-index.yml-example');
            if($cfgs) $cfgs = S::mergeRecursive($n, $cfgs);
            else $cfgs = $n;
            unset($n);
        }

        if($cfgs) {
            foreach($cfgs['all'] as $k=>$v) {
                if($k!='studio') {
                    if($this->$k) {
                        $this->$k += $v;
                    } else {
                        $this->$k = $v;
                    }
                }
            }
        }

        return true;
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

    public static function standaloneConfig()
    {
        if(S_ROOT!=S_APP_ROOT) S::debug('[ERROR] '.S::t('This action is only available on standalone installations.', 'exception'));

        // load data/config/config.yml-example, reload configuration, remove the file and forward user to http://127.0.0.1:9999/_studio
        if(!file_exists($c=S_ROOT.'/data/config/config.yml')) copy($c.'-example', $c);

        // (re)load server
        S::exec(['shell'=>S_ROOT.'/studio-server']);

        $C = new Config();
        $C->reloadConfiguration();

        $os = strtolower(substr(PHP_OS, 0, 3));
        if($os==='win') {
            $cmd = 'explorer';
        } else if($os==='dar') {
            $cmd = 'open';
        } else {
            $cmd = 'xdg-open';
        }

        S::exec(['shell'=>$cmd.' '.escapeshellarg('http://127.0.0.1:9999/_studio')]);
    }
}