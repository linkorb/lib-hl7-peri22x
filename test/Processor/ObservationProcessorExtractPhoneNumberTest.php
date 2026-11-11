<?php

namespace Hl7Peri22x\Test\Processor;

use Mimey\MimeTypes;
use Peri22x\Attachment\AttachmentFactory;
use Peri22x\Resource\ResourceFactory;
use Peri22x\Section\SectionFactory;
use Peri22x\Value\ValueFactory;
use PHPUnit_Framework_TestCase;
use ReflectionMethod;

use Hl7v2\Encoding\EncodingParametersBuilder;
use Hl7v2\Factory\DataTypeFactory;

use Hl7Peri22x\Document\DocumentFactory;
use Hl7Peri22x\Dossier\DossierFactory;
use Hl7Peri22x\Processor\ObservationProcessor;
use Hl7Peri22x\TextFilter\EscapeSequenceFilter;
use Hl7Peri22x\Transformer\IdentityTransformer;

/**
 * Pins ObservationProcessor::extractPhoneNumber as it behaves today.
 *
 * A populated local number is formatted and then thrown away: the method
 * returns null. The unformatted number and the deprecated telephone number
 * are returned as stored.
 */
class ObservationProcessorExtractPhoneNumberTest extends PHPUnit_Framework_TestCase
{
    private $processor;
    private $dataTypes;
    private $encodingParameters;

    protected function setUp()
    {
        $this->encodingParameters = (new EncodingParametersBuilder())
            ->withCharacterEncoding('ASCII')
            ->withComponentSep('^')
            ->withEscapeChar('\\')
            ->withFieldSep('|')
            ->withRepetitionSep('~')
            ->withSegmentSep("\r")
            ->withSubcomponentSep('&')
            ->build()
        ;

        $this->dataTypes = new DataTypeFactory();

        $this->processor = new ObservationProcessor(
            new DossierFactory(
                new AttachmentFactory(),
                new DocumentFactory(new MimeTypes())
            ),
            new ResourceFactory(),
            new SectionFactory(new ValueFactory()),
            new IdentityTransformer(),
            new EscapeSequenceFilter()
        );
        $this->processor->setEncodingParameters($this->encodingParameters);
    }

    /**
     * @dataProvider dataExtractPhoneNumber
     */
    public function testExtractPhoneNumber($components, $expected)
    {
        $this->assertSame(
            $expected,
            $this->extractPhoneNumber($components)
        );
    }

    public function dataExtractPhoneNumber()
    {
        return [
            'no telephone component is set' => [
                [],
                null,
            ],
            'deprecated telephone number is used when the others are empty' => [
                [
                    'localNumber' => '',
                    'unformattedTelephoneNumber' => '',
                    'telephoneNumber' => '0612345678',
                ],
                '0612345678',
            ],
            'unformatted telephone number is returned as stored' => [
                [
                    'unformattedTelephoneNumber' => '0619056432',
                ],
                '0619056432',
            ],
            'empty deprecated telephone number yields null' => [
                [
                    'telephoneNumber' => '',
                ],
                null,
            ],
            'local number alone is discarded' => [
                [
                    'localNumber' => '7654321',
                ],
                null,
            ],
            'empty country, area, extension and prefix are ignored' => [
                [
                    'localNumber' => '7654321',
                    'countryCode' => '',
                    'areaCityCode' => '',
                    'extension' => '',
                    'extensionPrefix' => '',
                ],
                null,
            ],
            'local number with only a country code is discarded' => [
                [
                    'localNumber' => '7654321',
                    'countryCode' => '31',
                ],
                null,
            ],
            'local number with only an area code is discarded' => [
                [
                    'localNumber' => '7654321',
                    'areaCityCode' => '20',
                ],
                null,
            ],
            'local number with country and area is discarded' => [
                [
                    'localNumber' => '7654321',
                    'countryCode' => '31',
                    'areaCityCode' => '20',
                ],
                null,
            ],
            'leading zero of the area code is stripped and the number is still discarded' => [
                [
                    'localNumber' => '7654321',
                    'countryCode' => '31',
                    'areaCityCode' => '020',
                ],
                null,
            ],
            'local number with only an extension is discarded' => [
                [
                    'localNumber' => '7654321',
                    'extension' => '9',
                ],
                null,
            ],
            'local number with extension and prefix is discarded' => [
                [
                    'localNumber' => '7654321',
                    'extension' => '9',
                    'extensionPrefix' => 'x',
                ],
                null,
            ],
        ];
    }

    private function extractPhoneNumber(array $components)
    {
        $method = new ReflectionMethod(
            'Hl7Peri22x\Processor\ObservationProcessor',
            'extractPhoneNumber'
        );
        $method->setAccessible(true);

        return $method->invoke($this->processor, $this->phoneNumber($components));
    }

    private function phoneNumber(array $components)
    {
        $xtn = $this->dataTypes->create('XTN', $this->encodingParameters);
        $setters = [
            'telephoneNumber' => 'setTelephoneNumber',
            'countryCode' => 'setCountryCode',
            'areaCityCode' => 'setAreaCityCode',
            'localNumber' => 'setLocalNumber',
            'extension' => 'setExtension',
            'extensionPrefix' => 'setExtensionPrefix',
            'unformattedTelephoneNumber' => 'setUnformattedTelephoneNumber',
        ];
        foreach ($components as $name => $value) {
            $xtn->{$setters[$name]}($value);
        }

        return $xtn;
    }
}
