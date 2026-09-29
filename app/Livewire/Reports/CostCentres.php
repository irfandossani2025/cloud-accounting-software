<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HasPeriod;
use App\Services\CostCentreReportService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Cost Centres')]
class CostCentres extends Component
{
    use HasPeriod;

    public function render(CostCentreReportService $service)
    {
        return view('livewire.reports.cost-centres', $service->report($this->fromDate(), $this->toDate()));
    }
}
