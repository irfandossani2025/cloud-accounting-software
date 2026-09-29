<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HasPeriod;
use App\Services\ReportService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Profit & Loss A/c')]
class ProfitLoss extends Component
{
    use HasPeriod;

    public function render(ReportService $reports)
    {
        return view('livewire.reports.profit-loss', $reports->profitAndLoss($this->fromDate(), $this->toDate()));
    }
}
