@php use App\Support\Money; @endphp
<div>
    <x-page-header :title="$title" :subtitle="'As on '.\Illuminate\Support\Carbon::parse($to)->format('d-M-Y')" :back="route('gateway')">
        <a href="{{ route('reports.outstanding', $kind === 'receivables' ? 'payables' : 'receivables') }}" wire:navigate class="btn-secondary">{{ $kind === 'receivables' ? 'Payables' : 'Receivables' }}</a>
    </x-page-header>
    @include('livewire.reports.partials.period', ['asOfOnly' => true, 'showDetailed' => false])

    <div class="mb-4 grid grid-cols-2 gap-2 sm:grid-cols-6">
        @foreach ($buckets as $bucket)
            <div class="card p-3">
                <div class="text-xs text-slate-500">{{ $bucket }} days</div>
                <div class="num text-left font-semibold">{{ Money::format($ageing[$bucket]) }}</div>
            </div>
        @endforeach
        <div class="card bg-brand-50 p-3">
            <div class="text-xs text-slate-500">Total</div>
            <div class="num text-left font-semibold">{{ Money::format($total) }}</div>
        </div>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Date</th><th>Ref. no.</th><th>Due on</th><th class="text-right">Overdue (days)</th><th class="text-right">Pending amount</th></tr></thead>
            <tbody>
                @forelse ($parties as $party)
                    <tr class="bg-slate-50 font-semibold">
                        <td colspan="4"><a href="{{ route('reports.ledger', $party->ledger) }}" wire:navigate class="hover:underline">{{ $party->ledger->name }}</a></td>
                        <td class="num"><x-amount :value="$party->total" /></td>
                    </tr>
                        @foreach ($party->bills as $bill)
                            <tr class="text-slate-600">
                                <td>{{ $bill->bill_date?->format('d-M-Y') }}</td>
                                <td>{{ $bill->reference }} @if ($bill->type->value === 'advance')<span class="text-xs">(advance)</span>@endif</td>
                                <td>{{ $bill->due_date?->format('d-M-Y') }}</td>
                                <td class="num {{ $bill->overdue_days > 0 ? 'text-red-700' : '' }}">{{ $bill->overdue_days ?: '' }}</td>
                                <td class="num"><x-amount :value="$bill->pending" /></td>
                            </tr>
                        @endforeach
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-400">Nothing outstanding.</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr class="font-semibold"><td colspan="4">Total</td><td class="num border-t-2 border-slate-400">{{ Money::format($total) }}</td></tr></tfoot>
        </table>
    </div>
    <p class="mt-2 text-xs text-slate-500">Ageing is by bill date. Negative amounts are advances or on-account payments not yet adjusted against bills.</p>
</div>
