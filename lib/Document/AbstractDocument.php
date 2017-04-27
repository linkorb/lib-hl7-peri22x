<?php

namespace Hl7Peri22x\Document;

/**
 * Base class for implementations of DocumentInterface.
 */
abstract class AbstractDocument implements DocumentInterface
{
    /**
     * @var string
     */
    private $characterSet;
    /**
     * @var string
     */
    private $extension;
    /**
     * @var string
     */
    private $mimeType;

    public function __construct(
        $mimeType,
        $extension = null,
        $characterSet = 'utf8'
    ) {
        $this->mimeType = $mimeType;
        $this->extension = $extension;
        $this->characterSet = $characterSet;
    }

    public function getCharacterSet()
    {
        return $this->characterSet;
    }

    public function getExtension()
    {
        return $this->extension;
    }

    public function getMimeType()
    {
        return $this->mimeType;
    }

    abstract public function toString();

    abstract public function save($filename);
}
