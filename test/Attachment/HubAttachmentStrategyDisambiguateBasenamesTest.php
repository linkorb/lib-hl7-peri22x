<?php

namespace Hl7Peri22x\Test\Attachment;

use PHPUnit_Framework_TestCase;
use ReflectionMethod;

use Hl7Peri22x\Attachment\HubAttachmentStrategy;
use Hl7Peri22x\Document\EmbeddedDocument;

class HubAttachmentStrategyDisambiguateBasenamesTest extends PHPUnit_Framework_TestCase
{
    public function testLeavesABasenameThatOccursOnceUnchanged()
    {
        $report = $this->document('report');

        $this->disambiguateBasenames(array($report));

        $this->assertSame('report', $report->getBasename());
    }

    public function testSuffixesEveryCopyWhenABasenameOccursMoreThanOnce()
    {
        $first = $this->document('report');
        $second = $this->document('report');

        $this->disambiguateBasenames(array($first, $second));

        $this->assertSame('report-1', $first->getBasename());
        $this->assertSame('report-2', $second->getBasename());
    }

    private function document($basename)
    {
        return new EmbeddedDocument('', $basename, 'text/plain');
    }

    private function disambiguateBasenames(array $documents)
    {
        $method = new ReflectionMethod(
            'Hl7Peri22x\Attachment\HubAttachmentStrategy',
            'disambiguateBasenames'
        );
        $method->setAccessible(true);
        $method->invoke(new HubAttachmentStrategy(), $documents);
    }
}
