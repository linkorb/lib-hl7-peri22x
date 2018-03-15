<?php

namespace Hl7Peri22x\Transformer;

class IdentityTransformer implements ValueTransformerInterface
{
    public function transform($transformationType, $value)
    {
        return $value;
    }
}
