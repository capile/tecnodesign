<?php

namespace Tecnodesign\Test\Api;

class OAuth2Cest
{
    public function _before()
    {
        if(!file_exists($f=TDZ_ROOT . '/data/config/oauth2.yml')) {
            copy(TDZ_ROOT . '/data/config/oauth2.yml-example', $f);
            touch(TDZ_ROOT.'/app.yml');
        }

        exec(TDZ_ROOT.'/app data-import "'.TDZ_ROOT.'/data/tests/_data/oauth2-before.yml"');
    }
    // test if it's not authenticated first
    public function accessToken(\ApiTester $I)
    {   // curl -is http://127.0.0.1:9999/examples/oauth2/access_token \
        //   -u test-client:test-secret -d 'grant_type=client_credentials'

        $I->haveHttpHeader('authorization', 'Basic '.base64_encode('test-client:test-secret'));
        $I->sendPost('/examples/oauth2/access_token', ['grant_type'=>'client_credentials']);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('"access_token":');
        list($accessToken) = $I->grabDataFromResponseByJsonPath('$.access_token');

        // curl -is http://127.0.0.1:9999/examples/oauth2/auth -d access_token=95244ab62feed0464dc86b3901f146d8b29267e9
        $I->deleteHeader('authorization');
        $I->sendPost('/examples/oauth2/auth', ['access_token'=>$accessToken]);
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success'=>true]);

        // fail authentication
        $I->sendPost('/examples/oauth2/auth', ['access_token'=>'blablabla']);
        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error'=>'invalid_token']);

        $I->haveHttpHeader('authorization', 'Bearer '.$accessToken);
        $I->sendGet('/examples/oauth2/auth');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['success'=>true]);

        $I->haveHttpHeader('authorization', 'Bearer blablabla');
        $I->sendGet('/examples/oauth2/auth');
        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['error'=>'invalid_token']);
    }

    public function _after()
    {
        if(file_exists($f=TDZ_ROOT . '/data/config/oauth2.yml')) {
            unlink($f);
        }
        exec(TDZ_ROOT.'/app data-import "'.TDZ_ROOT.'/data/tests/_data/oauth2-after.yml"');
    }
}
