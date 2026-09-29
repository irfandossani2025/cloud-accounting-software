<?php

namespace App\Services;

use App\Enums\GroupNature;
use App\Enums\TaxRole;
use App\Enums\VatCategory;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Oman VAT return working for a tax period. Values are baisa.
 *
 * Supplies are read from income ledgers that carry a VAT treatment, purchases from all other
 * ledgers with a VAT treatment, and tax from the Duties & Taxes ledgers by tax role.
 */
class VatReturnService
{
    public function report(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('voucher_entries as e')
            ->join('vouchers as v', 'v.id', '=', 'e.voucher_id')
            ->join('ledgers as l', 'l.id', '=', 'e.ledger_id')
            ->join('account_groups as g', 'g.id', '=', 'l.account_group_id')
            ->where('v.is_cancelled', false)
            ->whereBetween('v.date', [$from->toDateString(), $to->toDateString()])
            ->where(fn ($q) => $q->whereNotNull('e.vat_category')->orWhereNotNull('l.vat_category')->orWhereNotNull('l.tax_role'))
            ->groupBy('l.id', 'l.name', 'g.nature', 'l.tax_role', 'category')
            ->selectRaw('l.id, l.name, g.nature, l.tax_role, COALESCE(e.vat_category, l.vat_category) AS category')
            ->selectRaw('SUM(e.debit) AS debit, SUM(e.credit) AS credit')
            ->get();

        $supplies = array_fill_keys(array_map(fn ($c) => $c->value, VatCategory::cases()), 0);
        $purchases = $supplies;
        $tax = array_fill_keys(array_map(fn ($r) => $r->value, TaxRole::cases()), 0);
        $ledgers = [];

        foreach ($rows as $row) {
            $net = Money::toBaisa($row->debit) - Money::toBaisa($row->credit);

            if ($row->tax_role) {
                $isOutput = in_array($row->tax_role, [TaxRole::OutputVat->value, TaxRole::ReverseChargeOutput->value], true);
                $tax[$row->tax_role] += $isOutput ? -$net : $net;
                $ledgers[] = ['name' => $row->name, 'section' => TaxRole::from($row->tax_role)->label(), 'amount' => $isOutput ? -$net : $net];

                continue;
            }

            if (! $row->category) {
                continue;
            }

            if ($row->nature === GroupNature::Income->value) {
                $supplies[$row->category] += -$net;
                $ledgers[] = ['name' => $row->name, 'section' => 'Supplies: '.VatCategory::from($row->category)->label(), 'amount' => -$net];
            } else {
                $purchases[$row->category] += $net;
                $ledgers[] = ['name' => $row->name, 'section' => 'Purchases: '.VatCategory::from($row->category)->label(), 'amount' => $net];
            }
        }

        $outputTax = $tax[TaxRole::OutputVat->value] + $tax[TaxRole::ReverseChargeOutput->value];
        $inputTax = $tax[TaxRole::InputVat->value] + $tax[TaxRole::ReverseChargeInput->value];

        return [
            'supplies' => $supplies,
            'purchases' => $purchases,
            'tax' => $tax,
            'outputTax' => $outputTax,
            'inputTax' => $inputTax,
            'netPayable' => $outputTax - $inputTax,
            'ledgers' => $ledgers,
            // Expected output VAT on standard-rated supplies, to flag rounding or posting mistakes.
            'expectedOutputVat' => Money::vat($supplies[VatCategory::Standard->value], VatCategory::STANDARD_RATE),
        ];
    }
}
