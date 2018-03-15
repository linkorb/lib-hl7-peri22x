<?php

namespace Hl7Peri22x\Test\Attachments;

use PHPUnit_Framework_TestCase;

use Hl7Peri22x\Transformer\MappingTransformer;

class MappingTransformerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @dataProvider transformations
     */
    public function testTransformations($mappings, $transformType, $value, $expected)
    {
        $transformer = new MappingTransformer($mappings);
        $this->assertSame($expected, $transformer->transform($transformType, $value));
    }

    public function transformations()
    {
        $map = ['drinky_poos' => ['black_coffee' => 'tea', 'vodka' => 'rum']];

        return [
            'return identity if type and value are unkown' => [&$map, 'food', 'advocaat', 'advocaat'],
            'return identity if type is unkown' => [&$map, 'food', 'black_coffee', 'black_coffee'],
            'return identity if value is unkown' => [&$map, 'drinky_poos', 'beer', 'beer'],
            'return identity if value is already transformed' => [&$map, 'drinky_poos', 'rum', 'rum'],
            'normalise type of transformation' => [&$map, 'Drinky Poos', 'vodka', 'rum'],
            'normalise value before transformation' => [&$map, 'Drinky Poos', 'BLACK COFFEE', 'tea'],
        ];
    }
}
