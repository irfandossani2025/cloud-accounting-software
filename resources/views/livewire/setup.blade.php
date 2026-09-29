<div class="mx-auto max-w-2xl">
    <h1 class="mb-1 text-2xl font-semibold">Set up your company</h1>
    <p class="mb-6 text-sm text-slate-500">Creates the chart of accounts (Tally groups, Oman VAT ledgers) and the administrator account.</p>

    @if (! $databaseReady)
        <div class="card p-6">
            <h2 class="mb-2 font-semibold">1. Install the database</h2>
            <p class="mb-4 text-sm text-slate-600">Check the database settings in <code>.env</code>, then create the tables.</p>
            <button wire:click="install" wire:loading.attr="disabled" class="btn-primary">
                <span wire:loading.remove wire:target="install">Install database</span>
                <span wire:loading wire:target="install">Installing…</span>
            </button>
        </div>
    @else
        <form wire:submit="save" class="card space-y-6 p-6">
            <section class="grid gap-4 sm:grid-cols-2">
                <h2 class="font-semibold sm:col-span-2">Company</h2>
                <div>
                    <label class="label">Company name</label>
                    <input wire:model="companyName" class="input" autofocus>
                    @error('companyName') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Company name (Arabic)</label>
                    <input wire:model="companyNameAr" class="input" dir="rtl">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Address</label>
                    <textarea wire:model="address" rows="2" class="input"></textarea>
                </div>
                <div>
                    <label class="label">VATIN</label>
                    <input wire:model="vatin" class="input" placeholder="OM1100XXXXXX">
                    @error('vatin') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">CR number</label>
                    <input wire:model="crNumber" class="input">
                </div>
                <div>
                    <label class="label">Financial year beginning from</label>
                    <input type="date" wire:model="financialYearStart" class="input">
                    @error('financialYearStart') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Books beginning from</label>
                    <input type="date" wire:model="booksBeginFrom" class="input">
                    @error('booksBeginFrom') <p class="error">{{ $message }}</p> @enderror
                </div>
            </section>

            <section class="grid gap-4 sm:grid-cols-2">
                <h2 class="font-semibold sm:col-span-2">Administrator</h2>
                <div>
                    <label class="label">Name</label>
                    <input wire:model="name" class="input">
                    @error('name') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Email</label>
                    <input type="email" wire:model="email" class="input">
                    @error('email') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Password</label>
                    <input type="password" wire:model="password" class="input" autocomplete="new-password">
                    @error('password') <p class="error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label class="label">Confirm password</label>
                    <input type="password" wire:model="password_confirmation" class="input" autocomplete="new-password">
                </div>
            </section>

            <button class="btn-primary">Create company</button>
        </form>
    @endif
</div>
