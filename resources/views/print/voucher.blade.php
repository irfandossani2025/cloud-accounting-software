@php use App\Support\Money; @endphp
<x-print-layout :title="$title" :title-ar="$titleAr" :voucher="$voucher" :company="$company">
    <table class="totals" style="width: auto; margin-bottom: 12px">
        <tr><td class="muted">No.</td><td><strong>{{ $voucher->number }}</strong></td></tr>
        <tr><td class="muted">Date</td><td>{{ $voucher->date->format('d/m/Y') }}</td></tr>
        @if ($voucher->reference)<tr><td class="muted">Reference</td><td>{{ $voucher->reference }}</td></tr>@endif
    </table>
    <table>
        <thead><tr><th>Particulars</th><th class="num">Debit</th><th class="num">Credit</th></tr></thead>
        <tbody>
            @foreach ($voucher->entries as $entry)
                <tr><td>{{ $entry->ledger->name }}</td><td class="num">{{ Money::format($entry->debit, true) }}</td><td class="num">{{ Money::format($entry->credit, true) }}</td></tr>
            @endforeach
        </tbody>
        <tfoot><tr class="grand"><td>Total</td><td class="num">{{ Money::format($voucher->total) }}</td><td class="num">{{ Money::format($voucher->total) }}</td></tr></tfoot>
    </table>
    <p><span class="muted">Amount in words:</span> <strong>{{ Money::inWords(Money::toBaisa($voucher->total)) }}</strong></p>
    @if ($voucher->narration)<p class="muted" style="white-space: pre-line">{{ $voucher->narration }}</p>@endif
    <div class="row" style="margin-top: 48px">
        <div style="border-top: 1px solid #9ca3af; width: 30%; padding-top: 4px" class="muted">Prepared by</div>
        <div style="border-top: 1px solid #9ca3af; width: 30%; padding-top: 4px" class="muted">Checked by</div>
        <div style="border-top: 1px solid #9ca3af; width: 30%; padding-top: 4px" class="muted">Receiver's signature</div>
    </div>
</x-print-layout>
