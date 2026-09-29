<?php

namespace Database\Seeders;

use App\Enums\GroupNature;
use App\Enums\TaxRole;
use App\Enums\VatCategory;
use App\Enums\VoucherBaseType;
use App\Models\AccountGroup;
use App\Models\Ledger;
use App\Models\VoucherType;
use Illuminate\Database\Seeder;

/**
 * Tally's 28 predefined groups, reserved ledgers, Oman VAT ledgers and the accounting voucher types.
 * Idempotent: safe to run again.
 */
class ChartOfAccountsSeeder extends Seeder
{
    /** [name, parent, nature, affects gross profit] */
    private const GROUPS = [
        // 15 primary groups
        ['Branch / Divisions', null, GroupNature::Liabilities, false],
        ['Capital Account', null, GroupNature::Liabilities, false],
        ['Current Assets', null, GroupNature::Assets, false],
        ['Current Liabilities', null, GroupNature::Liabilities, false],
        ['Direct Expenses', null, GroupNature::Expenses, true],
        ['Direct Incomes', null, GroupNature::Income, true],
        ['Fixed Assets', null, GroupNature::Assets, false],
        ['Indirect Expenses', null, GroupNature::Expenses, false],
        ['Indirect Incomes', null, GroupNature::Income, false],
        ['Investments', null, GroupNature::Assets, false],
        ['Loans (Liability)', null, GroupNature::Liabilities, false],
        ['Misc. Expenses (ASSET)', null, GroupNature::Assets, false],
        ['Purchase Accounts', null, GroupNature::Expenses, true],
        ['Sales Accounts', null, GroupNature::Income, true],
        ['Suspense A/c', null, GroupNature::Liabilities, false],
        // 13 sub-groups
        ['Bank Accounts', 'Current Assets', GroupNature::Assets, false],
        ['Bank OD A/c', 'Loans (Liability)', GroupNature::Liabilities, false],
        ['Cash-in-Hand', 'Current Assets', GroupNature::Assets, false],
        ['Deposits (Asset)', 'Current Assets', GroupNature::Assets, false],
        ['Duties & Taxes', 'Current Liabilities', GroupNature::Liabilities, false],
        ['Loans & Advances (Asset)', 'Current Assets', GroupNature::Assets, false],
        ['Provisions', 'Current Liabilities', GroupNature::Liabilities, false],
        ['Reserves & Surplus', 'Capital Account', GroupNature::Liabilities, false],
        ['Secured Loans', 'Loans (Liability)', GroupNature::Liabilities, false],
        ['Stock-in-Hand', 'Current Assets', GroupNature::Assets, false],
        ['Sundry Creditors', 'Current Liabilities', GroupNature::Liabilities, false],
        ['Sundry Debtors', 'Current Assets', GroupNature::Assets, false],
        ['Unsecured Loans', 'Loans (Liability)', GroupNature::Liabilities, false],
    ];

    public function run(): void
    {
        foreach (self::GROUPS as $i => [$name, $parent, $nature, $affectsGp]) {
            AccountGroup::query()->updateOrCreate(['name' => $name], [
                'parent_id' => $parent ? AccountGroup::reserved($parent)->id : null,
                'nature' => $nature,
                'affects_gross_profit' => $affectsGp,
                'is_reserved' => true,
                'sort_order' => $i,
            ]);
        }

        $this->ledger(Ledger::CASH, 'Cash-in-Hand', reserved: true);
        $this->ledger(Ledger::PROFIT_AND_LOSS, 'Reserves & Surplus', reserved: true);

        $this->ledger('Output VAT 5%', 'Duties & Taxes', ['tax_role' => TaxRole::OutputVat, 'vat_rate' => VatCategory::STANDARD_RATE]);
        $this->ledger('Input VAT 5%', 'Duties & Taxes', ['tax_role' => TaxRole::InputVat, 'vat_rate' => VatCategory::STANDARD_RATE]);
        $this->ledger('Output VAT - Reverse Charge', 'Duties & Taxes', ['tax_role' => TaxRole::ReverseChargeOutput, 'vat_rate' => VatCategory::STANDARD_RATE]);
        $this->ledger('Input VAT - Reverse Charge', 'Duties & Taxes', ['tax_role' => TaxRole::ReverseChargeInput, 'vat_rate' => VatCategory::STANDARD_RATE]);

        $this->ledger('Sales - Standard Rated', 'Sales Accounts', ['vat_category' => VatCategory::Standard]);
        $this->ledger('Sales - Zero Rated', 'Sales Accounts', ['vat_category' => VatCategory::ZeroRated]);
        $this->ledger('Sales - Exempt', 'Sales Accounts', ['vat_category' => VatCategory::Exempt]);
        $this->ledger('Purchases - Standard Rated', 'Purchase Accounts', ['vat_category' => VatCategory::Standard]);
        $this->ledger('Purchases - Imports (Reverse Charge)', 'Purchase Accounts', ['vat_category' => VatCategory::ReverseCharge]);

        $types = [
            [VoucherBaseType::Contra, 'CTR-'],
            [VoucherBaseType::Payment, 'PMT-'],
            [VoucherBaseType::Receipt, 'RCT-'],
            [VoucherBaseType::Journal, 'JV-'],
            [VoucherBaseType::Sales, 'INV-'],
            [VoucherBaseType::Purchase, 'PUR-'],
            [VoucherBaseType::CreditNote, 'CN-'],
            [VoucherBaseType::DebitNote, 'DN-'],
        ];

        foreach ($types as [$base, $prefix]) {
            VoucherType::query()->firstOrCreate(['name' => $base->label()], [
                'base_type' => $base,
                'prefix' => $prefix,
                'is_reserved' => true,
            ]);
        }
    }

    private function ledger(string $name, string $group, array $attributes = [], bool $reserved = false): void
    {
        Ledger::query()->firstOrCreate(['name' => $name], [
            'account_group_id' => AccountGroup::reserved($group)->id,
            'is_reserved' => $reserved,
            ...$attributes,
        ]);
    }
}
