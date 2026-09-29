<div class="mx-auto max-w-3xl">
    <x-page-header :title="$ledger ? 'Alter ledger' : 'Create ledger'" :back="route('ledgers.index')" />

    <form wire:submit="save" class="card space-y-6 p-6">
        <section class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Name</label>
                <input wire:model="name" class="input" autofocus>
                @error('name') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">Name (Arabic)</label>
                <input wire:model="name_ar" class="input" dir="rtl">
            </div>
            <div>
                <label class="label">Alias</label>
                <input wire:model="alias" class="input">
            </div>
            <div>
                <label class="label">Under</label>
                <select wire:model.live="account_group_id" class="input" @disabled($ledger?->is_reserved)>
                    <option value="">— Select group —</option>
                    @foreach ($groups as $g)
                        <option value="{{ $g->id }}">{{ $g->name }}</option>
                    @endforeach
                </select>
                @error('account_group_id') <p class="error">{{ $message }}</p> @enderror
            </div>
            @unless ($isRevenue)
                <div>
                    <label class="label">Opening balance</label>
                    <div class="flex gap-2">
                        <input wire:model="opening_amount" class="input text-right font-mono" inputmode="decimal" placeholder="0.000">
                        <select wire:model="opening_side" class="input w-20"><option>Dr</option><option>Cr</option></select>
                    </div>
                    @error('opening_amount') <p class="error">{{ $message }}</p> @enderror
                </div>
            @endunless
            @if (($isParty || $isBank) && ! $isRevenue)
                <div>
                    <label class="label">Currency</label>
                    <div class="flex gap-2">
                        <select wire:model.live="currency_id" class="input w-32">
                            <option value="">OMR</option>
                            @foreach ($currencies as $c)<option value="{{ $c->id }}">{{ $c->code }}</option>@endforeach
                        </select>
                        @if ($currency_id)
                            <input wire:model="opening_fx_amount" class="input text-right font-mono" placeholder="Opening in {{ $currencies->firstWhere('id', $currency_id)?->code }}">
                        @endif
                    </div>
                    @error('opening_fx_amount') <p class="error">{{ $message }}</p> @enderror
                    @if ($currency_id)<p class="mt-1 text-xs text-slate-500">Opening in foreign currency uses the same Dr/Cr side as the OMR opening balance.</p>@endif
                </div>
            @endif
            <div class="flex items-end gap-6">
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active"> Active</label>
                @if ($isRevenue)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="cost_centres_applicable"> Cost centres applicable</label>
                @endif
                @if ($isParty)
                    <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_bill_wise"> Maintain bill-by-bill</label>
                @endif
            </div>
        </section>

        <section class="grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
            <h2 class="font-semibold sm:col-span-2">Oman VAT</h2>
            @if ($isTax)
                <div>
                    <label class="label">Tax type</label>
                    <select wire:model="tax_role" class="input">
                        <option value="">— Not VAT —</option>
                        @foreach (\App\Enums\TaxRole::cases() as $role)
                            <option value="{{ $role->value }}">{{ $role->label() }}</option>
                        @endforeach
                    </select>
                </div>
            @else
                <div>
                    <label class="label">VAT treatment</label>
                    <select wire:model="vat_category" class="input">
                        <option value="">— Not applicable —</option>
                        @foreach (\App\Enums\VatCategory::cases() as $cat)
                            <option value="{{ $cat->value }}">{{ $cat->label() }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Used on sales, purchase and expense ledgers for the VAT return.</p>
                </div>
            @endif
            @if ($isParty)
                <div>
                    <label class="label">VATIN</label>
                    <input wire:model="vatin" class="input" placeholder="OM1100XXXXXX">
                </div>
            @endif
        </section>

        @if ($isParty)
            <section class="grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
                <h2 class="font-semibold sm:col-span-2">Party details</h2>
                <div class="sm:col-span-2">
                    <label class="label">Address</label>
                    <textarea wire:model="address" rows="2" class="input"></textarea>
                </div>
                <div><label class="label">CR number</label><input wire:model="cr_number" class="input"></div>
                <div><label class="label">Phone</label><input wire:model="phone" class="input"></div>
                <div>
                    <label class="label">Email</label><input wire:model="email" class="input">
                    @error('email') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Credit period (days)</label><input wire:model="credit_days" class="input" inputmode="numeric">
                    @error('credit_days') <p class="error">{{ $message }}</p> @enderror
                </div>
            </section>
        @endif

        @if ($isBank)
            <section class="grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-3">
                <h2 class="font-semibold sm:col-span-3">Bank details</h2>
                <div><label class="label">Bank name</label><input wire:model="bank_name" class="input"></div>
                <div><label class="label">Account number</label><input wire:model="bank_account_no" class="input"></div>
                <div><label class="label">IBAN</label><input wire:model="iban" class="input"></div>
            </section>
        @endif

        <div class="flex justify-between">
            <button class="btn-primary" data-shortcut="Ctrl+A">Save <span class="kbd">Ctrl+A</span></button>
            @if ($ledger && ! $ledger->is_reserved)
                <button type="button" wire:click="delete" wire:confirm="Delete this ledger?" class="btn-danger">Delete</button>
            @endif
        </div>
    </form>
</div>
