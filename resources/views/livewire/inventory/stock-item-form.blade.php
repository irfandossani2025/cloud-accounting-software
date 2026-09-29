<div class="mx-auto max-w-3xl">
    <x-page-header :title="$item ? 'Alter stock item' : 'Create stock item'" :back="route('stock-items.index')" />

    <form wire:submit="save" class="card space-y-6 p-6">
        <section class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Name</label>
                <input wire:model="name" class="input" autofocus>
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div><label class="label">Name (Arabic)</label><input wire:model="name_ar" class="input" dir="rtl"></div>
            <div><label class="label">Alias</label><input wire:model="alias" class="input"></div>
            <div><label class="label">Part no. / barcode</label><input wire:model="part_no" class="input"></div>
            <div>
                <label class="label">Under (stock group)</label>
                <select wire:model="stock_group_id" class="input">
                    <option value="">Primary</option>
                    @foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Unit</label>
                <select wire:model="unit_id" class="input">
                    @foreach ($units as $u)<option value="{{ $u->id }}">{{ $u->symbol }} ({{ $u->name }})</option>@endforeach
                </select>
                @error('unit_id') <p class="error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active"> Active</label>
        </section>

        <section class="grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-3">
            <h2 class="font-semibold sm:col-span-3">Sales, purchase &amp; VAT defaults</h2>
            <div>
                <label class="label">VAT treatment</label>
                <select wire:model.live="vat_category" class="input">
                    <option value="">As per ledger</option>
                    @foreach (\App\Enums\VatCategory::cases() as $cat)<option value="{{ $cat->value }}">{{ $cat->label() }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Sales ledger</label>
                <select wire:model="sales_ledger_id" class="input">
                    <option value="">—</option>
                    @foreach ($salesLedgers as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Purchase ledger</label>
                <select wire:model="purchase_ledger_id" class="input">
                    <option value="">—</option>
                    @foreach ($purchaseLedgers as $l)<option value="{{ $l->id }}">{{ $l->name }}</option>@endforeach
                </select>
            </div>
            <div>
                <label class="label">Selling rate</label><input wire:model="sales_rate" class="input text-right font-mono" placeholder="0.000">
                @error('sales_rate') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Purchase rate</label><input wire:model="purchase_rate" class="input text-right font-mono" placeholder="0.000">
                @error('purchase_rate') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Reorder level</label><input wire:model="reorder_level" class="input text-right font-mono">
                @error('reorder_level') <p class="error">{{ $message }}</p> @enderror
            </div>
        </section>

        <section class="grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-3">
            <h2 class="font-semibold sm:col-span-3">E-invoice classification</h2>
            <div>
                <label class="label">Goods or services</label>
                <select wire:model.live="item_type" class="input">
                    @foreach (\App\Support\PintOm::ITEM_TYPES as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            @if ($item_type === 'G')
                <div>
                    <label class="label">Oman HS code (12 digits)</label>
                    <x-code-picker list="hs" model="hs_code" placeholder="Type code or words" />
                    @error('hs_code') <p class="error">{{ $message }}</p> @enderror
                </div>
            @endif
            <div>
                <label class="label">Industry (ISIC) code</label>
                <x-code-picker list="isic" model="isic_code" placeholder="Type code or words" />
                @error('isic_code') <p class="error">{{ $message }}</p> @enderror
            </div>
            @if (in_array($vat_category, ['zero_rated', 'exempt'], true))
                <div class="sm:col-span-3">
                    <label class="label">{{ $vat_category === 'exempt' ? 'Exemption reason' : 'Zero-rating reason' }}</label>
                    <select wire:model="exemption_code" class="input">
                        <option value="">—</option>
                        @foreach ($vat_category === 'exempt' ? \App\Support\PintOm::EXEMPTION : \App\Support\PintOm::ZERO_RATING as $code => $label)<option value="{{ $code }}">{{ $code }} · {{ $label }}</option>@endforeach
                    </select>
                </div>
            @endif
        </section>

        <section class="border-t border-slate-100 pt-4">
            <h2 class="mb-2 font-semibold">Opening stock (books beginning)</h2>
            <table class="w-full text-sm">
                <tr class="text-xs text-slate-500 uppercase"><td>Godown</td><td class="text-right">Quantity</td><td class="text-right">Rate</td><td class="text-right">Value</td></tr>
                @foreach ($openings as $i => $o)
                    <tr wire:key="opening-{{ $i }}">
                        <td class="py-1 pr-2">
                            <select wire:model="openings.{{ $i }}.godown_id" class="input">
                                <option value="">—</option>
                                @foreach ($godowns as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                            </select>
                        </td>
                        <td class="py-1 pr-2"><input wire:model.blur="openings.{{ $i }}.quantity" class="input text-right font-mono"></td>
                        <td class="py-1 pr-2"><input wire:model.blur="openings.{{ $i }}.rate" class="input text-right font-mono" placeholder="0.000"></td>
                        <td class="num py-1">
                            @php try { $v = \App\Support\Money::multiply(\App\Support\Money::toBaisa($o['rate'] ?: 0), $o['quantity'] ?: '0'); } catch (\InvalidArgumentException) { $v = 0; } @endphp
                            {{ \App\Support\Money::format($v, true) }}
                        </td>
                    </tr>
                @endforeach
                <tr class="font-semibold"><td colspan="3" class="pt-2"><button type="button" wire:click="addOpening" class="text-xs font-normal text-brand-700 hover:underline">+ Another godown</button></td><td class="num pt-2">{{ \App\Support\Money::format($openingTotal) }}</td></tr>
            </table>
            @error('openings') <p class="error">{{ $message }}</p> @enderror
            @error('openings.*') <p class="error">{{ $message }}</p> @enderror
            <p class="mt-1 text-xs text-slate-500">Opening stock value appears in the Balance Sheet. Balance it with opening balances on your ledgers (e.g. Capital), or it shows as a difference in opening balances.</p>
        </section>

        <div class="flex justify-between">
            <button class="btn-primary" data-shortcut="Ctrl+A">Save <span class="kbd">Ctrl+A</span></button>
            @if ($item)
                <button type="button" wire:click="delete" wire:confirm="Delete this stock item?" class="btn-danger">Delete</button>
            @endif
        </div>
    </form>
</div>
