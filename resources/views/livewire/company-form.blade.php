<div class="mx-auto max-w-3xl">
    <x-page-header title="Company & VAT details" :back="route('gateway')" />

    <form wire:submit="save" class="card grid gap-4 p-6 sm:grid-cols-2">
        <div>
            <label class="label">Company name</label>
            <input wire:model="name" class="input">
            @error('name') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div><label class="label">Company name (Arabic)</label><input wire:model="name_ar" class="input" dir="rtl"></div>
        <div><label class="label">Address</label><textarea wire:model="address" rows="3" class="input"></textarea></div>
        <div><label class="label">Address (Arabic)</label><textarea wire:model="address_ar" rows="3" class="input" dir="rtl"></textarea></div>
        <div><label class="label">VATIN</label><input wire:model="vatin" class="input" placeholder="OM1100XXXXXX">@error('vatin') <p class="error">{{ $message }}</p> @enderror</div>
        <div><label class="label">CR number</label><input wire:model="cr_number" class="input"></div>
        <div><label class="label">Phone</label><input wire:model="phone" class="input"></div>
        <div>
            <label class="label">Email</label><input wire:model="email" class="input">
            @error('email') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">Financial year beginning from</label><input type="date" wire:model="financial_year_start" class="input">
            @error('financial_year_start') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">Books beginning from</label><input type="date" wire:model="books_begin_from" class="input">
            @error('books_begin_from') <p class="error">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" wire:model="vat_registered"> Registered for VAT in Oman</label>

        <h2 class="mt-2 border-t border-tally-line pt-3 font-semibold sm:col-span-2">Structured address (used on e-invoices)</h2>
        <div><label class="label">Street / way &amp; building</label><input wire:model="street" class="input" placeholder="Way 2317, Building 192"></div>
        <div><label class="label">Area</label><input wire:model="additional_street" class="input" placeholder="Al Khuwair"></div>
        <div><label class="label">P.O. Box</label><input wire:model="po_box" class="input" placeholder="P.O. Box 192"></div>
        <div><label class="label">City</label><input wire:model="city" class="input" placeholder="Muscat"></div>
        <div><label class="label">Postal code</label><input wire:model="postal_code" class="input" placeholder="112"></div>
        <div>
            <label class="label">Location</label>
            <select wire:model="country_subdivision" class="input">
                @foreach (\App\Support\PintOm::SUBDIVISIONS as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach
            </select>
        </div>

        <h2 class="mt-2 border-t border-tally-line pt-3 font-semibold sm:col-span-2">E-invoicing (Fawtara)</h2>
        <label class="flex items-center gap-2 text-sm sm:col-span-2"><input type="checkbox" wire:model="einvoicing_enabled"> Issue e-invoices for sales invoices and credit notes (Peppol PINT OM)</label>
        <p class="text-xs text-slate-500 sm:col-span-2">E-invoices are sent through an OTA-accredited service provider. Until a provider connection is set up, the XML is downloaded from each invoice and uploaded in the provider's portal.</p>
        <div class="sm:col-span-2"><button class="btn-primary" data-shortcut="Ctrl+A">Save <span class="kbd">Ctrl+A</span></button></div>
    </form>
</div>
