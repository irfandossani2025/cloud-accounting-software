<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * OMR amounts are handled as integer baisa (1 OMR = 1000 baisa) to avoid float rounding.
 */
final class Money
{
    public const SCALE = 1000;

    public static function toBaisa(string|int|float|null $amount): int
    {
        if ($amount === null || $amount === '') {
            return 0;
        }

        $value = str_replace([',', ' '], '', (string) $amount);

        if (! preg_match('/^(-)?(\d*)(?:\.(\d*))?$/', $value, $m) || ($m[2] === '' && ($m[3] ?? '') === '')) {
            throw new InvalidArgumentException("Invalid amount [{$amount}].");
        }

        $fraction = str_pad($m[3] ?? '', 4, '0');
        $baisa = (int) $m[2] * self::SCALE + (int) substr($fraction, 0, 3);

        // Round half up on the 4th decimal place.
        if ((int) $fraction[3] >= 5) {
            $baisa++;
        }

        return $m[1] === '-' ? -$baisa : $baisa;
    }

    /** Convert baisa to a plain decimal string, e.g. 12345 -> "12.345". */
    public static function toDecimal(int $baisa): string
    {
        $sign = $baisa < 0 ? '-' : '';
        $abs = abs($baisa);

        return $sign.intdiv($abs, self::SCALE).'.'.str_pad((string) ($abs % self::SCALE), 3, '0', STR_PAD_LEFT);
    }

    /** Human format with thousands separators, e.g. 1234567 -> "1,234.567". */
    public static function format(string|int|float|null $amount, bool $blankZero = false): string
    {
        $baisa = is_int($amount) ? $amount : self::toBaisa($amount);

        if ($blankZero && $baisa === 0) {
            return '';
        }

        $sign = $baisa < 0 ? '-' : '';
        $abs = abs($baisa);

        return $sign.number_format(intdiv($abs, self::SCALE)).'.'.str_pad((string) ($abs % self::SCALE), 3, '0', STR_PAD_LEFT);
    }

    /** Format a signed balance Tally-style: "1,000.000 Dr" / "250.000 Cr". */
    public static function drCr(int $baisa): string
    {
        if ($baisa === 0) {
            return '';
        }

        return self::format(abs($baisa)).($baisa > 0 ? ' Dr' : ' Cr');
    }

    /** VAT on a net amount at a percentage rate, rounded half up to the nearest baisa. */
    public static function vat(int $netBaisa, string $ratePercent): int
    {
        $rateBasisPoints = (int) round(((float) $ratePercent) * 100);
        $numerator = $netBaisa * $rateBasisPoints;
        $sign = $numerator < 0 ? -1 : 1;

        return $sign * intdiv(abs($numerator) + 5000, 10000);
    }
}
