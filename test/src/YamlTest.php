<?php
namespace TecnodesignTest;

use Tecnodesign_Yaml;

class YamlTest extends \PHPUnit\Framework\TestCase
{

    public function setUp()
    {
        // Desabilita o auto install pois considero que está tudo no composer
        Tecnodesign_Yaml::setAutoInstall(false);
    }

    public function testParser()
    {
        Tecnodesign_Yaml::parser();
        $this->assertEquals(Tecnodesign_Yaml::PARSE_NATIVE, Tecnodesign_Yaml::$parser);
        Tecnodesign_Yaml::parser(Tecnodesign_Yaml::PARSE_NATIVE);
        $this->assertEquals(Tecnodesign_Yaml::PARSE_NATIVE, Tecnodesign_Yaml::$parser);
        Tecnodesign_Yaml::parser(Tecnodesign_Yaml::PARSE_SPYC);
        $this->assertEquals(Tecnodesign_Yaml::PARSE_SPYC, Tecnodesign_Yaml::$parser);
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage  Invalid parser: I do not exist
     */
    public function testParserException()
    {
        Tecnodesign_Yaml::parser('I do not exist');
    }


    public function testLoad()
    {

    }

    public function testAppend()
    {

    }

    public function testLoadString()
    {
        $yaml = Tecnodesign_Yaml::loadString('teste');
    }

    public function testDump()
    {

    }

    public function testSave()
    {

    }
}
