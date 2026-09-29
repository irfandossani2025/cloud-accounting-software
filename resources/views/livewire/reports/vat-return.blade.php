@php
    use App\Support\Money;
    use App\Enums\VatCategory;
    use App\Enums\TaxRole;
@endphp
<div>
    <x-page-header title="VAT Return (Oman)" :subtitle="\Illuminate\Support\Carbon::parse($from)->format('d-M-Y').' to '.\Illuminate\Support\Carbon::parse($to)->format('d-M-Y')" :back="route('gateway')">
        <button type="button" wire:click="quarter(-1)" class="btn-secondary">‹ Previous quarter</button>
        <button type="button" wire:click="quarter(1)" class="btn-secondary">Next quarter ›</button>
    </x-page-header>
    @include('livewire.reports.partials.period', ['showDetailed' => false])

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Supplies (sales)</th><th class="text-right">Value excl. VAT</th><th class="text-right">VAT</th></tr></thead>
                <tbody>
                    <tr><td>Standard rated supplies (5%)</td><td class="num">{{ Money::format($supplies['standard']) }}</td><td class="num">{{ Money::format($tax['output_vat']) }}</td></tr>
                    <tr><td>Zero rated supplies</td><td class="num">{{ Money::format($supplies['zero_rated']) }}</td><td class="num">—</td></tr>
                    <tr><td>Exempt supplies</td><td class="num">{{ Money::format($supplies['exempt']) }}</td><td class="num">—</td></tr>
                    <tr><td>Supplies where the customer accounts for VAT (reverse charge)</td><td class="num">{{ Money::format($supplies['reverse_charge']) }}</td><td class="num">—</td></tr>
                    <tr class="text-slate-500"><td>Out of scope</td><td class="num">{{ Money::format($supplies['out_of_scope']) }}</td><td class="num">—</td></tr>
                </tbody>
            </table>
        </div>

        <div class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Purchases &amp; expenses</th><th class="text-right">Value excl. VAT</th><th class="text-right">VAT</th></tr></thead>
                <tbody>
                    <tr><td>Standard rated purchases (input VAT)</td><td class="num">{{ Money::format($purchases['standard']) }}</td><td class="num">{{ Money::format($tax['input_vat']) }}</td></tr>
                    <tr><td>Purchases subject to reverse charge (imports)</td><td class="num">{{ Money::format($purchases['reverse_charge']) }}</td><td class="num">{{ Money::format($tax['rcm_input']) }}</td></tr>
                    <tr><td>Zero rated purchases</td><td class="num">{{ Money::format($purchases['zero_rated']) }}</td><td class="num">—</td></tr>
                    <tr><td>Exempt purchases</td><td class="num">{{ Money::format($purchases['exempt']) }}</td><td class="num">—</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card mt-4 max-w-xl">
        <table class="table">
            <tbody>
                <tr><td>Output VAT on supplies</td><td class="num">{{ Money::format($tax['output_vat']) }}</td></tr>
                <tr><td>Output VAT due under reverse charge</td><td class="num">{{ Money::format($tax['rcm_output']) }}</td></tr>
                <tr class="font-semibold"><td>Total VAT due</td><td class="num">{{ Money::format($outputTax) }}</td></tr>
                <tr><td>Input VAT on purchases</td><td class="num">({{ Money::format($tax['input_vat']) }})</td></tr>
                <tr><td>Input VAT recoverable under reverse charge</td><td class="num">({{ Money::format($tax['rcm_input']) }})</td></tr>
                <tr class="font-semibold"><td>Total recoverable VAT</td><td class="num">({{ Money::format($inputTax) }})</td></tr>
                <tr class="text-base font-semibold {{ $netPayable >= 0 ? 'text-red-700' : 'text-green-700' }}">
                    <td>{{ $netPayable >= 0 ? 'Net VAT payable' : 'Net VAT refundable' }}</td>
                    <td class="num border-t-2 border-slate-400">{{ Money::format(abs($netPayable)) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    @if (abs($expectedOutputVat - $tax['output_vat']) > 10)
        <div class="mt-4 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            Check: 5% of standard-rated supplies is {{ Money::format($expectedOutputVat) }}, but Output VAT posted is {{ Money::format($tax['output_vat']) }}.
            Look for sales entered without VAT, or VAT posted to the wrong ledger.
        </div>
    @endif

    <details class="card mt-4 p-4">
        <summary class="cursor-pointer text-sm font-semibold">Ledger breakdown</summary>
        <table class="table mt-2">
            <thead><tr><th>Ledger</th><th>Section</th><th class="text-right">Amount</th></tr></thead>
            <tbody>
                @foreach ($ledgers as $row)
                    <tr><td>{{ $row['name'] }}</td><td class="text-slate-500">{{ $row['section'] }}</td><td class="num">{{ Money::format($row['amount']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </details>

    <p class="mt-3 text-xs text-slate-500">Working figures for the return filed on the Oman Tax Authority portal. Match each line to the boxes on the current OTA return form before filing.</p>
</div>
