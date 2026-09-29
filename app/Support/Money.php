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

    /** Quantity (up to 3 decimals) × rate in baisa, rounded half up to the nearest baisa. */
    public static function multiply(int $rateBaisa, string|int|float $quantity): int
    {
        $qtyMilli = self::toBaisa($quantity); // same 3-decimal scale
        $product = $rateBaisa * $qtyMilli;
        $sign = $product < 0 ? -1 : 1;

        return $sign * intdiv(abs($product) + 500, 1000);
    }

    /** "Two Hundred Ten Omani Rials and Five Hundred Baisa Only" */
    public static function inWords(int $baisa): string
    {
        $baisa = abs($baisa);
        $rials = intdiv($baisa, self::SCALE);
        $fraction = $baisa % self::SCALE;

        $words = $rials > 0 ? self::numberToWords($rials).' Omani Rial'.($rials === 1 ? '' : 's') : '';

        if ($fraction > 0) {
            $words .= ($words ? ' and ' : '').self::numberToWords($fraction).' Baisa';
        }

        return ($words ?: 'Zero Omani Rials').' Only';
    }

    private static function numberToWords(int $n): string
    {
        $ones = ['', 'One', 'Two', 'Three', 'Four', 'Five', 'Six', 'Seven', 'Eight', 'Nine', 'Ten', 'Eleven', 'Twelve',
            'Thirteen', 'Fourteen', 'Fifteen', 'Sixteen', 'Seventeen', 'Eighteen', 'Nineteen'];
        $tens = ['', '', 'Twenty', 'Thirty', 'Forty', 'Fifty', 'Sixty', 'Seventy', 'Eighty', 'Ninety'];

        if ($n < 20) {
            return $ones[$n];
        }
        if ($n < 100) {
            return trim($tens[intdiv($n, 10)].' '.$ones[$n % 10]);
        }
        if ($n < 1000) {
            return trim($ones[intdiv($n, 100)].' Hundred '.self::numberToWords($n % 100));
        }

        foreach ([1_000_000_000 => 'Billion', 1_000_000 => 'Million', 1000 => 'Thousand'] as $size => $name) {
            if ($n >= $size) {
                return trim(self::numberToWords(intdiv($n, $size)).' '.$name.' '.self::numberToWords($n % $size));
            }
        }

        return '';
    }

    /** Convert a foreign amount (thousandths) to baisa at an OMR-per-unit rate with up to 6 decimals. */
    public static function convert(int $foreign, string|float $rate): int
    {
        $rateMicro = (int) round(((float) $rate) * 1_000_000);
        $product = $foreign * $rateMicro;
        $sign = $product < 0 ? -1 : 1;

        return $sign * intdiv(abs($product) + 500_000, 1_000_000);
    }
}
