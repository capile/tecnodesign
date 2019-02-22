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
        /**
         * The default parser should be the PHP-YAML
         * but there is a call to this class file that the autoloader executes and setup
         * It should be removed soon
         */
        $currentParser = Tecnodesign_Yaml::parser();
        $this->assertEquals(Tecnodesign_Yaml::PARSE_NATIVE, $currentParser);
        $this->assertEquals(Tecnodesign_Yaml::PARSE_NATIVE, Tecnodesign_Yaml::parser());

        $currentParser = Tecnodesign_Yaml::parser(Tecnodesign_Yaml::PARSE_SPYC);
        $this->assertEquals(Tecnodesign_Yaml::PARSE_SPYC, $currentParser);
        $this->assertEquals(Tecnodesign_Yaml::PARSE_SPYC, Tecnodesign_Yaml::parser());

        $currentParser = Tecnodesign_Yaml::parser(Tecnodesign_Yaml::PARSE_NATIVE);
        $this->assertEquals(Tecnodesign_Yaml::PARSE_NATIVE, $currentParser);
        $this->assertEquals(Tecnodesign_Yaml::PARSE_NATIVE, Tecnodesign_Yaml::parser());
    }

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage  Invalid parser: I do not exist
     */
    public function testParserException()
    {
        Tecnodesign_Yaml::parser('I do not exist');
    }

    public function testLoadDump()
    {
        $yamlFilePath = __DIR__ . '/../assets/sample.yml';
        $yamlFileContent = file_get_contents($yamlFilePath);

        Tecnodesign_Yaml::parser(Tecnodesign_Yaml::PARSE_NATIVE);
        $loadNativeFile = Tecnodesign_Yaml::load($yamlFilePath);
        $loadNativeContent = Tecnodesign_Yaml::load($yamlFileContent);
        $loadStringNative =  Tecnodesign_Yaml::loadString($yamlFileContent);
        $this->assertEquals($loadNativeContent, $loadNativeFile);
        $this->assertEquals($loadNativeContent, $loadStringNative);

        Tecnodesign_Yaml::parser(Tecnodesign_Yaml::PARSE_SPYC);
        $loadSpycFile = Tecnodesign_Yaml::load($yamlFilePath);
        $loadSpycContent = Tecnodesign_Yaml::load($yamlFileContent);
        $loadStringSpyc =  Tecnodesign_Yaml::loadString($yamlFileContent);
        $this->assertEquals($loadSpycContent, $loadSpycFile);
        $this->assertEquals($loadSpycContent, $loadStringSpyc);

        $this->assertEquals($loadSpycContent, $loadNativeContent);
        $yaml = $loadSpycContent;

        // A simple keys test because what matters is tha both parser gives the same answers
        $this->assertInternalType('array', $yaml);
        $this->assertArrayHasKey('all', $yaml);
        $this->assertArrayHasKey('title', $yaml['all']);
        $this->assertArrayHasKey('auth', $yaml['all']);
        $this->assertArrayHasKey('credential', $yaml['all']['auth']);
        $this->assertEquals(['first one', 'second one'], $yaml['all']['auth']['credential']);

        /**
         * Testing the dump()
         */
        Tecnodesign_Yaml::parser(Tecnodesign_Yaml::PARSE_NATIVE);
        $yamlString = Tecnodesign_Yaml::dump($yaml);
        //@todo Esse teste vai quebrar! Temos que decidir se vai continuar usando php-yaml
        $this->assertEquals($yamlFileContent, $yamlString);

        Tecnodesign_Yaml::parser(Tecnodesign_Yaml::PARSE_SPYC);
        $yamlString = Tecnodesign_Yaml::dump($yaml);
        $this->assertEquals($yamlFileContent, $yamlString);

        $this->markTestIncomplete('Test the cache!');
    }

    public function testAppend()
    {

    }

    public function testSave()
    {

    }
}
