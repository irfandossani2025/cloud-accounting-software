<?php

namespace App\Livewire\Reports;

use App\Enums\VoucherBaseType;
use App\Livewire\Reports\Concerns\HasPeriod;
use App\Models\Einvoice;
use App\Models\Voucher;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('E-invoices (Fawtara)')]
class EInvoiceRegister extends Component
{
    use HasPeriod;

    #[Url]
    public string $status = '';

    public function render()
    {
        $vouchers = Voucher::query()
            ->with(['type', 'party', 'einvoice'])
            ->where('is_cancelled', false)
            ->whereBetween('date', [$this->fromDate()->toDateString(), $this->toDate()->toDateString()])
            ->whereHas('type', fn ($q) => $q->whereIn('base_type', [VoucherBaseType::Sales->value, VoucherBaseType::CreditNote->value]))
            ->orderBy('date')->orderBy('id')
            ->get()
            ->filter(fn ($v) => match ($this->status) {
                '' => true,
                'pending' => ! $v->einvoice || in_array($v->einvoice->status, [Einvoice::DRAFT, Einvoice::REJECTED], true),
                default => $v->einvoice?->status === $this->status,
            })
            ->values();

        return view('livewire.reports.einvoice-register', [
            'vouchers' => $vouchers,
            'counts' => [
                'pending' => $vouchers->filter(fn ($v) => ! $v->einvoice || in_array($v->einvoice->status, [Einvoice::DRAFT, Einvoice::REJECTED], true))->count(),
            ],
        ]);
    }
}
