<?php

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