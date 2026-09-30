<div>
    <x-page-header :title="($voucher ? 'Alter ' : '').$type->name" :subtitle="'No. '.$nextNumber.' · Invoice mode'" :back="route('gateway')">
        @unless ($voucher)
            @foreach ($types as $t)
                <a href="{{ $t->createUrl() }}" wire:navigate data-shortcut="{{ $t->base_type->shortcut() }}"
                   class="rounded px-2 py-1 text-xs {{ $t->id === $type->id ? 'bg-brand-600 text-white' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                    {{ $t->base_type->shortcut() }} {{ $t->name }}
                </a>
            @endforeach
            <a href="{{ route('vouchers.create', $type) }}" wire:navigate data-shortcut="Ctrl+H" class="btn-secondary">Accounting mode <span class="kbd">Ctrl+H</span></a>
        @endunless
    </x-page-header>

    @if (\App\Support\PeriodLock::isLocked($voucher?->date ?? $date))
        <div class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            The books are locked up to {{ \App\Support\PeriodLock::lockedUntil()->format('d-M-Y') }}. Entries on or before that date cannot be saved or cancelled.
        </div>
    @endif

    <form wire:submit="save" class="card p-4 sm:p-6">
        <div class="mb-4 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div>
                <label class="label">Date</label>
                <input type="date" wire:model="date" class="input">
                @error('date') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="label">{{ $isSalesSide ? 'Customer' : 'Supplier' }} (party A/c)</label>
                <select wire:model.live="party_ledger_id" class="input">
                    <option value="">— Select party —</option>
                    @foreach ($parties as $party)
                        <option value="{{ $party->id }}">{{ $party->name }}</option>
                    @endforeach
                </select>
                @error('party_ledger_id') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">{{ $isSalesSide ? 'Order / Ref. no.' : 'Supplier invoice no.' }}</label>
                <input wire:model="reference" class="input">
            </div>
            <div>
                <label class="label">Ref. date</label>
                <input type="date" wire:model="reference_date" class="input">
            </div>
            <div>
                <label class="label">Due date</label>
                <input type="date" wire:model="due_date" class="input" title="Leave blank to use the party's credit period">
            </div>
            <div>
                <label class="label">Currency</label>
                <select wire:model.live="currency_id" class="input">
                    <option value="">OMR</option>
                    @foreach ($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach
                </select>
            </div>
            @if ($isCreditNote)
                <div class="sm:col-span-2">
                    <label class="label">Original invoice</label>
                    <select wire:model="original_voucher_id" class="input">
                        <option value="">— Select invoice —</option>
                        @foreach ($originalInvoices as $inv)
                            <option value="{{ $inv->id }}">{{ $inv->number }} · {{ $inv->date->format('d-M-Y') }} · {{ \App\Support\Money::format($inv->total) }}</option>
                        @endforeach
                    </select>
                    @error('original_voucher_id') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Reason</label>
                    <select wire:model="issuance_reason" class="input">
                        <option value="">—</option>
                        @foreach (\App\Support\PintOm::ISSUANCE_REASONS as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
            @endif
            @if ($currency)
                <div>
                    <label class="label">Rate (OMR per {{ $currency->code }})</label>
                    <input wire:model.live.debounce.500ms="fx_rate" class="input text-right font-mono" placeholder="0.000000">
                    @error('fx_rate') <p class="error">{{ $message }}</p> @enderror
                </div>
            @endif
        </div>

        <div class="overflow-x-auto">
            <table class="table min-w-[76rem]">
                <thead>
                    <tr>
                        <th class="w-8">#</th>
                        <th class="w-56">Stock item</th>
                        <th class="w-56">{{ $isSalesSide ? 'Sales' : 'Purchase / expense' }} ledger</th>
                        <th>Description</th>
                        <th class="w-24 text-right">Qty</th>
                        <th class="w-20">Unit</th>
                        <th class="w-28 text-right">Rate</th>
                        <th class="w-24 text-right">Discount</th>
                        <th class="w-40">VAT</th>
                        @if ($costCentres->isNotEmpty())<th class="w-40">Cost centre</th>@endif
                        <th class="w-28 text-right">Amount</th>
                        <th class="w-24 text-right">VAT amt</th>
                        <th class="w-8"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($lines as $i => $line)
                        <tr wire:key="line-{{ $i }}" class="align-top">
                            <td class="pt-3 text-slate-400">{{ $i + 1 }}</td>
                            <td>
                                <select wire:model.live="lines.{{ $i }}.stock_item_id" class="input">
                                    <option value="">— Service / no item —</option>
                                    @foreach ($items as $item)
                                        <option value="{{ $item->id }}">{{ $item->name }}</option>
                                    @endforeach
                                </select>
                                @if ($line['stock_item_id'])
                                    @if ($godowns->count() > 1)
                                        <select wire:model.live="lines.{{ $i }}.godown_id" class="input mt-1 text-xs">
                                            @foreach ($godowns as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                                        </select>
                                    @endif
                                    @if (isset($available[$i]))
                                        @php $q = rescue(fn () => \App\Support\Money::toBaisa($line['quantity'] ?: 0), 0, false); @endphp
                                        <div class="mt-1 text-xs {{ $available[$i] - $q < 0 ? 'text-red-600' : 'text-slate-500' }}">
                                            Available: {{ \App\Services\StockService::qty($available[$i]) }}{{ $available[$i] - $q < 0 ? ' — goes negative' : '' }}
                                        </div>
                                    @endif
                                @endif
                            </td>
                            <td>
                                <select wire:model.live="lines.{{ $i }}.ledger_id" class="input">
                                    <option value="">— Ledger —</option>
                                    @foreach ($lineLedgers as $ledger)
                                        <option value="{{ $ledger->id }}">{{ $ledger->name }}</option>
                                    @endforeach
                                </select>
                            </td>
                            <td>
                                <input wire:model.blur="lines.{{ $i }}.description" class="input" placeholder="Description">
                                <input wire:model.blur="lines.{{ $i }}.description_ar" class="input mt-1" dir="rtl" placeholder="الوصف (اختياري)">
                            </td>
                            <td><input wire:model.blur="lines.{{ $i }}.quantity" class="input text-right font-mono" inputmode="decimal"></td>
                            <td><input wire:model.blur="lines.{{ $i }}.unit" class="input" placeholder="pcs"></td>
                            <td><input wire:model.blur="lines.{{ $i }}.rate" class="input text-right font-mono" inputmode="decimal" placeholder="0.000"></td>
                            <td><input wire:model.blur="lines.{{ $i }}.discount" class="input text-right font-mono" inputmode="decimal" placeholder="0.000"></td>
                            <td>
                                <select wire:model.live="lines.{{ $i }}.vat_category" class="input text-xs">
                                    <option value="">As per ledger</option>
                                    @foreach (\App\Enums\VatCategory::cases() as $cat)
                                        <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                                    @endforeach
                                </select>
                            </td>
                            @if ($costCentres->isNotEmpty())
                                <td>
                                    <select wire:model="lines.{{ $i }}.cost_centre_id" class="input text-xs">
                                        <option value="">—</option>
                                        @foreach ($costCentres as $cc)<option value="{{ $cc->id }}">{{ $cc->name }}</option>@endforeach
                                    </select>
                                </td>
                            @endif
                            <td class="num pt-3">{{ $computed[$i] ? $computed[$i]['amount'] : '' }}</td>
                            <td class="num pt-3">{{ $computed[$i] && \App\Support\Money::toBaisa($computed[$i]['vat_amount']) ? $computed[$i]['vat_amount'] : '' }}</td>
                            <td class="pt-2"><button type="button" wire:click="removeLine({{ $i }})" class="text-slate-400 hover:text-red-600" title="Remove line">✕</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="mt-2">
            <button type="button" wire:click="addLine" class="btn-secondary" data-shortcut="Alt+N">+ Line <span class="kbd">Alt+N</span></button>
        </div>
        @error('lines') <p class="error mt-2 text-sm">{{ $message }}</p> @enderror

        <div class="mt-4 grid gap-4 md:grid-cols-2">
            <div>
                <label class="label">Narration</label>
                <textarea wire:model="narration" rows="3" class="input"></textarea>
            </div>
            <table class="w-full self-start text-sm">
                <tr><td class="py-1 text-slate-600">Total excluding VAT</td><td class="num">{{ \App\Support\Money::format($calc['net']) }}</td></tr>
                <tr><td class="py-1 text-slate-600">VAT</td><td class="num">{{ \App\Support\Money::format($calc['vat']) }}</td></tr>
                @if ($calc['reverseChargeVat'])
                    <tr><td class="py-1 text-slate-500">Reverse charge VAT (self-assessed, not payable to supplier)</td><td class="num text-slate-500">{{ \App\Support\Money::format($calc['reverseChargeVat']) }}</td></tr>
                @endif
                <tr class="text-base font-semibold"><td class="border-t border-slate-300 py-1">Total ({{ $currency?->code ?? 'OMR' }})</td><td class="num border-t border-slate-300">{{ \App\Support\Money::format($calc['total']) }}</td></tr>
                @if ($books)
                    <tr class="text-slate-600"><td class="pt-2">In OMR: excluding VAT</td><td class="num pt-2">{{ \App\Support\Money::format($books['net']) }}</td></tr>
                    <tr class="text-slate-600"><td>In OMR: VAT 5% (accounted in OMR)</td><td class="num">{{ \App\Support\Money::format($books['vat']) }}</td></tr>
                    <tr class="font-semibold"><td>Total in OMR</td><td class="num">{{ \App\Support\Money::format($books['total']) }}</td></tr>
                @elseif (! $currency)
                    <tr><td colspan="2" class="text-xs text-slate-500">{{ \App\Support\Money::inWords($calc['total']) }}</td></tr>
                @endif
            </table>
        </div>

        <div class="mt-4 flex flex-wrap justify-between gap-2">
            <div class="flex gap-2">
                <button class="btn-primary" data-shortcut="Ctrl+A" wire:loading.attr="disabled">Accept <span class="kbd">Ctrl+A</span></button>
                @if ($voucher)
                    <a href="{{ route('vouchers.print', $voucher) }}" target="_blank" data-print class="btn-secondary">Print</a>
                @endif
            </div>
            @if ($voucher && auth()->user()->can('cancel-vouchers'))
                <button type="button" wire:click="cancelVoucher" wire:confirm="Cancel this invoice? Its number is kept but it no longer affects the books." class="btn-danger">Cancel invoice</button>
            @endif
        </div>
    </form>

    @if ($showEinvoice)
        @php $e = $einvoice; @endphp
        <section class="card mt-4 p-4" id="einvoice">
            <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-semibold">E-invoice (Fawtara)</h2>
                @php
                    [$label, $class] = match ($e?->status) {
                        \App\Models\Einvoice::ACCEPTED => ['Accepted', 'bg-green-100 text-green-800'],
                        \App\Models\Einvoice::SUBMITTED => ['Submitted, awaiting outcome', 'bg-blue-100 text-blue-800'],
                        \App\Models\Einvoice::REJECTED => ['Rejected: correct and generate again', 'bg-red-100 text-red-800'],
                        \App\Models\Einvoice::DRAFT => [$e->xml ? 'XML ready to submit' : 'Changed since generated: generate again', 'bg-amber-100 text-amber-800'],
                        default => ['Not generated', 'bg-slate-100 text-slate-600'],
                    };
                @endphp
                <span class="rounded px-2 py-0.5 text-xs {{ $class }}">{{ $label }}</span>
            </div>

            @if ($e)
                <dl class="mb-3 grid gap-x-6 gap-y-1 text-xs text-slate-600 sm:grid-cols-2">
                    <div><dt class="inline font-semibold">UUID:</dt> <dd class="inline font-mono">{{ $e->uuid }}</dd></div>
                    <div><dt class="inline font-semibold">Kind:</dt> <dd class="inline">{{ str_starts_with($e->transaction_type, '1') ? 'Full tax invoice (B2B)' : 'Simplified tax invoice (B2C)' }}</dd></div>
                    @if ($e->generated_at)<div><dt class="inline font-semibold">Generated:</dt> <dd class="inline">{{ $e->generated_at->format('d-M-Y H:i') }}</dd></div>@endif
                    @if ($e->submitted_at)<div><dt class="inline font-semibold">Submitted:</dt> <dd class="inline">{{ $e->submitted_at->format('d-M-Y H:i') }} {{ $e->provider_reference ? '· ref '.$e->provider_reference : '' }}</dd></div>@endif
                    @if ($e->message)<div class="sm:col-span-2"><dt class="inline font-semibold">Provider message:</dt> <dd class="inline">{{ $e->message }}</dd></div>@endif
                </dl>
            @endif

            @if ($einvoiceProblems)
                <div class="mb-3 rounded border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900">
                    <div class="mb-1 font-semibold">Fix these before generating:</div>
                    <ul class="list-disc space-y-0.5 pl-5">
                        @foreach ($einvoiceProblems as $problem)
                            <li>{{ $problem['message'] }} @if ($problem['fix'])<a href="{{ $problem['fix'] }}" wire:navigate class="font-semibold underline">Fix</a>@endif <span class="text-xs text-amber-700">({{ $problem['rule'] }})</span></li>
                        @endforeach
                    </ul>
                </div>
            @endif
            @error('einvoice') <p class="error mb-2 text-sm">{{ $message }}</p> @enderror

            @can('einvoice')
                <div class="flex flex-wrap items-end gap-2">
                    @if (! $e?->isLocked())
                        <button type="button" wire:click="generateEinvoice" class="btn-primary" @disabled($einvoiceProblems)>Generate e-invoice XML</button>
                    @endif
                    @if ($e?->xml)
                        <a href="{{ route('einvoices.xml', $e) }}" class="btn-secondary">Download XML</a>
                    @endif
                    @if ($e?->status === \App\Models\Einvoice::DRAFT && $e->xml)
                        <input wire:model="einvoiceReference" class="input w-48" placeholder="Provider reference (optional)">
                        <button type="button" wire:click="markEinvoiceSubmitted" wire:confirm="Mark as submitted to the service provider? After this the invoice can no longer be altered or cancelled." class="btn-secondary">Mark as submitted</button>
                    @endif
                    @if ($e?->status === \App\Models\Einvoice::SUBMITTED)
                        <input wire:model="einvoiceMessage" class="input w-64" placeholder="Provider message (optional)">
                        <button type="button" wire:click="markEinvoiceOutcome(true)" class="btn-secondary">Accepted</button>
                        <button type="button" wire:click="markEinvoiceOutcome(false)" class="btn-danger">Rejected</button>
                    @endif
                </div>
                <p class="mt-2 text-xs text-slate-500">Upload the XML in your accredited service provider's portal, then record it here. Submitted e-invoices are final; correct them with a credit note.</p>
            @endcan
        </section>
    @endif
</div>
