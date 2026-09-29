<?php

namespace App\Enums;

enum InstrumentType: string
{
    case Cheque = 'cheque';
    case Transfer = 'transfer';
    case Card = 'card';
    case CashDeposit = 'cash_deposit';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cheque => 'Cheque',
            self::Transfer => 'Bank transfer',
            self::Card => 'Card',
            self::CashDeposit => 'Cash deposit',
            self::Other => 'Other',
        };
    }
}
