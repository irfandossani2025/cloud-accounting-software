<?php

namespace App\Services;

use App\Enums\BillType;
use App\Enums\VoucherBaseType;
use App\Models\BillAllocation;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    /**
     * Create or update a voucher.
     *
     * $data: voucher_type_id, date, reference?, reference_date?, due_date?, narration?,
     *        entries: list of [ledger_id, debit, credit, narration?, bills?],
     *        invoice_lines?: pre-computed lines (see InvoiceService), party_ledger_id?
     *
     * bills (optional, bill-wise ledgers only): list of [type, reference, amount, due_date?].
     * Without it, Sales/Purchase/Credit Note/Debit Note create a New Ref; other vouchers go On Account.
     */
    public function save(array $data, ?Voucher $voucher = null, ?int $userId = null): Voucher
    {
        $type = VoucherType::query()->findOrFail($data['voucher_type_id']);
        $entries = $this->normaliseEntries($data['entries'] ?? []);
        $ledgers = Ledger::query()->whereIn('id', array_column($entries, 'ledger_id'))->get()->keyBy('id');

        $this->validate($type, $entries, $ledgers, $data);

        return DB::transaction(function () use ($type, $entries, $ledgers, $data, $voucher, $userId) {
            $total = array_sum(array_column($entries, 'debit'));

            $attributes = [
                'voucher_type_id' => $type->id,
                'date' => $data['date'],
                'reference' => $data['reference'] ?? null,
                'reference_date' => $data['reference_date'] ?? null,
                'due_date' => $data['due_date'] ?? null,
                'party_ledger_id' => $data['party_ledger_id'] ?? $this->partyLedgerId($type, $entries, $ledgers),
                'is_invoice' => isset($data['invoice_lines']),
                'narration' => $data['narration'] ?? null,
                'total' => Money::toDecimal($total),
                'updated_by' => $userId,
            ];

            if ($voucher === null || $voucher->voucher_type_id !== $type->id) {
                $attributes['number'] = $this->nextNumber($type);
            }

            if ($voucher === null) {
                $voucher = Voucher::query()->create($attributes + ['created_by' => $userId]);
            } else {
                $voucher->update($attributes);
                $voucher->entries()->delete();
                $voucher->invoiceLines()->delete();
                $voucher->bills()->delete();
            }

            foreach ($entries as $i => $entry) {
                $voucher->entries()->create([
                    'ledger_id' => $entry['ledger_id'],
                    'debit' => Money::toDecimal($entry['debit']),
                    'credit' => Money::toDecimal($entry['credit']),
                    'vat_category' => $ledgers[$entry['ledger_id']]->vat_category,
                    'narration' => $entry['narration'],
                    'sort_order' => $i,
                ]);
            }

            foreach ($data['invoice_lines'] ?? [] as $i => $line) {
                $voucher->invoiceLines()->create($line + ['sort_order' => $i]);
            }

            $this->saveBills($voucher, $type, $entries, $ledgers);

            return $voucher->load('entries');
        });
    }

    /** Cancel keeps the voucher number in sequence (as Tally does) but removes its effect on the books. */
    public function cancel(Voucher $voucher): void
    {
        DB::transaction(function () use ($voucher) {
            $voucher->update(['is_cancelled' => true, 'total' => 0]);
            $voucher->entries()->delete();
            $voucher->stockMovements()->delete();
            $voucher->invoiceLines()->delete();
            $voucher->bills()->delete();
        });
    }

    /** Opening bill-wise balance entered on the ledger master. */
    public function syncOpeningBill(Ledger $ledger): void
    {
        $ledger->bills()->whereNull('voucher_id')->delete();
        $amount = Money::toBaisa($ledger->opening_balance);

        if ($ledger->is_bill_wise && $amount !== 0) {
            $ledger->bills()->create([
                'type' => BillType::NewRef,
                'reference' => 'Opening',
                'bill_date' => CompanySetting::current()?->books_begin_from ?? now(),
                'amount' => Money::toDecimal($amount),
            ]);
        }
    }

    private function saveBills(Voucher $voucher, VoucherType $type, array $entries, Collection $ledgers): void
    {
        $isInvoiceType = in_array($type->base_type, [VoucherBaseType::Sales, VoucherBaseType::Purchase, VoucherBaseType::CreditNote, VoucherBaseType::DebitNote], true);
        $defaultReference = in_array($type->base_type, [VoucherBaseType::Purchase, VoucherBaseType::DebitNote], true) && $voucher->reference
            ? $voucher->reference
            : $voucher->number;

        foreach ($entries as $entry) {
            $ledger = $ledgers[$entry['ledger_id']];

            if (! $ledger->is_bill_wise) {
                continue;
            }

            $sign = $entry['debit'] > 0 ? 1 : -1;
            $bills = $entry['bills'] ?? [[
                'type' => $isInvoiceType ? BillType::NewRef->value : BillType::OnAccount->value,
                'reference' => $isInvoiceType ? $defaultReference : $voucher->number,
                'amount' => $entry['debit'] ?: $entry['credit'],
                'due_date' => $voucher->due_date,
            ]];

            foreach ($bills as $bill) {
                BillAllocation::query()->create([
                    'ledger_id' => $ledger->id,
                    'voucher_id' => $voucher->id,
                    'type' => $bill['type'],
                    'reference' => $bill['reference'],
                    'bill_date' => $voucher->date,
                    'due_date' => $bill['due_date'] ?? null,
                    'amount' => Money::toDecimal($sign * $bill['amount']),
                ]);
            }
        }
    }

    /** @return list<array{ledger_id:int, debit:int, credit:int, narration:?string, bills:?array}> */
    private function normaliseEntries(array $rows): array
    {
        $entries = [];

        foreach ($rows as $row) {
            if (empty($row['ledger_id'])) {
                continue;
            }

            $debit = Money::toBaisa($row['debit'] ?? 0);
            $credit = Money::toBaisa($row['credit'] ?? 0);

            if ($debit === 0 && $credit === 0) {
                continue;
            }

            $bills = null;
            if (isset($row['bills'])) {
                $bills = [];
                foreach ($row['bills'] as $bill) {
                    $amount = Money::toBaisa($bill['amount'] ?? 0);
                    if ($amount !== 0) {
                        $bills[] = [
                            'type' => $bill['type'],
                            'reference' => trim((string) ($bill['reference'] ?? '')),
                            'amount' => $amount,
                            'due_date' => ($bill['due_date'] ?? null) ?: null,
                        ];
                    }
                }
            }

            $entries[] = [
                'ledger_id' => (int) $row['ledger_id'],
                'debit' => $debit,
                'credit' => $credit,
                'narration' => $row['narration'] ?? null,
                'bills' => $bills,
            ];
        }

        return $entries;
    }

    private function validate(VoucherType $type, array $entries, Collection $ledgers, array $data): void
    {
        $errors = [];

        if (empty($data['date'])) {
            $errors['date'] = 'Date is required.';
        } elseif ($company = CompanySetting::current()) {
            if (Carbon::parse($data['date'])->lt($company->books_begin_from)) {
                $errors['date'] = 'Date is before books begin ('.$company->books_begin_from->format('d-M-Y').').';
            }
        }

        foreach ($entries as $entry) {
            if ($entry['debit'] < 0 || $entry['credit'] < 0) {
                $errors['entries'] = 'Amounts cannot be negative.';
            }
            if ($entry['debit'] > 0 && $entry['credit'] > 0) {
                $errors['entries'] = 'A line cannot have both a debit and a credit amount.';
            }
        }

        if (count($entries) < 2) {
            $errors['entries'] ??= 'At least one debit and one credit line are required.';
        }

        $debits = array_sum(array_column($entries, 'debit'));
        $credits = array_sum(array_column($entries, 'credit'));

        if (! isset($errors['entries']) && $debits !== $credits) {
            $errors['entries'] = 'Debit total ('.Money::format($debits).') does not equal credit total ('.Money::format($credits).').';
        }

        if ($ledgers->count() !== count(array_unique(array_column($entries, 'ledger_id')))) {
            $errors['entries'] = 'One or more ledgers do not exist.';
        } elseif (! isset($errors['entries'])) {
            $errors['entries'] = $this->typeRuleViolation($type->base_type, $entries, $ledgers)
                ?? $this->billViolation($entries, $ledgers);
        }

        $errors = array_filter($errors);

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    private function billViolation(array $entries, Collection $ledgers): ?string
    {
        foreach ($entries as $entry) {
            if ($entry['bills'] === null) {
                continue;
            }

            $ledger = $ledgers[$entry['ledger_id']];
            $amount = $entry['debit'] ?: $entry['credit'];
            $allocated = array_sum(array_column($entry['bills'], 'amount'));

            if ($allocated !== $amount) {
                return "Bill allocations for {$ledger->name} (".Money::format($allocated).') must equal the amount ('.Money::format($amount).').';
            }

            foreach ($entry['bills'] as $bill) {
                if (! BillType::tryFrom($bill['type'])) {
                    return 'Invalid bill type.';
                }
                if ($bill['type'] !== BillType::OnAccount->value && $bill['reference'] === '') {
                    return "Enter a bill reference for {$ledger->name}.";
                }
                if ($bill['amount'] < 0) {
                    return 'Bill amounts cannot be negative.';
                }
            }
        }

        return null;
    }

    /** Tally's per-voucher-type ledger rules. */
    private function typeRuleViolation(VoucherBaseType $base, array $entries, Collection $ledgers): ?string
    {
        $isCashBank = fn (array $e) => $ledgers[$e['ledger_id']]->isCashOrBank();

        return match ($base) {
            VoucherBaseType::Contra => collect($entries)->every($isCashBank)
                ? null : 'Contra vouchers can only use Cash and Bank ledgers.',
            VoucherBaseType::Payment => collect($entries)->contains(fn ($e) => $e['credit'] > 0 && $isCashBank($e))
                ? null : 'A Payment must credit a Cash or Bank ledger.',
            VoucherBaseType::Receipt => collect($entries)->contains(fn ($e) => $e['debit'] > 0 && $isCashBank($e))
                ? null : 'A Receipt must debit a Cash or Bank ledger.',
            default => null,
        };
    }

    /** The first non cash/bank, non tax ledger on the "party" side, used for listings and outstanding reports. */
    private function partyLedgerId(VoucherType $type, array $entries, Collection $ledgers): ?int
    {
        $side = match ($type->base_type) {
            VoucherBaseType::Sales, VoucherBaseType::DebitNote, VoucherBaseType::Payment => 'debit',
            VoucherBaseType::Purchase, VoucherBaseType::CreditNote, VoucherBaseType::Receipt => 'credit',
            default => null,
        };

        if ($side === null) {
            return null;
        }

        foreach ($entries as $entry) {
            $ledger = $ledgers[$entry['ledger_id']];
            if ($entry[$side] > 0 && ! $ledger->isCashOrBank() && $ledger->tax_role === null) {
                return $ledger->id;
            }
        }

        return null;
    }

    public function nextNumber(VoucherType $type): string
    {
        $locked = VoucherType::query()->lockForUpdate()->findOrFail($type->id);

        do {
            $number = ($locked->prefix ?? '').$locked->next_number;
            $locked->next_number++;
        } while (Voucher::query()->where('voucher_type_id', $locked->id)->where('number', $number)->exists());

        $locked->save();

        return $number;
    }
}
