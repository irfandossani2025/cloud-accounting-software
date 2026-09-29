<div>
    <x-page-header :title="($voucher ? 'Alter ' : '').$type->name" :subtitle="'No. '.$nextNumber" :back="route('gateway')">
        @unless ($voucher)
            @foreach ($types as $t)
                <a href="{{ route('vouchers.create', $t) }}" wire:navigate data-shortcut="{{ $t->base_type->shortcut() }}"
                   class="rounded px-2 py-1 text-xs {{ $t->id === $type->id ? 'bg-brand-600 text-white' : 'bg-white text-slate-600 hover:bg-slate-50 border border-slate-200' }}">
                    {{ $t->base_type->shortcut() }} {{ $t->name }}
                </a>
            @endforeach
        @endunless
    </x-page-header>

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
            <button class="btn-primary" data-shortcut="Ctrl+A" wire:loading.attr="disabled">Accept <span class="kbd">Ctrl+A</span></button>
            @if ($voucher)
                <button type="button" wire:click="cancelVoucher" wire:confirm="Cancel this voucher? Its number is kept but it no longer affects the books." class="btn-danger">Cancel voucher</button>
            @endif
        </div>
    </form>
</div>
