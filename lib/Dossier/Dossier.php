<?php

namespace Hl7Peri22x\Dossier;

use DomDocument;

use Hl7Peri22x\Document\DocumentFactory;
use Hl7Peri22x\Document\DocumentInterface;
use Peri22x\Attachment\AttachmentFactory;
use Peri22x\Resource\Resource;

/**
 * Model of a Hub dossier.
 */
class Dossier implements DossierInterface
{
    /**
     * @var \Peri22x\Attachment\AttachmentFactory
     */
    private $attachmentFactory;
    /**
     * @var \Hl7Peri22x\Document\DocumentFactory
     */
    private $documentFactory;
    /**
     * @var \Hl7Peri22x\Document\DocumentInterface[]
     */
    private $embeddedFiles = [];
    /**
     * @var array
     */
    private $metadata = [];
    /**
     * @var \Peri22x\Resource\Resource
     */
    private $resource;

    public function __construct(
        AttachmentFactory $attachmentFactory,
        DocumentFactory $documentFactory
    ) {
        $this->attachmentFactory = $attachmentFactory;
        $this->documentFactory = $documentFactory;
    }

    public function toXmlDocument($filename = 'dossier.xml')
    {
        $xml = new DomDocument('1.0', 'UTF-8');
        $xml->formatOutput = true;
        $xml->appendChild($this->resource->toXmlNode($xml));

        $xmlDoc = $this->documentFactory->createXmlDocument($xml);
        return $xmlDoc;
    }

    public function getEmbeddedFiles()
    {
        return $this->embeddedFiles;
    }

    public function setResource(Resource $resource)
    {
        $this->resource = $resource;
        if (!sizeof($this->embeddedFiles)) {
            return;
        }
        foreach ($this->embeddedFiles as $embeddedFiles) {
            $this->registerAttachment($embeddedFile);
        }
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function addFileData($data)
    {
        $embeddedFile = $this->documentFactory->createEmbeddedDocument($data);
        $this->embeddedFiles[] = $embeddedFile;

        if ($this->resource) {
            $this->registerAttachment($embeddedFile);
        }
    }

    public function addMetadata($name, $value)
    {
        $this->metadata[$name] = $value;
    }

    public function hasMetadata($name)
    {
        return array_key_exists($name, $this->metadata);
    }

    public function getMetadata($name = null)
    {
        if ($name === null) {
            return $this->metadata;
        } elseif (!array_key_exists($name, $this->metadata)) {
            return null;
        }
        return $this->metadata[$name];
    }

    private function registerAttachment(DocumentInterface $embeddedFile)
    {
        $attachment = $this->attachmentFactory->create();
        $attachment->setMimeType($embeddedFile->getMimeType());
        $this->resource->addAttachment($attachment);
    }
}
