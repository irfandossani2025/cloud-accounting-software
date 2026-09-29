<?php

namespace App\Livewire\Inventory;

use App\Models\StockGroup;
use App\Services\StockService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Stock Items')]
class StockItemIndex extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public ?int $group = null;

    public function render(StockService $stock)
    {
        $positions = $stock->positions(now()->startOfDay())
            ->filter(fn ($p) => $this->search === '' || str_contains(mb_strtolower($p->item->name.' '.$p->item->alias.' '.$p->item->part_no), mb_strtolower($this->search)))
            ->filter(fn ($p) => ! $this->group || $p->item->stock_group_id === $this->group)
            ->values();

        return view('livewire.inventory.stock-item-index', [
            'positions' => $positions,
            'groups' => StockGroup::query()->orderBy('name')->get(),
        ]);
    }
}
