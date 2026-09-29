<div>
    <x-page-header :title="($voucher ? 'Alter ' : '').$type->name" :subtitle="'No. '.$nextNumber" :back="route('gateway')">
        @unless ($voucher)
            @foreach ($types as $t)
                <a href="{{ $t->createUrl() }}" wire:navigate data-shortcut="{{ $t->base_type->shortcut() }}"
                   class="rounded px-2 py-1 text-xs {{ $t->id === $type->id ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200' }}">
                    {{ $t->base_type->shortcut() }} {{ $t->name }}
                </a>
            @endforeach
            @if (in_array($type->base_type, \App\Services\InvoiceService::INVOICE_TYPES, true))
                <a href="{{ route('invoices.create', $type) }}" wire:navigate data-shortcut="Ctrl+H" class="btn-secondary">Invoice mode <span class="kbd">Ctrl+H</span></a>
            @endif
        @endunless
    </x-page-header>

    @if (\App\Support\PeriodLock::isLocked($voucher?->date ?? $date))
        <div class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            The books are locked up to {{ \App\Support\PeriodLock::lockedUntil()->format('d-M-Y') }}. Entries on or before that date cannot be saved or cancelled.
        </div>
    @endif

    <form wire:submit="save" class="card p-4 sm:p-6">
        <div class="mb-4 grid gap-4 sm:grid-cols-4">
            <div>
                <label class="label">Date</label>
                <input type="date" wire:model="date" class="input">
                @error('date') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">{{ in_array($type->base_type->value, ['sales', 'purchase', 'credit_note', 'debit_note']) ? 'Supplier invoice / Ref. no.' : 'Reference' }}</label>
                <input wire:model="reference" class="input">
            </div>
            <div>
                <label class="label">Ref. date</label>
                <input type="date" wire:model="reference_date" class="input">
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="table">
                <thead>
                    <tr>
                        <th class="w-20">Dr/Cr</th>
                        <th>Particulars</th>
                        <th class="w-40 text-right">Debit</th>
                        <th class="w-40 text-right">Credit</th>
                        <th class="w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $i => $row)
                        <tr wire:key="row-{{ $i }}">
                            <td>
                                <select wire:model.live="rows.{{ $i }}.side" class="input">
                                    <option>Dr</option><option>Cr</option>
                                </select>
                            </td>
                            <td>
                                <select wire:model.live="rows.{{ $i }}.ledger_id" class="input">
                                    <option value="">— Select ledger —</option>
                                    @foreach ($ledgers as $ledger)
                                        <option value="{{ $ledger->id }}">{{ $ledger->name }} · {{ $ledger->group->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                @if ($row['side'] === 'Dr')
                                    <input wire:model.blur="rows.{{ $i }}.amount" class="input text-right font-mono" inputmode="decimal" placeholder="0.000">
                                @endif
                            </td>
                            <td>
                                @if ($row['side'] === 'Cr')
                                    <input wire:model.blur="rows.{{ $i }}.amount" class="input text-right font-mono" inputmode="decimal" placeholder="0.000">
                                @endif
                            </td>
                            <td>
                                @if (count($rows) > 2)
                                    <button type="button" wire:click="removeRow({{ $i }})" class="text-slate-400 hover:text-red-600" title="Remove line">✕</button>
                                @endif
                            </td>
                        </tr>
                        @php
                            $rowLedger = $row['ledger_id'] ? $ledgers->firstWhere('id', (int) $row['ledger_id']) : null;
                            $isBankRow = $rowLedger && in_array($rowLedger->account_group_id, $bankGroupIds, true);
                            $isFxRow = $rowLedger && $rowLedger->currency_id;
                            $isCostRow = $rowLedger && $rowLedger->cost_centres_applicable && $costCentres->isNotEmpty();
                        @endphp
                        @if ($isBankRow || $isFxRow || $isCostRow)
                            <tr wire:key="details-{{ $i }}" class="bg-slate-50/60">
                                <td></td>
                                <td colspan="4" class="space-y-2 pb-3 text-xs">
                                    @if ($isFxRow)
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="w-24 font-semibold text-slate-600">{{ $rowLedger->currency->code }} amount</span>
                                            <input wire:model.blur="rows.{{ $i }}.fx_amount" class="input w-32 py-0.5 text-right font-mono text-xs" placeholder="0.00">
                                            <span class="text-slate-500">@ rate</span>
                                            <input wire:model.blur="rows.{{ $i }}.fx_rate" class="input w-28 py-0.5 text-right font-mono text-xs" placeholder="OMR per {{ $rowLedger->currency->code }}">
                                            <span class="text-slate-500">= OMR {{ $row['amount'] ?: '0.000' }}</span>
                                        </div>
                                    @endif
                                    @if ($isBankRow)
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="w-24 font-semibold text-slate-600">Instrument</span>
                                            <select wire:model="rows.{{ $i }}.instrument_type" class="input w-36 py-0.5 text-xs">
                                                <option value="">—</option>
                                                @foreach (\App\Enums\InstrumentType::cases() as $it)<option value="{{ $it->value }}">{{ $it->label() }}</option>@endforeach
                                            </select>
                                            <input wire:model="rows.{{ $i }}.instrument_no" class="input w-32 py-0.5 text-xs" placeholder="Cheque / ref no.">
                                            <input type="date" wire:model="rows.{{ $i }}.instrument_date" class="input w-36 py-0.5 text-xs" title="Instrument date">
                                        </div>
                                    @endif
                                    @if ($isCostRow)
                                        <div>
                                            <div class="mb-1 flex items-center gap-2">
                                                <span class="w-24 font-semibold text-slate-600">Cost centres</span>
                                                <button type="button" wire:click="addCostCentre({{ $i }})" class="rounded border border-slate-300 bg-white px-2 py-0.5 hover:bg-slate-50">+ Cost centre</button>
                                                @if (! $row['cost_centres'])<span class="text-slate-400">Not allocated.</span>@endif
                                            </div>
                                            @foreach ($row['cost_centres'] as $c => $allocation)
                                                <div wire:key="cc-{{ $i }}-{{ $c }}" class="mb-1 flex items-center gap-2 pl-26">
                                                    <select wire:model="rows.{{ $i }}.cost_centres.{{ $c }}.cost_centre_id" class="input w-56 py-0.5 text-xs">
                                                        <option value="">— Cost centre —</option>
                                                        @foreach ($costCentres as $cc)<option value="{{ $cc->id }}">{{ $cc->name }}</option>@endforeach
                                                    </select>
                                                    <input wire:model.blur="rows.{{ $i }}.cost_centres.{{ $c }}.amount" class="input w-32 py-0.5 text-right font-mono text-xs">
                                                </div>
                                            @endforeach
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endif
                        @if ($rowLedger?->is_bill_wise)
                            <tr wire:key="bills-{{ $i }}" class="bg-slate-50/60">
                                <td></td>
                                <td colspan="4" class="pb-3">
                                    <div class="mb-1 flex flex-wrap items-center gap-2 text-xs text-slate-600">
                                        <span class="font-semibold">Bill-wise details</span>
                                        <button type="button" wire:click="allocateBills({{ $i }})" class="rounded border border-slate-300 bg-white px-2 py-0.5 hover:bg-slate-50">Allocate against pending bills</button>
                                        <button type="button" wire:click="addBill({{ $i }})" class="rounded border border-slate-300 bg-white px-2 py-0.5 hover:bg-slate-50">+ Reference</button>
                                        @if (! $row['bills'])<span class="text-slate-400">Not allocated: saved as {{ in_array($type->base_type->value, ['sales', 'purchase', 'credit_note', 'debit_note']) ? 'a New Ref for this voucher' : 'On Account' }}.</span>@endif
                                    </div>
                                    @if ($row['bills'])
                                        <table class="w-full max-w-2xl text-xs">
                                            <tr class="text-slate-500"><td class="w-28">Type</td><td>Reference</td><td>Pending</td><td class="w-32 text-right">Amount</td></tr>
                                            @foreach ($row['bills'] as $b => $bill)
                                                <tr wire:key="bill-{{ $i }}-{{ $b }}">
                                                    <td class="py-0.5 pr-2">
                                                        <select wire:model.live="rows.{{ $i }}.bills.{{ $b }}.type" class="input py-0.5 text-xs">
                                                            @foreach (\App\Enums\BillType::cases() as $bt)
                                                                <option value="{{ $bt->value }}">{{ $bt->label() }}</option>
                                                            @endforeach
                                                        </select>
                                                    </td>
                                                    <td class="py-0.5 pr-2">
                                                        @if ($bill['type'] !== 'on_account')
                                                            <input wire:model.blur="rows.{{ $i }}.bills.{{ $b }}.reference" class="input py-0.5 text-xs">
                                                        @endif
                                                    </td>
                                                    <td class="py-0.5 pr-2 whitespace-nowrap text-slate-500">{{ $bill['pending'] }}</td>
                                                    <td class="py-0.5"><input wire:model.blur="rows.{{ $i }}.bills.{{ $b }}.amount" class="input py-0.5 text-right font-mono text-xs"></td>
                                                </tr>
                                            @endforeach
                                        </table>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="font-semibold">
                        <td colspan="2">
                            <div class="flex flex-wrap gap-2">
                                <button type="button" wire:click="addRow" class="btn-secondary" data-shortcut="Alt+N">+ Line <span class="kbd">Alt+N</span></button>
                                <button type="button" wire:click="applyVat" class="btn-secondary" data-shortcut="Alt+V">Apply VAT 5% <span class="kbd">Alt+V</span></button>
                            </div>
                        </td>
                        <td class="num">{{ \App\Support\Money::format($debit) }}</td>
                        <td class="num">{{ \App\Support\Money::format($credit) }}</td>
                        <td></td>
                    </tr>
                    @if ($debit !== $credit)
                        <tr><td colspan="5" class="text-right text-sm text-amber-700">Difference: {{ \App\Support\Money::drCr($debit - $credit) }}</td></tr>
                    @endif
                </tfoot>
            </table>
        </div>
        @error('rows') <p class="error mt-2 text-sm">{{ $message }}</p> @enderror

        <div class="mt-4">
            <label class="label">Narration</label>
            <textarea wire:model="narration" rows="2" class="input"></textarea>
        </div>

        <div class="mt-4 flex justify-between">
            <div class="flex gap-2">
                <button class="btn-primary" data-shortcut="Ctrl+A" wire:loading.attr="disabled">Accept <span class="kbd">Ctrl+A</span></button>
                @if ($voucher)
                    <a href="{{ route('vouchers.print', $voucher) }}" target="_blank" data-print class="btn-secondary">Print</a>
                @endif
            </div>
            @if ($voucher && auth()->user()->can('cancel-vouchers'))
                <button type="button" wire:click="cancelVoucher" wire:confirm="Cancel this voucher? Its number is kept but it no longer affects the books." class="btn-danger">Cancel voucher</button>
            @endif
        </div>
    </form>
</div>
