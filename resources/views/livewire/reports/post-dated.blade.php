@php use App\Support\Money; @endphp
<div>
    <x-page-header title="Post-dated Cheques" subtitle="Vouchers dated after today. They affect the books only from their date." :back="route('gateway')" />

    @foreach (['Receivable (receipts)' => $receipts, 'Payable (payments & contra)' => $payments] as $heading => $list)
        <div class="card mb-4 overflow-x-auto">
            <div class="border-b border-slate-100 px-3 py-2 font-semibold">{{ $heading }}</div>
            <table class="table">
                <thead><tr><th>Due on</th><th>Party</th><th>Vch type</th><th>Vch no.</th><th>Bank / instrument</th><th class="text-right">Amount</th><th class="text-right">Days to go</th></tr></thead>
                <tbody>
                    @forelse ($list as $voucher)
                        @php $bankLine = $voucher->entries->first(fn ($e) => $e->instrument_no || $e->ledger->isBank()); @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="whitespace-nowrap">{{ $voucher->date->format('d-M-Y') }}</td>
                            <td><a href="{{ $voucher->editUrl() }}" wire:navigate class="hover:underline">{{ $voucher->party?->name ?? '—' }}</a></td>
                            <td>{{ $voucher->type->name }}</td>
                            <td>{{ $voucher->number }}</td>
                            <td class="text-xs">{{ $bankLine?->ledger->name }} {{ $bankLine?->instrument_type?->label() }} {{ $bankLine?->instrument_no }}</td>
                            <td class="num">{{ Money::format($voucher->total) }}</td>
                            <td class="num">{{ (int) now()->startOfDay()->diffInDays($voucher->date) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-4 text-center text-slate-400">None.</td></tr>
                    @endforelse
                </tbody>
                @if ($list->isNotEmpty())
                    <tfoot><tr class="font-semibold"><td colspan="5">Total</td><td class="num">{{ Money::format($list->sum(fn ($v) => Money::toBaisa($v->total))) }}</td><td></td></tr></tfoot>
                @endif
            </table>
        </div>
    @endforeach
    <p class="text-xs text-slate-500">To record a post-dated cheque, enter the Receipt or Payment with the cheque date as the voucher date.</p>
</div>
