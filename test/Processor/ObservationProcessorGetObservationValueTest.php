<?php

namespace Hl7Peri22x\Test\Processor;

use Mimey\MimeTypes;
use Peri22x\Attachment\AttachmentFactory;
use Peri22x\Resource\ResourceFactory;
use Peri22x\Section\SectionFactory;
use Peri22x\Value\ValueFactory;
use PHPUnit_Framework_TestCase;
use ReflectionMethod;

use Hl7v2\DataType\SimpleDataTypeInterface;
use Hl7v2\DataType\TsDataType;
use Hl7v2\Segment\ObxSegment;

use Hl7Peri22x\Document\DocumentFactory;
use Hl7Peri22x\Dossier\DossierFactory;
use Hl7Peri22x\Processor\ObservationProcessor;
use Hl7Peri22x\TextFilter\EscapeSequenceFilter;
use Hl7Peri22x\Transformer\IdentityTransformer;

class ObservationProcessorGetObservationValueTest extends PHPUnit_Framework_TestCase
{
    private $processor;

    protected function setUp()
    {
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
    }

    public function testSameUnitReturnsTheObservationUnchanged()
    {
        $obx = $this->segment(
            array($this->scalar('251')),
            $this->units('mm')
        );

        $this->assertSame('251', $this->observationValue($obx, 'mm', false));
    }

    public function testDateFlagFormatsTimestampsAndLeavesOtherValuesNull()
    {
        $obx = $this->segment(array(
            $this->timestamp('20160809000000'),
            new \stdClass(),
        ));

        // A value that is not a timestamp stays null, and implode keeps the separator.
        $this->assertSame(
            '2016-08-09 00:00:00 ',
            $this->observationValue($obx, null, true)
        );
    }

    private function observationValue(ObxSegment $obx, $unit, $isDate)
    {
        $method = new ReflectionMethod($this->processor, 'getObservationValue');
        $method->setAccessible(true);

        return $method->invoke($this->processor, $obx, $unit, $isDate);
    }

    private function segment(array $values, $units = null)
    {
        return new class($values, $units) extends ObxSegment {
            private $values;
            private $units;

            public function __construct(array $values, $units)
            {
                $this->values = $values;
                $this->units = $units;
            }

            public function getFieldObservationValue()
            {
                return $this->values;
            }

            public function getFieldUnits()
            {
                return $this->units;
            }
        };
    }

    private function timestamp($value)
    {
        $time = $this->scalar($value);

        return new class($time) extends TsDataType {
            private $time;

            public function __construct(SimpleDataTypeInterface $time)
            {
                $this->time = $time;
            }

            public function getTime()
            {
                return $this->time;
            }
        };
    }

    private function units($identifier)
    {
        $identifier = $this->scalar($identifier);

        return new class($identifier) {
            private $identifier;

            public function __construct(SimpleDataTypeInterface $identifier)
            {
                $this->identifier = $identifier;
            }

            public function getIdentifier()
            {
                return $this->identifier;
            }
        };
    }

    private function scalar($value)
    {
        return new class($value) implements SimpleDataTypeInterface {
            private $value;

            public function __construct($value)
            {
                $this->value = $value;
            }

            public function hasValue()
            {
                return $this->value !== null && $this->value !== '';
            }

            public function getValue()
            {
                return $this->value;
            }

            public function getCharacterEncoding()
            {
                return 'ISO-8859-1';
            }
        };
    }
}
