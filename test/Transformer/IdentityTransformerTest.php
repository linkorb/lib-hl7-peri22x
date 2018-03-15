<?php

namespace Hl7Peri22x\Test\Attachments;

use PHPUnit_Framework_TestCase;

use Hl7Peri22x\Transformer\IdentityTransformer;

class IdentityTransformerTest extends PHPUnit_Framework_TestCase
{
    private $transformer;

    protected function setUp()
    {
        $this->transformer = new IdentityTransformer();
    }

    /**
     * @dataProvider bitsAndBobs
     */
    public function testTransformReturnsTheIdentity($thing)
    {
        $this->assertSame($thing, $this->transformer->transform($thing, $thing));
    }

    public function bitsAndBobs()
    {
        return [
            ['a'], [null], ['foo'], [1], [1.1], [new \stdClass()], [''], [[]]
        ];
    }
}
