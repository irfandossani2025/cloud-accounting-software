<?php

namespace App\Services;

use App\Models\CostCentre;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CostCentreReportService
{
    /**
     * Income and expenses per cost centre for a period. Amounts are baisa: income positive as credit,
     * expenses positive as debit. Also lists what was posted to cost-centre ledgers without allocation.
     */
    public function report(Carbon $from, Carbon $to): array
    {
        $allocated = DB::table('cost_allocations as a')
            ->join('voucher_entries as e', 'e.id', '=', 'a.voucher_entry_id')
            ->join('vouchers as v', 'v.id', '=', 'e.voucher_id')
            ->join('ledgers as l', 'l.id', '=', 'e.ledger_id')
            ->join('account_groups as g', 'g.id', '=', 'l.account_group_id')
            ->where('v.is_cancelled', false)
            ->whereBetween('v.date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('a.cost_centre_id', 'l.id', 'l.name', 'g.nature')
            ->selectRaw('a.cost_centre_id, l.id AS ledger_id, l.name, g.nature, SUM(a.amount) AS amount')
            ->get();

        $unallocated = DB::table('voucher_entries as e')
            ->join('vouchers as v', 'v.id', '=', 'e.voucher_id')
            ->join('ledgers as l', 'l.id', '=', 'e.ledger_id')
            ->join('account_groups as g', 'g.id', '=', 'l.account_group_id')
            ->leftJoin(DB::raw('(SELECT voucher_entry_id, SUM(amount) AS allocated FROM cost_allocations GROUP BY voucher_entry_id) AS ca'), 'ca.voucher_entry_id', '=', 'e.id')
            ->where('v.is_cancelled', false)
            ->where('l.cost_centres_applicable', true)
            ->whereBetween('v.date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('l.id', 'l.name', 'g.nature')
            ->selectRaw('l.id AS ledger_id, l.name, g.nature, SUM(e.debit - e.credit - COALESCE(ca.allocated, 0)) AS amount')
            ->get()
            ->filter(fn ($r) => Money::toBaisa($r->amount) !== 0);

        $row = function ($r) {
            $signed = Money::toBaisa($r->amount);
            $isIncome = $r->nature === 'income';

            return (object) ['ledger_id' => $r->ledger_id, 'name' => $r->name, 'isIncome' => $isIncome, 'amount' => $isIncome ? -$signed : $signed];
        };

        $centres = CostCentre::query()->orderBy('name')->get()->map(function (CostCentre $centre) use ($allocated, $row) {
            $ledgers = $allocated->where('cost_centre_id', $centre->id)->map($row)->values();

            return (object) [
                'centre' => $centre,
                'ledgers' => $ledgers,
                'income' => $ledgers->where('isIncome', true)->sum('amount'),
                'expenses' => $ledgers->where('isIncome', false)->sum('amount'),
            ];
        })->filter(fn ($c) => $c->ledgers->isNotEmpty())->values();

        $unallocatedRows = $unallocated->map($row)->values();

        return [
            'centres' => $centres,
            'unallocated' => (object) [
                'ledgers' => $unallocatedRows,
                'income' => $unallocatedRows->where('isIncome', true)->sum('amount'),
                'expenses' => $unallocatedRows->where('isIncome', false)->sum('amount'),
            ],
        ];
    }
}
