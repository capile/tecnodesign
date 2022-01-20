<?php
/**
 * Configuration files updater
 *
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */

namespace Studio\Model;

use Studio\App as App;
use Studio\Model;
use Studio\User;
use Studio\Crypto;
use Tecnodesign_Yaml as Yaml;
use Tecnodesign_Exception as Exception;
use Studio as S;

class Config extends Model
{
    public static $schema, $webRepoClient=['ssh'=>'*SSH using public keys', 'http'=>'*HTTP using token'];

    protected $app, $studio, $user;

    public function choicesStudioVersion()
    {
        return ["2.5"=>"2.5","2.6"=>"2.6"];
    }

    public function choicesLanguage()
    {
        return ["en"=>"English", "pt"=>"Português"];
    }

    public function choiceswebRepoClient()
    {
        static $options;
        if(is_null($options)) {
            $options=[];
            foreach(self::$webRepoClient as $n=>$v) {
                $options[$n] = S::t($v, 'interface');
            }
        }

        return $options;
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
        if(isset($this->_admin_password) && $this->_admin_password) {
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

    public static function executePreview($Interface, $args=[])
    {
        $Interface->getButtons();
        $r = $Interface['text'];

        $s = S::markdown($r['text']);
        $s .= '<p>Version '.S::VERSION.'</p>';

        $r['preview'] = $s;

        $Interface['text'] = $r;
    }

    public function validateStudioWebRepos($v)
    {
        if($v && is_array($v)) {
            foreach($v as $i=>$o) {
                if(!$this->syncRepo($v[$i])) {
                    $n = (isset($o['id'])) ?$o['id'] :$i+1;
                    throw new Exception(sprintf(S::t('The repository %s could not be synchronized.', 'exception'), $n));
                }
            }
        }

        return $v;
    }

    public static function syncRepo(&$repo, $push=null)
    {
        if(!isset($repo['id']) || !$repo['id'] || !isset($repo['src']) || !$repo['src']) return false;

        $rr = S::config('repo-dir');
        if(!$rr) $rr = S_VAR.'/web-repos';

        if(!isset($repo['client']) || !$repo['client']) $repo['client'] = null;
        if(!isset($repo['secret']) || !$repo['secret']) $repo['secret'] = null;

        $o = [];
        if($repo['client']) {
            $o[] = '-c '.escapeshellarg('credential.'.$repo['src'].'.username='.$repo['client']);
        }
        if($repo['secret']) {
            $o[] = '-c '.escapeshellarg('credential.'.$repo['src'].'.password='.$repo['secret']);
        }

        $d = $rr.'/'.$repo['id'];
        $clone = null;
        if(!is_dir($d)) {
            if(!mkdir($d, 0777, true)) return false;
            $clone = true;
        } else if(S::isEmptyDir($d)) {
            $clone = true;
        } else if(!file_exists($d.'/.git')) {
            // not a git repo
            return false;
        }

        if($clone && isset($repo['mount-src']) && $repo['mount-src'] && strpos($repo['mount-src'], ':')) {
            $o[] = '--branch '.escapeshellarg(substr($repo['mount-src'], 0, strpos($repo['mount-src'], ':')));
        }

        if($clone) $a = 'git -C '.escapeshellarg($d).' clone '.implode(' ', $o).' '.escapeshellarg($repo['src']).' .';
        else if($push) $a = 'git -C '.escapeshellarg($d).' push '.implode(' ', $o);
        else $a = 'git -C '.escapeshellarg($d).' pull '.implode(' ', $o);

        if(!S::exec(['shell'=>$a])) return false;

        return true;
    }
}