@php use App\Support\Money; use App\Models\Einvoice; @endphp
<div>
    <x-page-header title="E-invoices (Fawtara)" :subtitle="\Illuminate\Support\Carbon::parse($from)->format('d-M-Y').' to '.\Illuminate\Support\Carbon::parse($to)->format('d-M-Y')" :back="route('gateway')" />

    <div class="no-print mb-1 flex flex-wrap items-end gap-2">
        <div>
            <label class="label">Status</label>
            <select wire:model.live="status" class="input">
                <option value="">All</option>
                <option value="pending">Not yet submitted</option>
                <option value="submitted">Submitted</option>
                <option value="accepted">Accepted</option>
                <option value="rejected">Rejected</option>
            </select>
        </div>
    </div>
    @include('livewire.reports.partials.period', ['showDetailed' => false])

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Date</th><th>Vch no.</th><th>Type</th><th>Customer</th><th class="text-right">Total</th><th>E-invoice</th><th>Kind</th><th>Provider ref.</th></tr></thead>
            <tbody>
                @forelse ($vouchers as $voucher)
                    @php $e = $voucher->einvoice; @endphp
                    <tr>
                        <td class="whitespace-nowrap">{{ $voucher->date->format('d-M-Y') }}</td>
                        <td><a href="{{ $voucher->editUrl() }}" wire:navigate class="hover:underline">{{ $voucher->number }}</a></td>
                        <td>{{ $voucher->type->name }}</td>
                        <td>{{ $voucher->party?->name }}</td>
                        <td class="num">{{ $voucher->currency?->code }} {{ Money::format($voucher->total) }}</td>
                        <td>
                            @php
                                [$label, $class] = match ($e?->status) {
                                    Einvoice::ACCEPTED => ['Accepted', 'bg-green-100 text-green-800'],
                                    Einvoice::SUBMITTED => ['Submitted', 'bg-blue-100 text-blue-800'],
                                    Einvoice::REJECTED => ['Rejected', 'bg-red-100 text-red-800'],
                                    Einvoice::DRAFT => [$e->xml ? 'XML ready' : 'Needs regenerating', 'bg-amber-100 text-amber-800'],
                                    default => [$voucher->is_invoice ? 'Not generated' : 'Accounting mode', 'bg-slate-100 text-slate-600'],
                                };
                            @endphp
                            <span class="rounded px-1.5 py-0.5 text-xs {{ $class }}">{{ $label }}</span>
                            @if ($e?->xml)<a href="{{ route('einvoices.xml', $e) }}" class="ml-1 text-xs text-brand-700 hover:underline">XML</a>@endif
                        </td>
                        <td class="text-xs text-slate-500">{{ $e ? (str_starts_with($e->transaction_type, '1') ? 'Full tax invoice' : 'Simplified') : '' }}</td>
                        <td class="text-xs">{{ $e?->provider_reference }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="py-6 text-center text-slate-400">No sales invoices or credit notes in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-xs text-slate-500">{{ $counts['pending'] }} document(s) not yet submitted. Open an invoice to check, generate and submit it.</p>
</div>
