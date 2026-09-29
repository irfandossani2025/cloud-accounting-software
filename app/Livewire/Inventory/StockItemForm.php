<?php

namespace App\Livewire\Inventory;

use App\Enums\GroupNature;
use App\Enums\VatCategory;
use App\Models\AccountGroup;
use App\Models\Godown;
use App\Models\Ledger;
use App\Models\StockGroup;
use App\Models\StockItem;
use App\Models\Unit;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Component;

class StockItemForm extends Component
{
    public ?StockItem $item = null;

    public string $name = '';

    public string $name_ar = '';

    public string $alias = '';

    public string $part_no = '';

    public ?int $stock_group_id = null;

    public ?int $unit_id = null;

    public string $vat_category = '';

    public ?int $sales_ledger_id = null;

    public ?int $purchase_ledger_id = null;

    public string $sales_rate = '';

    public string $purchase_rate = '';

    public string $reorder_level = '';

    public bool $is_active = true;

    /** @var list<array{godown_id: int|string|null, quantity: string, rate: string}> */
    public array $openings = [];

    public function mount(?StockItem $item = null): void
    {
        $this->item = $item?->exists ? $item->load('openings') : null;

        if (! $this->item) {
            $this->unit_id = Unit::query()->where('symbol', 'Nos')->value('id');
            $this->vat_category = VatCategory::Standard->value;
            $this->openings = [['godown_id' => Godown::main()->id, 'quantity' => '', 'rate' => '']];

            return;
        }

        foreach (['name', 'name_ar', 'alias', 'part_no'] as $field) {
            $this->{$field} = (string) $this->item->{$field};
        }
        foreach (['stock_group_id', 'unit_id', 'sales_ledger_id', 'purchase_ledger_id'] as $field) {
            $this->{$field} = $this->item->{$field};
        }
        foreach (['sales_rate', 'purchase_rate', 'reorder_level'] as $field) {
            $this->{$field} = (string) $this->item->{$field};
        }
        $this->vat_category = $this->item->vat_category?->value ?? '';
        $this->is_active = $this->item->is_active;
        $this->openings = $this->item->openings->map(fn ($o) => [
            'godown_id' => $o->godown_id,
            'quantity' => rtrim(rtrim($o->quantity, '0'), '.'),
            'rate' => $o->rate,
        ])->all() ?: [['godown_id' => Godown::main()->id, 'quantity' => '', 'rate' => '']];
    }

    public function addOpening(): void
    {
        $this->openings[] = ['godown_id' => null, 'quantity' => '', 'rate' => ''];
    }

    public function save()
    {
        $amount = ['nullable', 'regex:/^\d{1,15}(\.\d{1,3})?$/'];

        $this->validate([
            'name' => ['required', 'max:255', Rule::unique('stock_items', 'name')->ignore($this->item)],
            'name_ar' => 'nullable|max:255',
            'alias' => 'nullable|max:100',
            'part_no' => 'nullable|max:100',
            'stock_group_id' => 'nullable|exists:stock_groups,id',
            'unit_id' => 'required|exists:units,id',
            'vat_category' => ['nullable', Rule::enum(VatCategory::class)],
            'sales_ledger_id' => 'nullable|exists:ledgers,id',
            'purchase_ledger_id' => 'nullable|exists:ledgers,id',
            'sales_rate' => $amount,
            'purchase_rate' => $amount,
            'reorder_level' => $amount,
            'openings.*.godown_id' => 'nullable|exists:godowns,id',
            'openings.*.quantity' => $amount,
            'openings.*.rate' => $amount,
        ], ['regex' => 'Enter a number with up to 3 decimals.']);

        $openings = collect($this->openings)
            ->filter(fn ($o) => $o['godown_id'] && Money::toBaisa($o['quantity'] ?: 0) > 0)
            ->groupBy('godown_id');

        if ($openings->contains(fn ($rows) => $rows->count() > 1)) {
            $this->addError('openings', 'Enter each godown only once.');

            return;
        }

        DB::transaction(function () {
            $item = $this->item ?? new StockItem;
            $item->fill([
                'name' => $this->name,
                'name_ar' => $this->name_ar ?: null,
                'alias' => $this->alias ?: null,
                'part_no' => $this->part_no ?: null,
                'stock_group_id' => $this->stock_group_id ?: null,
                'unit_id' => $this->unit_id,
                'vat_category' => $this->vat_category ?: null,
                'sales_ledger_id' => $this->sales_ledger_id ?: null,
                'purchase_ledger_id' => $this->purchase_ledger_id ?: null,
                'sales_rate' => $this->sales_rate !== '' ? $this->sales_rate : null,
                'purchase_rate' => $this->purchase_rate !== '' ? $this->purchase_rate : null,
                'reorder_level' => $this->reorder_level !== '' ? $this->reorder_level : null,
                'is_active' => $this->is_active,
            ])->save();

            $item->openings()->delete();
            foreach ($this->openings as $o) {
                $qty = Money::toBaisa($o['quantity'] ?: 0);
                if (! $o['godown_id'] || $qty <= 0) {
                    continue;
                }
                $rate = Money::toBaisa($o['rate'] ?: 0);
                $item->openings()->create([
                    'godown_id' => $o['godown_id'],
                    'quantity' => Money::toDecimal($qty),
                    'rate' => Money::toDecimal($rate),
                    'value' => Money::toDecimal(Money::multiply($rate, Money::toDecimal($qty))),
                ]);
            }
        });

        session()->flash('status', "Stock item \"{$this->name}\" saved.");

        return $this->redirectRoute('stock-items.index', navigate: true);
    }

    public function delete()
    {
        abort_unless($this->item, 404);

        if ($this->item->movements()->exists()) {
            $this->addError('name', 'This item has transactions and cannot be deleted. Mark it inactive instead.');

            return;
        }

        $this->item->delete();
        session()->flash('status', 'Stock item deleted.');

        return $this->redirectRoute('stock-items.index', navigate: true);
    }

    public function render()
    {
        $ledgersIn = fn (GroupNature $nature) => Ledger::query()
            ->whereIn('account_group_id', AccountGroup::query()->where('nature', $nature->value)->pluck('id'))
            ->orderBy('name')->get();

        $openingTotal = 0;
        foreach ($this->openings as $o) {
            try {
                $openingTotal += Money::multiply(Money::toBaisa($o['rate'] ?: 0), $o['quantity'] ?: '0');
            } catch (\InvalidArgumentException) {
            }
        }

        return view('livewire.inventory.stock-item-form', [
            'groups' => StockGroup::query()->orderBy('name')->get(),
            'units' => Unit::query()->orderBy('symbol')->get(),
            'godowns' => Godown::query()->orderBy('name')->get(),
            'salesLedgers' => $ledgersIn(GroupNature::Income),
            'purchaseLedgers' => $ledgersIn(GroupNature::Expenses),
            'openingTotal' => $openingTotal,
        ])->title($this->item ? 'Alter stock item' : 'Create stock item');
    }
}
