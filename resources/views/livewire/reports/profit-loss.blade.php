<div>
    <x-page-header title="Profit & Loss A/c" :subtitle="\Illuminate\Support\Carbon::parse($from)->format('d-M-Y').' to '.\Illuminate\Support\Carbon::parse($to)->format('d-M-Y')" :back="route('gateway')" />
    @include('livewire.reports.partials.period')

    @php
        $gpExpenseSide = $grossProfit > 0 ? $grossProfit : 0;
        $gpIncomeSide = $grossProfit < 0 ? -$grossProfit : 0;
        $tradingTotal = collect($tradingExpenses)->sum('total') + $openingStock + $gpExpenseSide;
        $netExpenseSide = $netProfit > 0 ? $netProfit : 0;
        $netIncomeSide = $netProfit < 0 ? -$netProfit : 0;
        $plTotal = collect($indirectExpenses)->sum('total') + $gpIncomeSide + $netExpenseSide;
    @endphp

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Particulars</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    @if ($openingStock)
                        <tr class="font-semibold"><td><a href="{{ route('reports.stock-summary') }}?from={{ $from }}&to={{ $to }}" wire:navigate class="hover:underline">Opening Stock</a></td><td class="num">{{ \App\Support\Money::format($openingStock) }}</td></tr>
                    @endif
                    @include('livewire.reports.partials.tree', ['nodes' => $tradingExpenses, 'depth' => 0, 'columns' => 'single'])
                    @if ($gpExpenseSide)
                        <tr class="font-semibold text-green-700"><td>Gross profit c/o</td><td class="num">{{ \App\Support\Money::format($gpExpenseSide) }}</td></tr>
                    @endif
                    <tr class="bg-slate-50 font-semibold"><td></td><td class="num">{{ \App\Support\Money::format($tradingTotal) }}</td></tr>
                    @if ($gpIncomeSide)
                        <tr class="font-semibold text-red-700"><td>Gross loss b/f</td><td class="num">{{ \App\Support\Money::format($gpIncomeSide) }}</td></tr>
                    @endif
                    @include('livewire.reports.partials.tree', ['nodes' => $indirectExpenses, 'depth' => 0, 'columns' => 'single'])
                    @if ($netExpenseSide)
                        <tr class="font-semibold text-green-700"><td>Net profit</td><td class="num">{{ \App\Support\Money::format($netExpenseSide) }}</td></tr>
                    @endif
                </tbody>
                <tfoot><tr class="font-semibold"><td>Total</td><td class="num border-t-2 border-slate-400">{{ \App\Support\Money::format($plTotal) }}</td></tr></tfoot>
            </table>
        </div>

        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Particulars</th><th class="text-right">Amount</th></tr></thead>
                <tbody>
                    @include('livewire.reports.partials.tree', ['nodes' => $tradingIncome, 'depth' => 0, 'columns' => 'single'])
                    @if ($closingStock)
                        <tr class="font-semibold"><td><a href="{{ route('reports.stock-summary') }}?from={{ $from }}&to={{ $to }}" wire:navigate class="hover:underline">Closing Stock</a></td><td class="num">{{ \App\Support\Money::format($closingStock) }}</td></tr>
                    @endif
                    @if ($gpIncomeSide)
                        <tr class="font-semibold text-red-700"><td>Gross loss c/o</td><td class="num">{{ \App\Support\Money::format($gpIncomeSide) }}</td></tr>
                    @endif
                    <tr class="bg-slate-50 font-semibold"><td></td><td class="num">{{ \App\Support\Money::format($tradingTotal) }}</td></tr>
                    @if ($gpExpenseSide)
                        <tr class="font-semibold text-green-700"><td>Gross profit b/f</td><td class="num">{{ \App\Support\Money::format($gpExpenseSide) }}</td></tr>
                    @endif
                    @include('livewire.reports.partials.tree', ['nodes' => $indirectIncome, 'depth' => 0, 'columns' => 'single'])
                    @if ($netIncomeSide)
                        <tr class="font-semibold text-red-700"><td>Net loss</td><td class="num">{{ \App\Support\Money::format($netIncomeSide) }}</td></tr>
                    @endif
                </tbody>
                <tfoot><tr class="font-semibold"><td>Total</td><td class="num border-t-2 border-slate-400">{{ \App\Support\Money::format($plTotal) }}</td></tr></tfoot>
            </table>
        </div>
    </div>
</div>
