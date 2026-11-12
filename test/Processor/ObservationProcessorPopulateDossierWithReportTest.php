<?php

namespace Hl7Peri22x\Test\Processor;

use DateTime;
use Hl7v2\Encoding\EncodingParametersBuilder;
use Hl7v2\MessageParserBuilder;
use Hl7v2\Segment\Group\SegmentGroup;
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
use Hl7Peri22x\Dossier\DossierInterface;
use Hl7Peri22x\Processor\ObservationProcessor;
use Hl7Peri22x\Test\DatagramBuilder;
use Hl7Peri22x\TextFilter\EscapeSequenceFilter;
use Hl7Peri22x\Transformer\IdentityTransformer;

class ObservationProcessorPopulateDossierWithReportTest extends PHPUnit_Framework_TestCase
{
    private $parser;
    private $processor;

    protected function setUp()
    {
        $this->parser = (new MessageParserBuilder())->build();
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
    }

    public function testGroupThatDoesNotOpenWithObrIsIgnored()
    {
        $message = $this->parse($this->bareObrMessage());
        $patients = $message->getSegmentGroups();
        $patient = array_shift($patients);

        $dossier = $this->emptyDossier();
        $this->populate($dossier, $patient);

        $this->assertSame([], $dossier->getMetadata());
        $this->assertSame([], $dossier->getResource()->getSections());
        $this->assertSame([], $dossier->getEmbeddedFiles());
    }

    public function testReportWithObservationTimeStoresHeaderAndKnownObservations()
    {
        $dossier = $this->populateParsed($this->fullReportMessage());

        $this->assertSame(
            [
                'order_number' => '819',
                'filler_application' => 'ACME',
                'observation_time' => '2016-05-13 13:03:00',
                'result_status' => 'F',
                'referring_practice' => 'praktijkveendam',
            ],
            $dossier->getMetadata()
        );
        $this->assertSame(
            ['intake', 'echo'],
            $this->sectionTypes($dossier)
        );
        $this->assertFalse($dossier->getResource()->hasSection('consult'));

        $intake = $dossier->getResource()->getSection('intake');
        $echo = $dossier->getResource()->getSection('echo');
        $this->assertSectionStamp($intake, '2016-05-13 13:03:00', 1);
        $this->assertSectionStamp($echo, '2016-05-13 13:03:00', 2);
        $this->assertSame(
            [
                ['peri22-dataelement-20153', '1', null],
                ['peri22-dataelement-20030', '2016-08-09 00:00:00', null],
            ],
            $this->recordedValues($intake)
        );
        $this->assertSame(
            [
                ['peri22-dataelement-50020', '2016-05-13 13:03:00', null],
                ['peri22-dataelement-80754', 'dr. paracelsus', null],
                ['peri22-dataelement-50021', '192', null],
                ['peri22-dataelement-60060', '251', '1'],
                ['peri22-dataelement-60061', '23.3', '1'],
                ['peri22-dataelement-60061', '10', '2'],
                ['peri22-dataelement-60100', '51', '1'],
                ['peri22-dataelement-60101', '46.6', '1'],
                ['peri22-dataelement-60101', '7', '2'],
                ['peri22-dataelement-60080', '226', '1'],
                ['peri22-dataelement-60081', '26', '1'],
                ['peri22-dataelement-60081', '8', '2'],
                ['peri22-dataelement-80944', 'transvers', '1'],
                ['peri22-dataelement-80946', 'hoog anterior', '1'],
                ['peri22x-echo-diagnose', "Groei:\ngoede groei op de p ", null],
                ['peri22x-echo-conclusie', "dit is de conclusie\ndit is de 2e regel", null],
            ],
            $this->recordedValues($echo)
        );

        $files = $dossier->getEmbeddedFiles();
        $this->assertCount(1, $files);
        $this->assertSame('rapport', $files[0]->getBasename());
        $this->assertSame(base64_decode('JVBERi0xLjQKJeLj', true), $files[0]->toString());
    }

    public function testReportWithoutObservationTimeLeavesEffectStampUnset()
    {
        $dossier = $this->populateParsed($this->reportWithoutObservationTimeMessage());

        $this->assertSame([], $dossier->getMetadata());
        $this->assertSame(['intake', 'echo'], $this->sectionTypes($dossier));
        $this->assertFalse($dossier->getResource()->hasSection('consult'));
        $this->assertSame([], $dossier->getEmbeddedFiles());

        $intake = $dossier->getResource()->getSection('intake');
        $echo = $dossier->getResource()->getSection('echo');
        $this->assertSectionStamp($intake, null, 1);
        $this->assertSectionStamp($echo, null, 2);
        $this->assertSame(
            [
                ['peri22-dataelement-20010', '4', null],
            ],
            $this->recordedValues($intake)
        );
        $this->assertSame(
            [
                ['peri22-dataelement-82340', '1034', null],
            ],
            $this->recordedValues($echo)
        );
    }

    public function testObrWithoutObservationsAddsNoSections()
    {
        $dossier = $this->populateParsed($this->bareObrMessage());

        $this->assertSame([], $dossier->getMetadata());
        $this->assertSame([], $dossier->getResource()->getSections());
        $this->assertFalse($dossier->getResource()->hasSection('intake'));
        $this->assertFalse($dossier->getResource()->hasSection('consult'));
        $this->assertFalse($dossier->getResource()->hasSection('echo'));
        $this->assertSame([], $dossier->getEmbeddedFiles());
    }

    private function populateParsed($raw)
    {
        $message = $this->parse($raw);
        $this->processor->setEncodingParameters(
            $message->getMessageHeader()->getEncodingParameters()
        );

        $dossier = $this->emptyDossier();
        $this->populate($dossier, $this->observationReport($message));

        return $dossier;
    }

    private function populate(DossierInterface $dossier, SegmentGroup $report)
    {
        $method = new ReflectionMethod(
            ObservationProcessor::class,
            'populateDossierWithReport'
        );
        $method->setAccessible(true);
        $method->invoke($this->processor, $dossier, $report);
    }

    private function emptyDossier()
    {
        $dossier = (new DossierFactory(
            new AttachmentFactory(),
            new DocumentFactory(new MimeTypes())
        ))->create();
        $dossier->setResource(
            (new ResourceFactory())->create(DossierInterface::RESOURCE_TYPE)
        );

        return $dossier;
    }

    private function parse($raw)
    {
        $builder = new DatagramBuilder(new EncodingParametersBuilder());

        return $this->parser->parse(
            $builder->withMessage(str_replace("\n", "\r", $raw) . "\r")->build()
        );
    }

    private function observationReport($message)
    {
        $patients = $message->getSegmentGroups();
        $patient = array_shift($patients);
        foreach ($patient as $part) {
            if ($part instanceof SegmentGroup) {
                return $part;
            }
        }

        $this->fail('The parsed message has no observation report.');
    }

    private function sectionTypes(DossierInterface $dossier)
    {
        $types = [];
        foreach ($dossier->getResource()->getSections() as $section) {
            $types[] = $section->getType();
        }

        return $types;
    }

    private function recordedValues(SectionInterface $section)
    {
        $recorded = [];
        foreach ($section->getValues() as $value) {
            $attributes = $value->getAttributes();
            $recorded[] = [
                $attributes['concept'],
                (string) $value,
                isset($attributes['repeat']) ? $attributes['repeat'] : null,
            ];
        }

        return $recorded;
    }

    private function assertSectionStamp(SectionInterface $section, $effectStamp, $id)
    {
        $attributes = $section->getAttributes();
        $this->assertSame($id, $attributes['id']);
        $this->assertSame(
            (new DateTime())->format(SectionInterface::TIMESTAMP_FORMAT),
            $attributes['createStamp']
        );
        if ($effectStamp === null) {
            $this->assertArrayNotHasKey('effectStamp', $attributes);

            return;
        }
        $this->assertSame($effectStamp, $attributes['effectStamp']);
    }

    private function fullReportMessage()
    {
        return <<<'EOD'
MSH|^~\&|ACME|ACMETESTCENTRUM|TEST|TEST|20160719132745||ORU^R01|906|P|2.5.1|||AL|AL||8859/1
PID|1||123548999^^^^NNNLD||ACMETEST^K||19850317|F
OBR|||819^ACME|^OBS|||20160513130300|||||||||^NoId~praktijkveendam^Verloskundigenpraktijk Veendam|||||code  testlocatie||||F|||||||dr. paracelsus&&&&&&&&2147483647
OBX|1|NM|PARITY||1||||||F
OBX|2|TS|DUE_DATE||20160809000000||||||F
OBX|3|ST|GESTATIONAL_AGE||27w3d||||||F
OBX|4|NM|HC|1|251|mm|||||F
OBX|5|NM|HCperc|1|23.3|Verb|||||F
OBX|6|NM|HCP|2|10|Verb|||||F
OBX|7|NM|FL|1|51|mm|||||F
OBX|8|NM|FLperc|1|46.6|Verb|||||F
OBX|9|NM|FLP|2|7|Verb|||||F
OBX|10|NM|AC|1|226|mm|||||F
OBX|11|NM|ACperc|1|26|Verb|||||F
OBX|12|NM|ACP|2|8|Verb|||||F
OBX|13|TX|LIGGING|1|transvers||||||F
OBX|14|TX|PLACENTALOC|1|hoog anterior||||||F
OBX|15|TX|DIAGNOSIS||Groei:\.br\goede groei op de p ||||||F
OBX|16|TX|CONCLUSION||dit is de \H\conclusie\N\~dit is de 2e regel||||||F
OBX|17|ED|RAPPORT||PDF^TEXT^^Base64^JVBERi0xLjQKJeLj||||||F
OBX|18|NM|WEIGHTperc||48,5|Yudkin|||||F
EOD;
    }

    private function reportWithoutObservationTimeMessage()
    {
        return <<<'EOD'
MSH|^~\&|ACME|ACMETESTCENTRUM|TEST|TEST|20160719132745||ORU^R01|906|P|2.5.1|||AL|AL||8859/1
PID|1||123548999^^^^NNNLD||ACMETEST^K||19850317|F
OBR|1|||
OBX|1|NM|GRAVIDA||4||||||F
OBX|2|NM|WEIGHT||1034|g||||||F
EOD;
    }

    private function bareObrMessage()
    {
        return <<<'EOD'
MSH|^~\&|ACME|ACMETESTCENTRUM|TEST|TEST|20160719132745||ORU^R01|906|P|2.5.1|||AL|AL||8859/1
PID|1||123548999^^^^NNNLD||ACMETEST^K||19850317|F
OBR|1|||
EOD;
    }
}
