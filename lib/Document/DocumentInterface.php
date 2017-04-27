<?php

namespace Hl7Peri22x\Document;

/**
 * Document Interface.
 */
interface DocumentInterface
{
    /**
     * @return string
     * @throws \Hl7Peri22x\Document\DocumentError;
     */
    public function toString();

    /**
     * @throws \Hl7Peri22x\Document\DocumentError;
     */
    public function save($filename);

    /**
     * @return string
     */
    public function getMimeType();

    /**
     * @return string
     */
    public function getExtension();

    /**
     * @return string
     */
    public function getCharacterSet();
}
