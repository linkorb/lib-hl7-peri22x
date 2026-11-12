<?php

namespace Hl7Peri22x\Test\Processor;

use Hl7v2\MessageParserBuilder;
use Hl7v2\Segment\ObxSegment;
use Mimey\MimeTypes;
use Peri22x\Attachment\AttachmentFactory;
use Peri22x\Resource\ResourceFactory;
use Peri22x\Section\SectionFactory;
use Peri22x\Value\ValueFactory;
use PHPUnit_Framework_TestCase;
use ReflectionMethod;

use Hl7Peri22x\Document\DocumentFactory;
use Hl7Peri22x\Dossier\DossierFactory;
use Hl7Peri22x\Processor\ObservationProcessor;
use Hl7Peri22x\Test\SampleMessages;
use Hl7Peri22x\TextFilter\EscapeSequenceFilter;
use Hl7Peri22x\Transformer\IdentityTransformer;

class ObservationProcessorExtractEmbeddedFileTest extends PHPUnit_Framework_TestCase
{
    private $dossierFactory;
    private $messageParser;
    private $observationProcessor;

    protected function setUp()
    {
        $this->messageParser = (new MessageParserBuilder)->build();
        $this->dossierFactory = new DossierFactory(
            new AttachmentFactory,
            new DocumentFactory(new MimeTypes)
        );
        $this->observationProcessor = new ObservationProcessor(
            $this->dossierFactory,
            new ResourceFactory(),
            new SectionFactory(new ValueFactory),
            new IdentityTransformer,
            new EscapeSequenceFilter
        );
    }

    /**
     * @dataProvider dataExtractEmbeddedFile
     */
    public function testExtractEmbeddedFile($valueType, $obxLine, array $expectedFiles)
    {
        $message = $this->messageParser->parse(SampleMessages::getDatagramBuilder(
            "MSH|^~\\&|ACME|ACME|TEST|TEST|20160719130739||ORU^R01|969|P|2.5.1|||AL|AL||8859/1\n"
            . "PID|1||64654^^^^PI||ACMETEST^K||19850317|F\n"
            . "OBR|||919^ACME|^OBS|||20160407123100\n"
            . $obxLine
        )->build());
        $this->observationProcessor->setEncodingParameters(
            $message->getMessageHeader()->getEncodingParameters()
        );

        $obx = null;
        foreach ($message->getSegments() as $segment) {
            if ($segment instanceof ObxSegment) {
                $obx = $segment;
                break;
            }
        }

        $dossier = $this->dossierFactory->create();
        $method = new ReflectionMethod(ObservationProcessor::class, 'extractEmbeddedFile');
        $method->setAccessible(true);
        $method->invoke($this->observationProcessor, $dossier, $obx, $valueType);

        $files = $dossier->getEmbeddedFiles();
        $this->assertCount(count($expectedFiles), $files);
        foreach ($expectedFiles as $index => $expected) {
            $this->assertSame($expected['data'], $files[$index]->toString());
            $this->assertSame($expected['basename'], $files[$index]->getBasename());
        }
    }

    public function dataExtractEmbeddedFile()
    {
        $decodedPdf = "%PDF-1.4\n%\xE2\xE3";
        // CE is not stored as an observation value, so the foreach body is skipped.
        $emptyObservation = 'OBX|1|CE|Rapport||||||||F';

        return [
            'ED stores the decoded payload under the lowercased id' => [
                'ED',
                'OBX|1|ED|Rapport||^TEXT^^Base64^JVBERi0xLjQKJeLj||||||F',
                [
                    ['data' => $decodedPdf, 'basename' => 'rapport'],
                ],
            ],
            'ED with an empty observation list adds no file' => [
                'ED',
                $emptyObservation,
                [],
            ],
            'TX joins repetitions and keeps the trailing newline' => [
                'TX',
                'OBX|1|TX|Rapport||linha um~linha dois||||||F',
                [
                    ['data' => "linha um\nlinha dois\n", 'basename' => 'rapport'],
                ],
            ],
            'TX with an empty observation list still stores a newline' => [
                'TX',
                $emptyObservation,
                [
                    ['data' => "\n", 'basename' => 'rapport'],
                ],
            ],
        ];
    }
}
