<?php

namespace App\Livewire\Vouchers;

use App\Enums\BillType;
use App\Enums\TaxRole;
use App\Enums\VatCategory;
use App\Enums\VoucherBaseType;
use App\Models\CostCentre;
use App\Models\Ledger;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Services\OutstandingService;
use App\Services\VoucherService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class VoucherForm extends Component
{
    #[Locked]
    public ?int $voucherId = null;

    public int $voucher_type_id;

    public string $date = '';

    public string $reference = '';

    public string $reference_date = '';

    public string $narration = '';

    /** @var list<array{side: string, ledger_id: int|string|null, amount: string}> */
    public array $rows = [];

    public function mount(?VoucherType $type = null, ?Voucher $voucher = null): void
    {
        if ($voucher?->exists) {
            abort_if($voucher->is_cancelled, 404);

            if ($voucher->is_invoice || $voucher->type->base_type->isInventoryOnly()) {
                $this->redirect($voucher->editUrl(), navigate: true);

                return;
            }

            $voucher->load('entries.costAllocations', 'bills');

            $this->voucherId = $voucher->id;
            $this->voucher_type_id = $voucher->voucher_type_id;
            $this->date = $voucher->date->toDateString();
            $this->reference = (string) $voucher->reference;
            $this->reference_date = (string) $voucher->reference_date?->toDateString();
            $this->narration = (string) $voucher->narration;
            $this->rows = $voucher->entries->map(fn ($e) => $this->row([
                'instrument_type' => $e->instrument_type?->value ?? '',
                'instrument_no' => (string) $e->instrument_no,
                'instrument_date' => (string) $e->instrument_date?->toDateString(),
                'cost_centres' => $e->costAllocations->map(fn ($a) => [
                    'cost_centre_id' => $a->cost_centre_id,
                    'amount' => Money::toDecimal(abs(Money::toBaisa($a->amount))),
                ])->all(),
                'currency_id' => $e->currency_id,
                'fx_amount' => $e->currency_id ? Money::toDecimal(abs(Money::toBaisa($e->fx_amount))) : '',
                'fx_rate' => $e->currency_id ? (string) $e->fx_rate : '',
                'side' => Money::toBaisa($e->debit) > 0 ? 'Dr' : 'Cr',
                'ledger_id' => $e->ledger_id,
                'amount' => Money::toDecimal(Money::toBaisa($e->debit) ?: Money::toBaisa($e->credit)),
                'bills' => $voucher->bills->where('ledger_id', $e->ledger_id)->map(fn ($b) => [
                    'type' => $b->type->value,
                    'reference' => $b->type === BillType::OnAccount ? '' : $b->reference,
                    'pending' => '',
                    'amount' => Money::toDecimal(abs(Money::toBaisa($b->amount))),
                ])->values()->all(),
            ]))->all();

            return;
        }

        if ($type->base_type->isInventoryOnly()) {
            $this->redirect($type->createUrl(), navigate: true);

            return;
        }

        $this->voucher_type_id = $type->id;
        $this->date = session('voucher_date', now()->toDateString());
        // Tally's default first line: the party side of the voucher.
        $first = in_array($type->base_type, [VoucherBaseType::Receipt, VoucherBaseType::Purchase, VoucherBaseType::CreditNote], true) ? 'Cr' : 'Dr';
        $this->rows = [
            $this->row(['side' => $first]),
            $this->row(['side' => $first === 'Dr' ? 'Cr' : 'Dr']),
        ];
    }

    /** A voucher line with every optional detail present. */
    private function row(array $values = []): array
    {
        return array_merge([
            'side' => 'Dr', 'ledger_id' => null, 'amount' => '', 'bills' => [],
            'instrument_type' => '', 'instrument_no' => '', 'instrument_date' => '',
            'cost_centres' => [],
            'currency_id' => null, 'fx_amount' => '', 'fx_rate' => '',
        ], $values);
    }

    public function addRow(): void
    {
        [$debit, $credit] = $this->totals();
        $difference = $debit - $credit;

        $this->rows[] = $this->row([
            'side' => $difference > 0 ? 'Cr' : 'Dr',
            'amount' => $difference !== 0 ? Money::toDecimal(abs($difference)) : '',
        ]);
    }

    public function removeRow(int $index): void
    {
        unset($this->rows[$index]);
        $this->rows = array_values($this->rows);
    }

    /** Tally-style: when a ledger is chosen on a line with no amount, fill in the balancing figure. */
    public function updated(string $property, $value): void
    {
        if (! preg_match('/^rows\.(\d+)\.(\w+)$/', $property, $m)) {
            return;
        }

        [, $index, $field] = $m;

        if ($field === 'ledger_id') {
            $ledger = $value ? Ledger::query()->with('currency')->find($value) : null;
            $this->rows[$index] = array_merge($this->rows[$index], [
                'bills' => [], 'cost_centres' => [],
                'currency_id' => $ledger?->currency_id,
                'fx_amount' => '',
                'fx_rate' => $ledger?->currency ? (string) $ledger->currency->rateOn(Carbon::parse($this->date ?: 'today')) : '',
            ]);
        }

        // Foreign currency line: OMR amount = currency amount × rate.
        if (in_array($field, ['fx_amount', 'fx_rate'], true) && $this->rows[$index]['currency_id']) {
            $fx = $this->safeBaisa($this->rows[$index]['fx_amount']);
            $rate = $this->rows[$index]['fx_rate'];
            if ($fx && is_numeric($rate)) {
                $this->rows[$index]['amount'] = Money::toDecimal(Money::convert($fx, $rate));
            }

            return;
        }

        if ($field === 'ledger_id' && $value && ($this->rows[$index]['amount'] ?? '') === '') {
            [$debit, $credit] = $this->totals();
            $difference = $debit - $credit;

            if ($difference !== 0) {
                $this->rows[$index]['side'] = $difference > 0 ? 'Cr' : 'Dr';
                $this->rows[$index]['amount'] = Money::toDecimal(abs($difference));
            }
        }
    }

    /**
     * List the party's pending bills on the opposite side and allocate the line amount to them,
     * oldest first. Any remainder goes On Account.
     */
    public function allocateBills(int $index, OutstandingService $outstanding): void
    {
        $row = $this->rows[$index] ?? null;
        $ledger = $row ? Ledger::query()->find($row['ledger_id']) : null;

        if (! $ledger?->is_bill_wise) {
            return;
        }

        $remaining = $this->safeBaisa($row['amount']);
        $rowSign = $row['side'] === 'Dr' ? 1 : -1;
        $bills = [];

        $pending = $outstanding->pendingBills($ledger, Carbon::parse($this->date ?: 'today'), $this->voucherId)
            ->filter(fn ($bill) => $bill->pending * $rowSign < 0 && $bill->type !== BillType::OnAccount);

        foreach ($pending as $bill) {
            $take = min(abs($bill->pending), $remaining);
            $remaining -= $take;
            $bills[] = [
                'type' => BillType::AgainstRef->value,
                'reference' => $bill->reference,
                'pending' => Money::format(abs($bill->pending)).' '.($bill->pending > 0 ? 'Dr' : 'Cr').' · '.$bill->bill_date->format('d-M-y'),
                'amount' => $take ? Money::toDecimal($take) : '',
            ];
        }

        if ($remaining > 0 || ! $bills) {
            $bills[] = ['type' => BillType::OnAccount->value, 'reference' => '', 'pending' => '', 'amount' => $remaining ? Money::toDecimal($remaining) : ''];
        }

        $this->rows[$index]['bills'] = $bills;
    }

    public function addCostCentre(int $index): void
    {
        $allocated = array_sum(array_map(fn ($c) => $this->safeBaisa($c['amount']), $this->rows[$index]['cost_centres']));
        $remaining = $this->safeBaisa($this->rows[$index]['amount']) - $allocated;

        $this->rows[$index]['cost_centres'][] = ['cost_centre_id' => '', 'amount' => $remaining > 0 ? Money::toDecimal($remaining) : ''];
    }

    public function addBill(int $index): void
    {
        $this->rows[$index]['bills'][] = ['type' => BillType::NewRef->value, 'reference' => '', 'pending' => '', 'amount' => ''];
    }

    private function safeBaisa(?string $amount): int
    {
        try {
            return Money::toBaisa($amount ?: 0);
        } catch (\InvalidArgumentException) {
            return 0;
        }
    }

    /**
     * Compute Oman VAT (5%) on lines whose ledger is standard rated or reverse charge, and add
     * or refresh the VAT lines. Reverse charge adds both the output and input VAT lines.
     */
    public function applyVat(): void
    {
        $this->resetErrorBag();
        $type = VoucherType::query()->findOrFail($this->voucher_type_id);
        $ledgers = Ledger::query()->whereIn('id', array_filter(array_column($this->rows, 'ledger_id')))->get()->keyBy('id');
        $vatLedgers = Ledger::query()->whereNotNull('tax_role')->get();

        // Drop existing VAT lines; they are recalculated.
        $this->rows = array_values(array_filter($this->rows, fn ($row) => ! $row['ledger_id'] || ! $ledgers->get($row['ledger_id'])?->tax_role));

        $standard = 0;
        $reverse = 0;

        foreach ($this->rows as $row) {
            $ledger = $ledgers->get($row['ledger_id']);
            $signed = Money::toBaisa($row['amount'] ?: 0) * ($row['side'] === 'Dr' ? 1 : -1);

            match ($ledger?->vat_category) {
                VatCategory::Standard => $standard += $signed,
                VatCategory::ReverseCharge => $reverse += $signed,
                default => null,
            };
        }

        $outputSide = in_array($type->base_type, [VoucherBaseType::Sales, VoucherBaseType::CreditNote], true)
            || (! in_array($type->base_type, [VoucherBaseType::Purchase, VoucherBaseType::DebitNote], true) && $standard < 0);

        $vat = Money::vat($standard, VatCategory::STANDARD_RATE);
        if ($vat !== 0) {
            $this->pushVatRow($vatLedgers, $outputSide ? TaxRole::OutputVat : TaxRole::InputVat, $vat);
        }

        $rcm = Money::vat(abs($reverse), VatCategory::STANDARD_RATE);
        if ($rcm !== 0) {
            // Self-assessed: output VAT payable (Cr) and input VAT recoverable (Dr) cancel out.
            $this->pushVatRow($vatLedgers, TaxRole::ReverseChargeInput, $rcm);
            $this->pushVatRow($vatLedgers, TaxRole::ReverseChargeOutput, -$rcm);
        }

        if ($vat === 0 && $rcm === 0) {
            $this->addError('rows', 'No standard-rated or reverse-charge ledgers on this voucher. Set "VAT treatment" on the sales/purchase ledger.');

            return;
        }

        $this->fillBalancingRow();
    }

    /** Put the remaining difference on the single line that has a ledger but no amount (usually the party). */
    private function fillBalancingRow(): void
    {
        $blank = array_keys(array_filter($this->rows, fn ($row) => $row['ledger_id'] && $row['amount'] === ''));

        if (count($blank) !== 1) {
            return;
        }

        [$debit, $credit] = $this->totals();
        $difference = $debit - $credit;

        if ($difference !== 0) {
            $this->rows[$blank[0]]['side'] = $difference > 0 ? 'Cr' : 'Dr';
            $this->rows[$blank[0]]['amount'] = Money::toDecimal(abs($difference));
        }
    }

    private function pushVatRow($vatLedgers, TaxRole $role, int $signed): void
    {
        $ledger = $vatLedgers->first(fn ($l) => $l->tax_role === $role);

        if (! $ledger) {
            $this->addError('rows', "No ledger is set up as \"{$role->label()}\" under Duties & Taxes.");

            return;
        }

        $this->rows[] = $this->row(['side' => $signed > 0 ? 'Dr' : 'Cr', 'ledger_id' => $ledger->id, 'amount' => Money::toDecimal(abs($signed))]);
    }

    public function save(VoucherService $service)
    {
        $this->resetErrorBag();
        $entries = array_map(function ($row) {
            $entry = [
                'ledger_id' => $row['ledger_id'] ?: null,
                'debit' => $row['side'] === 'Dr' ? $row['amount'] : 0,
                'credit' => $row['side'] === 'Cr' ? $row['amount'] : 0,
                'instrument_type' => $row['instrument_type'] ?? null,
                'instrument_no' => $row['instrument_no'] ?? null,
                'instrument_date' => $row['instrument_date'] ?? null,
                'currency_id' => $row['currency_id'] ?? null,
                'fx_amount' => $row['fx_amount'] ?? '',
                'fx_rate' => $row['fx_rate'] ?? null,
            ];

            if (! empty($row['cost_centres'])) {
                $entry['cost_centres'] = $row['cost_centres'];
            }

            $bills = array_values(array_filter($row['bills'] ?? [], fn ($b) => $this->safeBaisa($b['amount']) > 0));

            if ($bills) {
                // Whatever is not allocated goes On Account.
                $remainder = $this->safeBaisa($row['amount']) - array_sum(array_map(fn ($b) => $this->safeBaisa($b['amount']), $bills));
                if ($remainder > 0) {
                    $bills[] = ['type' => BillType::OnAccount->value, 'reference' => '', 'amount' => Money::toDecimal($remainder)];
                }
                $entry['bills'] = array_map(fn ($b) => [
                    'type' => $b['type'],
                    'reference' => $b['type'] === BillType::OnAccount->value ? 'On Account' : $b['reference'],
                    'amount' => $b['amount'],
                ], $bills);
            }

            return $entry;
        }, $this->rows);

        try {
            $voucher = $service->save([
                'voucher_type_id' => $this->voucher_type_id,
                'date' => $this->date,
                'reference' => $this->reference ?: null,
                'reference_date' => $this->reference_date ?: null,
                'narration' => $this->narration ?: null,
                'entries' => $entries,
            ], $this->voucherId ? Voucher::query()->findOrFail($this->voucherId) : null, auth()->id());
        } catch (\InvalidArgumentException $e) {
            $this->addError('rows', 'Check the amounts: '.$e->getMessage());

            return;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field === 'entries' ? 'rows' : $field, $messages[0]);
            }

            return;
        }

        session(['voucher_date' => $this->date]);
        session()->flash('status', "{$voucher->type->name} {$voucher->number} saved.");

        return $this->voucherId
            ? $this->redirectRoute('reports.day-book', ['from' => $this->date, 'to' => $this->date], navigate: true)
            : $this->redirectRoute('vouchers.create', $this->voucher_type_id, navigate: true);
    }

    public function cancelVoucher(VoucherService $service)
    {
        $voucher = Voucher::query()->findOrFail($this->voucherId);
        $service->cancel($voucher);
        session()->flash('status', "Voucher {$voucher->number} cancelled.");

        return $this->redirectRoute('reports.day-book', navigate: true);
    }

    /** @return array{int, int} */
    private function totals(): array
    {
        $debit = 0;
        $credit = 0;

        foreach ($this->rows as $row) {
            try {
                $amount = Money::toBaisa($row['amount'] ?: 0);
            } catch (\InvalidArgumentException) {
                $amount = 0;
            }
            $row['side'] === 'Dr' ? $debit += $amount : $credit += $amount;
        }

        return [$debit, $credit];
    }

    public function render()
    {
        $type = VoucherType::query()->findOrFail($this->voucher_type_id);
        [$debit, $credit] = $this->totals();
        $voucher = $this->voucherId ? Voucher::query()->find($this->voucherId) : null;

        return view('livewire.vouchers.voucher-form', [
            'costCentres' => CostCentre::query()->orderBy('name')->get(),
            'bankGroupIds' => Ledger::bankGroupIds(),
            'type' => $type,
            'voucher' => $voucher,
            'ledgers' => Ledger::query()->with(['group', 'currency'])->where('is_active', true)->orderBy('name')->get(),
            'debit' => $debit,
            'credit' => $credit,
            'nextNumber' => $voucher?->number ?? ($type->prefix.$type->next_number),
            'types' => VoucherType::query()->where('is_active', true)->where('is_reserved', true)->get()->reject(fn ($t) => $t->base_type->isInventoryOnly()),
        ])->title(($voucher ? 'Alter ' : '').$type->name);
    }
}
