<div>
    <x-page-header :title="'Ledger: '.$ledger->name" :subtitle="\Illuminate\Support\Carbon::parse($from)->format('d-M-Y').' to '.\Illuminate\Support\Carbon::parse($to)->format('d-M-Y')" :back="route('ledgers.index')">
        <a href="{{ route('ledgers.edit', $ledger) }}" wire:navigate class="btn-secondary">Alter ledger</a>
    </x-page-header>
    @include('livewire.reports.partials.period', ['showDetailed' => false])

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Date</th><th>Particulars</th><th>Vch type</th><th>Vch no.</th><th class="text-right">Debit</th><th class="text-right">Credit</th>@if ($ledger->currency)<th class="text-right">{{ $ledger->currency->code }}</th>@endif<th class="text-right">Balance</th></tr></thead>
            <tbody>
                <tr class="font-semibold"><td colspan="4">Opening balance</td><td class="num">{{ $opening > 0 ? \App\Support\Money::format($opening) : '' }}</td><td class="num">{{ $opening < 0 ? \App\Support\Money::format(-$opening) : '' }}</td>@if ($ledger->currency)<td></td>@endif<td class="num"><x-amount :value="$opening" drcr /></td></tr>
                @foreach ($lines as $line)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap">{{ $line->date->format('d-M-Y') }}</td>
                        <td>
                            <a href="{{ route('vouchers.edit', $line->voucher_id) }}" wire:navigate class="hover:underline">{{ $line->particulars }}</a>
                            @if ($line->narration)<div class="text-xs text-slate-400">{{ $line->narration }}</div>@endif
                        </td>
                        <td>{{ $line->type }}</td>
                        <td>{{ $line->number }}</td>
                        <td class="num">{{ \App\Support\Money::format($line->debit, true) }}</td>
                        <td class="num">{{ \App\Support\Money::format($line->credit, true) }}</td>
                        @if ($ledger->currency)<td class="num text-slate-500">{{ $line->fx !== null ? \App\Support\Money::drCr($line->fx) : '' }}</td>@endif
                        <td class="num"><x-amount :value="$line->balance" drcr /></td>
                    </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr class="font-semibold"><td colspan="4">Current total</td><td class="num">{{ \App\Support\Money::format($totalDebit) }}</td><td class="num">{{ \App\Support\Money::format($totalCredit) }}</td>@if ($ledger->currency)<td></td>@endif<td></td></tr>
                <tr class="font-semibold"><td colspan="{{ $ledger->currency ? 7 : 6 }}">Closing balance</td><td class="num border-t-2 border-slate-400"><x-amount :value="$closing" drcr /></td></tr>
            </tfoot>
        </table>
    </div>
    @if (\App\Models\AccountGroup::reserved('Sundry Debtors')->descendantAndSelfIds()->contains($ledger->account_group_id))
        <livewire:send-panel :ledger-id="$ledger->id" :key="'send-ledger-'.$ledger->id" />
    @endif
</div>
