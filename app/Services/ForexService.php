<?php

namespace App\Services;

use App\Models\Ledger;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ForexService
{
    public function __construct(private ReportService $reports) {}

    /**
     * Foreign currency ledgers: balance in the currency, book balance in OMR, value at the rate on $asOf,
     * and the unrealised gain (+) or loss (-) that a revaluation journal would post.
     */
    public function position(Carbon $asOf): array
    {
        $balances = $this->reports->ledgerBalances($this->reports->financialYearStart($asOf), $asOf);

        $fx = DB::table('voucher_entries as e')
            ->join('vouchers as v', 'v.id', '=', 'e.voucher_id')
            ->where('v.is_cancelled', false)
            ->where('v.date', '<=', $asOf->toDateString())
            ->whereNotNull('e.currency_id')
            ->groupBy('e.ledger_id')
            ->selectRaw('e.ledger_id, SUM(e.fx_amount) AS fx')
            ->pluck('fx', 'ledger_id');

        $rows = Ledger::query()->with('currency')->whereNotNull('currency_id')->orderBy('name')->get()
            ->map(function (Ledger $ledger) use ($balances, $fx, $asOf) {
                $fxBalance = Money::toBaisa($ledger->opening_fx_balance ?? 0) + Money::toBaisa($fx[$ledger->id] ?? 0);
                $book = $balances->get($ledger->id)?->closing ?? 0;
                $rate = $ledger->currency->rateOn($asOf);
                $revalued = $rate !== null ? Money::convert($fxBalance, $rate) : null;

                return (object) [
                    'ledger' => $ledger,
                    'fxBalance' => $fxBalance,
                    'book' => $book,
                    'rate' => $rate,
                    'revalued' => $revalued,
                    // A higher debit (or lower credit) value than the books is a gain.
                    'gain' => $revalued !== null ? $revalued - $book : null,
                ];
            })
            ->filter(fn ($r) => $r->fxBalance !== 0 || $r->book !== 0)
            ->values();

        return ['rows' => $rows, 'totalGain' => $rows->sum('gain')];
    }

    /** Journal that brings each ledger to its revalued OMR amount against Forex Gain/Loss. */
    public function revaluationEntries(Carbon $asOf): array
    {
        $forex = Ledger::query()->where('name', 'Forex Gain/Loss')->firstOrFail();
        $entries = [];
        $net = 0;

        foreach ($this->position($asOf)['rows'] as $row) {
            if (! $row->gain) {
                continue;
            }
            $entries[] = ['ledger_id' => $row->ledger->id, $row->gain > 0 ? 'debit' : 'credit' => Money::toDecimal(abs($row->gain))];
            $net += $row->gain;
        }

        if ($net !== 0) {
            $entries[] = ['ledger_id' => $forex->id, $net > 0 ? 'credit' : 'debit' => Money::toDecimal(abs($net))];
        }

        return $entries;
    }
}
