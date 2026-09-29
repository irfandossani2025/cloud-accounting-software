<?php

namespace App\Enums;

enum VoucherBaseType: string
{
    case Contra = 'contra';
    case Payment = 'payment';
    case Receipt = 'receipt';
    case Journal = 'journal';
    case Sales = 'sales';
    case Purchase = 'purchase';
    case CreditNote = 'credit_note';
    case DebitNote = 'debit_note';

    public function label(): string
    {
        return match ($this) {
            self::CreditNote => 'Credit Note',
            self::DebitNote => 'Debit Note',
            default => ucfirst($this->value),
        };
    }

    /** Tally keyboard shortcut for the voucher type. */
    public function shortcut(): string
    {
        return match ($this) {
            self::Contra => 'F4',
            self::Payment => 'F5',
            self::Receipt => 'F6',
            self::Journal => 'F7',
            self::Sales => 'F8',
            self::Purchase => 'F9',
            self::CreditNote => 'Ctrl+F8',
            self::DebitNote => 'Ctrl+F9',
        };
    }
}
