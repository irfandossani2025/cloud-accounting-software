<?php

namespace App\Services;

use App\Enums\BillType;
use App\Models\AccountGroup;
use App\Models\BillAllocation;
use App\Models\Ledger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Bill-by-bill receivables and payables with ageing. Amounts are baisa, signed +Dr / -Cr.
 */
class OutstandingService
{
    public const BUCKETS = ['0-30', '31-60', '61-90', '91-180', '180+'];

    public function __construct(private ReportService $reports) {}

    /**
     * Pending bills for one ledger as of a date, oldest first.
     *
     * @return Collection<int, object{reference: string, type: BillType, bill_date: Carbon, due_date: ?Carbon, pending: int, overdue_days: int}>
     */
    public function pendingBills(Ledger $ledger, ?Carbon $asOf = null, ?int $excludeVoucherId = null): Collection
    {
        $asOf ??= now()->startOfDay();

        return BillAllocation::query()
            ->where('ledger_id', $ledger->id)
            ->where('bill_date', '<=', $asOf->toDateString())
            ->when($excludeVoucherId, fn ($q) => $q->where(fn ($q) => $q->whereNull('voucher_id')->orWhere('voucher_id', '!=', $excludeVoucherId)))
            ->orderBy('bill_date')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($bill) => $bill->type === BillType::OnAccount ? 'On Account' : $bill->reference)
            ->map(function (Collection $bills, string $reference) use ($asOf) {
                // The first New Ref / Advance defines the bill's date and due date.
                $origin = $bills->first(fn ($b) => in_array($b->type, [BillType::NewRef, BillType::Advance], true)) ?? $bills->first();
                $pending = $bills->sum(fn ($b) => Money::toBaisa($b->amount));
                $ageFrom = $origin->due_date ?? $origin->bill_date;

                return (object) [
                    'reference' => $reference,
                    'type' => $origin->type,
                    'bill_date' => $origin->bill_date,
                    'due_date' => $origin->due_date,
                    'pending' => $pending,
                    'age_days' => (int) $origin->bill_date->diffInDays($asOf),
                    'overdue_days' => max(0, (int) $ageFrom->diffInDays($asOf, false)),
                ];
            })
            ->filter(fn ($bill) => $bill->pending !== 0)
            ->values();
    }

    /**
     * @param  'receivables'|'payables'  $kind
     */
    public function report(string $kind, Carbon $asOf): array
    {
        $group = AccountGroup::reserved($kind === 'receivables' ? 'Sundry Debtors' : 'Sundry Creditors');
        $sign = $kind === 'receivables' ? 1 : -1;
        $balances = $this->reports->ledgerBalances($this->reports->financialYearStart($asOf), $asOf);

        $parties = Ledger::query()
            ->whereIn('account_group_id', $group->descendantAndSelfIds())
            ->orderBy('name')
            ->get()
            ->map(function (Ledger $ledger) use ($asOf, $sign, $balances) {
                $bills = $ledger->is_bill_wise
                    ? $this->pendingBills($ledger, $asOf)->map(fn ($b) => tap($b, fn ($b) => $b->pending *= $sign))
                    : collect();

                $balance = $sign * ($balances->get($ledger->id)?->closing ?? 0);
                $billTotal = $bills->sum('pending');

                // Anything not covered by bills (non bill-wise ledgers, or entries made before bill-wise was switched on).
                if ($balance !== $billTotal) {
                    $bills->push((object) [
                        'reference' => $ledger->is_bill_wise ? 'Unallocated' : 'Balance',
                        'type' => BillType::OnAccount,
                        'bill_date' => null,
                        'due_date' => null,
                        'pending' => $balance - $billTotal,
                        'age_days' => null,
                        'overdue_days' => 0,
                    ]);
                }

                $ageing = array_fill_keys(self::BUCKETS, 0);
                foreach ($bills as $bill) {
                    $ageing[$this->bucket($bill->age_days)] += $bill->pending;
                }

                return (object) ['ledger' => $ledger, 'bills' => $bills, 'total' => $balance, 'ageing' => $ageing];
            })
            ->filter(fn ($party) => $party->total !== 0 || $party->bills->isNotEmpty())
            ->values();

        $ageing = array_fill_keys(self::BUCKETS, 0);
        foreach ($parties as $party) {
            foreach ($party->ageing as $bucket => $amount) {
                $ageing[$bucket] += $amount;
            }
        }

        return ['parties' => $parties, 'total' => $parties->sum('total'), 'ageing' => $ageing];
    }

    private function bucket(?int $days): string
    {
        return match (true) {
            $days === null, $days <= 30 => '0-30',
            $days <= 60 => '31-60',
            $days <= 90 => '61-90',
            $days <= 180 => '91-180',
            default => '180+',
        };
    }
}
