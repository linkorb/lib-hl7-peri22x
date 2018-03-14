<?php

namespace Hl7Peri22x\Attachment;

use InvalidArgumentException;

use Peri22x\Attachment\AttachmentFactory;
use Peri22x\Attachment\AttachmentFactoryAwareInterface;
use Peri22x\Resource\Resource;

class HubAttachmentStrategy implements AttachmentStrategyInterface, AttachmentFactoryAwareInterface
{
    private $attachmentFactory;

    public function setAttachmentFactory(AttachmentFactory $attachmentFactory)
    {
        $this->attachmentFactory = $attachmentFactory;
    }

    /**
     * This implementation creates a unique sequential id attribute for each
     * attachment and a filename attribute based on the basename of the
     * corresponding embedded document.
     *
     * A numerical suffix as appended to the embedded document's basename if
     * there are mulitple documents with the same basename.
     *
     * The embedded document is given a storage key which follows the Hub
     * convention of <attachment_id>@<parent_resource_storage_key>.
     *
     * Options:-
     *
     * - "storage_key": Required, to pass to EmbeddedDocument::setStorageKey
     *
     * {@inheritdoc}
     */
    public function process(
        Resource $resource,
        array $embeddedDocuments,
        array $options = []
    ) {
        if (1 > sizeof($embeddedDocuments)) {
            return;
        }
        if (!isset($options['storage_key'])) {
            throw new InvalidArgumentException(
                'This method requires an option named "storage_key".'
            );
        }

        $this->disambiguateBasenames($embeddedDocuments);

        $sequence = 0;
        foreach ($embeddedDocuments as $doc) {
            ++$sequence;

            $doc->setStorageKey("{$sequence}@{$options['storage_key']}");

            $attachment = $this->attachmentFactory->create();
            $attachment->setId($sequence);
            $attachment->setMimeType($doc->getMimeType());
            $attachment->setFilename("{$doc->getBasename()}.{$doc->getExtension()}");

            $resource->addAttachment($attachment);
        }
    }

    private function disambiguateBasenames($embeddedDocuments)
    {
        $docNameMap = [];
        foreach ($embeddedDocuments as $doc) {
            if (!array_key_exists($doc->getBasename(), $docNameMap)) {
                $docNameMap[$doc->getBasename()] = [];
            }
            $docNameMap[$doc->getBasename()][] = $doc;
        }
        foreach ($docNameMap as $similarlyNamedDocs) {
            if (1 == sizeof($similarlyNamedDocs)) {
                continue;
            }
            $sequence = 0;
            foreach ($similarlyNamedDocs as $doc) {
                ++$sequence;
                $doc->setBasename("{$doc->getBasename()}-{$sequence}");
            }
        }
    }
}
