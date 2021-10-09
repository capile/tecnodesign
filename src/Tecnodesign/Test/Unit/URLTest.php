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
class URLTest extends \Codeception\Test\Unit
{
    /**
     * @var \UnitTester
     */
    protected $tester;
    
    protected function _before()
    {
    }

    protected function _after()
    {
    }

    // tests
    public function testValidUrl()
    {
        $this->assertEquals(\tdz::slug('áéíóúãẽĩõũñàèìòùïü'), 'aeiouaeiounaeiouiu');
        $D = \Tecnodesign_Yaml::load(TDZ_ROOT.'/data/tests/_data/valid-url.yml');
        foreach($D as $source => $valid) {
            $this->assertEquals(\tdz::validUrl($source), $valid);
        }
    }
}