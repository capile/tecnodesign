<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   2.6
 */
namespace Tecnodesign\Test\Api;

use Tecnodesign_Cache as Cache;
use tdz as S;

class UserAuthenticationCest
{
    protected $configFiles = [], $configs=['user', 'studio'], $uri='http://127.0.0.1:9999', $cookie;
    public function _before()
    {
        foreach($this->configs as $fn) {
            if(!file_exists($f=TDZ_ROOT . '/data/config/'.$fn.'.yml') && copy($f.'-example', $f)) {
                $this->configFiles[] = $f;
            }
        }
        if($this->configFiles) {
            touch(TDZ_ROOT.'/app.yml');
        }
        foreach($this->configs as $fn) {
            if(file_exists($f=TDZ_ROOT.'/data/tests/_data/'.$fn.'-before.yml')) {
                exec(TDZ_ROOT.'/studio :import "'.$f.'"');
            }
        }
    }

    // test if it's not authenticated first
    public function notAuthenticated(\ApiTester $I)
    {
        $I->sendGet($this->uri.'/_me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('[]');
    }

    // test if it's authenticated now -- might need a cache reset
    public function userAuthenticated(\ApiTester $I)
    {
        $I->haveHttpHeader('referer', $this->uri.'/_me');
        $I->sendGet($this->uri.'/signin?ref=1');
        $res = $I->grabResponse();
        $d = ['user'=>'test-user', 'pass'=>'test-password'];
        if(preg_match_all('#<input[^>]* name="([^"]+)"[^>]* value="([^"]*)"[^>]+>#', $res, $m)) {
            $post = [];
            foreach($m[1] as $i=>$n) {
                $post[$n] = (!$m[2][$i]) ?array_shift($d) :$m[2][$i];
            }
        } else {
            $post = $d;
        }
        $cs = $I->grabHttpHeader('set-cookie');
        if($cs) {
            if(!is_array($cs)) {
                $this->cookie = preg_replace('/\;.*/', '', $cs);
            } else {
                foreach($cs as $c) {
                    $this->cookie .= ($this->cookie) ?'; ' :'';
                    $this->cookie .= preg_replace('/\;.*/', '', $c);
                }
            }
        }
        $I->haveHttpHeader('cookie', $this->cookie);
        $I->sendPost($this->uri.'/signin', $post);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('test-user');
    }

    public function _after()
    {
        foreach($this->configs as $fn) {
            if(file_exists($f=TDZ_ROOT.'/data/tests/_data/'.$fn.'-after.yml')) {
                exec(TDZ_ROOT.'/studio :import "'.$f.'"');
            }
        }

        if($this->configFiles) {
            foreach($this->configFiles as $i=>$f) {
                unlink($f);
                unset($this->configFiles[$i], $i, $f);
            }
            touch(TDZ_ROOT.'/app.yml');
        }
    }
}
