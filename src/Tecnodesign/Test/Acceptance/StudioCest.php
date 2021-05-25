<?php

namespace Tecnodesign\Test\Acceptance;

class StudioCest
{
    public function _before()
    {
    }

    public function docsPageWorks(\AcceptanceTester $I)
    {
        // remove cached css and see if it was properly generated
        $css = TDZ_DOCUMENT_ROOT . '/_/site.css';
        if (file_exists($css)) {
            unlink($css);
        }

        $I->amOnPage('/docs/');
        $I->see('Tecnodesign Studio');
        $I->seeElement('link[href^="/_/site.css?"]');
    }

    public function _after()
    {
    }
}
