@php use App\Support\Money; use App\Services\StockService as S; @endphp
<div>
    <x-page-header title="Stock Summary" :subtitle="\Illuminate\Support\Carbon::parse($from)->format('d-M-Y').' to '.\Illuminate\Support\Carbon::parse($to)->format('d-M-Y')" :back="route('gateway')" />
    <div class="no-print mb-1 flex flex-wrap items-end gap-2">
        <div>
            <label class="label">Godown</label>
            <select wire:model.live="godown" class="input">
                <option value="">All godowns</option>
                @foreach ($godowns as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
            </select>
        </div>
    </div>
    @include('livewire.reports.partials.period', ['showDetailed' => false])

    <div class="card overflow-x-auto">
        <table class="table min-w-[56rem]">
            <thead>
                <tr><th rowspan="2">Particulars</th><th colspan="2" class="text-center">Opening</th><th colspan="2" class="text-center">Inwards</th><th colspan="2" class="text-center">Outwards</th><th colspan="3" class="text-center">Closing</th></tr>
                <tr><th class="text-right">Qty</th><th class="text-right">Value</th><th class="text-right">Qty</th><th class="text-right">Value</th><th class="text-right">Qty</th><th class="text-right">Value</th><th class="text-right">Qty</th><th class="text-right">Rate</th><th class="text-right">Value</th></tr>
            </thead>
            <tbody>
                @forelse ($groups as $groupName => $rows)
                    <tr class="bg-slate-50 font-semibold">
                        <td>{{ $groupName }}</td>
                        <td></td><td class="num">{{ Money::format($rows->sum('openingValue'), true) }}</td>
                        <td></td><td class="num">{{ Money::format($rows->sum('inValue'), true) }}</td>
                        <td></td><td class="num">{{ Money::format($rows->sum('outValue'), true) }}</td>
                        <td></td><td></td><td class="num">{{ Money::format($rows->sum('closingValue'), true) }}</td>
                    </tr>
                    @foreach ($rows as $r)
                        @php $u = $r->item->unit->symbol; @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="pl-6"><a href="{{ route('reports.stock-item', $r->item) }}?from={{ $from }}&to={{ $to }}" wire:navigate class="hover:underline">{{ $r->item->name }}</a></td>
                            <td class="num">{{ $r->openingQty ? S::qty($r->openingQty).' '.$u : '' }}</td><td class="num">{{ Money::format($r->openingValue, true) }}</td>
                            <td class="num">{{ $r->inQty ? S::qty($r->inQty).' '.$u : '' }}</td><td class="num">{{ Money::format($r->inValue, true) }}</td>
                            <td class="num">{{ $r->outQty ? S::qty($r->outQty).' '.$u : '' }}</td><td class="num">{{ Money::format($r->outValue, true) }}</td>
                            <td class="num {{ $r->closingQty < 0 ? 'text-red-700' : '' }}">{{ S::qty($r->closingQty) }} {{ $u }}</td>
                            <td class="num">{{ Money::format($r->closingRate, true) }}</td>
                            <td class="num">{{ Money::format($r->closingValue, true) }}</td>
                        </tr>
                    @endforeach
                @empty
                    <tr><td colspan="10" class="py-6 text-center text-slate-400">No stock.</td></tr>
                @endforelse
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <td>Grand total</td>
                    <td></td><td class="num">{{ Money::format($totals['openingValue']) }}</td>
                    <td></td><td class="num">{{ Money::format($totals['inValue']) }}</td>
                    <td></td><td class="num">{{ Money::format($totals['outValue']) }}</td>
                    <td></td><td></td><td class="num border-t-2 border-slate-400">{{ Money::format($totals['closingValue']) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="mt-2 text-xs text-slate-500">Valuation: weighted average cost. Inward value is purchase cost net of VAT and purchase returns; outwards are shown at the closing average cost.</p>
</div>
