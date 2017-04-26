<?php

namespace Hl7Peri22x\Document;

/**
 * An Embedded document.
 */
class EmbeddedDocument implements DocumentInterface
{
    /**
     * @var string
     */
    private $data;

    public function __construct($data)
    {
        $this->data = $data;
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
