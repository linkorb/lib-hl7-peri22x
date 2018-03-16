<?php

namespace Hl7Peri22x\Test\Attachments;

use Hl7v2\DataType\StDataType;
use Hl7v2\Encoding\EncodingParametersBuilder;
use PHPUnit_Framework_TestCase;

use Hl7Peri22x\TextFilter\EscapeSequenceFilter;

class EscapeSequenceFilterTest extends PHPUnit_Framework_TestCase
{
    private $encodingParamBuilder;
    private $filter;

    protected function setUp()
    {
        $this->encodingParamBuilder = (new EncodingParametersBuilder())
            ->withComponentSep('^')
            ->withFieldSep('|')
            ->withRepetitionSep('~')
            ->withSegmentSep("\r")
            ->withSubcomponentSep('&')
        ;
        $this->filter = new EscapeSequenceFilter();
    }

    public function testFilterWillThrowExceptionWhenEncodingParametersWereNotSet()
    {
        $data = new StDataType();
        $data->setCharacterEncoding('7bit');
        $data->setValue('foo');

        $this->setExpectedException(\RuntimeException::class);

        $this->filter->filter($data);
    }

    /**
     * @dataProvider textWithEscapeSequences
     *
     * @param string $textEncoding char enc of $text
     * @param string $text the text to put through the filter
     * @param string $expectedText the expected output of the filter
     */
    public function testFilter($textEncoding, $text, $expectedText, $escapeChar = null)
    {
        if ($escapeChar) {
            $this->encodingParamBuilder->withEscapeChar($escapeChar);
        } else {
            $this->encodingParamBuilder->withEscapeChar('\\');
        }
        $this->encodingParamBuilder->withCharacterEncoding($textEncoding);

        $this->filter->setEncodingParameters($this->encodingParamBuilder->build());

        $data = new StDataType();
        $data->setCharacterEncoding($textEncoding);
        $data->setValue($text);

        $this->assertSame($expectedText, $this->filter->filter($data));
    }

    public function textWithEscapeSequences()
    {
        return [
            ['utf8', 'Здравей\\.br\\свят', "Здравей\nсвят"],
            ['7bit', 'Hello\\.br\\World', "Hello\nWorld"],
            ['7bit', 'Hello/.br/World', "Hello\nWorld", '/'],
            ['7bit', 'Hello\\.ce\\World', "Hello\nWorld"],
            ['7bit', 'Hello\\.sp\\World', "Hello\nWorld"],
            ['7bit', 'Hello\\.sp1\\World', "Hello\nWorld"],
            ['7bit', 'Hello\\.sp23\\World', "Hello\nWorld"],
            ['7bit', 'Hello\\.in\\World', 'Hello World'],
            ['7bit', 'Hello\\.in1\\World', 'Hello World'],
            ['7bit', 'Hello\\.in23\\World', 'Hello World'],
            ['7bit', 'Hello\\.in+1\\World', 'Hello World'],
            ['7bit', 'Hello\\.in-1\\World', 'Hello World'],
            ['7bit', 'Hello\\.sk\\World', 'Hello World'],
            ['7bit', 'Hello\\.sk1\\World', 'Hello World'],
            ['7bit', 'Hello\\.sk23\\World', 'Hello World'],
            ['7bit', 'Hello\\.ti\\World', 'HelloWorld'],
            ['7bit', 'Hello\\.ti1\\World', 'HelloWorld'],
            ['7bit', 'Hello\\.ti23\\World', 'HelloWorld'],
            ['7bit', 'Hello\\.ti+1\\World', 'HelloWorld'],
            ['7bit', 'Hello\\.ti-1\\World', 'HelloWorld'],
            ['7bit', 'Hello\\.fi\\World', 'HelloWorld'],
            ['7bit', 'Hello\\.nf\\World', 'HelloWorld'],
            ['7bit', 'Hello\\H\\World', 'HelloWorld'],
            ['7bit', 'Hello\\N\\World', 'HelloWorld'],
            ['7bit', 'Hello\\F\\World', 'Hello|World'],
            ['7bit', 'Hello\\S\\World', 'Hello^World'],
            ['7bit', 'Hello\\T\\World', 'Hello&World'],
            ['7bit', 'Hello\\R\\World', 'Hello~World'],
            ['7bit', 'Hello\\E\\World', 'Hello\\World'],
        ];
    }
}
