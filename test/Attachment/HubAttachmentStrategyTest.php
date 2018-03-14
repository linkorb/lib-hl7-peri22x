<?php

namespace Hl7Peri22x\Test\Attachments;

use Peri22x\Attachment\AttachmentFactory;
use Peri22x\Resource\Resource;
use PHPUnit_Framework_TestCase;

use Hl7Peri22x\Attachment\HubAttachmentStrategy;
use Hl7Peri22x\Document\EmbeddedDocument;

class HubAttachmentStrategyTest extends PHPUnit_Framework_TestCase
{
    private $opts;
    private $resource;
    private $strategy;

    protected function setUp()
    {
        $this->opts = ['storage_key' => 'a-key'];
        $this->resource = new Resource('a-resource-type');
        $this->strategy = new HubAttachmentStrategy();
        $this->strategy->setAttachmentFactory(new AttachmentFactory);
    }

    public function testProcessWillThrowInvalidArgWhenStorageKeyOptIsAbsent()
    {
        $this->setExpectedException('InvalidArgumentException');
        $this->strategy->process($this->resource, ['looks-like-a-doc']);
    }

    public function testProcessWillNotDiambiguateDocBasenamesUnlessTheyConflict()
    {
        $doc1 = new EmbeddedDocument('', 'report', 'text/plain');
        $doc2 = new EmbeddedDocument('', 'summary', 'text/plain');
        $docs = [$doc1, $doc2];

        $this->strategy->process($this->resource, $docs, $this->opts);

        $this->assertSame('report', $doc1->getBasename());
        $this->assertSame('summary', $doc2->getBasename());
    }

    public function testProcessWillDiambiguateDocBasenamesWhenTheyConflict()
    {
        $doc1 = new EmbeddedDocument('', 'report', 'text/plain');
        $doc2 = new EmbeddedDocument('', 'report', 'text/plain');
        $doc3 = new EmbeddedDocument('', 'summary', 'text/plain');
        $doc4 = new EmbeddedDocument('', 'report', 'text/plain');
        $doc5 = new EmbeddedDocument('', 'summary', 'text/plain');
        $docs = [$doc1, $doc2, $doc3, $doc4, $doc5];

        $this->strategy->process($this->resource, $docs, $this->opts);

        $this->assertSame('report-1', $doc1->getBasename());
        $this->assertSame('report-2', $doc2->getBasename());
        $this->assertSame('summary-1', $doc3->getBasename());
        $this->assertSame('report-3', $doc4->getBasename());
        $this->assertSame('summary-2', $doc5->getBasename());
    }

    public function testProcessWillGiveDocumentsUniqueSequentialStorageKey()
    {
        $doc1 = new EmbeddedDocument('', 'report', 'text/plain');
        $doc2 = new EmbeddedDocument('', 'summary', 'text/plain');
        $docs = [$doc1, $doc2];

        $this->strategy->process($this->resource, $docs, $this->opts);

        $this->assertSame('1@a-key', $doc1->getStorageKey());
        $this->assertSame('2@a-key', $doc2->getStorageKey());
    }

    public function testProcessWillGiveAttachmentsUniqueSequentialIdAttribute()
    {
        $doc1 = new EmbeddedDocument('', 'report', 'text/plain');
        $doc2 = new EmbeddedDocument('', 'summary', 'text/plain');
        $docs = [$doc1, $doc2];

        $this->strategy->process($this->resource, $docs, $this->opts);

        $attachments = $this->resource->getAttachments();

        $this->assertEquals('1', $attachments[0]->getAttributes()['id']);
        $this->assertEquals('2', $attachments[1]->getAttributes()['id']);
    }

    public function testProcessWillGiveAttachmentsMimetypeOfCorrespondingDocument()
    {
        $doc1 = new EmbeddedDocument('', 'report', 'text/plain');
        $doc2 = new EmbeddedDocument('', 'summary', 'text/csv');
        $docs = [$doc1, $doc2];

        $this->strategy->process($this->resource, $docs, $this->opts);

        $attachments = $this->resource->getAttachments();

        $this->assertEquals('text/plain', $attachments[0]->getAttributes()['mimeType']);
        $this->assertEquals('text/csv', $attachments[1]->getAttributes()['mimeType']);
    }

    public function testProcessWillGenerateAttachmentsFilenameFromCorrespondingDocument()
    {
        $doc1 = new EmbeddedDocument('', 'report', 'text/plain', 'txt');
        $doc2 = new EmbeddedDocument('', 'summary', 'text/csv', 'csv');
        $doc3 = new EmbeddedDocument('', 'report', 'application/pdf', 'pdf');
        $docs = [$doc1, $doc2, $doc3];

        $this->strategy->process($this->resource, $docs, $this->opts);

        $attachments = $this->resource->getAttachments();

        $this->assertEquals('report-1.txt', $attachments[0]->getAttributes()['filename']);
        $this->assertEquals('summary.csv', $attachments[1]->getAttributes()['filename']);
        $this->assertEquals('report-2.pdf', $attachments[2]->getAttributes()['filename']);
    }
}
