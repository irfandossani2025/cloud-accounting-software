<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HasPeriod;
use App\Services\ReportService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Day Book')]
class DayBook extends Component
{
    use HasPeriod;

    public function mount(): void
    {
        // Day Book defaults to today only, as in Tally.
        $this->from = request()->query('from') ?: now()->toDateString();
    }

    public function render(ReportService $reports)
    {
        return view('livewire.reports.day-book', [
            'vouchers' => $reports->dayBook($this->fromDate(), $this->toDate()),
        ]);
    }
}
