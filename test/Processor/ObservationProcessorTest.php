<?php

namespace Hl7Peri22x\Test\Processor;

use Hl7v2\MessageParserBuilder;
use Mimey\MimeTypes;
use Peri22x\Resource\ResourceFactory;
use Peri22x\Section\SectionFactory;
use Peri22x\Value\ValueFactory;
use PHPUnit_Framework_TestCase;

use Hl7Peri22x\Document\DocumentFactory;
use Hl7Peri22x\Dossier\DossierFactory;
use Hl7Peri22x\Processor\ObservationProcessor;

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
            new DossierFactory(new DocumentFactory(new MimeTypes)),
            new ResourceFactory(),
            new SectionFactory(new ValueFactory)
        );
    }

    /**
     * @dataProvider dataTransformMessage
     */
    public function testTransformMessage($messageNum, $sectionCount)
    {
        $message = $this
            ->messageParser
            ->parse(SampleMessages::getDatagramBuilder($messageNum)->build())
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
            'Message 1 will result in 4 dossier sections.' => [1, 4],
            'Message 2 will result in 5 dossier sections.' => [2, 4],
        ];
    }
}
