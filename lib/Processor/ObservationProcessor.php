<?php

namespace Hl7Peri22x\Processor;

use DateTime;

use Hl7v2\DataType\EdDataType;
use Hl7v2\DataType\SimpleDataTypeInterface;
use Hl7v2\DataType\TsDataType;
use Hl7v2\DataType\XpnDataType;
use Hl7v2\DataType\XtnDataType;
use Hl7v2\Encoding\EncodingParameters;
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
use Hl7Peri22x\Processor\Helper\DateTimeHelper;
use Hl7Peri22x\TextFilter\EscapeSequenceFilter;
use Hl7Peri22x\Transformer\ValueTransformerInterface;

/**
 * Convert a group of HL7 Segments, representing an Observation report and
 * header, into a Dossier.
 */
class ObservationProcessor
{
    private $dossierFac;
    private $escapeSequenceFilter;
    private $observationValueTransformer;
    private $resourceFac;
    private $sectionFac;

    public function __construct(
        DossierFactory $dossierFac,
        ResourceFactory $resourceFac,
        SectionFactory $sectionFac,
        ValueTransformerInterface $observationValueTransformer,
        EscapeSequenceFilter $escapeSequenceFilter
    ) {
        $this->dossierFac = $dossierFac;
        $this->resourceFac = $resourceFac;
        $this->sectionFac = $sectionFac;
        $this->observationValueTransformer = $observationValueTransformer;
        $this->escapeSequenceFilter = $escapeSequenceFilter;
    }

    public function setObservationValueTransformer(ValueTransformerInterface $transformer)
    {
        $this->observationValueTransformer = $transformer;
    }

    /**
     * Set the encoding parameters that were used to parse the Observation.
     *
     * @param \Hl7v2\Encoding\EncodingParameters $parameters
     */
    public function setEncodingParameters(EncodingParameters $parameters)
    {
        $this->escapeSequenceFilter->setEncodingParameters($parameters);
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

        if ($pid->getFieldSetId() && $pid->getFieldSetId()->hasValue()) {
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
            if (!$nameData->getNameTypeCode()->hasValue()
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

        foreach ($pid->getFieldPhoneNumberHome() as $contactDetails) {
            if (null === $contactDetails->getTelepcommunicationEquipmentType()
                || !$contactDetails->getTelepcommunicationEquipmentType()->hasValue()
            ) {
                continue;
            }
            $contactType = $this->getValue($contactDetails->getTelepcommunicationEquipmentType());
            $contactUsage = $contactDetails->getTelecommunicationUseCode()
                && $contactDetails->getTelecommunicationUseCode()->hasValue()
                ? $this->getValue($contactDetails->getTelecommunicationUseCode())
                : null
            ;
            if ('CP' === $contactType) {
                $value = $this->extractPhoneNumber($contactDetails);
                if (!$value) {
                    continue;
                }
                $dossier->addMetadata('client_mobile_phone_number', $value);
            } elseif ('PH' === $contactType) {
                $value = $this->extractPhoneNumber($contactDetails);
                if (!$value) {
                    continue;
                }
                $dossier->addMetadata('client_phone_number', $value);
            } elseif ('X.400' === $contactType && 'NET' === $contactUsage) {
                $value = $this->getValue($contactDetails->getEmailAddress());
                if (!$value) {
                    continue;
                }
                $dossier->addMetadata('client_email_address', $value);
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
         * @var \Hl7v2\Segment\ObrSegment
         */
        $obr = $report->current();

        $intakeSection = $this->sectionFac->create(SectionInterface::TYPE_INTAKE);
        $consultSection = $this->sectionFac->create(SectionInterface::TYPE_CONSULT);
        $echoSection = $this->sectionFac->create(SectionInterface::TYPE_ECHO);

        if ($obr->getFieldFillerOrderNumber() !== null &&
            $obr->getFieldFillerOrderNumber()->getEntityIdentifier() !== null &&
            $obr->getFieldFillerOrderNumber()->getEntityIdentifier()->hasValue()
        ) {
            $dossier->addMetadata(
                'order_number',
                $this->getValue($obr->getFieldFillerOrderNumber()->getEntityIdentifier())
            );
        }
        if ($obr->getFieldFillerOrderNumber() !== null &&
            $obr->getFieldFillerOrderNumber()->getNamespaceId() !== null &&
            $obr->getFieldFillerOrderNumber()->getNamespaceId()->hasValue()
        ) {
            $dossier->addMetadata(
                'filler_application',
                $this->getValue($obr->getFieldFillerOrderNumber()->getNamespaceId())
            );
        }
        if ($obr->getFieldObservationDatetime() !== null &&
            $obr->getFieldObservationDatetime()->getTime() !== null &&
            $obr->getFieldObservationDatetime()->getTime()->hasValue()
        ) {
            $dossier->addMetadata(
                'observation_time',
                $this->getTimestampValue($obr->getFieldObservationDatetime()->getTime())
            );
            $echoSection->addValue(
                'peri22-dataelement-50020',
                $this->getDateValue($obr->getFieldObservationDatetime()->getTime())
            );
        }
        if ($obr->getFieldResultStatus() !== null &&
            $obr->getFieldResultStatus()->hasValue()
        ) {
            $dossier->addMetadata(
                'result_status',
                $this->getValue($obr->getFieldResultStatus())
            );
        }
        if ($obr->getFieldPrincipalResultInterpreter() !== null &&
            $obr->getFieldPrincipalResultInterpreter()->getName() !== null &&
            $obr->getFieldPrincipalResultInterpreter()->getName()->getIdNumber() !== null &&
            $obr->getFieldPrincipalResultInterpreter()->getName()->getIdNumber()->hasValue()
        ) {
            // a name!
            $echoSection->addValue(
                'peri22-dataelement-80754',
                $this->getValue($obr->getFieldPrincipalResultInterpreter()->getName()->getIdNumber())
            );
        }
        if (is_array($obr->getFieldOrderingProvider())
            && 0 < sizeof($obr->getFieldOrderingProvider())
        ) {
            $orderingProviders = $obr->getFieldOrderingProvider();
            foreach ($orderingProviders as $orderingProvider) {
                if (null === $orderingProvider->getIdNumber()
                    || !$orderingProvider->getIdNumber()->hasValue()
                ) {
                    continue;
                }
                $dossier->addMetadata(
                    'referring_practice',
                    $this->getValue($orderingProvider->getIdNumber())
                );
                break;
            }
        }
        for (; $report->valid(); $report->next()) {
            if (!$report->current() instanceof ObxSegment) {
                continue;
            }
            /**
             * @var \Hl7v2\Segment\ObxSegment
             */
            $obx = $report->current();
            $valueType = $this->getValue($obx->getFieldValueType());
            $valueName = $this->getValue($obx->getFieldObservationIdentifier()->getIdentifier());
            switch (strtolower($valueName)) {
                case 'gravida':
                    $this->addObservationValue(
                        $intakeSection,
                        'peri22-dataelement-20010', // graviditeit
                        $this->getObservationValue($obx)
                    );
                    break;
                case 'parity':
                    $this->addObservationValue(
                        $intakeSection,
                        'peri22-dataelement-20153', // pariteit
                        $this->getObservationValue($obx)
                    );
                    break;
                case 'due_date':
                    $this->addObservationValue(
                        $intakeSection,
                        'peri22-dataelement-20030', // a terme datum
                        $this->getObservationValue($obx, null, true)
                    );
                    break;
                case 'gestational_age':
                    $this->addObservationValue(
                        $echoSection,
                        'peri22-dataelement-50021', // Zwangerschapsduur op datum onderzoek,
                        $this->getObservationValue($obx, 'days')
                    );
                    break;
                case 'weight':
                    $this->addObservationValue(
                        $echoSection,
                        'peri22-dataelement-82340', // efw in grammes
                        $this->getObservationValue($obx, 'g')
                    );
                    break;
                case 'hc':
                    $this->addObservationValue(
                        $echoSection,
                        'peri22-dataelement-60060', // hc in mm
                        $this->getObservationValue($obx, 'mm'),
                        $this->getValue($obx->getFieldObservationSubid())
                    );
                    break;
                case 'hcperc':
                case 'hcp':
                    $this->addObservationValue(
                        $echoSection,
                        'peri22-dataelement-60061', // hc percentiel
                        $this->getObservationValue($obx),
                        $this->getValue($obx->getFieldObservationSubid())
                    );
                    break;
                case 'fl':
                    $this->addObservationValue(
                        $echoSection,
                        'peri22-dataelement-60100', // fl in mm
                        $this->getObservationValue($obx, 'mm'),
                        $this->getValue($obx->getFieldObservationSubid())
                    );
                    break;
                case 'flperc':
                case 'flp':
                    $this->addObservationValue(
                        $echoSection,
                        'peri22-dataelement-60101', // fl percentiel
                        $this->getObservationValue($obx),
                        $this->getValue($obx->getFieldObservationSubid())
                    );
                    break;
                case 'ac':
                    $this->addObservationValue(
                        $echoSection,
                        'peri22-dataelement-60080', // ac in mm
                        $this->getObservationValue($obx, 'mm'),
                        $this->getValue($obx->getFieldObservationSubid())
                    );
                    break;
                case 'acperc':
                case 'acp':
                    $this->addObservationValue(
                        $echoSection,
                        'peri22-dataelement-60081', // ac percentiel
                        $this->getObservationValue($obx),
                        $this->getValue($obx->getFieldObservationSubid())
                    );
                    break;
                case 'placentaloc':
                    $value = $this
                        ->observationValueTransformer
                        ->transform(
                            $valueName,
                            $this->getObservationValue($obx)
                        )
                    ;
                    $this->addObservationValue(
                        $echoSection,
                        'peri22-dataelement-80946', // placentalokalisatie
                        $value,
                        $this->getValue($obx->getFieldObservationSubid())
                    );
                    break;
                case 'diagnosis':
                    $this->addMultilineObservation(
                        $echoSection,
                        'peri22x-echo-diagnose',
                        $this->getMultilineObservationValue($obx)
                    );
                    break;
                case 'conclusion':
                    $this->addMultilineObservation(
                        $echoSection,
                        'peri22x-echo-conclusie',
                        $this->getMultilineObservationValue($obx)
                    );
                    break;
                case 'rapport':
                    $this->extractEmbeddedFile(
                        $dossier,
                        $obx,
                        $valueType
                    );
                    break;
            }
        }
        $now = (new DateTime)->format(SectionInterface::TIMESTAMP_FORMAT);
        if (!empty($intakeSection->getValues())) {
            $intakeSection->setCreateStamp($now);
            if ($dossier->hasMetadata('observation_time')) {
                $intakeSection->setEffectStamp($dossier->getMetadata('observation_time'));
            }
            $dossier->getResource()->addSection($intakeSection);
        }
        if (!empty($consultSection->getValues())) {
            $consultSection->setCreateStamp($now);
            if ($dossier->hasMetadata('observation_time')) {
                $consultSection->setEffectStamp($dossier->getMetadata('observation_time'));
            }
            $dossier->getResource()->addSection($consultSection);
        }
        if (!empty($echoSection->getValues())) {
            $echoSection->setCreateStamp($now);
            if ($dossier->hasMetadata('observation_time')) {
                $echoSection->setEffectStamp($dossier->getMetadata('observation_time'));
            }
            $dossier->getResource()->addSection($echoSection);
        }
    }

    private function getObservationValue(
        ObxSegment $obx,
        $unit = null,
        $isDate = false
    ) {
        $values = [];
        foreach ($obx->getFieldObservationValue() as $obsVal) {
            $value = null;
            if ($isDate) {
                if ($obsVal instanceof TsDataType) {
                    $value = $this->getDateValue($obsVal->getTime());
                }
            } else {
                $value = $this->getValue($obsVal);
            }
            if ($unit) {
                $value = $this->unitConversion(
                    $value,
                    $this->getValue($obx->getFieldUnits()->getIdentifier()),
                    $unit
                );
            }
            $values[] = $value;
        }
        return implode(' ', $values);
    }

    private function addObservationValue(
        SectionInterface $section,
        $conceptId,
        $value,
        $subId = null
    ) {
        if ($subId) {
            $section->addValue($conceptId, $value, ['repeat' => $subId]);
        } else {
            $section->addValue($conceptId, $value);
        }
    }

    private function getMultilineObservationValue(ObxSegment $obx)
    {
        $lines = [];
        foreach ($obx->getFieldObservationValue() as $obsVal) {
            $lines[] = $this->getValue($obsVal);
        }

        return implode("\n", $lines);
    }

    private function addMultilineObservation(
        SectionInterface $section,
        $conceptId,
        $value
    ) {
        $section->addCdataValue($conceptId, $value);
    }

    private function unitConversion($value, $unit, $targetUnit)
    {
        if ($unit === $targetUnit) {
            return $value;
        }
        $conversions = [
            'Kg' => [
                'g' => function ($x) {
                    return 1e-3 * (float) $x;
                },
            ],
            'g' => [
                'Kg' => function ($x) {
                    return 1e3 * (float) $x;
                },
            ],
            'days' => [
                '' => function ($x) {
                    return DateTimeHelper::convertToDays($x);
                },
            ],
        ];
        if (!array_key_exists($targetUnit, $conversions)
            || !array_key_exists($unit, $conversions[$targetUnit])
        ) {
            return ''; // no value is better than the wrong value
        }
        return (string) $conversions[$targetUnit][$unit]($value);
    }

    private function extractEmbeddedFile(DossierInterface $dossier, ObxSegment $obx, $valueType)
    {
        $observationId = strtolower($this->getValue($obx->getFieldObservationIdentifier()->getIdentifier()));

        if ($valueType === 'ED') {
            foreach ($obx->getFieldObservationValue() as $obsVal) {
                $dossier->addFileData($this->extractEncapsulatedData($obsVal), $observationId);
            }
        }
        if ($valueType === 'TX') {
            $lines = [];
            foreach ($obx->getFieldObservationValue() as $obsVal) {
                $lines[] = $this->getValue($obsVal);
            }
            $dossier->addFileData(implode("\n", $lines) . "\n", $observationId);
        }
    }

    private function extractEncapsulatedData(EdDataType $data)
    {
        $fileData = null;
        $enc = $this->getValue($data->getEncoding());
        if ($enc === 'Base64') {
            $fileData = base64_decode($data->getData()->getValue(), true);
            if ($fileData === false) {
                throw new ProcessorError(
                    'Unable to decode Base64 encoded embedded file; encoding is invalid.'
                );
            }
        } elseif ($enc === 'A') {
            $fileData = $this->getValue($data->getData());
        } else {
            throw new ProcessorError(
                "Unable to extract embedded file encoded as \"{$enc}\"."
            );
        }
        return $fileData;
    }

    /*
     * Extract a phone number from an XTN.
     *
     * @param XtnDataType $data
     *
     * @return string|null
     */
    private function extractPhoneNumber(XtnDataType $data)
    {
        $number = null;

        if ($data->getLocalNumber() && $data->getLocalNumber()->hasValue()) {
            $num = $this->getValue($data->getLocalNumber());
            $cc = $data->getCountryCode() && $data->getCountryCode()->hasValue()
                ? $this->getValue($data->getCountryCode())
                : null
            ;
            $ac = $data->getAreaCityCode() && $data->getAreaCityCode()->hasValue()
                ? $this->getValue($data->getAreaCityCode())
                : null
            ;
            $ext = $data->getExtension() && $data->getExtension()->hasValue()
                ? $this->getValue($data->getExtension())
                : null
            ;
            $extPrefix = $data->getExtensionPrefix() && $data->getExtensionPrefix()->hasValue()
                ? $this->getValue($data->getExtensionPrefix())
                : null
            ;
            if ($cc && $ac && '0' === substr($ac, 0, 1)) {
                $ac = substr($ac, 1);
            }
            if ($cc && $ac) {
                $num = "{$cc} {$ac}{$num}";
            } elseif ($cc) {
                $num = "{$cc} {$num}";
            } elseif ($ac) {
                $num = "{$ac}{$num}";
            }
            if ($ext && $extPrefix) {
                $num .= " {$extPrefix}{$ext}";
            } elseif ($ext) {
                $num .= " {$ext}";
            }
            $number = null;
        } elseif ($data->getUnformattedTelephoneNumber() && $data->getUnformattedTelephoneNumber()->hasValue()) {
            $number = $this->getValue($data->getUnformattedTelephoneNumber());
        } elseif ($data->getTelephoneNumber() && $data->getTelephoneNumber()->hasValue()) {
            $number = $this->getValue($data->getTelephoneNumber());
        }

        return $number;
    }

    private function getValue(SimpleDataTypeInterface $data)
    {
        if ($this->escapeSequenceFilter->isValueSupported($data)) {
            return $this->escapeSequenceFilter->filter($data);
        }
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
        try {
            return DateTimeHelper::format($value);
        } catch (Exception $e) {
            throw new ProcessorError(
                "Unable to normalise a date/time with value \"{$value}\".",
                null,
                $e
            );
        }
    }

    private function normaliseTimestamp($value)
    {
        try {
            return DateTimeHelper::format($value);
        } catch (Exception $e) {
            throw new ProcessorError(
                "Unable to normalise a timestamp with value \"{$value}\".",
                null,
                $e
            );
        }
    }
}
