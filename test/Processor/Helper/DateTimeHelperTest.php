<?php

namespace Hl7Peri22x\Test\Processor\Helper;

use PHPUnit_Framework_TestCase;

use Hl7Peri22x\Processor\Helper\DateTimeHelper;

class DateTimeHelperTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dataDateTimeHelperFormat
     */
    public function testFormat($expected, $input)
    {
        $this->assertSame(
            $expected,
            DateTimeHelper::format($input)
        );
    }

    public function dataDateTimeHelperFormat()
    {
        return [
            [
                '2020',
                '2020',
            ],
            [
                '2020 +01:00',
                '2020+0100',
            ],
            [
                '2020 -11:00',
                '2020-1100',
            ],
            [
                '2020-12',
                '202012',
            ],
            [
                '2020-12 +01:00',
                '202012+0100',
            ],
            [
                '2020-12 -11:00',
                '202012-1100',
            ],
            [
                '2020-12-22',
                '20201222',
            ],
            [
                '2020-12-22 +01:00',
                '20201222+0100',
            ],
            [
                '2020-12-22 -11:00',
                '20201222-1100',
            ],
            [
                '2020-12-22 12:00',
                '2020122212',
            ],
            [
                '2020-12-22 12:00 +01:00',
                '2020122212+0100',
            ],
            [
                '2020-12-22 12:00 -11:00',
                '2020122212-1100',
            ],
            [
                '2020-12-22 12:22',
                '202012221222',
            ],
            [
                '2020-12-22 12:22 +01:00',
                '202012221222+0100',
            ],
            [
                '2020-12-22 12:22 -11:00',
                '202012221222-1100',
            ],
            [
                '2020-12-22 12:22:33',
                '20201222122233',
            ],
            [
                '2020-12-22 12:22:33 +01:00',
                '20201222122233+0100',
            ],
            [
                '2020-12-22 12:22:33 -11:00',
                '20201222122233-1100',
            ],
            [
                '2020-12-22 12:22:33.400000',
                '20201222122233.4',
            ],
            [
                '2020-12-22 12:22:33.400000 +01:00',
                '20201222122233.4+0100',
            ],
            [
                '2020-12-22 12:22:33.400000 -11:00',
                '20201222122233.4-1100',
            ],
            [
                '2020-12-22 12:22:33.450000',
                '20201222122233.45',
            ],
            [
                '2020-12-22 12:22:33.450000 +01:00',
                '20201222122233.45+0100',
            ],
            [
                '2020-12-22 12:22:33.450000 -11:00',
                '20201222122233.45-1100',
            ],
            [
                '2020-12-22 12:22:33.456000',
                '20201222122233.456',
            ],
            [
                '2020-12-22 12:22:33.456000 +01:00',
                '20201222122233.456+0100',
            ],
            [
                '2020-12-22 12:22:33.456000 -11:00',
                '20201222122233.456-1100',
            ],
        ];
    }

    /**
     * @dataProvider dataDateTimeHelperDays
     */
    public function testConvertToDays($expected, $input)
    {
        $this->assertSame(
            $expected,
            DateTimeHelper::convertToDays($input)
        );
    }

    public function dataDateTimeHelperDays()
    {
        return [
            'An integer is assumed to be a number of days already' => [
                '123',
                '123',
            ],
            '1d is interpreted as 1 day' => [
                '1',
                '1d',
            ],
            '1w1 is interpreted as 1 week and 1 day' => [
                '8',
                '1w1',
            ],
            '1w1d is interpreted as 1 week and 1 day' => [
                '8',
                '1w1d',
            ],
            '1wd is interpreted as 1 week' => [
                '7',
                '1w0d',
            ],
            '1wd is interpreted as 1 week' => [
                '7',
                '1wd',
            ],
            'w1d is interpreted as 1 day' => [
                '1',
                'w1d',
            ],
            'w1 is interpreted as 1 day' => [
                '1',
                'w1',
            ],
            'x1 is interpreted as 1 day' => [
                '1',
                'x1',
            ],
            'x1w1d is interpreted as 1 week and 1 day' => [
                '8',
                'x1w1d',
            ],
            '365x1 is interpreted as 1 day' => [
                '1',
                '365x1',
            ],
            'wd has no digits and is interpreted as zero' => [
                '0',
                'wd',
            ],
            'xyz has no digits and is interpreted as zero' => [
                '0',
                'xyz',
            ],
        ];
    }
}
