<?php

namespace Hl7Peri22x\Test\Processor;

use Hl7v2\MessageParserBuilder;
use Mimey\MimeTypes;
use Peri22x\Attachment\AttachmentFactory;
use Peri22x\Resource\ResourceFactory;
use Peri22x\Section\SectionFactory;
use Peri22x\Value\ValueFactory;
use PHPUnit_Framework_TestCase;

use Hl7Peri22x\Document\DocumentFactory;
use Hl7Peri22x\Dossier\DossierFactory;
use Hl7Peri22x\Processor\ObservationProcessor;
use Hl7Peri22x\TextFilter\EscapeSequenceFilter;
use Hl7Peri22x\Transformer\IdentityTransformer;
use Hl7Peri22x\Transformer\MappingTransformer;

use Hl7Peri22x\Test\SampleMessages;

class ObservationProcessorTest extends PHPUnit_Framework_TestCase
{
    private $messageParser;
    private $observationProcessor;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
    }

    protected function setUp()
    {
        $messageParserBuilder = new MessageParserBuilder;
        $this->messageParser = $messageParserBuilder->build();

        $this->observationProcessor = new ObservationProcessor(
            new DossierFactory(
                new AttachmentFactory,
                new DocumentFactory(new MimeTypes)
            ),
            new ResourceFactory(),
            new SectionFactory(new ValueFactory),
            new IdentityTransformer,
            new EscapeSequenceFilter
        );
    }

    /**
     * @dataProvider dataTransformMessage
     */
    public function testTransformMessage($sampleMessage, $sectionCount)
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder($sampleMessage)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        foreach ($message->getSegmentGroups() as $observationParts) {
            $dossier = $this->observationProcessor->getDossier($observationParts);
            $this->assertCount(
                $sectionCount,
                $dossier->getResource()->getSections(),
                'The dossier contains the required number of sections.'
            );
            # dump($dossier->toXmlDocument()->toString());
            # $dossier->toXmlDocument()->save('dossier-one.xml');
            foreach ($dossier->getEmbeddedFiles() as $file) {
                $this->assertSame(
                    'application/pdf',
                    $file->getMimeType(),
                    'The mime type of the embedded file is obtained.'
                );
                $this->assertSame(
                    'pdf',
                    $file->getExtension(),
                    'The mime extension of the embedded file is obtained.'
                );
                $this->assertSame(
                    'iso-8859-1',
                    $file->getCharacterSet(),
                    'The character set of the embedded file is obtained.'
                );
            }
        }
    }

    public function dataTransformMessage()
    {
        return [
            'Message 1 will result in 3 dossier sections.' => [SampleMessages::MESSAGE, 3],
            'Message 2 will result in 3 dossier sections.' => [SampleMessages::MESSAGE_TWIN, 3],
        ];
    }

    public function testGravidaIsExtracted()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('intake')
            ->getValue('peri22-dataelement-20010')
        ;
        $this->assertSame('1', (string) $concept);
    }

    public function testParityIsExtracted()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('intake')
            ->getValue('peri22-dataelement-20153')
        ;
        $this->assertSame('1', (string) $concept);
    }

    public function testGestationalAgeIsMeasuredInDays()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22-dataelement-50021')
        ;
        $this->assertSame('192', (string) $concept);
    }

    public function testDueDateIsExtractedFromObservationValue()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('intake')
            ->getValue('peri22-dataelement-20030')
        ;
        $this->assertSame('2016-08-09 00:00:00', (string) $concept);
    }

    public function testExaminerNameIsExtractedFromFieldPrincipalResultInterpreter()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22-dataelement-80754')
        ;
        $this->assertSame('dr. paracelsus', (string) $concept);
    }

    public function testReferringPracticeMetadataIsExtractedFromFieldOrderingProvider()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $referrer = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getMetadata('referring_practice')
        ;
        $this->assertSame('praktijkveendam', $referrer);
    }

    public function testExaminationDateIsExtractedFromFieldObservationDatetime()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22-dataelement-50020')
        ;
        $this->assertSame('2016-05-13 13:03:00', (string) $concept);
    }

    public function testWeightIsExtracted()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22-dataelement-82340')
        ;
        $this->assertSame('1034', (string) $concept);
    }

    public function testHeadCircumferenceIsExtracted()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22-dataelement-60060')
        ;
        $this->assertSame('251', (string) $concept);
    }

    public function testHeadCircumferencePercentileIsExtracted()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22-dataelement-60061')
        ;
        $this->assertSame('23.3', (string) $concept);
    }

    public function testFemurLengthIsExtracted()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22-dataelement-60100')
        ;
        $this->assertSame('51', (string) $concept);
    }

    public function testFemurLengthPercentileIsExtracted()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22-dataelement-60101')
        ;
        $this->assertSame('46.6', (string) $concept);
    }

    public function testAbdominalCircumferenceIsExtracted()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22-dataelement-60080')
        ;
        $this->assertSame('226', (string) $concept);
    }

    public function testAbdominalCircumferencePercentileIsExtracted()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22-dataelement-60081')
        ;
        $this->assertSame('26', (string) $concept);
    }

    public function testPlacentaLocationIsExtracted()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22-dataelement-80946')
        ;
        $this->assertSame('hoog anterior', (string) $concept);
    }

    public function testPlacentaLocationIsTransformed()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $this
            ->observationProcessor
            ->setObservationValueTransformer(
                new MappingTransformer(
                    ['placentaloc' => ['hoog_anterior' => '7371000146105']]
                )
            )
        ;
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22-dataelement-80946')
        ;
        $this->assertSame('7371000146105', (string) $concept);
    }

    public function testDiagnosisIsExtractedAndUnescaped()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22x-echo-diagnose')
        ;
        $this->assertSame("Groei:\ngoede groei op de p ", (string) $concept);
    }

    public function testConclusionIsExtractedAndUnescaped()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $concept = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getResource()
            ->getSection('echo')
            ->getValue('peri22x-echo-conclusie')
        ;
        $this->assertSame("dit is de conclusie\ndit is de 2e regel", (string) $concept);
    }

    public function testEDAIsExtractedAndUnescaped()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE_TEXT_REPORT)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $files = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
            ->getEmbeddedFiles()
        ;
        $this->assertSame("This is a report:\nEnde", $files[0]->toString());
    }

    public function testPatientMobilePhoneNumberIsExtractedAsDossierMetadata()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $dossier = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
        ;
        $this->assertTrue($dossier->hasMetadata('client_mobile_phone_number'));
        $this->assertSame('0619056432', $dossier->getMetadata('client_mobile_phone_number'));
    }

    public function testPatientEmailAddressIsExtractedAsDossierMetadata()
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build())
        ;
        $this
            ->observationProcessor
            ->setEncodingParameters($message->getMessageHeader()->getEncodingParameters())
        ;

        $segmentGroups = $message->getSegmentGroups();
        $dossier = $this
            ->observationProcessor
            ->getDossier(array_shift($segmentGroups))
        ;
        $this->assertTrue($dossier->hasMetadata('client_email_address'));
        $this->assertSame('acmetest@hotmail.com', $dossier->getMetadata('client_email_address'));
    }
}
