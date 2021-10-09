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

use Studio\OAuth2\Server;
use Tecnodesign_Cache as Cache;
use tdz as S;

class OAuth2Cest
{
    protected $configFiles = [], $configs=['oauth2', 'studio'], $uri='http://127.0.0.1:9999/examples/oauth2', $metadata, $accessToken, $expiredAccessToken;
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

        Cache::delete('oauth2/metadata/'.$this->uri);
        $this->metadata = Server::metadata(true, $this->uri);
    }

    public function metadata(\ApiTester $I)
    {
        $I->sendGet($this->uri.'/.well-known/openid-configuration');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson($this->metadata);
    }

    // curl -is http://127.0.0.1:9999/examples/oauth2/access_token -u test-client:test-secret -d 'grant_type=client_credentials'
    public function tokenEndpoint(\ApiTester $I)
    {
        $url = $this->metadata['token_endpoint'];

        // client_secret_basic
        $I->haveHttpHeader('authorization', 'Basic '.base64_encode('test-client:test-secret'));
        $I->sendPost($url, ['grant_type'=>'client_credentials']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('"access_token":');
        list($this->accessToken) = $I->grabDataFromResponseByJsonPath('$.access_token');

        $I->deleteHeader('authorization');

        // client_secret_post
        $I->sendPost($url, ['grant_type'=>'client_credentials', 'client_id'=>'test-client', 'client_secret'=>'test-secret']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('"access_token":');
        list($accessToken) = $I->grabDataFromResponseByJsonPath('$.access_token');
        $I->dontSeeResponseContainsJson(['access_token'=>$this->accessToken]);

        $this->expiredAccessToken = $this->accessToken;
        $this->accessToken = $accessToken;

        // client_secret_post with JSON payload
        $I->haveHttpHeader('content-type', 'application/json');
        $I->sendPost($url, ['grant_type'=>'client_credentials', 'client_id'=>'test-client', 'client_secret'=>'test-secret']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('"access_token":');
        list($accessToken) = $I->grabDataFromResponseByJsonPath('$.access_token');
        $I->dontSeeResponseContainsJson(['username'=>$this->accessToken]);

        $this->accessToken = $accessToken;
        $I->deleteHeader('content-type');

        // @todo jwt_bearer
    }

    // test if it's not authenticated first
    public function userInfo(\ApiTester $I)
    {
        $I->sendPost($this->metadata['token_endpoint'], ['grant_type'=>'client_credentials', 'client_id'=>'test-client', 'client_secret'=>'test-secret']);
        list($this->accessToken) = $I->grabDataFromResponseByJsonPath('$.access_token');

        $url = $this->metadata['userinfo_endpoint'];
        // fetch userinfo
        $I->haveHttpHeader('authorization', 'Bearer '.$this->accessToken);
        $I->sendGet($url);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['username'=>'test-user']);

        $I->haveHttpHeader('authorization', 'Bearer '.$this->accessToken);
        $I->sendGet('/_me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['username'=>'test-user']);


        // this is not valid by default
        $I->sendPost($url, ['access_token'=>$this->accessToken]);
        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error'=>'invalid_request']);

        $I->sendPost($url, ['access_token'=>$this->expiredAccessToken]);
        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error'=>'invalid_request']);

        $I->haveHttpHeader('authorization', 'Bearer '.$this->expiredAccessToken);
        $I->sendGet('/_me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->dontSeeResponseContainsJson(['username'=>'test-user']);
        $I->seeResponseContains('[]');

        // post is not supported on resources
        $I->sendPost('/_me', ['access_token'=>$this->accessToken]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('[]');
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
