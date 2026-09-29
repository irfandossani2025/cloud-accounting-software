<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HasPeriod;
use App\Services\VatReturnService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('VAT Return')]
class VatReturn extends Component
{
    use HasPeriod;

    public function mount(): void
    {
        // Default to the current calendar quarter (Oman VAT returns are usually quarterly).
        if (! request()->query('from')) {
            $this->from = now()->firstOfQuarter()->toDateString();
            $this->to = now()->lastOfQuarter()->toDateString();
        }
    }

    public function quarter(int $offset): void
    {
        $start = $this->fromDate()->firstOfQuarter()->addQuarters($offset);
        $this->from = $start->toDateString();
        $this->to = $start->copy()->lastOfQuarter()->toDateString();
    }

    public function render(VatReturnService $vat)
    {
        return view('livewire.reports.vat-return', $vat->report($this->fromDate(), $this->toDate()));
    }
}
