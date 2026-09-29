@php use App\Support\Money; @endphp
<div>
    <x-page-header title="Forex Gain/Loss" :subtitle="'Foreign currency balances revalued at rates on '.\Illuminate\Support\Carbon::parse($to)->format('d-M-Y')" :back="route('gateway')">
        <a href="{{ route('currencies.index') }}" wire:navigate class="btn-secondary">Exchange rates</a>
    </x-page-header>
    @include('livewire.reports.partials.period', ['asOfOnly' => true, 'showDetailed' => false])

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Ledger</th><th class="text-right">Foreign balance</th><th class="text-right">Book value (OMR)</th><th class="text-right">Rate</th><th class="text-right">Revalued (OMR)</th><th class="text-right">Gain / (loss)</th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        <td><a href="{{ route('reports.ledger', $row->ledger) }}" wire:navigate class="hover:underline">{{ $row->ledger->name }}</a></td>
                        <td class="num">{{ $row->ledger->currency->code }} {{ Money::drCr($row->fxBalance) ?: '0.000' }}</td>
                        <td class="num"><x-amount :value="$row->book" drcr /></td>
                        <td class="num">{{ $row->rate ?? 'no rate' }}</td>
                        <td class="num">{{ $row->revalued !== null ? Money::drCr($row->revalued) : '' }}</td>
                        <td class="num {{ ($row->gain ?? 0) < 0 ? 'text-red-700' : 'text-green-700' }}">{{ $row->gain !== null ? Money::format($row->gain, true) : '' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-400">No foreign currency ledgers with balances. Set a currency on party or bank ledgers.</td></tr>
                @endforelse
            </tbody>
            @if ($rows->isNotEmpty())
                <tfoot><tr class="font-semibold"><td colspan="5">Net unrealised gain / (loss)</td><td class="num">{{ Money::format($totalGain) }}</td></tr></tfoot>
            @endif
        </table>
    </div>

    @if ($totalGain !== 0 || $rows->contains(fn ($r) => $r->gain))
        <div class="no-print mt-4 flex items-center gap-3">
            <button wire:click="postRevaluation" wire:confirm="Post a journal adjusting these ledgers to the revalued amounts against Forex Gain/Loss?" class="btn-primary">Post revaluation journal</button>
            <span class="text-xs text-slate-500">Adjusts each ledger's OMR value to the rate on this date, against the Forex Gain/Loss ledger. Balances that are zero in foreign currency but not in OMR are realised gains/losses on settlement.</span>
        </div>
    @endif
</div>
