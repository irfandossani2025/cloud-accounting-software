<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HasPeriod;
use App\Services\OutstandingService;
use Livewire\Component;

class Outstanding extends Component
{
    use HasPeriod;

    public string $kind = 'receivables';

    public function mount(string $kind): void
    {
        $this->kind = $kind;
    }

    public function render(OutstandingService $outstanding)
    {
        $title = $this->kind === 'receivables' ? 'Bills Receivable' : 'Bills Payable';

        return view('livewire.reports.outstanding', $outstanding->report($this->kind, $this->toDate()) + [
            'title' => $title,
            'buckets' => OutstandingService::BUCKETS,
        ])->title($title);
    }
}
