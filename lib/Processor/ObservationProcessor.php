<?php

namespace Hl7Peri22x\Processor;

use DateTime;

use Hl7v2\DataType\SimpleDataTypeInterface;
use Hl7v2\DataType\XpnDataType;
use Hl7v2\Segment\Group\SegmentGroup;
use Hl7v2\Segment\ObrSegment;
use Hl7v2\Segment\ObxSegment;
use Hl7v2\Segment\PidSegment;
use Hl7v2\Segment\Pv1Segment;
use Peri22x\Resource\ResourceFactory;
use Peri22x\Section\SectionFactory;
use Peri22x\Section\SectionInterface;

use Hl7Peri22x\Dossier\DossierFactory;
use Hl7Peri22x\Dossier\DossierInterface;

/**
 * Convert a group of HL7 Segments, representing an Observation report and
 * header, into a Dossier.
 */
class ObservationProcessor
{
    private $dossierFac;
    private $resourceFac;
    private $sectionFac;

    public function __construct(
        DossierFactory $dossierFac,
        ResourceFactory $resourceFac,
        SectionFactory $sectionFac
    ) {
        $this->dossierFac = $dossierFac;
        $this->resourceFac = $resourceFac;
        $this->sectionFac = $sectionFac;
    }

    public function getDossier(SegmentGroup $patient)
    {
        $dossier = $this->dossierFac->create();
        $dossier
            ->setResource($this->resourceFac->create(DossierInterface::RESOURCE_TYPE))
        ;

        $patient->rewind();

        $pid = null;
        $pv1 = null;
        if ($patient->current() instanceof PidSegment) {
            $pid = $patient->current();
        }
        $patient->next();
        if ($patient->valid()
            && $patient->current() instanceof Pv1Segment
        ) {
            $pv1 = $patient->current();
            $patient->next();
        }

        $this->populateDossierWithClientSection($dossier, $pid, $pv1);

        for (; $patient->valid(); $patient->next()) {
            if ($patient->current() instanceof SegmentGroup) {
                $this->populateDossierWithReport($dossier, $patient->current());
            }
        }

        return $dossier;
    }

    private function populateDossierWithClientSection(
        DossierInterface $dossier,
        PidSegment $pid,
        Pv1Segment $pv1 = null
    ) {
        $section = $this->sectionFac->create(SectionInterface::TYPE_CLIENT);
        $dossier->getResource()->addSection($section);

        $now = new DateTime;
        $section->setCreateStamp($now->format(SectionInterface::TIMESTAMP_FORMAT));

        if ($pid->getFieldSetId() !== null) {
            $section->setId($this->getValue($pid->getFieldSetId()));
        }

        foreach ($pid->getFieldPatientIdentifierList() as $id) {
            if ($id->getIdNumber() !== null
                && $id->getIdNumber()->hasValue()
                && $id->getIdentifierTypeCode() !== null
                && $id->getIdentifierTypeCode()->hasValue()
            ) {
                $code = $this->getValue($id->getIdentifierTypeCode());
                $concept = null;
                if ($code == 'NNNLD') {
                    $concept = 'peri22-dataelement-10030'; // BSN (does id type NNNLD, National Person Identifier for NLD, refer to BSN?)
                } elseif ($code == 'PI') {
                    $concept = 'peri22-dataelement-10031'; // lokale persoonsidentificatie?
                }
                if ($concept) {
                    $section->addValue(
                        $concept,
                        $this->getValue($id->getIdNumber())
                    );
                }
            }
        }

        if ($pid->getFieldDatetimeOfBirth() !== null
            && $pid->getFieldDatetimeOfBirth()->getTime() !== null
            && $pid->getFieldDatetimeOfBirth()->getTime()->hasValue()
        ) {
            $section->addValue(
                'peri22-dataelement-10040', // Geboortedatum (Birth date)
                $this->getDateValue($pid->getFieldDatetimeOfBirth()->getTime())
            );
        }

        foreach ($pid->getFieldPatientName() as $nameData) {
            if (! $nameData->getNameTypeCode()->hasValue()
                || $nameData->getNameTypeCode()->getValue() == 'L'
            ) {
                $this->addLegalNameValue($section, $nameData);
            } elseif ($nameData->getNameTypeCode()->hasValue()
                && $nameData->getNameTypeCode()->getValue() == 'N'
            ) {
                $this->addNicknameValue($section, $nameData);
            }
        }

        foreach ($pid->getFieldPatientAddress() as $addr) {
            if ($addr->getStreetAddress() !== null) {
                $streetAddr = $addr->getStreetAddress();
                if ($streetAddr->getStreetOrMailingAddress() !== null &&
                    $streetAddr->getStreetOrMailingAddress()->hasValue()
                ) {
                    $section->addValue(
                        'peri22-dataelement-10301', // straatnaam
                        $this->getValue($streetAddr->getStreetOrMailingAddress())
                    );
                } elseif ($streetAddr->getStreetName() !== null &&
                    $streetAddr->getStreetName()->hasValue()
                ) {
                    $section->addValue(
                        'peri22-dataelement-10301', // straatnaam
                        $this->getValue($streetAddr->getStreetName())
                    );
                    if ($streetAddr->getDwellingNumber() !== null &&
                        $streetAddr->getDwellingNumber()->hasValue()
                    ) {
                        $section->addValue(
                            'peri22-dataelement-10302', // huisnummer
                            $this->getValue($streetAddr->getDwellingNumber())
                        );
                    }
                }
            }
            if ($addr->getOtherDesignation() !== null &&
                $addr->getOtherDesignation()->hasValue()
            ) {
                $section->addValue(
                    'peri22-dataelement-10306', // gemeentenaam?
                    $this->getValue($addr->getOtherDesignation())
                );
            }
            if ($addr->getCity() !== null &&
                $addr->getCity()->hasValue()
            ) {
                $section->addValue(
                    'peri22-dataelement-10305', // plaats
                    $this->getValue($addr->getCity())
                );
            }
            if ($addr->getZipOrPostalCode() !== null &&
                $addr->getZipOrPostalCode()->hasValue()
            ) {
                $section->addValue(
                    'peri22-dataelement-10304', // postcode
                    $this->getValue($addr->getZipOrPostalCode())
                );
            }
            if ($addr->getCountry() !== null &&
                $addr->getCountry()->hasValue()
            ) {
                $section->addValue(
                    'peri22-dataelement-10307', // land
                    $this->getValue($addr->getCountry())
                );
            }
            if ($addr->getAddressType() !== null &&
                $addr->getAddressType()->hasValue()
            ) {
                $section->addValue(
                    'peri22-dataelement-10308', // adrestype
                    $this->getValue($addr->getAddressType())
                );
            }
        }
    }

    private function addLegalNameValue(SectionInterface $section, XpnDataType $nameData)
    {
        $forenames = [];
        if ($nameData->getGivenName() && $nameData->getGivenName()->hasValue()) {
            $forenames[] = $this->getValue($nameData->getGivenName());
        }
        if ($nameData->getSecondNames() && $nameData->getSecondNames()->hasValue()) {
            $forenames[] = $this->getValue($nameData->getSecondNames());
        }
        if (!empty($forenames)) {
            $section->addValue(
                'peri22-dataelement-10042', // Voornamen (Given and Second names)
                implode(' ', $forenames)
            );
        }
        if ($nameData->getFamilyName()) {
            $section->addValue(
                'peri22-dataelement-82361', // Familienaam
                $this->getValue($nameData->getFamilyName()->getSurname())
            );
        }
    }

    private function addNicknameValue(SectionInterface $section, XpnDataType $nameData)
    {
        if ($nameData->getGivenName() && $nameData->getGivenName()->hasValue()) {
            $section->addValue(
                'peri22-dataelement-82360', // Roepnaam (Nickname/"Call me" name)
                $this->getValue($nameData->getGivenName())
            );
        }
    }

    private function populateDossierWithReport(
        DossierInterface $dossier,
        SegmentGroup $report
    ) {
        $report->rewind();
        if (!$report->current() instanceof ObrSegment) {
            return;
        }
        /**
         * @var \Hl7v2\Segment\ObrSegment $obr
         */
        $obr = $report->current();
        $obsTimestamp = null;
        $resultStatus = null;
        if ($obr->getFieldObservationDatetime() !== null &&
            $obr->getFieldObservationDatetime()->getTime() !== null &&
            $obr->getFieldObservationDatetime()->getTime()->hasValue()
        ) {
            $obsTimestamp = $this->getTimestampValue($obr->getFieldObservationDatetime()->getTime());
        }
        if ($obr->getFieldResultStatus() !== null &&
            $obr->getFieldResultStatus()->hasValue()
        ) {
            $resultStatus = $this->getValue($obr->getFieldResultStatus());
        }
        if ($obr->getFieldPrincipalResultInterpreter() !== null &&
            $obr->getFieldPrincipalResultInterpreter()->getName() !== null &&
            $obr->getFieldPrincipalResultInterpreter()->getName()->getIdNumber() !== null &&
            $obr->getFieldPrincipalResultInterpreter()->getName()->getIdNumber()->hasValue()
        ) {
            # a name!
        }
        $intakeSection = $this->sectionFac->create(SectionInterface::TYPE_INTAKE);
        $consultSection = $this->sectionFac->create(SectionInterface::TYPE_CONSULT);
        $echoSections = [];
        for (;$report->valid(); $report->next()) {
            if (!$report->current() instanceof ObxSegment) {
                continue;
            }
            /**
             * @var \Hl7v2\Segment\ObxSegment $obx
             */
            $obx = $report->current();
            $valueName = $this->getValue($obx->getFieldObservationIdentifier()->getIdentifier());
            switch (strtolower($valueName)) {
                case 'gravida':
                    $this->addObservationValue(
                        $obx,
                        $intakeSection,
                        'peri22-dataelement-20010' // graviditeit
                    );
                    break;
                case 'parity':
                    $this->addObservationValue(
                        $obx,
                        $intakeSection,
                        'peri22-dataelement-20153' // pariteit
                    );
                    break;
                case 'due_date':
                    $this->addObservationValue(
                        $obx,
                        $intakeSection,
                        'peri22-dataelement-20030', // a terme datum
                        true
                    );
                    break;
                case 'gestational_age':
                    $this->addObservationValue(
                        $obx,
                        $consultSection,
                        'peri22-dataelement-80738' // (Zwangerschap) am duur
                    );
                    break;
                case 'weight':
                    $this->addObservationValueMulti(
                        $obx,
                        $echoSections,
                        SectionInterface::TYPE_ECHO,
                        'peri22-dataelement-82304', // gewicht (gemeten)
                        false,
                        true
                    );
                    break;
                case 'hc':
                    $this->addObservationValueMulti(
                        $obx,
                        $echoSections,
                        SectionInterface::TYPE_ECHO,
                        'peri22-dataelement-60060' // hc in mm
                    );
                    break;
                case 'hcperc':
                    $this->addObservationValueMulti(
                        $obx,
                        $echoSections,
                        SectionInterface::TYPE_ECHO,
                        'peri22-dataelement-60061' // hc percentiel
                    );
                    break;
                case 'fl':
                    $this->addObservationValueMulti(
                        $obx,
                        $echoSections,
                        SectionInterface::TYPE_ECHO,
                        'peri22-dataelement-60080' // fl in mm
                    );
                    break;
                case 'flperc':
                    $this->addObservationValueMulti(
                        $obx,
                        $echoSections,
                        SectionInterface::TYPE_ECHO,
                        'peri22-dataelement-60081' // fl percentiel
                    );
                    break;
                case 'ac':
                    $this->addObservationValueMulti(
                        $obx,
                        $echoSections,
                        SectionInterface::TYPE_ECHO,
                        'peri22-dataelement-60100' // ac in mm
                    );
                    break;
                case 'acperc':
                    $this->addObservationValueMulti(
                        $obx,
                        $echoSections,
                        SectionInterface::TYPE_ECHO,
                        'peri22-dataelement-60101' // ac percentiel
                    );
                    break;
                case 'placentaloc':
                    $this->addObservationValueMulti(
                        $obx,
                        $echoSections,
                        SectionInterface::TYPE_ECHO,
                        'peri22-dataelement-80946' // placentalokalisatie
                    );
                    break;
                case 'rapport':
                    $this->extractEmbeddedFile(
                        $dossier,
                        $obx
                    );
                    break;
            }
        }
        $now = (new DateTime)->format(SectionInterface::TIMESTAMP_FORMAT);
        if (!empty($intakeSection->getValues())) {
            $intakeSection->setCreateStamp($now);
            if ($obsTimestamp) {
                $intakeSection->setEffectStamp($obsTimestamp);
            }
            $dossier->getResource()->addSection($intakeSection);
        }
        if (!empty($consultSection->getValues())) {
            $consultSection->setCreateStamp($now);
            if ($obsTimestamp) {
                $consultSection->setEffectStamp($obsTimestamp);
            }
            $dossier->getResource()->addSection($consultSection);
        }
        foreach (array_values($echoSections) as $echoSection) {
            if (!empty($echoSection->getValues())) {
                $echoSection->setCreateStamp($now);
                if ($obsTimestamp) {
                    $echoSection->setEffectStamp($obsTimestamp);
                }
                $dossier->getResource()->addSection($echoSection);
            }
        }
    }

    private function addObservationValue(
        ObxSegment $obx,
        SectionInterface $section,
        $concept,
        $isDate = false,
        $hasUnit = false
    ) {
        foreach ($obx->getFieldObservationValue() as $obsVal) {
            $v = $this->getValue($obsVal);
            if ($isDate) {
                $v = $this->normaliseDate($v);
            }
            if ($hasUnit) {
                $v .= ' ' . $this->getValue($obx->getFieldUnits()->getIdentifier());
            }
            $section->addValue($concept, $v);
        }
    }

    /*
     * Call this for the kinds of observation value which exhibit a SubId.
     * The SubId is used to distinguish one set observations from another.
     */
    private function addObservationValueMulti(
        ObxSegment $obx,
        &$sections,
        $sectionType,
        $concept,
        $isDate = false,
        $hasUnit = false
    ) {
        $subId = $this->getValue($obx->getFieldObservationSubid());
        if (!array_key_exists($subId, $sections)) {
            $sections[$subId] = $this->sectionFac->create($sectionType);
        }
        $this->addObservationValue(
            $obx,
            $sections[$subId],
            $concept,
            $isDate,
            $hasUnit
        );
    }

    private function extractEmbeddedFile(DossierInterface $dossier, ObxSegment $obx)
    {
        foreach ($obx->getFieldObservationValue() as $obsVal) {
            $fileData = base64_decode($obsVal->getValue(), true);
            $dossier->addFileData($fileData);
        }
    }

    private function getValue(SimpleDataTypeInterface $data)
    {
        return $this->normaliseEncoding(
            $data->getValue(),
            $data->getCharacterEncoding()
        );
    }

    private function getDateValue(SimpleDataTypeInterface $data)
    {
        return $this->normaliseDate(
            $this->normaliseEncoding(
                $data->getValue(),
                $data->getCharacterEncoding()
            )
        );
    }

    private function getTimestampValue(SimpleDataTypeInterface $data)
    {
        return $this->normaliseTimestamp(
            $this->normaliseEncoding(
                $data->getValue(),
                $data->getCharacterEncoding()
            )
        );
    }

    private function normaliseEncoding($value, $encoding)
    {
        return mb_convert_encoding($value, 'utf8', $encoding);
    }

    private function normaliseDate($value)
    {
        $dt = null;
        try {
            $dt = new DateTime($value);
        } catch (Exception $e) {
            throw new ProcessorError(
                "Unable to normalise a date/time with value \"{$value}\".",
                null,
                $e
            );
        }
        return $dt->format(SectionInterface::DATETIME_FORMAT);
    }

    private function normaliseTimestamp($value)
    {
        $dt = null;
        try {
            $dt = new DateTime($value);
        } catch (Exception $e) {
            throw new ProcessorError(
                "Unable to normalise a timestamp with value \"{$value}\".",
                null,
                $e
            );
        }
        return $dt->format(SectionInterface::TIMESTAMP_FORMAT);
    }
}
