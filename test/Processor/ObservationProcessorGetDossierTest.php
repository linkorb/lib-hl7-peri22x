<?php

namespace Hl7Peri22x\Test\Processor;

use Hl7v2\MessageParserBuilder;
use Hl7v2\Segment\Group\SegmentGroup;
use Hl7v2\Segment\ObxSegment;
use Hl7v2\Segment\PidSegment;
use Hl7v2\Segment\Pv1Segment;
use Mimey\MimeTypes;
use Peri22x\Attachment\AttachmentFactory;
use Peri22x\Resource\ResourceFactory;
use Peri22x\Section\SectionFactory;
use Peri22x\Section\SectionInterface;
use Peri22x\Value\ValueFactory;
use PHPUnit_Framework_TestCase;

use Hl7Peri22x\Document\DocumentFactory;
use Hl7Peri22x\Dossier\DossierFactory;
use Hl7Peri22x\Dossier\DossierInterface;
use Hl7Peri22x\Processor\ObservationProcessor;
use Hl7Peri22x\TextFilter\EscapeSequenceFilter;
use Hl7Peri22x\Transformer\IdentityTransformer;

use Hl7Peri22x\Test\SampleMessages;

class ObservationProcessorGetDossierTest extends PHPUnit_Framework_TestCase
{
    private $processor;
    private $pid;
    private $pv1;
    private $report;
    private $gravida;

    protected function setUp()
    {
        $parser = (new MessageParserBuilder)->build();
        $message = $parser->parse(
            SampleMessages::getDatagramBuilder(SampleMessages::MESSAGE)->build()
        );

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
        $this->processor->setEncodingParameters(
            $message->getMessageHeader()->getEncodingParameters()
        );

        foreach ($message->getSegmentGroups()[0] as $part) {
            if ($part instanceof PidSegment) {
                $this->pid = $part;
            } elseif ($part instanceof Pv1Segment) {
                $this->pv1 = $part;
            } elseif ($part instanceof SegmentGroup) {
                $this->report = $part;
            }
        }
        foreach ($this->report as $segment) {
            if (!$segment instanceof ObxSegment) {
                continue;
            }
            $name = strtolower(
                $segment->getFieldObservationIdentifier()->getIdentifier()->getValue()
            );
            if ($name === 'gravida') {
                $this->gravida = $segment;
                break;
            }
        }
    }

    public function testPatientGroupWithoutAPidIsPassedOnAsNull()
    {
        try {
            $this->processor->getDossier(new SegmentGroup());
            $this->fail('A patient group without a PID still reaches the client section.');
        } catch (\TypeError $error) {
            $this->assertContains(
                'must be an instance of Hl7v2\Segment\PidSegment, null given',
                $error->getMessage()
            );
        }
    }

    public function testPidAloneYieldsOnlyTheClientSection()
    {
        $dossier = $this->processor->getDossier($this->patientGroup([$this->pid]));

        $this->assertSame(DossierInterface::RESOURCE_TYPE, $dossier->getResource()->getType());
        $this->assertSame(['client'], $this->sectionTypes($dossier));
        $this->assertSame(
            '1',
            $dossier->getResource()->getSection('client')->getAttributes()['id']
        );
        $this->assertSame(
            '123548999',
            (string) $dossier->getResource()->getSection('client')->getValue('peri22-dataelement-10030')
        );
        $this->assertSame(
            date(SectionInterface::TIMESTAMP_FORMAT),
            $dossier->getResource()->getSection('client')->getAttributes()['createStamp']
        );
        $this->assertSame(
            [
                'client_mobile_phone_number' => '0619056432',
                'client_email_address' => 'acmetest@hotmail.com',
            ],
            $dossier->getMetadata()
        );
    }

    public function testObservationOutsideAReportGroupIsIgnored()
    {
        $dossier = $this->processor->getDossier(
            $this->patientGroup([$this->pid, $this->gravida])
        );

        $this->assertSame(['client'], $this->sectionTypes($dossier));
        $this->assertFalse($dossier->getResource()->hasSection('intake'));
    }

    public function testVisitIsDroppedAndAReportGroupIsApplied()
    {
        $dossier = $this->processor->getDossier(
            $this->patientGroup([$this->pid, $this->pv1, $this->report])
        );

        $this->assertSame(['client', 'intake', 'echo'], $this->sectionTypes($dossier));
        $this->assertFalse($dossier->getResource()->hasSection('consult'));
        $this->assertSame(
            '1',
            (string) $dossier->getResource()->getSection('intake')->getValue('peri22-dataelement-20010')
        );
        $this->assertSame(
            [
                'client_mobile_phone_number' => '0619056432',
                'client_email_address' => 'acmetest@hotmail.com',
                'order_number' => '819',
                'filler_application' => 'ACME',
                'observation_time' => '2016-05-13 13:03:00',
                'result_status' => 'F',
                'referring_practice' => 'praktijkveendam',
            ],
            $dossier->getMetadata()
        );
    }

    private function patientGroup(array $parts)
    {
        $group = new SegmentGroup();
        foreach ($parts as $part) {
            $group->push($part);
        }

        return $group;
    }

    private function sectionTypes(DossierInterface $dossier)
    {
        $types = [];
        foreach ($dossier->getResource()->getSections() as $section) {
            $types[] = $section->getType();
        }

        return $types;
    }
}
