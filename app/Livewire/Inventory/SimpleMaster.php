<?php

namespace App\Livewire\Inventory;

use App\Models\Godown;
use App\Models\StockGroup;
use App\Models\StockItem;
use App\Models\StockMovement;
use App\Models\StockOpening;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Stock Groups, Units and Godowns: small masters edited inline on one page.
 */
class SimpleMaster extends Component
{
    #[Locked]
    public string $kind;

    #[Locked]
    public ?int $editingId = null;

    public array $form = [];

    private const CONFIG = [
        'stock-groups' => ['title' => 'Stock Groups', 'model' => StockGroup::class, 'order' => 'name'],
        'units' => ['title' => 'Units of Measure', 'model' => Unit::class, 'order' => 'symbol'],
        'godowns' => ['title' => 'Godowns (Locations)', 'model' => Godown::class, 'order' => 'name'],
    ];

    public function mount(string $kind): void
    {
        abort_unless(isset(self::CONFIG[$kind]), 404);
        $this->kind = $kind;
        $this->resetForm();
    }

    public function edit(int $id): void
    {
        $record = $this->model()::query()->findOrFail($id);
        $this->editingId = $id;
        $this->form = array_map(fn ($v) => $v ?? '', $record->only(array_keys($this->blank())));
        $this->resetErrorBag();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->form = $this->blank();
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $table = (new ($this->model()))->getTable();
        $unique = fn ($column) => Rule::unique($table, $column)->ignore($this->editingId);

        $rules = match ($this->kind) {
            'stock-groups' => [
                'form.name' => ['required', 'max:255', $unique('name')],
                'form.name_ar' => 'nullable|max:255',
                'form.parent_id' => ['nullable', 'exists:stock_groups,id', Rule::notIn([$this->editingId])],
            ],
            'units' => [
                'form.symbol' => ['required', 'max:20', $unique('symbol')],
                'form.name' => 'required|max:100',
                'form.decimal_places' => 'required|integer|min:0|max:3',
            ],
            'godowns' => [
                'form.name' => ['required', 'max:255', $unique('name')],
                'form.name_ar' => 'nullable|max:255',
                'form.address' => 'nullable|max:255',
            ],
        };

        $data = array_map(fn ($v) => $v === '' ? null : $v, $this->validate($rules)['form']);
        $this->model()::query()->updateOrCreate(['id' => $this->editingId], $data);

        session()->flash('status', 'Saved.');
        $this->resetForm();
    }

    public function delete(int $id): void
    {
        $record = $this->model()::query()->findOrFail($id);

        $inUse = match ($this->kind) {
            'stock-groups' => $record->children()->exists() || $record->items()->exists(),
            'units' => StockItem::query()->where('unit_id', $id)->exists(),
            'godowns' => $record->is_reserved || StockMovement::query()->where('godown_id', $id)->exists() || StockOpening::query()->where('godown_id', $id)->exists(),
        };

        if ($inUse) {
            $this->addError('form', 'This is in use and cannot be deleted.');

            return;
        }

        $record->delete();
        $this->resetForm();
    }

    private function blank(): array
    {
        return match ($this->kind) {
            'stock-groups' => ['name' => '', 'name_ar' => '', 'parent_id' => ''],
            'units' => ['symbol' => '', 'name' => '', 'decimal_places' => '0'],
            'godowns' => ['name' => '', 'name_ar' => '', 'address' => ''],
        };
    }

    /** @return class-string<Model> */
    private function model(): string
    {
        return self::CONFIG[$this->kind]['model'];
    }

    public function render()
    {
        $config = self::CONFIG[$this->kind];

        return view('livewire.inventory.simple-master', [
            'title' => $config['title'],
            'records' => $config['model']::query()->orderBy($config['order'])->get(),
            'groups' => StockGroup::query()->orderBy('name')->get(),
        ])->title($config['title']);
    }
}
