<?php

namespace Hl7Peri22x\Test\Processor;

use Hl7v2\MessageParserBuilder;
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
    private $finfo;
    private $messageParser;
    private $observationProcessor;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        if (class_exists('\\finfo')) {
            $this->finfo = new \finfo(FILEINFO_MIME);
        }
        parent::__construct($name, $data, $dataName);
    }

    protected function setUp()
    {
        $messageParserBuilder = new MessageParserBuilder;
        $this->messageParser = $messageParserBuilder->build();

        $this->observationProcessor = new ObservationProcessor(
            new DossierFactory(new DocumentFactory),
            new ResourceFactory(),
            new SectionFactory(new ValueFactory)
        );
    }

    public function testTransformMessageOne()
    {
        $message = $this->messageParser->parse(SampleMessages::getDatagramBuilder(1)->build());
        foreach ($message->getSegmentGroups() as $observationParts) {
            $dossier = $this->observationProcessor->getDossier($observationParts);
            $this->assertCount(
                4,
                $dossier->getResource()->getSections(),
                'The dossier contains the required number of sections.'
            );
            # dump($dossier->toXmlDocument()->toString());
            # $dossier->toXmlDocument()->save('dossier-one.xml');
            if (!$this->finfo) {
                continue;
            }
            foreach ($dossier->getEmbeddedFiles() as $file) {
                $finfo = $this->finfo->buffer($file->toString());
                $this->assertNotTrue(
                    $finfo,
                    'A description of the type of the embedded file is obtained.'
                );
                $this->assertSame('application/pdf; charset=iso-8859-1', $finfo);
            }
        }
        if (!$this->finfo) {
            $this->markTestIncomplete('Unable to invoke finfo_buffer to obtain mime info of embedded files.');
        }
    }

    public function testTransformMessageTwo()
    {
        $message = $this->messageParser->parse(SampleMessages::getDatagramBuilder(2)->build());
        foreach ($message->getSegmentGroups() as $observationParts) {
            $dossier = $this->observationProcessor->getDossier($observationParts);
            $this->assertCount(
                5,
                $dossier->getResource()->getSections(),
                'The dossier contains the required number of sections.'
            );
            # dump($dossier->toXmlDocument()->toString());
            # $dossier->toXmlDocument()->save('dossier-two.xml');
            if (!$this->finfo) {
                continue;
            }
            foreach ($dossier->getEmbeddedFiles() as $file) {
                $finfo = $this->finfo->buffer($file->toString());
                $this->assertNotTrue(
                    $finfo,
                    'A description of the type of the embedded file is obtained.'
                );
                $this->assertSame('application/pdf; charset=iso-8859-1', $finfo);
            }
        }
        if (!$this->finfo) {
            $this->markTestIncomplete('Unable to invoke finfo_buffer to obtain mime info of embedded files.');
        }
    }
}
