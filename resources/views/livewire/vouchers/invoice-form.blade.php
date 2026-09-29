<div>
    <x-page-header :title="($voucher ? 'Alter ' : '').$type->name" :subtitle="'No. '.$nextNumber.' · Invoice mode'" :back="route('gateway')">
        @unless ($voucher)
            @foreach ($types as $t)
                <a href="{{ in_array($t->base_type, \App\Services\InvoiceService::INVOICE_TYPES, true) ? route('invoices.create', $t) : route('vouchers.create', $t) }}" wire:navigate data-shortcut="{{ $t->base_type->shortcut() }}"
                   class="rounded px-2 py-1 text-xs {{ $t->id === $type->id ? 'bg-brand-600 text-white' : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50' }}">
                    {{ $t->base_type->shortcut() }} {{ $t->name }}
                </a>
            @endforeach
            <a href="{{ route('vouchers.create', $type) }}" wire:navigate data-shortcut="Ctrl+H" class="btn-secondary">Accounting mode <span class="kbd">Ctrl+H</span></a>
        @endunless
    </x-page-header>

    <form wire:submit="save" class="card p-4 sm:p-6">
        <div class="mb-4 grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <div>
                <label class="label">Date</label>
                <input type="date" wire:model="date" class="input">
                @error('date') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div class="sm:col-span-2">
                <label class="label">{{ $isSalesSide ? 'Customer' : 'Supplier' }} (party A/c)</label>
                <select wire:model="party_ledger_id" class="input">
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
        </div>

        <div class="overflow-x-auto">
            <table class="table min-w-[64rem]">
                <thead>
                    <tr>
                        <th class="w-8">#</th>
                        <th class="w-56">{{ $isSalesSide ? 'Sales' : 'Purchase / expense' }} ledger</th>
                        <th>Description</th>
                        <th class="w-24 text-right">Qty</th>
                        <th class="w-20">Unit</th>
                        <th class="w-28 text-right">Rate</th>
                        <th class="w-24 text-right">Discount</th>
                        <th class="w-40">VAT</th>
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
                <tr class="text-base font-semibold"><td class="border-t border-slate-300 py-1">Total (OMR)</td><td class="num border-t border-slate-300">{{ \App\Support\Money::format($calc['total']) }}</td></tr>
                <tr><td colspan="2" class="text-xs text-slate-500">{{ \App\Support\Money::inWords($calc['total']) }}</td></tr>
            </table>
        </div>

        <div class="mt-4 flex flex-wrap justify-between gap-2">
            <div class="flex gap-2">
                <button class="btn-primary" data-shortcut="Ctrl+A" wire:loading.attr="disabled">Accept <span class="kbd">Ctrl+A</span></button>
                @if ($voucher)
                    <a href="{{ route('vouchers.print', $voucher) }}" target="_blank" class="btn-secondary">Print</a>
                @endif
            </div>
            @if ($voucher)
                <button type="button" wire:click="cancelVoucher" wire:confirm="Cancel this invoice? Its number is kept but it no longer affects the books." class="btn-danger">Cancel invoice</button>
            @endif
        </div>
    </form>
</div>
