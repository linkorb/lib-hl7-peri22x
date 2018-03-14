<?php

namespace Hl7Peri22x\Document;

use DomDocument;
use finfo;

use Mimey\MimeTypes;

class DocumentFactory
{
    private $finfo;
    private $mimeTypes;

    public function __construct(MimeTypes $mimeTypes)
    {
        $this->mimeTypes = $mimeTypes;
        $this->finfo = new finfo(FILEINFO_MIME);
    }

    /**
     * Create an XmlDocument with the supplied DomDocument.
     *
     * @param DomDocument $domDocument
     *
     * @return \Hl7Peri22x\Document\DocumentInterface
     */
    public function createXmlDocument(DomDocument $domDocument)
    {
        return new XmlDocument($domDocument, 'application/xml', 'xml', 'utf-8');
    }

    /**
     * Create an EmbeddedDocument with the supplied data.
     *
     * @param string $data
     *
     * @return \Hl7Peri22x\Document\DocumentInterface
     */
    public function createEmbeddedDocument($data)
    {
        $mimeInfo = $this->finfo->buffer($data);
        list($mimeType, $charsetInfo) = explode('; ', $mimeInfo);
        list(, $charset) = explode('=', $charsetInfo);
        return new EmbeddedDocument(
            $data,
            $mimeType,
            $this->mimeTypes->getExtension($mimeType),
            $charset
        );
    }
}
