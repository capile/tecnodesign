<?php
/**
 * PHP version 7.3+
 *
 * @package   capile/tecnodesign
 * @author    Tecnodesign <ti@tecnodz.com>
 * @license   GNU General Public License v3.0
 * @link      https://tecnodz.com
 * @version   3.0
 */
namespace Tecnodesign\Test\Acceptance;

class StudioCest
{
    public function _before()
    {
    }

    public function homePageWorks(\AcceptanceTester $I)
    {
        // remove cached css and see if it was properly generated
        $css = TDZ_DOCUMENT_ROOT . '/_/site.css';
        if (file_exists($css)) {
            unlink($css);
        }

        $I->amOnPage('/');
        $I->see('Welcome to Studio!');
        $I->seeElement('link[href^="/_/site.css?"]');
    }

    public function _after()
    {
    }
}
