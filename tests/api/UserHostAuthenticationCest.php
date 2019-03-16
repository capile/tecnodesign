<?php 

class UserHostAuthenticationCest
{
    // test if it's not authenticated first
    public function notAuthenticated(ApiTester $I)
    {
        // change cache key to force a new app config
        file_put_contents(TDZ_ROOT.'/.appkey', 'app-noauth');
        touch(TDZ_ROOT.'/app.yml');

        $I->sendGET('/_me');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('[]');
    }

    // test if it's authenticated now -- might need a cache reset
    public function hostAuthenticated(ApiTester $I)
    {
        copy(TDZ_ROOT.'/data/config/user-host-admin.yml-example', TDZ_ROOT.'/data/config/user-host-admin.yml');
        file_put_contents(TDZ_ROOT.'/.appkey', 'app-host-auth');
        touch(TDZ_ROOT.'/app.yml');

        $I->sendGET('/_me?new');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContains('{"username":"admin","credentials":["Administrator"]}');

        unlink(TDZ_ROOT.'/data/config/user-host-admin.yml');
        unlink(TDZ_ROOT.'/.appkey');
        touch(TDZ_ROOT.'/app.yml');
    }
}
