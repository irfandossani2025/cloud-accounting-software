<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HasPeriod;
use App\Models\Ledger;
use App\Services\ReportService;
use Livewire\Component;

class LedgerStatement extends Component
{
    use HasPeriod;

    public Ledger $ledger;

    public function render(ReportService $reports)
    {
        return view('livewire.reports.ledger-statement', $reports->ledgerStatement($this->ledger, $this->fromDate(), $this->toDate()))
            ->title('Ledger: '.$this->ledger->name);
    }
}
