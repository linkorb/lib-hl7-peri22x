<?php

namespace Hl7Peri22x\Transformer;

interface ValueTransformerInterface
{
    /**
     * Transform the supplied value using the given transformation type.
     *
     * @param string $transformationName
     * @param string $value
     *
     * @return string
     */
    public function transform($transformationType, $value);
}
