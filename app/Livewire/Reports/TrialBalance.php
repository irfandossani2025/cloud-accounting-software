<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HasPeriod;
use App\Services\ReportService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Trial Balance')]
class TrialBalance extends Component
{
    use HasPeriod;

    public function render(ReportService $reports)
    {
        return view('livewire.reports.trial-balance', $reports->trialBalance($this->fromDate(), $this->toDate()));
    }
}
