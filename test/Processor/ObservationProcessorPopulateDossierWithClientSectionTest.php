<?php

namespace Hl7Peri22x\Test\Processor;

use Mimey\MimeTypes;
use Peri22x\Attachment\AttachmentFactory;
use Peri22x\Resource\ResourceFactory;
use Peri22x\Section\SectionFactory;
use Peri22x\Section\SectionInterface;
use Peri22x\Value\ValueFactory;
use PHPUnit_Framework_TestCase;
use ReflectionMethod;

use Hl7v2\Encoding\EncodingParametersBuilder;
use Hl7v2\Factory\DataTypeFactory;
use Hl7v2\Factory\SegmentFactory;
use Hl7v2\Segment\PidSegment;

use Hl7Peri22x\Document\DocumentFactory;
use Hl7Peri22x\Dossier\DossierFactory;
use Hl7Peri22x\Dossier\DossierInterface;
use Hl7Peri22x\Processor\ObservationProcessor;
use Hl7Peri22x\TextFilter\EscapeSequenceFilter;
use Hl7Peri22x\Transformer\IdentityTransformer;

/**
 * Pins ObservationProcessor::populateDossierWithClientSection as it behaves
 * today. The method is private; the test calls it by reflection.
 */
class ObservationProcessorPopulateDossierWithClientSectionTest extends PHPUnit_Framework_TestCase
{
    private $encoding;
    private $segments;
    private $processor;
    private $dossiers;
    private $resources;
    private $populate;

    protected function setUp()
    {
        $this->encoding = (new EncodingParametersBuilder())
            ->withCharacterEncoding('UTF-8')
            ->withComponentSep('^')
            ->withEscapeChar('\\')
            ->withFieldSep('|')
            ->withRepetitionSep('~')
            ->withSegmentSep("\r")
            ->withSubcomponentSep('&')
            ->build()
        ;
        $this->segments = new SegmentFactory(new DataTypeFactory());
        $this->dossiers = new DossierFactory(
            new AttachmentFactory(),
            new DocumentFactory(new MimeTypes())
        );
        $this->resources = new ResourceFactory();
        $this->processor = new ObservationProcessor(
            $this->dossiers,
            $this->resources,
            new SectionFactory(new ValueFactory()),
            new IdentityTransformer(),
            new EscapeSequenceFilter()
        );
        $this->processor->setEncodingParameters($this->encoding);
        $this->populate = new ReflectionMethod(
            ObservationProcessor::class,
            'populateDossierWithClientSection'
        );
        $this->populate->setAccessible(true);
    }

    public function testBlankPidOpensClientSectionAndStoresNothing()
    {
        $dossier = $this->populatePid($this->finishablePid());
        $section = $this->client($dossier);

        $this->assertSame('client', $section->getType());
        $this->assertSame(1, $section->getAttributes()['id']);
        $this->assertSame(
            date(SectionInterface::TIMESTAMP_FORMAT),
            $section->getAttributes()['createStamp']
        );
        $this->assertSame([], $this->concepts($section));
        $this->assertSame([], $dossier->getMetadata());
    }

    public function testMissingIdentifierListWarnsAndStops()
    {
        $dossier = $this->dossier();
        try {
            $this->invoke($dossier, $this->pid());
            $this->fail('A PID with no identifier list warns in foreach.');
        } catch (\PHPUnit_Framework_Error_Warning $warning) {
            $this->assertContains('Invalid argument supplied for foreach', $warning->getMessage());
        }

        $section = $this->client($dossier);
        $this->assertSame(1, $section->getAttributes()['id']);
        $this->assertSame(
            date(SectionInterface::TIMESTAMP_FORMAT),
            $section->getAttributes()['createStamp']
        );
        $this->assertSame([], $this->concepts($section));
    }

    public function testMissingNameListWarnsAfterIdentifiers()
    {
        $pid = $this->pid();
        $this->addIdentifier($pid, '123', 'NNNLD');
        $dossier = $this->dossier();
        try {
            $this->invoke($dossier, $pid);
            $this->fail('A PID with no name list warns in foreach.');
        } catch (\PHPUnit_Framework_Error_Warning $warning) {
            $this->assertContains('Invalid argument supplied for foreach', $warning->getMessage());
        }

        $this->assertSame(
            [['peri22-dataelement-10030', '123']],
            $this->concepts($this->client($dossier))
        );
    }

    public function testSetIdBecomesTheSectionId()
    {
        $pid = $this->finishablePid();
        $pid->setFieldSetId('4');

        $section = $this->client($this->populatePid($pid));

        $this->assertSame('4', $section->getAttributes()['id']);
    }

    public function testEmptySetIdLeavesTheGeneratedSectionId()
    {
        $pid = $this->finishablePid();
        $pid->setFieldSetId('');

        $section = $this->client($this->populatePid($pid));

        $this->assertSame(1, $section->getAttributes()['id']);
    }

    public function testIdentifierWithoutNumberIsIgnored()
    {
        $pid = $this->pid();
        $this->addIdentifier($pid, '', 'NNNLD');
        $this->addNeutralName($pid);

        $this->assertSame([], $this->concepts($this->client($this->populatePid($pid))));
    }

    public function testIdentifierWithoutTypeCodeIsIgnored()
    {
        $pid = $this->pid();
        $this->addIdentifier($pid, '123', '');
        $this->addNeutralName($pid);

        $this->assertSame([], $this->concepts($this->client($this->populatePid($pid))));
    }

    public function testNationalPersonIdentifierIsStoredAsBsn()
    {
        $pid = $this->pid();
        $this->addIdentifier($pid, '123548999', 'NNNLD');
        $this->addNeutralName($pid);

        $this->assertSame(
            [['peri22-dataelement-10030', '123548999']],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testLocalPersonIdentifierIsStored()
    {
        $pid = $this->pid();
        $this->addIdentifier($pid, '64654', 'PI');
        $this->addNeutralName($pid);

        $this->assertSame(
            [['peri22-dataelement-10031', '64654']],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testUnknownIdentifierTypeIsIgnored()
    {
        $pid = $this->pid();
        $this->addIdentifier($pid, '999', 'MR');
        $this->addNeutralName($pid);

        $this->assertSame([], $this->concepts($this->client($this->populatePid($pid))));
    }

    public function testBirthDateIsStored()
    {
        $pid = $this->finishablePid();
        $pid->setFieldDatetimeOfBirth('19850317');

        $this->assertSame(
            [['peri22-dataelement-10040', '1985-03-17']],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testEmptyBirthDateIsIgnored()
    {
        $pid = $this->finishablePid();
        $pid->setFieldDatetimeOfBirth('');

        $this->assertSame([], $this->concepts($this->client($this->populatePid($pid))));
    }

    public function testNameWithoutTypeCodeIsStoredAsLegalName()
    {
        $pid = $this->pid();
        $this->addNeutralIdentifier($pid);
        $this->addName($pid, '', 'Jansen', 'Anna', 'Maria');

        $this->assertSame(
            [
                ['peri22-dataelement-10042', 'Anna Maria'],
                ['peri22-dataelement-82361', 'Jansen'],
            ],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testLegalNameTypeIsStoredAsLegalName()
    {
        $pid = $this->pid();
        $this->addNeutralIdentifier($pid);
        $this->addName($pid, 'L', 'Jansen', 'Anna', 'Maria');

        $this->assertSame(
            [
                ['peri22-dataelement-10042', 'Anna Maria'],
                ['peri22-dataelement-82361', 'Jansen'],
            ],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testNicknameTypeStoresTheGivenName()
    {
        $pid = $this->pid();
        $this->addNeutralIdentifier($pid);
        $this->addName($pid, 'N', 'Jansen', 'An', '');

        $this->assertSame(
            [['peri22-dataelement-82360', 'An']],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testOtherNameTypeIsIgnored()
    {
        $pid = $this->pid();
        $this->addNeutralIdentifier($pid);
        $this->addName($pid, 'M', 'Jansen', 'Anna', 'Maria');

        $this->assertSame([], $this->concepts($this->client($this->populatePid($pid))));
    }

    public function testMailingAddressWinsOverStreetNameAndDwelling()
    {
        $pid = $this->finishablePid();
        $this->addAddress($pid, [
            0 => 'Dam 1',
            1 => 'Kalverstraat',
            2 => '10',
        ]);

        $this->assertSame(
            [['peri22-dataelement-10301', 'Dam 1']],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testStreetNameAndDwellingAreStoredWhenMailingAddressIsEmpty()
    {
        $pid = $this->finishablePid();
        $this->addAddress($pid, [
            1 => 'Kalverstraat',
            2 => '10',
        ]);

        $this->assertSame(
            [
                ['peri22-dataelement-10301', 'Kalverstraat'],
                ['peri22-dataelement-10302', '10'],
            ],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testStreetNameWithoutDwellingOmitsTheHouseNumber()
    {
        $pid = $this->finishablePid();
        $this->addAddress($pid, [1 => 'Kalverstraat']);

        $this->assertSame(
            [['peri22-dataelement-10301', 'Kalverstraat']],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testAddressWithoutStreetOrLocalityStoresNothing()
    {
        $pid = $this->finishablePid();
        $this->addAddress($pid, []);

        $this->assertSame([], $this->concepts($this->client($this->populatePid($pid))));
    }

    public function testOtherDesignationIsStored()
    {
        $pid = $this->finishablePid();
        $this->addAddress($pid, [3 => 'Centrum']);

        $this->assertSame(
            [['peri22-dataelement-10306', 'Centrum']],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testCityIsStored()
    {
        $pid = $this->finishablePid();
        $this->addAddress($pid, [4 => 'Amsterdam']);

        $this->assertSame(
            [['peri22-dataelement-10305', 'Amsterdam']],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testPostalCodeIsStored()
    {
        $pid = $this->finishablePid();
        $this->addAddress($pid, [6 => '1000 AA']);

        $this->assertSame(
            [['peri22-dataelement-10304', '1000 AA']],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testCountryIsStored()
    {
        $pid = $this->finishablePid();
        $this->addAddress($pid, [7 => 'NLD']);

        $this->assertSame(
            [['peri22-dataelement-10307', 'NLD']],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testAddressTypeIsStored()
    {
        $pid = $this->finishablePid();
        $this->addAddress($pid, [8 => 'H']);

        $this->assertSame(
            [['peri22-dataelement-10308', 'H']],
            $this->concepts($this->client($this->populatePid($pid)))
        );
    }

    public function testPhoneWithoutEquipmentTypeIsIgnored()
    {
        $pid = $this->finishablePid();
        $this->addPhone($pid, [11 => '0612345678']);

        $stored = $this->populatePid($pid);

        $this->assertSame([], $this->concepts($this->client($stored)));
        $this->assertSame([], $stored->getMetadata());
    }

    public function testMobileNumberFromTheUnformattedFieldIsStored()
    {
        $pid = $this->finishablePid();
        $this->addPhone($pid, [2 => 'CP', 11 => '0612345678']);

        $this->assertSame(
            ['client_mobile_phone_number' => '0612345678'],
            $this->populatePid($pid)->getMetadata()
        );
    }

    /**
     * The local-number parts are formatted and then thrown away, so a mobile
     * that exists only there is not stored.
     */
    public function testLocalMobileNumberIsDiscarded()
    {
        $pid = $this->finishablePid();
        $this->addPhone($pid, [2 => 'CP', 6 => '612345678']);

        $this->assertSame([], $this->populatePid($pid)->getMetadata());
    }

    public function testHomeNumberFromTheUnformattedFieldIsStored()
    {
        $pid = $this->finishablePid();
        $this->addPhone($pid, [2 => 'PH', 11 => '0201234567']);

        $this->assertSame(
            ['client_phone_number' => '0201234567'],
            $this->populatePid($pid)->getMetadata()
        );
    }

    /**
     * The local-number parts are formatted and then thrown away, so a home
     * phone that exists only there is not stored.
     */
    public function testLocalHomeNumberIsDiscarded()
    {
        $pid = $this->finishablePid();
        $this->addPhone($pid, [2 => 'PH', 6 => '201234567']);

        $this->assertSame([], $this->populatePid($pid)->getMetadata());
    }

    public function testEmailIsStoredWhenUseIsNet()
    {
        $pid = $this->finishablePid();
        $this->addPhone($pid, [1 => 'NET', 2 => 'X.400', 3 => 'a@b.nl']);

        $this->assertSame(
            ['client_email_address' => 'a@b.nl'],
            $this->populatePid($pid)->getMetadata()
        );
    }

    public function testEmptyEmailIsNotStored()
    {
        $pid = $this->finishablePid();
        $this->addPhone($pid, [1 => 'NET', 2 => 'X.400']);

        $this->assertSame([], $this->populatePid($pid)->getMetadata());
    }

    public function testEmailWithoutNetUseIsIgnored()
    {
        $pid = $this->finishablePid();
        $this->addPhone($pid, [1 => 'PRN', 2 => 'X.400', 3 => 'a@b.nl']);

        $this->assertSame([], $this->populatePid($pid)->getMetadata());
    }

    public function testOtherEquipmentTypeIsIgnored()
    {
        $pid = $this->finishablePid();
        $this->addPhone($pid, [2 => 'FX', 11 => '0201234567']);

        $this->assertSame([], $this->populatePid($pid)->getMetadata());
    }

    private function populatePid(PidSegment $pid)
    {
        $dossier = $this->dossier();
        $this->invoke($dossier, $pid);

        return $dossier;
    }

    private function invoke(DossierInterface $dossier, PidSegment $pid)
    {
        $this->populate->invoke($this->processor, $dossier, $pid, null);
    }

    private function dossier()
    {
        $dossier = $this->dossiers->create();
        $dossier->setResource($this->resources->create(DossierInterface::RESOURCE_TYPE));

        return $dossier;
    }

    private function pid()
    {
        return $this->segments->create('PID', $this->encoding);
    }

    private function finishablePid()
    {
        $pid = $this->pid();
        $this->addNeutralIdentifier($pid);
        $this->addNeutralName($pid);

        return $pid;
    }

    private function addNeutralIdentifier(PidSegment $pid)
    {
        $this->addIdentifier($pid, '', '');
    }

    private function addNeutralName(PidSegment $pid)
    {
        $this->addName($pid, 'M', '', '', '');
    }

    private function addIdentifier(PidSegment $pid, $idNumber, $typeCode)
    {
        $args = array_fill(0, 30, '');
        $args[0] = $idNumber;
        $args[6] = $typeCode;
        call_user_func_array([$pid, 'addFieldPatientIdentifierList'], $args);
    }

    private function addName(PidSegment $pid, $typeCode, $surname, $given, $second)
    {
        $args = array_fill(0, 28, '');
        $args[0] = $surname;
        $args[5] = $given;
        $args[6] = $second;
        $args[10] = $typeCode;
        call_user_func_array([$pid, 'addFieldPatientName'], $args);
    }

    private function addAddress(PidSegment $pid, array $overrides)
    {
        $args = array_fill(0, 21, '');
        foreach ($overrides as $index => $value) {
            $args[$index] = $value;
        }
        call_user_func_array([$pid, 'addFieldPatientAddress'], $args);
    }

    private function addPhone(PidSegment $pid, array $overrides)
    {
        $args = array_fill(0, 12, '');
        foreach ($overrides as $index => $value) {
            $args[$index] = $value;
        }
        call_user_func_array([$pid, 'addFieldPhoneNumberHome'], $args);
    }

    private function client($dossier)
    {
        return $dossier->getResource()->getSection('client');
    }

    private function concepts($section)
    {
        $found = [];
        foreach ($section->getValues() as $value) {
            $attributes = $value->getAttributes();
            $found[] = [$attributes['concept'], (string) $value];
        }

        return $found;
    }
}
