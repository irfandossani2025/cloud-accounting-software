<?php

namespace App\Livewire\Reports;

use App\Services\BankReconciliationService;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Post-dated Cheques')]
class PostDated extends Component
{
    public function render(BankReconciliationService $service)
    {
        $vouchers = $service->postDated(now()->startOfDay());

        return view('livewire.reports.post-dated', [
            'receipts' => $vouchers->filter(fn ($v) => $v->type->base_type->value === 'receipt'),
            'payments' => $vouchers->reject(fn ($v) => $v->type->base_type->value === 'receipt'),
        ]);
    }
}
