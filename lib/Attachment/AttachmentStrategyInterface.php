<?php

namespace Hl7Peri22x\Attachment;

use Peri22x\Resource\Resource;

use Hl7Peri22x\Document\EmbeddedDocument;

interface AttachmentStrategyInterface
{
    /**
     * Process a collection of instances of EmbeddedDocument, registering with
     * a Resource a collection of corresponding Attachments.
     *
     * The aim of implementing strategies is to associate instances of
     * EmbeddedDocument with instances of Attachment so that the latter refer
     * to the former when stored in separate repositories.  That is, attachments
     * of a resource stored in the Hub refer to objects retrieved from storage
     * by ResourceProxy.
     *
     * @param \Peri22x\Resource\Resource $resource
     * @param array|\Hl7Peri22x\Document\EmbeddedDocument[] $embeddedDocuments
     * @param array $options
     */
    public function process(
        Resource $resource,
        array $embeddedDocuments,
        array $options = []
    );
}
