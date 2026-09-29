<?php

namespace App\Services;

use App\Enums\VoucherBaseType;
use App\Models\Ledger;
use App\Models\Voucher;
use App\Models\VoucherEntry;
use App\Support\Audit;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BankReconciliationService
{
    public function __construct(private ReportService $reports) {}

    /**
     * Bank reconciliation statement for a bank ledger as of a date.
     * Amounts are baisa, signed + debit (money in) / - credit (money out).
     */
    public function statement(Ledger $bank, Carbon $asOf, bool $includeReconciled = false): array
    {
        $entries = VoucherEntry::query()
            ->with(['voucher.type', 'voucher.party'])
            ->where('ledger_id', $bank->id)
            ->whereHas('voucher', fn ($q) => $q->where('is_cancelled', false)->where('date', '<=', $asOf->toDateString()))
            ->when(! $includeReconciled, fn ($q) => $q->where(fn ($q) => $q->whereNull('bank_date')->orWhere('bank_date', '>', $asOf->toDateString())))
            ->get()
            ->sortBy(fn ($e) => $e->voucher->date->format('Ymd').str_pad((string) $e->voucher_id, 10, '0', STR_PAD_LEFT))
            ->values();

        $books = $this->reports->ledgerBalances($this->reports->financialYearStart($asOf), $asOf)->get($bank->id)?->closing ?? 0;

        $pending = $entries->filter(fn ($e) => ! $e->bank_date || $e->bank_date->gt($asOf));
        $notInBankDebit = $pending->sum(fn ($e) => Money::toBaisa($e->debit));
        $notInBankCredit = $pending->sum(fn ($e) => Money::toBaisa($e->credit));

        return [
            'entries' => $entries,
            'booksBalance' => $books,
            'notInBankDebit' => $notInBankDebit,
            'notInBankCredit' => $notInBankCredit,
            // Deposits not yet credited by the bank reduce, cheques not yet presented increase the bank balance.
            'bankBalance' => $books - $notInBankDebit + $notInBankCredit,
        ];
    }

    /** @param  array<int, ?string>  $bankDates  voucher_entry_id => Y-m-d (or null/'' to clear) */
    public function reconcile(Ledger $bank, array $bankDates): int
    {
        $entries = VoucherEntry::query()->with(['voucher', 'ledger'])->where('ledger_id', $bank->id)->whereIn('id', array_keys($bankDates))->get()->keyBy('id');
        $errors = [];

        foreach ($bankDates as $id => $date) {
            $entry = $entries->get($id);
            if (! $entry) {
                $errors["bankDates.$id"] = 'Entry not found for this bank.';
            } elseif ($date && Carbon::parse($date)->lt($entry->voucher->date)) {
                $errors["bankDates.$id"] = 'Bank date cannot be before the voucher date ('.$entry->voucher->date->format('d-M-Y').').';
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return DB::transaction(function () use ($entries, $bankDates) {
            $changed = 0;
            foreach ($bankDates as $id => $date) {
                $entry = $entries->get($id);
                $new = $date ?: null;
                if ($entry->bank_date?->toDateString() !== $new) {
                    $entry->update(['bank_date' => $new]);
                    $changed++;
                }
            }

            if ($changed) {
                Audit::log('reconciled', 'Ledger', $entries->first()->ledger_id,
                    "{$changed} bank date(s) set on ".$entries->first()->ledger->name, null, $bankDates);
            }

            return $changed;
        });
    }

    /**
     * Post-dated cheques: receipts, payments and contras dated after $today.
     *
     * @return Collection<int, Voucher>
     */
    public function postDated(Carbon $today): Collection
    {
        return Voucher::query()
            ->with(['type', 'party', 'entries.ledger'])
            ->where('is_cancelled', false)
            ->where('date', '>', $today->toDateString())
            ->whereHas('type', fn ($q) => $q->whereIn('base_type', [VoucherBaseType::Receipt->value, VoucherBaseType::Payment->value, VoucherBaseType::Contra->value]))
            ->orderBy('date')
            ->get();
    }
}
