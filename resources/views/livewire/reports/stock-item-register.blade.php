@php use App\Services\StockService as S; use App\Support\Money; @endphp
<div>
    <x-page-header :title="'Stock item: '.$item->name" :subtitle="\Illuminate\Support\Carbon::parse($from)->format('d-M-Y').' to '.\Illuminate\Support\Carbon::parse($to)->format('d-M-Y')" :back="route('stock-items.index')">
        <a href="{{ route('stock-items.edit', $item) }}" wire:navigate class="btn-secondary">Alter item</a>
    </x-page-header>
    @include('livewire.reports.partials.period', ['showDetailed' => false])

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Date</th><th>Particulars</th><th>Vch type</th><th>Vch no.</th><th>Godown</th><th class="text-right">Inwards</th><th class="text-right">Outwards</th><th class="text-right">Rate</th><th class="text-right">Balance</th></tr></thead>
            <tbody>
                <tr class="font-semibold"><td colspan="8">Opening balance</td><td class="num">{{ S::qty($opening) }} {{ $unit }}</td></tr>
                @foreach ($lines as $line)
                    @php
                        $route = match (true) {
                            (bool) $line->is_invoice => route('invoices.edit', $line->voucher_id),
                            in_array($line->base_type, ['stock_journal', 'physical_stock']) => route('inventory-vouchers.edit', $line->voucher_id),
                            default => route('vouchers.edit', $line->voucher_id),
                        };
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap">{{ \Illuminate\Support\Carbon::parse($line->date)->format('d-M-Y') }}</td>
                        <td><a href="{{ $route }}" wire:navigate class="hover:underline">{{ $line->party ?? $line->type }}</a></td>
                        <td>{{ $line->type }}</td>
                        <td>{{ $line->number }}</td>
                        <td class="text-slate-500">{{ $line->godown }}</td>
                        <td class="num">{{ $line->qty > 0 ? S::qty($line->qty).' '.$unit : '' }}</td>
                        <td class="num">{{ $line->qty < 0 ? S::qty(-$line->qty).' '.$unit : '' }}</td>
                        <td class="num">{{ Money::format($line->rate, true) }}</td>
                        <td class="num">{{ S::qty($line->balance) }} {{ $unit }}</td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot><tr class="font-semibold"><td colspan="8">Closing balance</td><td class="num border-t-2 border-slate-400">{{ S::qty($closing) }} {{ $unit }}</td></tr></tfoot>
        </table>
    </div>
</div>
