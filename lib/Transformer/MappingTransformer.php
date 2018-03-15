<?php

namespace Hl7Peri22x\Transformer;

class MappingTransformer implements ValueTransformerInterface
{
    private $mappings = [];

    public function __construct($mappings)
    {
        $this->mappings = $mappings;
    }

    public function transform($transformationType, $value)
    {
        $type = $this->normaliseKey($transformationType);
        if (!array_key_exists($type, $this->mappings)) {
            return $value;
        }

        $transformable = $this->normaliseKey($value);
        if (!array_key_exists($transformable, $this->mappings[$type])
        ) {
            return $value;
        }

        return $this->mappings[$type][$transformable];
    }

    private function normaliseKey($key)
    {
        return str_replace([' ', '-'], '_', strtolower($key));
    }
}
