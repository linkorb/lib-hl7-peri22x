<?php

namespace Hl7Peri22x\Dossier;

use DomDocument;

use Hl7Peri22x\Document\DocumentFactory;
use Peri22x\Resource\Resource;

/**
 * Model of a Hub dossier.
 */
class Dossier implements DossierInterface
{
    /**
     * @var \Hl7Peri22x\Document\DocumentFactory
     */
    private $documentFactory;
    /**
     * @var \Hl7Peri22x\Document\DocumentInterface[]
     */
    private $embeddedFiles = [];
    /**
     * @var \Peri22x\Resource\Resource
     */
    private $resource;

    public function __construct(DocumentFactory $documentFactory)
    {
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
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function addFileData($data)
    {
        $this->embeddedFiles[] = $this->documentFactory->createEmbeddedDocument($data);
    }
}
