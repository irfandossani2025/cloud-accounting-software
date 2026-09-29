@php use App\Support\Money; @endphp
<div>
    <x-page-header title="Cost Centre Break-up" :subtitle="\Illuminate\Support\Carbon::parse($from)->format('d-M-Y').' to '.\Illuminate\Support\Carbon::parse($to)->format('d-M-Y')" :back="route('gateway')">
        <a href="{{ route('inventory.masters', 'cost-centres') }}" wire:navigate class="btn-secondary">Cost centres</a>
    </x-page-header>
    @include('livewire.reports.partials.period')

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Cost centre / ledger</th><th class="text-right">Income</th><th class="text-right">Expenses</th><th class="text-right">Net</th></tr></thead>
            <tbody>
                @foreach ($centres->concat($unallocated->ledgers->isNotEmpty() ? [(object) ['centre' => null, 'ledgers' => $unallocated->ledgers, 'income' => $unallocated->income, 'expenses' => $unallocated->expenses]] : []) as $row)
                    <tr class="bg-slate-50 font-semibold">
                        <td>{{ $row->centre?->name ?? 'Not allocated' }}</td>
                        <td class="num">{{ Money::format($row->income, true) }}</td>
                        <td class="num">{{ Money::format($row->expenses, true) }}</td>
                        <td class="num"><x-amount :value="$row->income - $row->expenses" /></td>
                    </tr>
                    @if ($detailed)
                        @foreach ($row->ledgers as $l)
                            <tr class="text-slate-600">
                                <td class="pl-8">{{ $l->name }}</td>
                                <td class="num">{{ $l->isIncome ? Money::format($l->amount) : '' }}</td>
                                <td class="num">{{ $l->isIncome ? '' : Money::format($l->amount) }}</td>
                                <td></td>
                            </tr>
                        @endforeach
                    @endif
                @endforeach
                @if ($centres->isEmpty() && $unallocated->ledgers->isEmpty())
                    <tr><td colspan="4" class="py-6 text-center text-slate-400">No cost centre postings in this period. Tick "Cost centres applicable" on income and expense ledgers, then allocate on vouchers.</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</div>
