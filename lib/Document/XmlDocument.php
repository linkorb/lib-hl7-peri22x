<?php

namespace Hl7Peri22x\Document;

use DOMDocument;

/**
 * An XML document.
 */
class XmlDocument extends AbstractDocument
{
    /**
     * @var \DOMDocument
     */
    private $document;

    public function __construct(
        DOMDocument $domDocument,
        $mimeType,
        $extension = null,
        $characterSet = 'utf8'
    ) {
        $this->document = $domDocument;
        parent::__construct($mimeType, $extension, $characterSet);
    }

    public function toString()
    {
        $xmlData = $this->document->saveXML();
        if ($xmlData === false) {
            throw new DocumentError('Unable to convert XmlDocument to a string.');
        }
        return $xmlData;
    }

    public function save($filename)
    {
        $written = $this->document->save($filename);
        if ($written === false) {
            throw new DocumentError("Unable to write XmlDocument to \"{$filename}\".");
        }
    }
}
