<?php

namespace App\Services;

use App\Enums\TaxRole;
use App\Enums\VatCategory;
use App\Enums\VoucherBaseType;
use App\Models\Ledger;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Item-wise invoices (Tally "invoice mode") for Sales, Purchase, Credit Note and Debit Note.
 * Lines are priced, VAT is calculated per line, and the double entry is generated from them.
 */
class InvoiceService
{
    public const INVOICE_TYPES = [
        VoucherBaseType::Sales,
        VoucherBaseType::Purchase,
        VoucherBaseType::CreditNote,
        VoucherBaseType::DebitNote,
    ];

    public function __construct(private VoucherService $vouchers) {}

    /**
     * $data: voucher_type_id, date, party_ledger_id, reference?, reference_date?, due_date?, narration?,
     *        lines: list of [ledger_id, description, description_ar?, quantity, unit?, rate, discount?, vat_category?]
     */
    public function save(array $data, ?Voucher $voucher = null, ?int $userId = null): Voucher
    {
        $type = VoucherType::query()->findOrFail($data['voucher_type_id']);
        abort_unless(in_array($type->base_type, self::INVOICE_TYPES, true), 422);

        $calc = $this->calculate($type, $data['lines'] ?? []);
        $party = Ledger::query()->find($data['party_ledger_id'] ?? null);

        $errors = [];
        if (! $party) {
            $errors['party_ledger_id'] = 'Select the party (customer or supplier).';
        }
        if (! $calc['lines']) {
            $errors['lines'] = 'Add at least one line with an amount.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        // Sales & Debit Note: party is debited. Purchase & Credit Note: party is credited.
        $partyDebit = in_array($type->base_type, [VoucherBaseType::Sales, VoucherBaseType::DebitNote], true);
        $lineSide = $partyDebit ? 'credit' : 'debit';
        $partySide = $partyDebit ? 'debit' : 'credit';

        $entries = [[
            'ledger_id' => $party->id,
            $partySide => Money::toDecimal($calc['total']),
        ]];

        foreach ($calc['byLedger'] as $ledgerId => $amount) {
            $entries[] = ['ledger_id' => $ledgerId, $lineSide => Money::toDecimal($amount)];
        }

        if ($calc['vat'] !== 0) {
            $role = in_array($type->base_type, [VoucherBaseType::Sales, VoucherBaseType::CreditNote], true) ? TaxRole::OutputVat : TaxRole::InputVat;
            $entries[] = ['ledger_id' => $this->taxLedger($role)->id, $lineSide => Money::toDecimal($calc['vat'])];
        }

        if ($calc['reverseChargeVat'] !== 0) {
            // Self-assessed VAT: recoverable input on the line side, payable output on the party side.
            $entries[] = ['ledger_id' => $this->taxLedger(TaxRole::ReverseChargeInput)->id, $lineSide => Money::toDecimal($calc['reverseChargeVat'])];
            $entries[] = ['ledger_id' => $this->taxLedger(TaxRole::ReverseChargeOutput)->id, $partySide => Money::toDecimal($calc['reverseChargeVat'])];
        }

        $dueDate = $data['due_date'] ?? null;
        if (! $dueDate && $party->credit_days) {
            $dueDate = Carbon::parse($data['date'])->addDays($party->credit_days)->toDateString();
        }

        return $this->vouchers->save([
            'voucher_type_id' => $type->id,
            'date' => $data['date'],
            'reference' => $data['reference'] ?? null,
            'reference_date' => $data['reference_date'] ?? null,
            'due_date' => $dueDate,
            'narration' => $data['narration'] ?? null,
            'party_ledger_id' => $party->id,
            'entries' => $entries,
            'invoice_lines' => $calc['lines'],
        ], $voucher, $userId);
    }

    /**
     * Price the lines. Amounts are baisa integers except in 'lines', which are ready to store.
     *
     * @return array{lines: list<array>, byLedger: array<int,int>, net: int, vat: int, reverseChargeVat: int, total: int}
     */
    public function calculate(VoucherType $type, array $rows): array
    {
        $lines = [];
        $byLedger = [];
        $net = 0;
        $vat = 0;
        $reverseChargeVat = 0;
        $isPurchaseSide = in_array($type->base_type, [VoucherBaseType::Purchase, VoucherBaseType::DebitNote], true);
        $ledgers = Ledger::query()->whereIn('id', array_filter(array_column($rows, 'ledger_id')))->get()->keyBy('id');

        foreach ($rows as $row) {
            $ledger = $ledgers->get($row['ledger_id'] ?? null);
            if (! $ledger) {
                continue;
            }

            $quantity = ($row['quantity'] ?? '') === '' ? '1' : (string) $row['quantity'];
            $rate = Money::toBaisa($row['rate'] ?? 0);
            $discount = Money::toBaisa($row['discount'] ?? 0);
            $amount = Money::multiply($rate, $quantity) - $discount;

            if ($amount <= 0) {
                continue;
            }

            $category = VatCategory::tryFrom((string) ($row['vat_category'] ?? '')) ?? $ledger->vat_category ?? VatCategory::OutOfScope;
            // Reverse charge is self-assessed by the buyer: it is never charged on a sales invoice.
            $chargesVat = $category === VatCategory::Standard || ($category === VatCategory::ReverseCharge && $isPurchaseSide);
            $lineVat = $chargesVat ? Money::vat($amount, $category->rate()) : 0;

            if ($category === VatCategory::ReverseCharge) {
                $reverseChargeVat += $lineVat;
            } else {
                $vat += $lineVat;
            }

            $net += $amount;
            $byLedger[$ledger->id] = ($byLedger[$ledger->id] ?? 0) + $amount;

            $lines[] = [
                'ledger_id' => $ledger->id,
                'description' => trim((string) ($row['description'] ?? '')) ?: $ledger->name,
                'description_ar' => trim((string) ($row['description_ar'] ?? '')) ?: null,
                'quantity' => Money::toDecimal(Money::toBaisa($quantity)),
                'unit' => trim((string) ($row['unit'] ?? '')) ?: null,
                'rate' => Money::toDecimal($rate),
                'discount' => Money::toDecimal($discount),
                'amount' => Money::toDecimal($amount),
                'vat_category' => $category,
                'vat_rate' => $chargesVat ? $category->rate() : '0.00',
                'vat_amount' => Money::toDecimal($lineVat),
            ];
        }

        return compact('lines', 'byLedger', 'net', 'vat', 'reverseChargeVat') + ['total' => $net + $vat];
    }

    private function taxLedger(TaxRole $role): Ledger
    {
        return Ledger::query()->where('tax_role', $role->value)->first()
            ?? throw ValidationException::withMessages(['lines' => "No ledger is set up as \"{$role->label()}\" under Duties & Taxes."]);
    }
}
