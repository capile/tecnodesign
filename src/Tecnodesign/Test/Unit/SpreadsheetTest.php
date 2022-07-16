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
namespace Tecnodesign\Test\Unit;

class SpreadsheetTest extends \PHPUnit\Framework\TestCase
{
    public function testLetterToNumber()
    {
        $this->assertEquals(\tdz::numberToLetter(0), 'a');
        $this->assertEquals(\tdz::numberToLetter(26), 'aa');
        $this->assertEquals(\tdz::numberToLetter(26*2), 'ba');
        $this->assertEquals(\tdz::numberToLetter(728), 'aba');
        $this->assertEquals(\tdz::numberToLetter(100), 'cw');
        $this->assertEquals(\tdz::numberToLetter(702), 'aaa');
        $this->assertEquals(\tdz::numberToLetter(36388720), 'capile');
    }

    public function testNumberToLetter()
    {
        $this->assertEquals(\tdz::lettertoNumber('a'), 0);
        $this->assertEquals(\tdz::lettertoNumber('aa'), 26);
        $this->assertEquals(\tdz::lettertoNumber('aba'), 728);
        $this->assertEquals(\tdz::lettertoNumber('cw'), 100);
        $this->assertEquals(\tdz::lettertoNumber('capile'), 36388720);
    }

    public function testTimedNumberConversion()
    {
        $i = 10;
        while($i--) {
            $n = rand(0, time());
            $this->assertEquals(\tdz::lettertoNumber(\tdz::numberToLetter($n)), $n);
        }
    }

}
