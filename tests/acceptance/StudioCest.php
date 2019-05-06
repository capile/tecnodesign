<?php

namespace TecnodesignAcceptanceTest;

class StudioCest
{
    public function docsPageWorks(AcceptanceTester $I)
    {
        // remove cached css and see if it was properly generated
        $css = TDZ_DOCUMENT_ROOT . '/_/docs.css';
        if (file_exists($css)) {
            unlink($css);
        }

        $I->amOnPage('/docs/');
        $I->see('Tecnodesign Application Development Framework');
        $I->seeElement('link[href^="/_/docs.css?"]');
    }
}
