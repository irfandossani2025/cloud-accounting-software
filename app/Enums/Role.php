<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Accountant = 'accountant';
    case DataEntry = 'data_entry';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Accountant => 'Accountant',
            self::DataEntry => 'Data entry',
            self::Viewer => 'Viewer (reports only)',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Admin => 'Everything, including users, company settings, period lock and backups.',
            self::Accountant => 'All vouchers and masters, cancellations, reconciliation and budgets.',
            self::DataEntry => 'Enter vouchers and alter their own vouchers. No masters, no cancellations.',
            self::Viewer => 'View and export reports only.',
        };
    }
}
