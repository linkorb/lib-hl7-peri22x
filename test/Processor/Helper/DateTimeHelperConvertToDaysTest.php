<?php

namespace Hl7Peri22x\Test\Processor\Helper;

use PHPUnit_Framework_TestCase;

use Hl7Peri22x\Processor\Helper\DateTimeHelper;

class DateTimeHelperConvertToDaysTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider dataConvertToDays
     */
    public function testConvertToDays($expected, $input)
    {
        $this->assertSame($expected, DateTimeHelper::convertToDays($input));
    }

    public function dataConvertToDays()
    {
        return [
            'empty string is returned unchanged' => [
                '',
                '',
            ],
            'a digit-only string is returned unchanged' => [
                '42',
                '42',
            ],
            'weeks and days are summed, an unknown unit drops its number, and trailing digits count as days' => [
                '22',
                '2w3d4x5',
            ],
            'a value that ends on a unit adds no trailing days' => [
                '6',
                '6d',
            ],
        ];
    }
}
