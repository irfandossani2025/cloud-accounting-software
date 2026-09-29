<?php

namespace App\Enums;

/** Role of a ledger under "Duties & Taxes" for Oman VAT. */
enum TaxRole: string
{
    case OutputVat = 'output_vat';
    case InputVat = 'input_vat';
    case ReverseChargeOutput = 'rcm_output';
    case ReverseChargeInput = 'rcm_input';

    public function label(): string
    {
        return match ($this) {
            self::OutputVat => 'Output VAT',
            self::InputVat => 'Input VAT',
            self::ReverseChargeOutput => 'Output VAT (reverse charge)',
            self::ReverseChargeInput => 'Input VAT (reverse charge)',
        };
    }
}
