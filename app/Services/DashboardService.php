<?php

namespace App\Services;

use App\Enums\GroupNature;
use App\Models\AccountGroup;
use App\Models\Voucher;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Figures for the dashboard. Amounts are baisa; receivables, payables and VAT are positive when owed.
 */
class DashboardService
{
    public function __construct(
        private ReportService $reports,
        private OutstandingService $outstanding,
        private VatReturnService $vat,
        private StockService $stock,
        private BankReconciliationService $banking,
    ) {}

    public function summary(Carbon $today): array
    {
        $fyStart = $this->reports->financialYearStart($today);
        $balances = $this->reports->ledgerBalances($fyStart, $today);
        $cashBank = $balances->filter(fn ($row) => $row->ledger->isCashOrBank());

        $receivables = $this->outstanding->report('receivables', $today);
        $payables = $this->outstanding->report('payables', $today);

        $quarterStart = $today->copy()->firstOfQuarter();
        $vat = $this->vat->report($quarterStart, $today);

        $monthStart = $today->copy()->startOfMonth();
        $lastMonthStart = $monthStart->copy()->subMonthNoOverflow();
        $sameDayLastMonth = $lastMonthStart->copy()->addDays(min($today->day, $lastMonthStart->daysInMonth) - 1);

        return [
            'cashBank' => $cashBank->values(),
            'cashTotal' => $cashBank->sum('closing'),
            'receivables' => $receivables['total'],
            'receivablesOverdue' => $this->overdue($receivables['parties']),
            'payables' => $payables['total'],
            'payablesOverdue' => $this->overdue($payables['parties']),
            'vatDue' => $vat['netPayable'],
            'quarterStart' => $quarterStart,
            'salesThisMonth' => $this->sales($monthStart, $today),
            // Month to date against the same days of last month, so early-month comparisons are fair.
            'salesLastMonthToDate' => $this->sales($lastMonthStart, $sameDayLastMonth),
            'netProfitYtd' => $this->reports->profitAndLoss($fyStart, $today)['netProfit'],
            'fyStart' => $fyStart,
            'monthly' => $this->monthly($today, 12),
            'topOverdue' => $this->topOverdue($receivables['parties'], 5),
            'payablesDueSoon' => $this->dueSoon($payables['parties'], $today, 14),
            'lowStock' => $this->stock->positions($today)
                ->filter(fn ($p) => $p->item->is_active && $p->item->reorder_level !== null && $p->qty <= Money::toBaisa($p->item->reorder_level))
                ->values(),
            'postDated' => $this->banking->postDated($today)->filter(fn ($v) => $v->date->lte($today->copy()->addDays(7)))->values(),
            'recent' => Voucher::query()->with(['type', 'party'])->latest('id')->limit(8)->get(),
        ];
    }

    /**
     * Sales (Sales Accounts) and expenses (all expense groups) per month, oldest first.
     *
     * @return list<array{month: Carbon, sales: int, expenses: int}>
     */
    public function monthly(Carbon $today, int $months): array
    {
        $from = $today->copy()->startOfMonth()->subMonthsNoOverflow($months - 1);
        $salesGroups = AccountGroup::reserved('Sales Accounts')->descendantAndSelfIds();
        $expenseGroups = AccountGroup::query()->where('nature', GroupNature::Expenses->value)->pluck('id');

        $rows = DB::table('voucher_entries as e')
            ->join('vouchers as v', 'v.id', '=', 'e.voucher_id')
            ->join('ledgers as l', 'l.id', '=', 'e.ledger_id')
            ->where('v.is_cancelled', false)
            ->whereBetween('v.date', [$from->toDateString(), $today->toDateString()])
            ->where(fn ($q) => $q->whereIn('l.account_group_id', $salesGroups)->orWhereIn('l.account_group_id', $expenseGroups))
            ->groupBy('month', 'l.account_group_id')
            ->selectRaw('SUBSTR(v.date, 1, 7) AS month, l.account_group_id AS group_id, SUM(e.credit - e.debit) AS net')
            ->get();

        $series = [];
        for ($m = $from->copy(); $m->lte($today); $m->addMonthNoOverflow()) {
            $series[$m->format('Y-m')] = ['month' => $m->copy(), 'sales' => 0, 'expenses' => 0];
        }

        foreach ($rows as $row) {
            if (! isset($series[$row->month])) {
                continue;
            }
            $net = Money::toBaisa($row->net);
            if ($salesGroups->contains($row->group_id)) {
                $series[$row->month]['sales'] += $net;
            } else {
                $series[$row->month]['expenses'] -= $net;
            }
        }

        return array_values($series);
    }

    private function sales(Carbon $from, Carbon $to): int
    {
        return Money::toBaisa(DB::table('voucher_entries as e')
            ->join('vouchers as v', 'v.id', '=', 'e.voucher_id')
            ->join('ledgers as l', 'l.id', '=', 'e.ledger_id')
            ->where('v.is_cancelled', false)
            ->whereBetween('v.date', [$from->toDateString(), $to->toDateString()])
            ->whereIn('l.account_group_id', AccountGroup::reserved('Sales Accounts')->descendantAndSelfIds())
            ->sum(DB::raw('e.credit - e.debit')));
    }

    private function overdue(Collection $parties): int
    {
        return $parties->sum(fn ($p) => $p->bills->filter(fn ($b) => $b->overdue_days > 0 && $b->pending > 0)->sum('pending'));
    }

    private function topOverdue(Collection $parties, int $limit): Collection
    {
        return $parties
            ->map(fn ($p) => (object) [
                'ledger' => $p->ledger,
                'overdue' => $p->bills->filter(fn ($b) => $b->overdue_days > 0 && $b->pending > 0)->sum('pending'),
                'oldestDays' => $p->bills->max('overdue_days'),
            ])
            ->filter(fn ($p) => $p->overdue > 0)
            ->sortByDesc('overdue')
            ->take($limit)
            ->values();
    }

    private function dueSoon(Collection $parties, Carbon $today, int $days): Collection
    {
        $until = $today->copy()->addDays($days);

        return $parties
            ->flatMap(fn ($p) => $p->bills
                ->filter(fn ($b) => $b->pending > 0 && $b->due_date && $b->due_date->lte($until))
                ->map(fn ($b) => (object) ['ledger' => $p->ledger, 'bill' => $b]))
            ->sortBy(fn ($row) => $row->bill->due_date->timestamp)
            ->values();
    }
}
