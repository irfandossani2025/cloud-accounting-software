<?php

namespace App\Services;

use App\Models\AccountGroup;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\Voucher;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Balances are signed integers in baisa: positive = debit, negative = credit.
 */
class ReportService
{
    /** Start of the financial year containing $date. */
    public function financialYearStart(Carbon $date): Carbon
    {
        $fy = CompanySetting::current()?->financial_year_start ?? Carbon::create($date->year, 1, 1);
        $start = Carbon::create($date->year, $fy->month, $fy->day)->startOfDay();

        return $start->gt($date) ? $start->subYear() : $start;
    }

    /**
     * Per-ledger opening, period debit/credit and closing for [$from, $to].
     *
     * Revenue (P&L) ledgers restart at zero each financial year; their earlier results
     * roll into the Profit & Loss A/c ledger, as in Tally.
     *
     * @return Collection<int, object{ledger: Ledger, opening: int, debit: int, credit: int, closing: int}>
     */
    public function ledgerBalances(Carbon $from, Carbon $to): Collection
    {
        $fyStart = $this->financialYearStart($from);

        $movements = DB::table('voucher_entries as e')
            ->join('vouchers as v', 'v.id', '=', 'e.voucher_id')
            ->where('v.is_cancelled', false)
            ->where('v.date', '<=', $to->toDateString())
            ->groupBy('e.ledger_id')
            ->selectRaw('e.ledger_id')
            ->selectRaw('SUM(CASE WHEN v.date < ? THEN e.debit - e.credit ELSE 0 END) AS before_fy', [$fyStart->toDateString()])
            ->selectRaw('SUM(CASE WHEN v.date >= ? AND v.date < ? THEN e.debit - e.credit ELSE 0 END) AS fy_to_from', [$fyStart->toDateString(), $from->toDateString()])
            ->selectRaw('SUM(CASE WHEN v.date >= ? THEN e.debit ELSE 0 END) AS debit', [$from->toDateString()])
            ->selectRaw('SUM(CASE WHEN v.date >= ? THEN e.credit ELSE 0 END) AS credit', [$from->toDateString()])
            ->get()
            ->keyBy('ledger_id');

        $ledgers = Ledger::query()->with('group')->orderBy('name')->get();
        $retained = 0;

        $rows = $ledgers->map(function (Ledger $ledger) use ($movements, &$retained) {
            $m = $movements->get($ledger->id);
            $beforeFy = Money::toBaisa($m->before_fy ?? 0);
            $fyToFrom = Money::toBaisa($m->fy_to_from ?? 0);

            if ($ledger->group->nature->isRevenue()) {
                $retained += $beforeFy;
                $opening = $fyToFrom;
            } else {
                $opening = Money::toBaisa($ledger->opening_balance) + $beforeFy + $fyToFrom;
            }

            $debit = Money::toBaisa($m->debit ?? 0);
            $credit = Money::toBaisa($m->credit ?? 0);

            return (object) (compact('ledger', 'opening', 'debit', 'credit') + ['closing' => $opening + $debit - $credit]);
        })->keyBy(fn ($row) => $row->ledger->id);

        if ($retained !== 0 && ($pl = $rows->first(fn ($r) => $r->ledger->name === Ledger::PROFIT_AND_LOSS))) {
            $pl->opening += $retained;
            $pl->closing += $retained;
        }

        return $rows;
    }

    /** Sum of all ledger opening balances; non-zero means the opening trial balance does not agree. */
    public function openingDifference(): int
    {
        return Ledger::query()->pluck('opening_balance')->sum(fn ($v) => Money::toBaisa($v));
    }

    /**
     * Nested group tree with ledger rows and signed totals.
     *
     * @param  callable(object): int  $value  Signed value to total for each ledger row.
     * @return list<array{group: AccountGroup, ledgers: list<object>, children: list<array>, total: int}>
     */
    public function groupTree(Collection $rows, callable $value, ?callable $groupFilter = null, bool $hideZero = true): array
    {
        $groups = AccountGroup::query()->orderBy('sort_order')->orderBy('name')->get();
        $byParent = $groups->groupBy(fn ($g) => $g->parent_id ?? 0);
        $rowsByGroup = $rows->groupBy(fn ($r) => $r->ledger->account_group_id);

        $build = function (AccountGroup $group) use (&$build, $byParent, $rowsByGroup, $value, $hideZero) {
            $ledgers = collect($rowsByGroup->get($group->id, []))
                ->map(fn ($r) => tap($r, fn ($r) => $r->value = $value($r)))
                ->when($hideZero, fn ($c) => $c->filter(fn ($r) => $r->value !== 0 || $r->debit || $r->credit || $r->opening))
                ->values()
                ->all();

            $children = collect($byParent->get($group->id, []))
                ->map(fn ($child) => $build($child))
                ->filter()
                ->values()
                ->all();

            if ($hideZero && ! $ledgers && ! $children) {
                return null;
            }

            $total = array_sum(array_column($ledgers, 'value')) + array_sum(array_column($children, 'total'));

            return compact('group', 'ledgers', 'children', 'total');
        };

        $primary = collect($byParent->get(0, []));

        if ($groupFilter) {
            $primary = $primary->filter($groupFilter);
        }

        return $primary
            ->map(fn ($g) => $build($g))
            ->filter()
            ->values()
            ->all();
    }

    public function trialBalance(Carbon $from, Carbon $to): array
    {
        $rows = $this->ledgerBalances($from, $to);
        $tree = $this->groupTree($rows, fn ($r) => $r->closing);

        return [
            'tree' => $tree,
            'debit' => $rows->sum(fn ($r) => max($r->closing, 0)),
            'credit' => $rows->sum(fn ($r) => max(-$r->closing, 0)),
            'openingDifference' => $this->openingDifference(),
        ];
    }

    public function profitAndLoss(Carbon $from, Carbon $to): array
    {
        $rows = $this->ledgerBalances($from, $to)->filter(fn ($r) => $r->ledger->group->nature->isRevenue());
        $periodNet = fn ($r) => $r->debit - $r->credit;
        $revenue = fn ($g) => $g->nature->isRevenue();

        // Income shown as positive credit; expenses as positive debit.
        $income = collect($this->groupTree($rows, fn ($r) => -$periodNet($r), fn ($g) => $revenue($g) && $g->nature->value === 'income'));
        $expenses = collect($this->groupTree($rows, $periodNet, fn ($g) => $revenue($g) && $g->nature->value === 'expenses'));

        $gpIncome = $income->filter(fn ($n) => $n['group']->affects_gross_profit)->sum('total');
        $gpExpense = $expenses->filter(fn ($n) => $n['group']->affects_gross_profit)->sum('total');
        $grossProfit = $gpIncome - $gpExpense;

        $netProfit = $income->sum('total') - $expenses->sum('total');

        return [
            'tradingIncome' => $income->filter(fn ($n) => $n['group']->affects_gross_profit)->values(),
            'tradingExpenses' => $expenses->filter(fn ($n) => $n['group']->affects_gross_profit)->values(),
            'indirectIncome' => $income->reject(fn ($n) => $n['group']->affects_gross_profit)->values(),
            'indirectExpenses' => $expenses->reject(fn ($n) => $n['group']->affects_gross_profit)->values(),
            'grossProfit' => $grossProfit,
            'netProfit' => $netProfit,
        ];
    }

    public function balanceSheet(Carbon $asOf): array
    {
        $from = $this->financialYearStart($asOf);
        $rows = $this->ledgerBalances($from, $asOf);
        $netProfit = $this->profitAndLoss($from, $asOf)['netProfit'];

        $plRow = $rows->first(fn ($r) => $r->ledger->name === Ledger::PROFIT_AND_LOSS);
        $balanceRows = $rows->reject(fn ($r) => $r->ledger->group->nature->isRevenue() || $r === $plRow);

        $liabilities = $this->groupTree($balanceRows, fn ($r) => -$r->closing, fn ($g) => $g->nature->value === 'liabilities');
        $assets = $this->groupTree($balanceRows, fn ($r) => $r->closing, fn ($g) => $g->nature->value === 'assets');

        // Profit & Loss A/c: accumulated (credit = profit) plus current period.
        $plOpening = -($plRow?->closing ?? 0);
        $openingDifference = $this->openingDifference();

        $liabilityTotal = collect($liabilities)->sum('total') + $plOpening + $netProfit;
        $assetTotal = collect($assets)->sum('total');

        return [
            'liabilities' => $liabilities,
            'assets' => $assets,
            'plOpening' => $plOpening,
            'netProfit' => $netProfit,
            'openingDifference' => $openingDifference,
            // Opening difference sits on whichever side makes the sheet balance, as Tally shows it.
            'liabilityTotal' => $liabilityTotal + max($openingDifference, 0),
            'assetTotal' => $assetTotal + max(-$openingDifference, 0),
        ];
    }

    /** @return Collection<int, Voucher> */
    public function dayBook(Carbon $from, Carbon $to): Collection
    {
        return Voucher::query()
            ->with(['type', 'party', 'entries.ledger'])
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->orderBy('id')
            ->get();
    }

    public function ledgerStatement(Ledger $ledger, Carbon $from, Carbon $to): array
    {
        $opening = $this->ledgerBalances($from, $from)->get($ledger->id)?->opening ?? 0;

        $lines = DB::table('voucher_entries as e')
            ->join('vouchers as v', 'v.id', '=', 'e.voucher_id')
            ->join('voucher_types as t', 't.id', '=', 'v.voucher_type_id')
            ->where('e.ledger_id', $ledger->id)
            ->where('v.is_cancelled', false)
            ->whereBetween('v.date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('v.date')->orderBy('v.id')->orderBy('e.sort_order')
            ->get(['v.id as voucher_id', 'v.date', 'v.number', 'v.narration', 't.name as type', 'e.debit', 'e.credit']);

        // Opposite ledger names ("particulars") for each voucher.
        $particulars = DB::table('voucher_entries as e')
            ->join('ledgers as l', 'l.id', '=', 'e.ledger_id')
            ->whereIn('e.voucher_id', $lines->pluck('voucher_id')->unique())
            ->where('e.ledger_id', '!=', $ledger->id)
            ->orderBy('e.sort_order')
            ->get(['e.voucher_id', 'l.name'])
            ->groupBy('voucher_id');

        $balance = $opening;
        $totalDebit = 0;
        $totalCredit = 0;

        $lines = $lines->map(function ($line) use (&$balance, &$totalDebit, &$totalCredit, $particulars) {
            $debit = Money::toBaisa($line->debit);
            $credit = Money::toBaisa($line->credit);
            $balance += $debit - $credit;
            $totalDebit += $debit;
            $totalCredit += $credit;
            $names = $particulars->get($line->voucher_id, collect())->pluck('name')->unique();

            return (object) [
                'voucher_id' => $line->voucher_id,
                'date' => Carbon::parse($line->date),
                'number' => $line->number,
                'type' => $line->type,
                'particulars' => $names->count() > 1 ? '(as per details) '.$names->implode(', ') : $names->first(),
                'narration' => $line->narration,
                'debit' => $debit,
                'credit' => $credit,
                'balance' => $balance,
            ];
        });

        return compact('opening', 'lines', 'totalDebit', 'totalCredit') + ['closing' => $balance];
    }
}
