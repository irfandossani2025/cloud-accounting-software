@php
    use App\Support\Money;
    $change = $salesLastMonthToDate ? round(($salesThisMonth - $salesLastMonthToDate) * 100 / abs($salesLastMonthToDate)) : null;
@endphp
<div>
    <x-page-header title="Dashboard" :subtitle="'As on '.now()->format('l, j F Y')" :back="route('gateway')" />

    {{-- Headline figures --}}
    <div class="mb-4 grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
        <a href="{{ route('gateway.menu', 'account-books') }}" wire:navigate class="card block p-3 hover:border-tally-top">
            <div class="tally-caption">Cash &amp; bank</div>
            <div class="mt-1 font-mono text-lg font-semibold">{{ Money::format($cashTotal) }}</div>
            <div class="text-xs text-slate-500">{{ $cashBank->count() }} account(s)</div>
        </a>
        <a href="{{ route('reports.outstanding', 'receivables') }}" wire:navigate class="card block p-3 hover:border-tally-top">
            <div class="tally-caption">Receivables</div>
            <div class="mt-1 font-mono text-lg font-semibold">{{ Money::format($receivables) }}</div>
            <div class="text-xs {{ $receivablesOverdue > 0 ? 'text-red-700' : 'text-slate-500' }}">{{ $receivablesOverdue > 0 ? '⚠ '.Money::format($receivablesOverdue).' overdue' : 'Nothing overdue' }}</div>
        </a>
        <a href="{{ route('reports.outstanding', 'payables') }}" wire:navigate class="card block p-3 hover:border-tally-top">
            <div class="tally-caption">Payables</div>
            <div class="mt-1 font-mono text-lg font-semibold">{{ Money::format($payables) }}</div>
            <div class="text-xs {{ $payablesOverdue > 0 ? 'text-amber-700' : 'text-slate-500' }}">{{ $payablesOverdue > 0 ? '⚠ '.Money::format($payablesOverdue).' overdue' : 'Nothing overdue' }}</div>
        </a>
        <a href="{{ route('reports.vat-return', ['from' => $quarterStart->toDateString(), 'to' => now()->toDateString()]) }}" wire:navigate class="card block p-3 hover:border-tally-top">
            <div class="tally-caption">VAT {{ $vatDue >= 0 ? 'payable' : 'refundable' }} (this quarter)</div>
            <div class="mt-1 font-mono text-lg font-semibold">{{ Money::format(abs($vatDue)) }}</div>
            <div class="text-xs text-slate-500">Since {{ $quarterStart->format('j M') }}</div>
        </a>
        <a href="{{ route('reports.day-book', ['from' => now()->startOfMonth()->toDateString(), 'to' => now()->toDateString()]) }}" wire:navigate class="card block p-3 hover:border-tally-top">
            <div class="tally-caption">Sales this month</div>
            <div class="mt-1 font-mono text-lg font-semibold">{{ Money::format($salesThisMonth) }}</div>
            <div class="text-xs text-slate-500">
                @if ($change === null) No sales same period last month
                @else {{ $change >= 0 ? '▲' : '▼' }} {{ abs($change) }}% vs same days last month @endif
            </div>
        </a>
        <a href="{{ route('reports.profit-loss') }}" wire:navigate class="card block p-3 hover:border-tally-top">
            <div class="tally-caption">Net {{ $netProfitYtd >= 0 ? 'profit' : 'loss' }} (year to date)</div>
            <div class="mt-1 font-mono text-lg font-semibold {{ $netProfitYtd < 0 ? 'text-red-700' : '' }}">{{ Money::format(abs($netProfitYtd)) }}</div>
            <div class="text-xs text-slate-500">Since {{ $fyStart->format('j M Y') }}</div>
        </a>
    </div>

    <div class="grid gap-4 xl:grid-cols-3">
        <section class="card p-4 xl:col-span-2">
            <h2 class="mb-2 font-semibold">Sales and expenses</h2>
            <x-sales-expense-chart :months="$monthly" />
        </section>

        <section class="card p-4">
            <h2 class="mb-2 font-semibold">Overdue customers</h2>
            @forelse ($topOverdue as $row)
                <a href="{{ route('reports.ledger', $row->ledger) }}" wire:navigate class="flex justify-between gap-2 border-b border-slate-100 py-1.5 text-sm last:border-0 hover:bg-tally-select/40">
                    <span>{{ $row->ledger->name }}<span class="block text-xs text-slate-500">oldest {{ $row->oldestDays }} day(s) overdue</span></span>
                    <span class="font-mono text-red-700">{{ Money::format($row->overdue) }}</span>
                </a>
            @empty
                <p class="text-sm text-slate-400">No overdue customer bills. 🎉</p>
            @endforelse
        </section>

        <section class="card p-4">
            <h2 class="mb-2 font-semibold">Bills to pay (next 14 days)</h2>
            @forelse ($payablesDueSoon->take(8) as $row)
                <div class="flex justify-between gap-2 border-b border-slate-100 py-1.5 text-sm last:border-0">
                    <span>{{ $row->ledger->name }}<span class="block text-xs {{ $row->bill->due_date->isPast() ? 'text-red-700' : 'text-slate-500' }}">{{ $row->bill->reference }} · due {{ $row->bill->due_date->format('j M') }}</span></span>
                    <span class="font-mono">{{ Money::format($row->bill->pending) }}</span>
                </div>
            @empty
                <p class="text-sm text-slate-400">Nothing due in the next 14 days.</p>
            @endforelse
        </section>

        <section class="card p-4">
            <h2 class="mb-2 font-semibold">Post-dated cheques (next 7 days)</h2>
            @forelse ($postDated as $voucher)
                <a href="{{ $voucher->editUrl() }}" wire:navigate class="flex justify-between gap-2 border-b border-slate-100 py-1.5 text-sm last:border-0 hover:bg-tally-select/40">
                    <span>{{ $voucher->party?->name ?? $voucher->type->name }}<span class="block text-xs text-slate-500">{{ $voucher->type->name }} {{ $voucher->number }} · {{ $voucher->date->format('j M') }}</span></span>
                    <span class="font-mono">{{ Money::format($voucher->total) }}</span>
                </a>
            @empty
                <p class="text-sm text-slate-400">None due this week.</p>
            @endforelse

            <h2 class="mt-4 mb-2 font-semibold">Low stock</h2>
            @forelse ($lowStock->take(6) as $p)
                <a href="{{ route('reports.stock-item', $p->item) }}" wire:navigate class="flex justify-between gap-2 border-b border-slate-100 py-1.5 text-sm last:border-0 hover:bg-tally-select/40">
                    <span>{{ $p->item->name }}</span>
                    <span class="font-mono text-amber-700">{{ \App\Services\StockService::qty($p->qty) }} / {{ rtrim(rtrim($p->item->reorder_level, '0'), '.') }} {{ $p->item->unit->symbol }}</span>
                </a>
            @empty
                <p class="text-sm text-slate-400">No items at or below reorder level.</p>
            @endforelse
        </section>

        <section class="card p-4">
            <h2 class="mb-2 font-semibold">Cash &amp; bank</h2>
            @forelse ($cashBank as $row)
                <a href="{{ route('reports.ledger', $row->ledger) }}" wire:navigate class="flex justify-between gap-2 border-b border-slate-100 py-1.5 text-sm last:border-0 hover:bg-tally-select/40">
                    <span>{{ $row->ledger->name }}</span><span class="font-mono"><x-amount :value="$row->closing" drcr /></span>
                </a>
            @empty
                <p class="text-sm text-slate-400">No cash or bank ledgers.</p>
            @endforelse

            <h2 class="mt-4 mb-2 font-semibold">Recent entries</h2>
            @forelse ($recent as $voucher)
                <a href="{{ $voucher->editUrl() }}" wire:navigate class="flex justify-between gap-2 border-b border-slate-100 py-1 text-sm last:border-0 hover:bg-tally-select/40 {{ $voucher->is_cancelled ? 'text-slate-400 line-through' : '' }}">
                    <span>{{ $voucher->type->name }} {{ $voucher->number }}<span class="block text-xs text-slate-500">{{ $voucher->party?->name }} · {{ $voucher->date->format('j M') }}</span></span>
                    <span class="font-mono">{{ Money::format($voucher->total, true) }}</span>
                </a>
            @empty
                <p class="text-sm text-slate-400">No vouchers yet.</p>
            @endforelse
        </section>
    </div>
</div>
