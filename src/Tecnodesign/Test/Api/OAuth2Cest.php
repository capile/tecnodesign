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
    public function notAuthenticated(\ApiTester $I)
    {
        $I->sendPOST('/_me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('[]');
    }

    public function _after()
    {
        if(file_exists($f=TDZ_ROOT . '/data/config/oauth2.yml')) {
            unlink($f);
        }
        exec(TDZ_ROOT.'/app data-import "'.TDZ_ROOT.'/data/tests/_data/oauth2-after.yml"');
    }
}
