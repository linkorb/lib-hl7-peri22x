<?php

namespace Hl7Peri22x;

/**
 * Storable Interface for things that can be stored in an object store.
 */
interface StorableInterface
{
    /**
     * @param string
     */
    public function setStorageKey($key);

    /**
     * @return string
     */
    public function getStorageKey();
}
