<div>
    <x-page-header title="Trial Balance" :subtitle="\Illuminate\Support\Carbon::parse($from)->format('d-M-Y').' to '.\Illuminate\Support\Carbon::parse($to)->format('d-M-Y')" :back="route('gateway')" />
    @include('livewire.reports.partials.period')

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Particulars</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead>
            <tbody>
                @include('livewire.reports.partials.tree', ['nodes' => $tree, 'depth' => 0, 'columns' => 'trial'])
                @if ($openingDifference !== 0)
                    <tr class="text-amber-700">
                        <td>Difference in opening balances</td>
                        <td class="num">{{ $openingDifference < 0 ? \App\Support\Money::format(-$openingDifference) : '' }}</td>
                        <td class="num">{{ $openingDifference > 0 ? \App\Support\Money::format($openingDifference) : '' }}</td>
                    </tr>
                @endif
            </tbody>
            <tfoot>
                <tr class="font-semibold">
                    <td>Grand total</td>
                    <td class="num border-t-2 border-slate-400">{{ \App\Support\Money::format($debit + max(-$openingDifference, 0)) }}</td>
                    <td class="num border-t-2 border-slate-400">{{ \App\Support\Money::format($credit + max($openingDifference, 0)) }}</td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
