<?php

namespace App\Livewire\Vouchers;

use App\Enums\TaxRole;
use App\Enums\VatCategory;
use App\Enums\VoucherBaseType;
use App\Models\Ledger;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Services\VoucherService;
use App\Support\Money;
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
            $voucher->load('entries');

            $this->voucherId = $voucher->id;
            $this->voucher_type_id = $voucher->voucher_type_id;
            $this->date = $voucher->date->toDateString();
            $this->reference = (string) $voucher->reference;
            $this->reference_date = (string) $voucher->reference_date?->toDateString();
            $this->narration = (string) $voucher->narration;
            $this->rows = $voucher->entries->map(fn ($e) => [
                'side' => Money::toBaisa($e->debit) > 0 ? 'Dr' : 'Cr',
                'ledger_id' => $e->ledger_id,
                'amount' => Money::toDecimal(Money::toBaisa($e->debit) ?: Money::toBaisa($e->credit)),
            ])->all();

            return;
        }

        $this->voucher_type_id = $type->id;
        $this->date = session('voucher_date', now()->toDateString());
        // Tally's default first line: the party side of the voucher.
        $first = in_array($type->base_type, [VoucherBaseType::Receipt, VoucherBaseType::Purchase, VoucherBaseType::CreditNote], true) ? 'Cr' : 'Dr';
        $this->rows = [
            ['side' => $first, 'ledger_id' => null, 'amount' => ''],
            ['side' => $first === 'Dr' ? 'Cr' : 'Dr', 'ledger_id' => null, 'amount' => ''],
        ];
    }

    public function addRow(): void
    {
        [$debit, $credit] = $this->totals();
        $difference = $debit - $credit;

        $this->rows[] = [
            'side' => $difference > 0 ? 'Cr' : 'Dr',
            'ledger_id' => null,
            'amount' => $difference !== 0 ? Money::toDecimal(abs($difference)) : '',
        ];
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

        $this->rows[] = ['side' => $signed > 0 ? 'Dr' : 'Cr', 'ledger_id' => $ledger->id, 'amount' => Money::toDecimal(abs($signed))];
    }

    public function save(VoucherService $service)
    {
        $this->resetErrorBag();
        $entries = array_map(fn ($row) => [
            'ledger_id' => $row['ledger_id'] ?: null,
            'debit' => $row['side'] === 'Dr' ? $row['amount'] : 0,
            'credit' => $row['side'] === 'Cr' ? $row['amount'] : 0,
        ], $this->rows);

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
            'type' => $type,
            'voucher' => $voucher,
            'ledgers' => Ledger::query()->with('group')->where('is_active', true)->orderBy('name')->get(),
            'debit' => $debit,
            'credit' => $credit,
            'nextNumber' => $voucher?->number ?? ($type->prefix.$type->next_number),
            'types' => VoucherType::query()->where('is_active', true)->where('is_reserved', true)->get(),
        ])->title(($voucher ? 'Alter ' : '').$type->name);
    }
}
