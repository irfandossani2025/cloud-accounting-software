<?php

namespace App\Services;

use App\Enums\VoucherBaseType;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    /**
     * Create or update a voucher.
     *
     * $data: voucher_type_id, date, reference?, reference_date?, narration?,
     *        entries: list of [ledger_id, debit, credit, narration?]
     */
    public function save(array $data, ?Voucher $voucher = null, ?int $userId = null): Voucher
    {
        $type = VoucherType::query()->findOrFail($data['voucher_type_id']);
        $entries = $this->normaliseEntries($data['entries'] ?? []);

        $this->validate($type, $entries, $data);

        return DB::transaction(function () use ($type, $entries, $data, $voucher, $userId) {
            $total = array_sum(array_column($entries, 'debit'));

            $attributes = [
                'voucher_type_id' => $type->id,
                'date' => $data['date'],
                'reference' => $data['reference'] ?? null,
                'reference_date' => $data['reference_date'] ?? null,
                'party_ledger_id' => $this->partyLedgerId($type, $entries),
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
            }

            foreach ($entries as $i => $entry) {
                $voucher->entries()->create([
                    'ledger_id' => $entry['ledger_id'],
                    'debit' => Money::toDecimal($entry['debit']),
                    'credit' => Money::toDecimal($entry['credit']),
                    'narration' => $entry['narration'],
                    'sort_order' => $i,
                ]);
            }

            return $voucher->load('entries');
        });
    }

    /** Cancel keeps the voucher number in sequence (as Tally does) but removes its effect on the books. */
    public function cancel(Voucher $voucher): void
    {
        DB::transaction(function () use ($voucher) {
            $voucher->update(['is_cancelled' => true, 'total' => 0]);
            $voucher->entries()->delete();
        });
    }

    /** @return list<array{ledger_id:int, debit:int, credit:int, narration:?string}> */
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

            $entries[] = [
                'ledger_id' => (int) $row['ledger_id'],
                'debit' => $debit,
                'credit' => $credit,
                'narration' => $row['narration'] ?? null,
            ];
        }

        return $entries;
    }

    private function validate(VoucherType $type, array $entries, array $data): void
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

        $ledgers = Ledger::query()->whereIn('id', array_column($entries, 'ledger_id'))->get()->keyBy('id');

        if ($ledgers->count() !== count(array_unique(array_column($entries, 'ledger_id')))) {
            $errors['entries'] = 'One or more ledgers do not exist.';
        } elseif (! isset($errors['entries'])) {
            if ($message = $this->typeRuleViolation($type->base_type, $entries, $ledgers)) {
                $errors['entries'] = $message;
            }
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }

    /** Tally's per-voucher-type ledger rules. */
    private function typeRuleViolation(VoucherBaseType $base, array $entries, $ledgers): ?string
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
    private function partyLedgerId(VoucherType $type, array $entries): ?int
    {
        $side = match ($type->base_type) {
            VoucherBaseType::Sales, VoucherBaseType::DebitNote, VoucherBaseType::Payment => 'debit',
            VoucherBaseType::Purchase, VoucherBaseType::CreditNote, VoucherBaseType::Receipt => 'credit',
            default => null,
        };

        if ($side === null) {
            return null;
        }

        $ledgers = Ledger::query()->whereIn('id', array_column($entries, 'ledger_id'))->get()->keyBy('id');

        foreach ($entries as $entry) {
            $ledger = $ledgers[$entry['ledger_id']];
            if ($entry[$side] > 0 && ! $ledger->isCashOrBank() && $ledger->tax_role === null) {
                return $ledger->id;
            }
        }

        return null;
    }

    private function nextNumber(VoucherType $type): string
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
