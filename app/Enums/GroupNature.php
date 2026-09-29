<?php

namespace App\Enums;

enum GroupNature: string
{
    case Assets = 'assets';
    case Liabilities = 'liabilities';
    case Income = 'income';
    case Expenses = 'expenses';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Whether the group belongs to the Profit & Loss statement (vs the Balance Sheet). */
    public function isRevenue(): bool
    {
        return $this === self::Income || $this === self::Expenses;
    }
}
