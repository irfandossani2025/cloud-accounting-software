<?php

namespace App\Livewire\Reports;

use App\Models\Budget;
use App\Services\BudgetService;
use Livewire\Component;

class BudgetVariance extends Component
{
    public Budget $budget;

    public function render(BudgetService $service)
    {
        return view('livewire.reports.budget-variance', $service->variance($this->budget))
            ->title('Budget: '.$this->budget->name);
    }
}
