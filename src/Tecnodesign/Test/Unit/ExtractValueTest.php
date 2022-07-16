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
class ExtractValueTest extends \Codeception\Test\Unit
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
    public function testDataExtraction()
    {
        $a = [
            'test' => 1234,
            'another-test' => 3456,
            [ 'subtest' => 5678 ],
            'a' => ['b'=> ['c'=>['d'=>9876]]],
        ];
        $this->assertEquals(\tdz::extractValue($a, 'test'), 1234);
        $this->assertEquals(\tdz::extractValue($a, '$.test|another-test'), 1234);
        $this->assertEquals(\tdz::extractValue($a, 'teste|another-test'), 3456);
        $this->assertEquals(\tdz::extractValue($a, 'teste.*'), null);
        $this->assertEquals(\tdz::extractValue($a, '0.subtest'), 5678);
        $this->assertEquals(\tdz::extractValue($a, '*.subtest'), [5678]);
        $this->assertEquals(\tdz::extractValue($a, 'a.b.c.d'), 9876);
        $this->assertEquals(\tdz::extractValue($a, 'a.*.*.d'), [9876]);
        $this->assertEquals(\tdz::extractValue($a, '*.*.*.*'), ['d'=>9876]);
        $this->assertEquals(\tdz::extractValue($a, '*.*'), ['subtest'=>5678, 'b'=>$a['a']['b']]);
        $this->assertEquals(\tdz::extractValue($a, '*.nonexisting'), null);
    }
}