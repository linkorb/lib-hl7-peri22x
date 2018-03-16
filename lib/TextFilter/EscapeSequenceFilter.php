<?php

namespace Hl7Peri22x\TextFilter;

use RuntimeException;

use Hl7v2\DataType\FtDataType;
use Hl7v2\DataType\SimpleDataTypeInterface;
use Hl7v2\DataType\StDataType;
use Hl7v2\DataType\TxDataType;
use Hl7v2\Encoding\EncodingParameters;

class EscapeSequenceFilter
{
    private $compiled = false;
    private $encodingParameters;
    /**
     * These are partial regex patterns and replacements.  The HL7 escape
     * sequence and pcre delimiters are wrapped around these patterns and extra
     * patterns are added once the HL7 encoding parameters are known.
     *
     * {@see compilePatterns}
     *
     * @var string
     */
    private $patterns = [
        '\.sp\d*'      => "\n",
        '\.br'         => "\n",
        '\.ce'         => "\n",
        '\.in[+-]?\d*' => ' ',
        '\.sk\d*'      => ' ',
        '\.ti[+-]?\d*' => '',
        '\.fi'         => '',
        '\.nf'         => '',
        'H'            => '',
        'N'            => '',
    ];

    /**
     * Check whether an instance of SimpleDataTypeInterface is supported by this
     * filter.
     *
     * @param \Hl7v2\DataType\SimpleDataTypeInterface $value
     *
     * @return bool
     */
    public function isValueSupported(SimpleDataTypeInterface $value)
    {
        return $value instanceof FtDataType
            || $value instanceof StDataType
            || $value instanceof TxDataType
        ;
    }

    /**
     * Set the HL7 message encoding parameters.
     *
     * @param \Hl7v2\Encoding\EncodingParameters $parameters
     */
    public function setEncodingParameters(EncodingParameters $parameters)
    {
        $this->encodingParameters = $parameters;
        $this->compilePatterns();
    }

    /**
     * Get the textual content of the supplied value after it has passed through
     * a text filter.
     *
     * You must call setEncodingParameters with the EncodingParameters used to
     * decode the HL7 message before calling this method.
     *
     * @param \Hl7v2\DataType\SimpleDataTypeInterface $value
     *
     * @return string
     */
    public function filter(SimpleDataTypeInterface $value)
    {
        if (!$this->compiled) {
            throw new RuntimeException('You must setEncodingParameters() before calling filter()');
        }

        if (!$this->isValueSupported($value)) {
            return $value;
        }

        return preg_replace(
            array_keys($this->patterns),
            array_values($this->patterns),
            $this->normaliseText($value->getValue(), $value->getCharacterEncoding())
        );
    }

    private function normaliseText($text, $characterEncoding)
    {
        return mb_convert_encoding($text, 'utf8', $characterEncoding);
    }

    private function compilePatterns()
    {
        // make sure the HL7 escape char doesn't match a special regex character
        $esc = preg_quote($this->encodingParameters->getEscapeChar(), '/');

        $compiled = [];
        foreach ($this->patterns as $search => $replace) {
            $compiled["/{$esc}{$search}{$esc}/S"] = $replace;
        }
        $compiled["/{$esc}F{$esc}/S"] = $this->encodingParameters->getFieldSep();
        $compiled["/{$esc}S{$esc}/S"] = $this->encodingParameters->getComponentSep();
        $compiled["/{$esc}T{$esc}/S"] = $this->encodingParameters->getSubcomponentSep();
        $compiled["/{$esc}R{$esc}/S"] = $this->encodingParameters->getRepetitionSep();
        $compiled["/{$esc}E{$esc}/S"] = $this->encodingParameters->getEscapeChar();

        $this->patterns = $compiled;
        $this->compiled = true;
    }
}
