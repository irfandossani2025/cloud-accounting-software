<?php

namespace App\Livewire\Masters;

use App\Models\AccountGroup;
use App\Services\ReportService;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Ledgers')]
class LedgerIndex extends Component
{
    #[Url]
    public string $search = '';

    #[Url]
    public ?int $group = null;

    public function render(ReportService $reports)
    {
        $today = now()->startOfDay();
        $groupIds = $this->group ? AccountGroup::query()->find($this->group)?->descendantAndSelfIds() : null;

        $rows = $reports->ledgerBalances($reports->financialYearStart($today), $today)
            ->filter(fn ($row) => $this->search === '' || str_contains(mb_strtolower($row->ledger->name.' '.$row->ledger->alias), mb_strtolower($this->search)))
            ->filter(fn ($row) => ! $groupIds || $groupIds->contains($row->ledger->account_group_id))
            ->values();

        return view('livewire.masters.ledger-index', [
            'rows' => $rows,
            'groups' => AccountGroup::query()->orderBy('name')->get(),
        ]);
    }
}
