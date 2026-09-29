<?php

namespace App\Livewire\Vouchers;

use App\Enums\GroupNature;
use App\Enums\VatCategory;
use App\Enums\VoucherBaseType;
use App\Models\AccountGroup;
use App\Models\Godown;
use App\Models\Ledger;
use App\Models\StockItem;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Services\InvoiceService;
use App\Services\StockService;
use App\Services\VoucherService;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class InvoiceForm extends Component
{
    #[Locked]
    public ?int $voucherId = null;

    #[Locked]
    public int $voucher_type_id;

    public string $date = '';

    public ?int $party_ledger_id = null;

    public string $reference = '';

    public string $reference_date = '';

    public string $due_date = '';

    public string $narration = '';

    /** @var list<array{ledger_id: int|string|null, description: string, description_ar: string, quantity: string, unit: string, rate: string, discount: string, vat_category: string}> */
    public array $lines = [];

    public function mount(?VoucherType $type = null, ?Voucher $voucher = null): void
    {
        if ($voucher?->exists) {
            abort_if($voucher->is_cancelled, 404);
            $voucher->load('invoiceLines');

            $this->voucherId = $voucher->id;
            $this->voucher_type_id = $voucher->voucher_type_id;
            $this->date = $voucher->date->toDateString();
            $this->party_ledger_id = $voucher->party_ledger_id;
            $this->reference = (string) $voucher->reference;
            $this->reference_date = (string) $voucher->reference_date?->toDateString();
            $this->due_date = (string) $voucher->due_date?->toDateString();
            $this->narration = (string) $voucher->narration;
            $this->lines = $voucher->invoiceLines->map(fn ($line) => [
                'stock_item_id' => $line->stock_item_id,
                'godown_id' => $line->godown_id,
                'ledger_id' => $line->ledger_id,
                'description' => $line->description,
                'description_ar' => (string) $line->description_ar,
                'quantity' => rtrim(rtrim($line->quantity, '0'), '.'),
                'unit' => (string) $line->unit,
                'rate' => $line->rate,
                'discount' => Money::toBaisa($line->discount) ? $line->discount : '',
                'vat_category' => $line->vat_category->value,
            ])->all();

            return;
        }

        abort_unless(in_array($type->base_type, InvoiceService::INVOICE_TYPES, true), 404);
        $this->voucher_type_id = $type->id;
        $this->date = session('voucher_date', now()->toDateString());
        $this->lines = [$this->blankLine()];
    }

    public function addLine(): void
    {
        $this->lines[] = $this->blankLine();
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines) ?: [$this->blankLine()];
    }

    public function updated(string $property, $value): void
    {
        // Default the line's VAT treatment and description from the chosen ledger.
        if (preg_match('/^lines\.(\d+)\.ledger_id$/', $property, $m) && $value) {
            $ledger = Ledger::query()->find($value);
            $this->lines[$m[1]]['vat_category'] = $ledger?->vat_category?->value ?? VatCategory::OutOfScope->value;
        }

        // A stock item fills in its ledger, description, unit, rate and VAT treatment.
        if (preg_match('/^lines\.(\d+)\.stock_item_id$/', $property, $m) && $value) {
            $item = StockItem::query()->with('unit')->find($value);
            if (! $item) {
                return;
            }

            $type = VoucherType::query()->findOrFail($this->voucher_type_id);
            $salesSide = in_array($type->base_type, [VoucherBaseType::Sales, VoucherBaseType::CreditNote], true);
            $ledgerId = ($salesSide ? $item->sales_ledger_id : $item->purchase_ledger_id) ?: $this->lines[$m[1]]['ledger_id'];
            $rate = $salesSide ? $item->sales_rate : $item->purchase_rate;

            $this->lines[$m[1]] = array_merge($this->lines[$m[1]], [
                'ledger_id' => $ledgerId,
                'description' => $item->name,
                'description_ar' => (string) $item->name_ar,
                'unit' => $item->unit->symbol,
                'rate' => $rate !== null ? $rate : $this->lines[$m[1]]['rate'],
                'vat_category' => $item->vat_category?->value
                    ?? Ledger::query()->find($ledgerId)?->vat_category?->value
                    ?? $this->lines[$m[1]]['vat_category'],
                'godown_id' => $this->lines[$m[1]]['godown_id'] ?: Godown::main()->id,
            ]);
        }
    }

    public function save(InvoiceService $invoices)
    {
        $this->resetErrorBag();

        try {
            $voucher = $invoices->save([
                'voucher_type_id' => $this->voucher_type_id,
                'date' => $this->date,
                'party_ledger_id' => $this->party_ledger_id,
                'reference' => $this->reference ?: null,
                'reference_date' => $this->reference_date ?: null,
                'due_date' => $this->due_date ?: null,
                'narration' => $this->narration ?: null,
                'lines' => $this->lines,
            ], $this->voucherId ? Voucher::query()->findOrFail($this->voucherId) : null, auth()->id());
        } catch (\InvalidArgumentException $e) {
            $this->addError('lines', 'Check the quantities and amounts: '.$e->getMessage());

            return;
        } catch (ValidationException $e) {
            foreach ($e->errors() as $field => $messages) {
                $this->addError($field === 'entries' ? 'lines' : $field, $messages[0]);
            }

            return;
        }

        session(['voucher_date' => $this->date]);
        session()->flash('status', "{$voucher->type->name} {$voucher->number} saved.");
        session()->flash('printVoucher', $voucher->id);

        return $this->voucherId
            ? $this->redirectRoute('reports.day-book', ['from' => $this->date, 'to' => $this->date], navigate: true)
            : $this->redirectRoute('invoices.create', $this->voucher_type_id, navigate: true);
    }

    public function cancelVoucher(VoucherService $service)
    {
        $voucher = Voucher::query()->findOrFail($this->voucherId);
        $service->cancel($voucher);
        session()->flash('status', "Voucher {$voucher->number} cancelled.");

        return $this->redirectRoute('reports.day-book', navigate: true);
    }

    private function blankLine(): array
    {
        return ['stock_item_id' => null, 'godown_id' => null, 'ledger_id' => null, 'description' => '', 'description_ar' => '', 'quantity' => '1', 'unit' => '', 'rate' => '', 'discount' => '', 'vat_category' => ''];
    }

    public function render(InvoiceService $invoices)
    {
        $type = VoucherType::query()->findOrFail($this->voucher_type_id);
        $isSalesSide = in_array($type->base_type, [VoucherBaseType::Sales, VoucherBaseType::CreditNote], true);

        $partyGroups = AccountGroup::reserved($isSalesSide ? 'Sundry Debtors' : 'Sundry Creditors')->descendantAndSelfIds()
            ->merge(Ledger::cashBankGroupIds());

        $lineGroupIds = AccountGroup::query()
            ->when($isSalesSide,
                fn ($q) => $q->where('nature', GroupNature::Income->value),
                fn ($q) => $q->where('nature', GroupNature::Expenses->value)->orWhere('name', 'Fixed Assets'))
            ->get()
            ->flatMap(fn ($g) => $g->descendantAndSelfIds())
            ->unique();

        try {
            $calc = $invoices->calculate($type, $this->lines);
        } catch (\InvalidArgumentException) {
            $calc = ['lines' => [], 'net' => 0, 'vat' => 0, 'reverseChargeVat' => 0, 'total' => 0];
        }

        // Per-line computed values keyed by input index, for display.
        $computed = [];
        foreach ($this->lines as $i => $line) {
            try {
                $single = $invoices->calculate($type, [$line])['lines'][0] ?? null;
            } catch (\InvalidArgumentException) {
                $single = null;
            }
            $computed[$i] = $single;
        }

        $voucher = $this->voucherId ? Voucher::query()->find($this->voucherId) : null;
        $items = StockItem::query()->with('unit')->where('is_active', true)->orderBy('name')->get();

        // Stock available per line, shown on sales-side vouchers so overselling is visible.
        $available = [];
        if ($isSalesSide) {
            $date = rescue(fn () => Carbon::parse($this->date), now(), false);
            foreach ($this->lines as $i => $line) {
                $item = $items->firstWhere('id', (int) ($line['stock_item_id'] ?? 0));
                $available[$i] = $item ? app(StockService::class)->available($item, $date, (int) ($line['godown_id'] ?? 0) ?: null, $this->voucherId) : null;
            }
        }

        return view('livewire.vouchers.invoice-form', [
            'items' => $items,
            'godowns' => Godown::query()->orderBy('name')->get(),
            'available' => $available,
            'type' => $type,
            'voucher' => $voucher,
            'isSalesSide' => $isSalesSide,
            'parties' => Ledger::query()->where('is_active', true)->whereIn('account_group_id', $partyGroups)->orderBy('name')->get(),
            'lineLedgers' => Ledger::query()->where('is_active', true)->whereIn('account_group_id', $lineGroupIds)->orderBy('name')->get(),
            'calc' => $calc,
            'computed' => $computed,
            'nextNumber' => $voucher?->number ?? ($type->prefix.$type->next_number),
            'types' => VoucherType::query()->where('is_active', true)->where('is_reserved', true)->get()->reject(fn ($t) => $t->base_type->isInventoryOnly()),
        ])->title(($voucher ? 'Alter ' : '').$type->name.' invoice');
    }
}
