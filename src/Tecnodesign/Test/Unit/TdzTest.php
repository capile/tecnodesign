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

use PHPUnit\Framework\TestCase;
use tdz;

class TdzTest extends TestCase
{
    public function testLetterToNumberAndViceVersa()
    {
        $tests = [
            0 => 'a',
            26 => 'aa',
            26 * 2 => 'ba',
            26 * 26 => 'za',
            728 => 'aba',
            100 => 'cw',
            702 => 'aaa',
            36388720 => 'capile',
        ];

        foreach ($tests as $number => $letter) {
            $this->assertEquals($letter, tdz::numberToLetter($number), "$number => $letter");
            $this->assertEquals(strtoupper($letter), tdz::numberToLetter($number, true),
                "$number => " . strtoupper($letter));
            $this->assertEquals($number, tdz::letterToNumber($letter), "$letter => $number");
        }
    }

    public function testTimedNumberConversion()
    {
        $i = 10;
        while ($i--) {
            $n = mt_rand(0, time());
            $this->assertEquals($n, tdz::lettertoNumber(tdz::numberToLetter($n)), "$n failed");
        }
    }

}
