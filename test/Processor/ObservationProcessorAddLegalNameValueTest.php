<?php

namespace Hl7Peri22x\Test\Processor;

use Hl7v2\Encoding\EncodingParametersBuilder;
use Hl7v2\Factory\DataTypeFactory;
use Mimey\MimeTypes;
use Peri22x\Attachment\AttachmentFactory;
use Peri22x\Resource\ResourceFactory;
use Peri22x\Section\SectionFactory;
use Peri22x\Section\SectionInterface;
use Peri22x\Value\ValueFactory;
use PHPUnit_Framework_TestCase;
use ReflectionMethod;

use Hl7Peri22x\Document\DocumentFactory;
use Hl7Peri22x\Dossier\DossierFactory;
use Hl7Peri22x\Processor\ObservationProcessor;
use Hl7Peri22x\TextFilter\EscapeSequenceFilter;
use Hl7Peri22x\Transformer\IdentityTransformer;

class ObservationProcessorAddLegalNameValueTest extends PHPUnit_Framework_TestCase
{
    private $processor;
    private $sectionFactory;
    private $dataTypeFactory;
    private $encodingParameters;

    protected function setUp()
    {
        $this->encodingParameters = (new EncodingParametersBuilder())
            ->withComponentSep('^')
            ->withFieldSep('|')
            ->withRepetitionSep('~')
            ->withSegmentSep("\r")
            ->withSubcomponentSep('&')
            ->withEscapeChar('\\')
            ->withCharacterEncoding('utf8')
            ->build()
        ;

        $this->processor = new ObservationProcessor(
            new DossierFactory(
                new AttachmentFactory,
                new DocumentFactory(new MimeTypes)
            ),
            new ResourceFactory(),
            new SectionFactory(new ValueFactory),
            new IdentityTransformer,
            new EscapeSequenceFilter
        );
        $this->processor->setEncodingParameters($this->encodingParameters);

        $this->sectionFactory = new SectionFactory(new ValueFactory);
        $this->dataTypeFactory = new DataTypeFactory();
    }

    public function testGivenNameSecondNamesAndFamilySurnameAreStored()
    {
        $section = $this->clientSection();
        $name = $this->name();
        $name->setGivenName('Anna');
        $name->setSecondNames('Maria');
        $name->setFamilyName('Jansen');

        $this->addLegalNameValue($section, $name);

        $this->assertSame(
            'Anna Maria',
            (string) $section->getValue('peri22-dataelement-10042')
        );
        $this->assertSame(
            'Jansen',
            (string) $section->getValue('peri22-dataelement-82361')
        );
    }

    public function testAbsentNameComponentsAddNothing()
    {
        $section = $this->clientSection();

        $this->addLegalNameValue($section, $this->name());

        $this->assertNull($section->getValue('peri22-dataelement-10042'));
        $this->assertNull($section->getValue('peri22-dataelement-82361'));
        $this->assertSame([], $section->getValues());
    }

    public function testEmptyGivenNameAndSecondNamesAreSkipped()
    {
        $section = $this->clientSection();
        $name = $this->name();
        $name->setGivenName('');
        $name->setSecondNames('');

        $this->addLegalNameValue($section, $name);

        $this->assertNull($section->getValue('peri22-dataelement-10042'));
        $this->assertNull($section->getValue('peri22-dataelement-82361'));
        $this->assertSame([], $section->getValues());
    }

    private function clientSection()
    {
        return $this->sectionFactory->create(SectionInterface::TYPE_CLIENT);
    }

    private function name()
    {
        return $this->dataTypeFactory->create('XPN', $this->encodingParameters);
    }

    private function addLegalNameValue($section, $name)
    {
        $method = new ReflectionMethod(ObservationProcessor::class, 'addLegalNameValue');
        $method->setAccessible(true);
        $method->invoke($this->processor, $section, $name);
    }
}
