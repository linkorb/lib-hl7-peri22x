<?php

namespace Hl7Peri22x\Test\Processor\Helper;

use PHPUnit_Framework_TestCase;

use Hl7Peri22x\Processor\Helper\DateTimeHelper;

class DateTimeHelperFormatTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider reachableBranches
     */
    public function testFormatPinsCurrentBehavior($expected, $input)
    {
        $this->assertSame(
            $expected,
            DateTimeHelper::format($input)
        );
    }

    public function reachableBranches()
    {
        return [
            'four digits stay a year' => [
                '2020',
                '2020',
            ],
            'five or six digits become a month' => [
                '2020-12',
                '202012',
            ],
            'seven or eight digits become a date' => [
                '2020-12-22',
                '20201222',
            ],
            'nine or ten digits print an hour and zero minutes' => [
                '2020-12-22 12:00',
                '2020122212',
            ],
            'eleven or twelve digits keep hour and minute' => [
                '2020-12-22 12:22',
                '202012221222',
            ],
            'thirteen or fourteen digits keep seconds' => [
                '2020-12-22 12:22:33',
                '20201222122233',
            ],
            'longer values keep a padded fraction and a timezone' => [
                '2020-12-22 12:22:33.400000 +01:00',
                '20201222122233.4+0100',
            ],
        ];
    }

    public function testFormatThrowsWhenParsingFails()
    {
        $this->setExpectedException(
            'Exception',
            'Failed to parse DateTime value "abcd" with format "Y": A four digit year could not be found.'
        );

        DateTimeHelper::format('abcd');
    }
}
