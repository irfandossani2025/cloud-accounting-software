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
                    <select wire:model.live="vat_category" class="input">
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
                    @error('vatin') <p class="error">{{ $message }}</p> @enderror
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

                <h3 class="mt-2 text-sm font-semibold text-tally-top sm:col-span-2">Structured address (needed for full tax e-invoices)</h3>
                <div><label class="label">Street / way &amp; building</label><input wire:model="street" class="input"></div>
                <div><label class="label">Area</label><input wire:model="additional_street" class="input"></div>
                <div><label class="label">P.O. Box</label><input wire:model="po_box" class="input"></div>
                <div><label class="label">City</label><input wire:model="city" class="input"></div>
                <div><label class="label">Postal code</label><input wire:model="postal_code" class="input"></div>
                <div class="flex gap-2">
                    <div class="w-20"><label class="label">Country</label><input wire:model="country_code" class="input uppercase" maxlength="2">@error('country_code') <p class="error">{{ $message }}</p> @enderror</div>
                    <div class="flex-1">
                        <label class="label">Location (Oman)</label>
                        <select wire:model="country_subdivision" class="input">
                            <option value="">Mainland (default)</option>
                            @foreach (\App\Support\PintOm::SUBDIVISIONS as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                        </select>
                    </div>
                </div>
                <div class="flex gap-2 sm:col-span-2">
                    <div class="w-56">
                        <label class="label">Buyer ID type</label>
                        <select wire:model="party_id_scheme" class="input">
                            <option value="">—</option>
                            @foreach (\App\Support\PintOm::PARTY_ID_SCHEMES as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                        </select>
                        @error('party_id_scheme') <p class="error">{{ $message }}</p> @enderror
                    </div>
                    <div class="flex-1"><label class="label">Buyer ID</label><input wire:model="party_id" class="input" placeholder="For customers without a VATIN (e.g. CR number)"></div>
                </div>
            </section>
        @endif

        @if ($isRevenue)
            <section class="grid gap-4 border-t border-slate-100 pt-4 sm:grid-cols-2">
                <h2 class="font-semibold sm:col-span-2">E-invoice defaults for lines posted to this ledger</h2>
                <div>
                    <label class="label">Goods or services</label>
                    <select wire:model.live="item_type" class="input">
                        <option value="">—</option>
                        @foreach (\App\Support\PintOm::ITEM_TYPES as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="label">Industry (ISIC) code</label>
                    <x-code-picker list="isic" model="isic_code" placeholder="Type code or words, e.g. consult" />
                    @error('isic_code') <p class="error">{{ $message }}</p> @enderror
                </div>
                @if ($item_type === 'G')
                    <div>
                        <label class="label">Oman HS code (12 digits)</label>
                        <x-code-picker list="hs" model="hs_code" placeholder="Type code or words, e.g. pump" />
                        @error('hs_code') <p class="error">{{ $message }}</p> @enderror
                    </div>
                @endif
                @if (in_array($vat_category, ['zero_rated', 'exempt'], true))
                    <div class="sm:col-span-2">
                        <label class="label">{{ $vat_category === 'exempt' ? 'Exemption reason' : 'Zero-rating reason' }}</label>
                        <select wire:model="exemption_code" class="input">
                            <option value="">—</option>
                            @foreach ($vat_category === 'exempt' ? \App\Support\PintOm::EXEMPTION : \App\Support\PintOm::ZERO_RATING as $code => $label)<option value="{{ $code }}">{{ $code }} · {{ $label }}</option>@endforeach
                        </select>
                        @error('exemption_code') <p class="error">{{ $message }}</p> @enderror
                    </div>
                @endif
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
