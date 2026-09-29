<div>
    <x-page-header title="Day Book" :subtitle="\Illuminate\Support\Carbon::parse($from)->format('d-M-Y').' to '.\Illuminate\Support\Carbon::parse($to)->format('d-M-Y')" :back="route('gateway')" />
    @include('livewire.reports.partials.period')

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Date</th><th>Particulars</th><th>Vch type</th><th>Vch no.</th><th class="text-right">Debit</th><th class="text-right">Credit</th></tr></thead>
            <tbody>
                @forelse ($vouchers as $voucher)
                    <tr class="{{ $voucher->is_cancelled ? 'text-slate-400 line-through' : '' }} hover:bg-slate-50">
                        <td class="whitespace-nowrap">{{ $voucher->date->format('d-M-Y') }}</td>
                        <td>
                            @if ($voucher->is_cancelled)
                                (cancelled)
                            @else
                                <a href="{{ $voucher->editUrl() }}" wire:navigate class="hover:underline">
                                    {{ $voucher->party?->name ?? $voucher->entries->first()?->ledger->name ?? $voucher->type->name }}
                                </a>
                                @if ($voucher->narration)<div class="text-xs text-slate-400">{{ $voucher->narration }}</div>@endif
                            @endif
                        </td>
                        <td>{{ $voucher->type->name }}</td>
                        <td class="whitespace-nowrap">
                            {{ $voucher->number }}
                            @unless ($voucher->is_cancelled)
                                <a href="{{ route('vouchers.print', $voucher) }}" target="_blank" class="no-print ml-1 text-xs text-brand-700 hover:underline">print</a>
                            @endunless
                        </td>
                        <td class="num">{{ $voucher->is_cancelled ? '' : \App\Support\Money::format($voucher->total) }}</td>
                        <td class="num">{{ $voucher->is_cancelled ? '' : \App\Support\Money::format($voucher->total) }}</td>
                    </tr>
                    @if ($detailed && ! $voucher->is_cancelled)
                        @foreach ($voucher->entries as $entry)
                            <tr class="text-xs text-slate-500">
                                <td></td>
                                <td class="pl-8">{{ $entry->ledger->name }}</td>
                                <td colspan="2"></td>
                                <td class="num">{{ \App\Support\Money::format($entry->debit, true) }}</td>
                                <td class="num">{{ \App\Support\Money::format($entry->credit, true) }}</td>
                            </tr>
                        @endforeach
                    @endif
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-400">No vouchers in this period.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
