<?php

namespace App\Livewire;

use App\Models\VoucherType;
use App\Services\ReportService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Gateway')]
class Gateway extends Component
{
    public function render(ReportService $reports)
    {
        $today = now()->startOfDay();
        $balances = $reports->ledgerBalances($reports->financialYearStart($today), $today);

        return view('livewire.gateway', [
            'voucherTypes' => VoucherType::query()->where('is_active', true)->orderBy('id')->get(),
            'cashBank' => $balances->filter(fn ($row) => $row->ledger->isCashOrBank()),
        ]);
    }
}
