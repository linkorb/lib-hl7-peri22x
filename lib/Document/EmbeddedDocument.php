<?php

namespace Hl7Peri22x\Document;

use Hl7Peri22x\StorableInterface;

/**
 * An Embedded document.
 */
class EmbeddedDocument extends AbstractDocument implements StorableInterface
{
    /**
     * @var string
     */
    private $basename;
    /**
     * @var string
     */
    private $data;
    /**
     * @var string
     */
    private $storageKey;

    public function __construct(
        $data,
        $basename,
        $mimeType,
        $extension = null,
        $characterSet = 'utf8'
    ) {
        $this->data = $data;
        $this->basename = $basename;
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

    /**
     * Set a different basename for this file.
     *
     * @param $basename
     */
    public function setBasename($basename)
    {
        $this->basename = $basename;
    }

    /**
     * The base of a name for this file.
     *
     * @return string
     */
    public function getBasename()
    {
        return $this->basename;
    }

    /**
     * @param string $key
     */
    public function setStorageKey($key)
    {
        $this->storageKey = $key;
    }

    /**
     * @return string
     */
    public function getStorageKey()
    {
        return $this->storageKey;
    }
}
