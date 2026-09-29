<?php

namespace App\Services;

use App\Enums\TaxRole;
use App\Enums\VatCategory;
use App\Enums\VoucherBaseType;
use App\Models\Godown;
use App\Models\Ledger;
use App\Models\StockItem;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
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
     *        currency_id? + fx_rate? (lines are then in that currency; the books are always in OMR),
     *        lines: list of [ledger_id, stock_item_id?, godown_id?, cost_centre_id?, description, description_ar?,
     *                        quantity, unit?, rate, discount?, vat_category?]
     */
    public function save(array $data, ?Voucher $voucher = null, ?int $userId = null): Voucher
    {
        $type = VoucherType::query()->findOrFail($data['voucher_type_id']);
        abort_unless(in_array($type->base_type, self::INVOICE_TYPES, true), 422);

        $calc = $this->calculate($type, $data['lines'] ?? []);
        $party = Ledger::query()->find($data['party_ledger_id'] ?? null);

        $errors = [];
        $currencyId = ($data['currency_id'] ?? null) ?: null;
        $fxRate = $currencyId ? (string) ($data['fx_rate'] ?? '') : null;
        if ($currencyId && (! is_numeric($fxRate) || (float) $fxRate <= 0)) {
            $errors['fx_rate'] = 'Enter the exchange rate (OMR per unit).';
        }
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

        $books = $this->inOmr($calc['lines'], $fxRate);

        $entries = [[
            'ledger_id' => $party->id,
            $partySide => Money::toDecimal($books['total']),
        ] + ($currencyId ? ['currency_id' => $currencyId, 'fx_amount' => Money::toDecimal($calc['total']), 'fx_rate' => $fxRate] : [])];

        foreach ($books['byLedger'] as $ledgerId => $amount) {
            $allocations = [];
            foreach ($books['byCostCentre'][$ledgerId] ?? [] as $costCentreId => $allocated) {
                $allocations[] = ['cost_centre_id' => $costCentreId, 'amount' => Money::toDecimal($allocated)];
            }
            $entries[] = ['ledger_id' => $ledgerId, $lineSide => Money::toDecimal($amount)] + ($allocations ? ['cost_centres' => $allocations] : []);
        }

        if ($books['vat'] !== 0) {
            $role = in_array($type->base_type, [VoucherBaseType::Sales, VoucherBaseType::CreditNote], true) ? TaxRole::OutputVat : TaxRole::InputVat;
            $entries[] = ['ledger_id' => $this->taxLedger($role)->id, $lineSide => Money::toDecimal($books['vat'])];
        }

        if ($books['reverseChargeVat'] !== 0) {
            // Self-assessed VAT: recoverable input on the line side, payable output on the party side.
            $entries[] = ['ledger_id' => $this->taxLedger(TaxRole::ReverseChargeInput)->id, $lineSide => Money::toDecimal($books['reverseChargeVat'])];
            $entries[] = ['ledger_id' => $this->taxLedger(TaxRole::ReverseChargeOutput)->id, $partySide => Money::toDecimal($books['reverseChargeVat'])];
        }

        $dueDate = $data['due_date'] ?? null;
        if (! $dueDate && $party->credit_days) {
            $dueDate = Carbon::parse($data['date'])->addDays($party->credit_days)->toDateString();
        }

        return DB::transaction(function () use ($type, $data, $dueDate, $party, $entries, $calc, $voucher, $userId, $currencyId, $fxRate) {
            $voucher = $this->vouchers->save([
                'voucher_type_id' => $type->id,
                'date' => $data['date'],
                'reference' => $data['reference'] ?? null,
                'reference_date' => $data['reference_date'] ?? null,
                'due_date' => $dueDate,
                'narration' => $data['narration'] ?? null,
                'party_ledger_id' => $party->id,
                'entries' => $entries,
                'invoice_lines' => $calc['lines'],
                'currency_id' => $currencyId,
                'fx_rate' => $fxRate,
            ], $voucher, $userId);

            $this->postStock($voucher, $type);

            return $voucher;
        });
    }

    /**
     * Book values in OMR. For a foreign-currency invoice each line is converted at the voucher rate
     * and VAT is recalculated on the OMR amount (Oman VAT is always accounted in OMR).
     *
     * @return array{byLedger: array<int,int>, byCostCentre: array<int,array<int,int>>, net: int, vat: int, reverseChargeVat: int, total: int}
     */
    public function inOmr(array $lines, ?string $fxRate): array
    {
        $byLedger = [];
        $byCostCentre = [];
        $net = 0;
        $vat = 0;
        $reverseChargeVat = 0;

        foreach ($lines as $line) {
            $amount = Money::toBaisa($line['amount']);
            $lineVat = Money::toBaisa($line['vat_amount']);

            if ($fxRate) {
                $amount = Money::convert($amount, $fxRate);
                $lineVat = $lineVat ? Money::vat($amount, (string) $line['vat_rate']) : 0;
            }

            $line['vat_category'] === VatCategory::ReverseCharge ? $reverseChargeVat += $lineVat : $vat += $lineVat;
            $net += $amount;
            $byLedger[$line['ledger_id']] = ($byLedger[$line['ledger_id']] ?? 0) + $amount;

            if (! empty($line['cost_centre_id'])) {
                $byCostCentre[$line['ledger_id']][$line['cost_centre_id']] = ($byCostCentre[$line['ledger_id']][$line['cost_centre_id']] ?? 0) + $amount;
            }
        }

        return compact('byLedger', 'byCostCentre', 'net', 'vat', 'reverseChargeVat') + ['total' => $net + $vat];
    }

    /**
     * Stock movements for lines with a stock item. Purchases (and purchase returns) set the average cost;
     * sales and sales returns move quantity only.
     */
    private function postStock(Voucher $voucher, VoucherType $type): void
    {
        $voucher->stockMovements()->delete();

        [$direction, $affectsCost] = match ($type->base_type) {
            VoucherBaseType::Purchase => [1, true],
            VoucherBaseType::DebitNote => [-1, true],
            VoucherBaseType::CreditNote => [1, false],
            default => [-1, false],
        };

        foreach ($voucher->invoiceLines()->whereNotNull('stock_item_id')->get() as $line) {
            $qty = Money::toBaisa($line->quantity);
            $amount = Money::toBaisa($line->amount);
            if ($voucher->fx_rate) {
                $amount = Money::convert($amount, $voucher->fx_rate);
            }

            $voucher->stockMovements()->create([
                'invoice_line_id' => $line->id,
                'stock_item_id' => $line->stock_item_id,
                'godown_id' => $line->godown_id,
                'date' => $voucher->date,
                'quantity' => Money::toDecimal($direction * $qty),
                'rate' => Money::toDecimal($qty ? intdiv($amount * 1000, $qty) : 0),
                'value' => Money::toDecimal($affectsCost ? $direction * $amount : 0),
                'affects_cost' => $affectsCost,
            ]);
        }
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
        $items = StockItem::query()->whereIn('id', array_filter(array_column($rows, 'stock_item_id')))->get()->keyBy('id');
        $ledgerIds = array_merge(
            array_filter(array_column($rows, 'ledger_id')),
            $items->pluck('sales_ledger_id')->filter()->all(),
            $items->pluck('purchase_ledger_id')->filter()->all(),
        );
        $ledgers = Ledger::query()->whereIn('id', $ledgerIds)->get()->keyBy('id');
        $mainGodown = null;

        foreach ($rows as $row) {
            $item = $items->get($row['stock_item_id'] ?? null);
            $defaultLedgerId = $isPurchaseSide ? $item?->purchase_ledger_id : $item?->sales_ledger_id;
            $ledger = $ledgers->get(($row['ledger_id'] ?? null) ?: $defaultLedgerId);
            if (! $ledger) {
                if ($item) {
                    throw ValidationException::withMessages(['lines' => "Choose the ledger for \"{$item->name}\" (or set a default on the stock item)."]);
                }

                continue;
            }

            $quantity = ($row['quantity'] ?? '') === '' ? '1' : (string) $row['quantity'];
            $rate = Money::toBaisa($row['rate'] ?? 0);
            $discount = Money::toBaisa($row['discount'] ?? 0);
            $amount = Money::multiply($rate, $quantity) - $discount;

            if ($amount <= 0) {
                continue;
            }

            if ($item && Money::toBaisa($quantity) <= 0) {
                throw ValidationException::withMessages(['lines' => "Enter a quantity for \"{$item->name}\"."]);
            }

            $category = VatCategory::tryFrom((string) ($row['vat_category'] ?? '')) ?? $item?->vat_category ?? $ledger->vat_category ?? VatCategory::OutOfScope;
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
                'stock_item_id' => $item?->id,
                'godown_id' => $item ? ((int) ($row['godown_id'] ?? 0) ?: ($mainGodown ??= Godown::main()->id)) : null,
                'cost_centre_id' => ((int) ($row['cost_centre_id'] ?? 0)) ?: null,
                'description' => trim((string) ($row['description'] ?? '')) ?: ($item?->name ?? $ledger->name),
                'description_ar' => trim((string) ($row['description_ar'] ?? '')) ?: $item?->name_ar,
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
