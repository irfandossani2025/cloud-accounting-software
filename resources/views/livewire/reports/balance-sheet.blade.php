<div>
    <x-page-header title="Balance Sheet" :subtitle="'As on '.\Illuminate\Support\Carbon::parse($to)->format('d-M-Y')" :back="route('gateway')" />
    @include('livewire.reports.partials.period', ['asOfOnly' => true])

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Liabilities</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    @include('livewire.reports.partials.tree', ['nodes' => $liabilities, 'depth' => 0, 'columns' => 'single'])
                    <tr class="font-semibold"><td>Profit &amp; Loss A/c</td><td class="num"><x-amount :value="$plOpening + $netProfit" /></td></tr>
                    <tr class="text-slate-600"><td class="pl-8">Opening balance</td><td class="num"><x-amount :value="$plOpening" /></td></tr>
                    <tr class="text-slate-600"><td class="pl-8">Current period</td><td class="num"><x-amount :value="$netProfit" /></td></tr>
                    @if ($openingDifference > 0)
                        <tr class="text-amber-700"><td>Difference in opening balances</td><td class="num">{{ \App\Support\Money::format($openingDifference) }}</td></tr>
                    @endif
                </tbody>
                <tfoot><tr class="font-semibold"><td>Total</td><td class="num border-t-2 border-slate-400">{{ \App\Support\Money::format($liabilityTotal) }}</td></tr></tfoot>
            </table>
        </div>
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Assets</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    @include('livewire.reports.partials.tree', ['nodes' => $assets, 'depth' => 0, 'columns' => 'single'])
                    @if ($closingStock)
                        <tr class="font-semibold"><td><a href="{{ route('reports.stock-summary') }}?to={{ $to }}" wire:navigate class="hover:underline">Closing Stock</a></td><td class="num">{{ \App\Support\Money::format($closingStock) }}</td></tr>
                    @endif
                    @if ($openingDifference < 0)
                        <tr class="text-amber-700"><td>Difference in opening balances</td><td class="num">{{ \App\Support\Money::format(-$openingDifference) }}</td></tr>
                    @endif
                </tbody>
                <tfoot><tr class="font-semibold"><td>Total</td><td class="num border-t-2 border-slate-400">{{ \App\Support\Money::format($assetTotal) }}</td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
