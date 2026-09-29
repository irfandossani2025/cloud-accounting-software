<?php

namespace App\Enums;

/**
 * Oman VAT treatment of a supply (Royal Decree 121/2020). Standard rate is 5%.
 */
enum VatCategory: string
{
    case Standard = 'standard';
    case ZeroRated = 'zero_rated';
    case Exempt = 'exempt';
    case ReverseCharge = 'reverse_charge';
    case OutOfScope = 'out_of_scope';

    public const STANDARD_RATE = '5.00';

    public function label(): string
    {
        return match ($this) {
            self::Standard => 'Standard rated (5%)',
            self::ZeroRated => 'Zero rated (0%)',
            self::Exempt => 'Exempt',
            self::ReverseCharge => 'Reverse charge (5%)',
            self::OutOfScope => 'Out of scope',
        };
    }

    public function rate(): string
    {
        return match ($this) {
            self::Standard, self::ReverseCharge => self::STANDARD_RATE,
            default => '0.00',
        };
    }
}
