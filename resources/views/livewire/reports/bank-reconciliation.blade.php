@php use App\Support\Money; @endphp
<div>
    <x-page-header title="Bank Reconciliation" :subtitle="$bank ? $bank->name.' · as on '.\Illuminate\Support\Carbon::parse($to)->format('d-M-Y') : null" :back="route('gateway')" />

    @if ($banks->isEmpty())
        <div class="card p-6 text-sm text-slate-500">Create a ledger under <strong>Bank Accounts</strong> to reconcile it.</div>
    @else
        <div class="no-print mb-3 flex flex-wrap items-end gap-2">
            <div>
                <label class="label">Bank</label>
                <select wire:model.live="bankId" class="input">
                    @foreach ($banks as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </div>
            <div><label class="label">Statement date</label><input type="date" wire:model.live="to" class="input"></div>
            <label class="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" wire:model.live="showReconciled"> Show reconciled</label>
            <button type="button" onclick="window.print()" class="btn-secondary">Print</button>
        </div>

        <form wire:submit="save" class="card overflow-x-auto">
            <table class="table">
                <thead><tr><th>Date</th><th>Particulars</th><th>Vch no.</th><th>Instrument</th><th class="text-right">Deposits (Dr)</th><th class="text-right">Withdrawals (Cr)</th><th class="w-44">Bank date</th></tr></thead>
                <tbody>
                    @forelse ($statement['entries'] as $entry)
                        <tr wire:key="brs-{{ $entry->id }}" class="{{ ($bankDates[$entry->id] ?? '') !== '' ? 'text-slate-500' : '' }}">
                            <td class="whitespace-nowrap">{{ $entry->voucher->date->format('d-M-Y') }}</td>
                            <td>
                                <a href="{{ $entry->voucher->editUrl() }}" wire:navigate class="hover:underline">{{ $entry->voucher->party?->name ?? $entry->voucher->type->name }}</a>
                                @if ($entry->voucher->narration)<div class="text-xs text-slate-400">{{ $entry->voucher->narration }}</div>@endif
                            </td>
                            <td>{{ $entry->voucher->number }}</td>
                            <td class="text-xs">{{ $entry->instrument_type?->label() }} {{ $entry->instrument_no }} {{ $entry->instrument_date?->format('d-M-y') }}</td>
                            <td class="num">{{ Money::format($entry->debit, true) }}</td>
                            <td class="num">{{ Money::format($entry->credit, true) }}</td>
                            <td>
                                <input type="date" wire:model="bankDates.{{ $entry->id }}" class="input py-0.5 text-xs">
                                @error("bankDates.{$entry->id}") <p class="error">{{ $message }}</p> @enderror
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-slate-400">Everything is reconciled. @if ($message)<span class="text-green-700">{{ $message }}</span>@endif</td></tr>
                    @endforelse
                </tbody>
            </table>
            @if ($statement['entries']->isNotEmpty())
                <div class="no-print flex gap-2 border-t border-slate-100 p-3">
                    <button class="btn-primary" data-shortcut="Ctrl+A">Save bank dates <span class="kbd">Ctrl+A</span></button>
                    <button type="button" wire:click="markAllOnVoucherDate" class="btn-secondary">Fill empty dates with voucher date</button>
                    @if ($message)<span class="self-center text-sm text-green-700">{{ $message }}</span>@endif
                </div>
            @endif
        </form>

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <table class="card table self-start">
                <tbody>
                    <tr><td>Balance as per company books</td><td class="num"><x-amount :value="$statement['booksBalance']" drcr /></td></tr>
                    <tr><td>Less: deposits not yet credited by bank</td><td class="num">{{ Money::format($statement['notInBankDebit']) }}</td></tr>
                    <tr><td>Add: payments not yet debited by bank</td><td class="num">{{ Money::format($statement['notInBankCredit']) }}</td></tr>
                    <tr class="font-semibold"><td>Balance as per bank</td><td class="num border-t-2 border-slate-400"><x-amount :value="$statement['bankBalance']" drcr /></td></tr>
                </tbody>
            </table>
            <div class="card self-start p-4 text-sm">
                <label class="label">Closing balance on bank statement (credit = money in the account)</label>
                <input wire:model.live.debounce.400ms="statementBalance" class="input w-48 text-right font-mono" placeholder="0.000">
                @if ($statementBalance !== '' && is_numeric(str_replace(',', '', $statementBalance)))
                    @php $diff = Money::toBaisa($statementBalance) - $statement['bankBalance']; @endphp
                    <p class="mt-2 {{ $diff === 0 ? 'text-green-700' : 'text-red-700' }}">
                        {{ $diff === 0 ? 'Reconciled: matches the bank statement.' : 'Difference of '.Money::format(abs($diff)).': look for missing entries or bank charges.' }}
                    </p>
                @endif
            </div>
        </div>
    @endif
</div>
