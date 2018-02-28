<?php

namespace Hl7Peri22x\Processor\Helper;

use DateTime;
use Exception;

class DateTimeHelper
{
    /**
     * Parse and format a date/time string according to its length.
     *
     * @param string $value
     * @return string
     * @throws \Exception
     */
    public static function format($value)
    {
        $timezonePos = false;
        foreach (['+', '-'] as $char) {
            $timezonePos = strpos($value, $char);
            if (false !== $timezonePos) {
                break;
            }
        }

        $len = $timezonePos !== false ? $timezonePos : strlen($value);

        $inFormat = 'YmdHis.u';
        $outFormat = 'Y-m-d H:i:s.u';

        if ($len <= 4) {
            $inFormat = 'Y';
            $outFormat = 'Y';
        } elseif ($len <= 6) {
            $inFormat = 'Ym';
            $outFormat = 'Y-m';
        } elseif ($len <= 8) {
            $inFormat = 'Ymd';
            $outFormat = 'Y-m-d';
        } elseif ($len <= 10) {
            $inFormat = 'YmdH';
            $outFormat = 'Y-m-d H:i';
        } elseif ($len <= 12) {
            $inFormat = 'YmdHi';
            $outFormat = 'Y-m-d H:i';
        } elseif ($len <= 14) {
            $inFormat = 'YmdHis';
            $outFormat = 'Y-m-d H:i:s';
        }

        if (false !== $timezonePos) {
            $inFormat .= 'P';
            $outFormat .= ' P';
        }

        $dt = DateTime::createFromFormat($inFormat, $value);

        if ($dt === false) {
            $msg = "Failed to parse DateTime value \"{$value}\" with format \"{$inFormat}\"";
            $err = DateTime::getLastErrors();
            if ($err['error_count']) {
                $msg .= ': ' . implode(', ', array_values($err['errors']));
            }
            throw new Exception("{$msg}.");
        }

        return $dt->format($outFormat);
    }

    /**
     * Convert a string like "1w1d" to a string representing a number of days.
     *
     * @param string $value
     *
     * @return string
     */
    public static function convertToDays($value)
    {
        if ('' === $value || ctype_digit($value)) {
            return $value;
        }

        $accumulator = 0;
        $digits = '';
        foreach (str_split($value) as $char) {
            if (ctype_digit($char)) {
                $digits .= $char;
                continue;
            }
            if ('d' === strtolower($char)) {
                $accumulator += (int) $digits;
                $digits = '';
            } elseif ('w' === strtolower($char)) {
                $accumulator += 7 * (int) $digits;
                $digits = '';
            } else {
                $digits = '';
            }
        }
        if ('' !== $digits) {
            $accumulator += (int) $digits;
        }

        return (string) $accumulator;
    }
}
