<div class="mx-auto max-w-5xl">
    <x-page-header title="Currencies & exchange rates" subtitle="Base currency: Omani Rial (OMR). Rates are OMR per one unit." :back="route('gateway')" />

    <div class="grid gap-4 md:grid-cols-3">
        <div class="card self-start p-3">
            <ul class="space-y-0.5 text-sm">
                @foreach ($currencies as $c)
                    <li>
                        <button wire:click="$set('currency', {{ $c->id }})" class="flex w-full justify-between rounded px-2 py-1 text-left {{ $selected?->id === $c->id ? 'bg-brand-600 text-white' : 'hover:bg-slate-50' }}">
                            <span>{{ $c->code }} · {{ $c->name }}</span>
                        </button>
                    </li>
                @endforeach
            </ul>
            <form wire:submit="addCurrency" class="mt-3 space-y-2 border-t border-slate-100 pt-3">
                <div class="text-xs font-semibold text-slate-500 uppercase">Add currency</div>
                <div class="flex gap-2">
                    <input wire:model="code" class="input w-20 uppercase" placeholder="KWD" maxlength="3">
                    <input wire:model="name" class="input" placeholder="Kuwaiti Dinar">
                </div>
                <div class="flex gap-2">
                    <input wire:model="symbol" class="input w-20" placeholder="Symbol">
                    <input wire:model="decimals" class="input w-20" placeholder="Decimals" title="Decimal places">
                    <button class="btn-secondary">Add</button>
                </div>
                @error('code') <p class="error">{{ $message }}</p> @enderror
                @error('name') <p class="error">{{ $message }}</p> @enderror
                @error('decimals') <p class="error">{{ $message }}</p> @enderror
            </form>
        </div>

        @if ($selected)
            <div class="card overflow-x-auto md:col-span-2">
                <form wire:submit="saveRate" class="flex flex-wrap items-end gap-2 border-b border-slate-100 p-3">
                    <div><label class="label">Date</label><input type="date" wire:model="rateDate" class="input"></div>
                    <div><label class="label">OMR per 1 {{ $selected->code }}</label><input wire:model="rate" class="input text-right font-mono" placeholder="0.000000"></div>
                    <button class="btn-primary" data-shortcut="Ctrl+A">Save rate</button>
                    @error('rate') <p class="error w-full">{{ $message }}</p> @enderror
                    @error('rateDate') <p class="error w-full">{{ $message }}</p> @enderror
                </form>
                <table class="table">
                    <thead><tr><th>Effective from</th><th class="text-right">OMR per {{ $selected->code }}</th><th class="text-right">{{ $selected->code }} per OMR</th><th></th></tr></thead>
                    <tbody>
                        @forelse ($rates as $r)
                            <tr>
                                <td>{{ $r->date->format('d-M-Y') }}</td>
                                <td class="num">{{ $r->rate }}</td>
                                <td class="num text-slate-500">{{ number_format(1 / (float) $r->rate, 4) }}</td>
                                <td class="text-right"><button wire:click="deleteRate({{ $r->id }})" wire:confirm="Delete this rate?" class="text-xs text-red-600 hover:underline">Delete</button></td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="py-6 text-center text-slate-400">No rates yet. Add the rate used on your invoices.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
