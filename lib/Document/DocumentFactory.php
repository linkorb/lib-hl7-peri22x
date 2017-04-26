<?php

namespace Hl7Peri22x\Document;

use DomDocument;

class DocumentFactory
{
    /**
     * Create an XmlDocument with the supplied DomDocument.
     *
     * @param DomDocument $domDocument
     * @return \Hl7Peri22x\Document\DocumentInterface
     */
    public function createXmlDocument(DomDocument $domDocument)
    {
        return new XmlDocument($domDocument);
    }

    /**
     * Create an EmbeddedDocument with the supplied data.
     *
     * @param string $data
     * @return \Hl7Peri22x\Document\DocumentInterface
     */
    public function createEmbeddedDocument($data)
    {
        return new EmbeddedDocument($data);
    }
}
