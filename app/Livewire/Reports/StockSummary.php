<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HasPeriod;
use App\Models\Godown;
use App\Services\StockService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Stock Summary')]
class StockSummary extends Component
{
    use HasPeriod;

    #[Url]
    public ?int $godown = null;

    public function render(StockService $stock)
    {
        $rows = $stock->summary($this->fromDate(), $this->toDate(), $this->godown ?: null);

        return view('livewire.reports.stock-summary', [
            'groups' => $rows->groupBy(fn ($r) => $r->item->group?->name ?? 'Primary')->sortKeys(),
            'totals' => [
                'openingValue' => $rows->sum('openingValue'),
                'inValue' => $rows->sum('inValue'),
                'outValue' => $rows->sum('outValue'),
                'closingValue' => $rows->sum('closingValue'),
            ],
            'godowns' => Godown::query()->orderBy('name')->get(),
        ]);
    }
}
