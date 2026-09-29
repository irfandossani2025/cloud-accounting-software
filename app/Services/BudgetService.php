<?php

namespace App\Services;

use App\Enums\GroupNature;
use App\Models\Budget;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class BudgetService
{
    /**
     * Budget against actual for the budget period. Amounts are baisa in the natural direction of the
     * account (expenses/assets as debit, income/liabilities as credit).
     */
    public function variance(Budget $budget): array
    {
        $budget->loadMissing('lines.ledger.group', 'lines.group', 'lines.costCentre');
        $from = $budget->from_date->toDateString();
        $to = $budget->to_date->toDateString();

        $rows = $budget->lines->map(function ($line) use ($from, $to) {
            if ($line->ledger) {
                $ledgerIds = [$line->ledger_id];
                $nature = $line->ledger->group->nature;
                $name = $line->ledger->name;
            } else {
                $ledgerIds = DB::table('ledgers')->whereIn('account_group_id', $line->group->descendantAndSelfIds())->pluck('id')->all();
                $nature = $line->group->nature;
                $name = $line->group->name.' (group)';
            }

            $query = DB::table('voucher_entries as e')
                ->join('vouchers as v', 'v.id', '=', 'e.voucher_id')
                ->where('v.is_cancelled', false)
                ->whereBetween('v.date', [$from, $to])
                ->whereIn('e.ledger_id', $ledgerIds ?: [0]);

            $net = $line->cost_centre_id
                ? $query->join('cost_allocations as a', 'a.voucher_entry_id', '=', 'e.id')->where('a.cost_centre_id', $line->cost_centre_id)->sum('a.amount')
                : $query->sum(DB::raw('e.debit - e.credit'));

            $debitNatural = in_array($nature, [GroupNature::Assets, GroupNature::Expenses], true);
            $actual = ($debitNatural ? 1 : -1) * Money::toBaisa($net);
            $planned = Money::toBaisa($line->amount);

            return (object) [
                'name' => $name,
                'costCentre' => $line->costCentre?->name,
                'isExpense' => $debitNatural,
                'budget' => $planned,
                'actual' => $actual,
                'variance' => $actual - $planned,
                'percent' => $planned ? round($actual * 100 / $planned, 1) : null,
            ];
        });

        return ['rows' => $rows];
    }
}
