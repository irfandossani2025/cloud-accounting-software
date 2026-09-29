<?php

namespace App\Livewire\Reports;

use App\Livewire\Reports\Concerns\HasPeriod;
use App\Models\VoucherType;
use App\Services\ForexService;
use App\Services\VoucherService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Forex Gain/Loss')]
class ForexPosition extends Component
{
    use HasPeriod;

    public function postRevaluation(ForexService $forex, VoucherService $vouchers)
    {
        $this->authorize('manage-masters');

        $entries = $forex->revaluationEntries($this->toDate());
        if (count($entries) < 2) {
            session()->flash('status', 'Nothing to revalue.');

            return;
        }

        $voucher = $vouchers->save([
            'voucher_type_id' => VoucherType::query()->where('base_type', 'journal')->where('is_reserved', true)->value('id'),
            'date' => $this->toDate()->toDateString(),
            'narration' => 'Forex revaluation at rates on '.$this->toDate()->format('d-M-Y'),
            'entries' => $entries,
        ], null, auth()->id());

        session()->flash('status', "Revaluation journal {$voucher->number} posted.");

        return $this->redirect(route('reports.forex', ['to' => $this->to]), navigate: true);
    }

    public function render(ForexService $forex)
    {
        return view('livewire.reports.forex-position', $forex->position($this->toDate()));
    }
}
