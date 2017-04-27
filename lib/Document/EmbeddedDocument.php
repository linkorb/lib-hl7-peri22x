<?php

namespace Hl7Peri22x\Document;

/**
 * An Embedded document.
 */
class EmbeddedDocument extends AbstractDocument
{
    /**
     * @var string
     */
    private $data;

    public function __construct(
        $data,
        $mimeType,
        $extension = null,
        $characterSet = 'utf8'
    ) {
        $this->data = $data;
        parent::__construct($mimeType, $extension, $characterSet);
    }

    public function toString()
    {
        return $this->data;
    }

    public function save($filename)
    {
        $written = file_put_contents($filename, $this->data);
        if ($written === false) {
            throw new DocumentError("Unable to write EmbeddedDocument to \"{$filename}\".");
        }
    }
}
