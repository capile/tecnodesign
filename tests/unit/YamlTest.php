<?php

namespace TecnodesignUnitTest;

use Tecnodesign_Yaml;

class YamlTest extends \PHPUnit\Framework\TestCase
{

    public function setUp()
    {
        // Desabilita o auto install pois considero que está tudo no composer
        Tecnodesign_Yaml::setAutoInstall(false);
        Tecnodesign_Yaml::$cache = false;
    }

    public function testParser()
    {
        /**
         * The default parser should be the PHP-YAML
         * but there is a call to this class file that the autoloader executes and setup
         * It should be removed soon
         */
        foreach([Tecnodesign_Yaml::PARSE_NATIVE, Tecnodesign_Yaml::PARSE_SPYC] as $parser) {
            if($currentParser = Tecnodesign_Yaml::parser($parser)) {
                $this->assertEquals($parser, $currentParser);
                $this->assertEquals($parser, Tecnodesign_Yaml::parser());
            }
        }
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

        foreach([Tecnodesign_Yaml::PARSE_NATIVE, Tecnodesign_Yaml::PARSE_SPYC] as $parser) {
            if(Tecnodesign_Yaml::parser($parser)) {
                $loadFile = Tecnodesign_Yaml::load($yamlFilePath);
                $loadContent = Tecnodesign_Yaml::load($yamlFileContent);
                $loadString = Tecnodesign_Yaml::loadString($yamlFileContent);
                $this->assertEquals($loadContent, $loadFile);
                $this->assertEquals($loadContent, $loadString);

                $yaml = $loadContent;

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
                $yamlString = Tecnodesign_Yaml::dump($yaml);
                $this->assertNotEquals($yamlFileContent, $yamlString,
                    "$parser dumps different than original");
                $yamlFileContentAlternative = str_replace('  -', '-', $yamlFileContent) . "...\n";
                $this->assertEquals($yamlFileContentAlternative, $yamlString);
                $this->assertEquals($yaml, Tecnodesign_Yaml::loadString($yamlFileContentAlternative),
                    "$parser loads the 'alternative format' just like the original");
            }
        }
    }

    /*
     redo this
    public function testAppend()
    {
        $resetFile = function () {
            copy(__DIR__ . '/../assets/sample-translate.yml', __DIR__ . '/../assets/sample-temp.yml');
        };
        $resetFile();
        $yamlFilePath = __DIR__ . '/../assets/sample-temp.yml';
        $yamlFileOriginalContent = file_get_contents($yamlFilePath);

        Tecnodesign_Yaml::parser(Tecnodesign_Yaml::PARSE_NATIVE);
        $yaml = Tecnodesign_Yaml::load($yamlFilePath);
        $append = [
            'nome' => 'Nombre',
            'aniversário' => 'Birthday'
        ];
        $yamlAppended = array_replace_recursive($yaml, ['all' => $append]);
        Tecnodesign_Yaml::append($yamlFilePath, $append);
        sleep(1);
        $yamlFileNewContent = file_get_contents($yamlFilePath);
        $yamlNew = Tecnodesign_Yaml::load($yamlFilePath);
        $this->assertNotEquals($yamlFileOriginalContent, $yamlFileNewContent);
        $this->assertEquals($yamlNew, $yamlAppended);
        $this->assertArraySubset($append, $yamlNew['all']);

        $resetFile();
        $append = ['all' => $append];
        Tecnodesign_Yaml::append($yamlFilePath, $append);
        sleep(1);
        $yamlFileNewContent = file_get_contents($yamlFilePath);
        $yamlNew = Tecnodesign_Yaml::load($yamlFilePath);
        $this->assertNotEquals($yamlFileOriginalContent, $yamlFileNewContent);
        $this->assertEquals($yamlNew, $yamlAppended);
        $this->assertArraySubset($append, $yamlNew);
    }
    */

    /**
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage  $append must be an array
     */
    public function testAppendException()
    {
        Tecnodesign_Yaml::append('whatever', 'I should be an array');
    }

    /*
     redo this
    public function testSave()
    {
        $loadSampleFile = function () {
            copy(__DIR__ . '/../assets/sample.yml', __DIR__ . '/../assets/sample-temp.yml');
        };
        $loadTranslateFile = function () {
            copy(__DIR__ . '/../assets/sample-translate.yml', __DIR__ . '/../assets/sample-temp.yml');
        };
        $loadSampleFile();
        $yamlFilePath = __DIR__ . '/../assets/sample-temp.yml';
        Tecnodesign_Yaml::$cache = true;
        $yaml = Tecnodesign_Yaml::load($yamlFilePath,0);
        // change the contents
        $loadTranslateFile();
        $yamlCached = Tecnodesign_Yaml::load($yamlFilePath,0);
        $this->assertEquals($yaml, $yamlCached);
        $this->markTestIncomplete('Needs to define better how cache works');
        $yamlCached = Tecnodesign_Yaml::load($yamlFilePath,100);
        $this->assertNotEquals($yaml, $yamlCached);
    }
    */

    public function testAutoInstall()
    {
        // Default should be true, but we set as false at SetupUp()
        $this->assertFalse(Tecnodesign_Yaml::isAutoInstall());
        Tecnodesign_Yaml::setAutoInstall(true);
        $this->assertTrue(Tecnodesign_Yaml::isAutoInstall());
        Tecnodesign_Yaml::setAutoInstall(false);
        $this->assertFalse(Tecnodesign_Yaml::isAutoInstall());
    }
}
