<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HasPeriod;
use App\Services\ReportService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Balance Sheet')]
class BalanceSheet extends Component
{
    use HasPeriod;

    public function render(ReportService $reports)
    {
        return view('livewire.reports.balance-sheet', $reports->balanceSheet($this->toDate()));
    }
}
