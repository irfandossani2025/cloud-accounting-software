<div class="mx-auto max-w-5xl">
    <x-page-header title="Budgets" :back="route('gateway')" />

    <div class="grid gap-4 md:grid-cols-4">
        <div class="card self-start p-3">
            <a href="{{ route('budgets.index') }}" wire:navigate class="mb-2 block rounded px-2 py-1 text-sm font-semibold text-brand-700 hover:bg-brand-50">+ New budget</a>
            <ul class="space-y-0.5 text-sm">
                @foreach ($budgets as $b)
                    <li class="flex items-center justify-between rounded px-2 py-1 {{ $budget?->id === $b->id ? 'bg-brand-50' : 'hover:bg-slate-50' }}">
                        <a href="{{ route('budgets.edit', $b) }}" wire:navigate>{{ $b->name }}</a>
                        <a href="{{ route('reports.budget', $b) }}" wire:navigate class="text-xs text-brand-700 hover:underline">report</a>
                    </li>
                @endforeach
            </ul>
        </div>

        <form wire:submit="save" class="card space-y-4 p-4 md:col-span-3">
            <div class="grid gap-3 sm:grid-cols-3">
                <div><label class="label">Name</label><input wire:model="name" class="input" placeholder="FY 2026">@error('name') <p class="error">{{ $message }}</p> @enderror</div>
                <div><label class="label">From</label><input type="date" wire:model="from_date" class="input">@error('from_date') <p class="error">{{ $message }}</p> @enderror</div>
                <div><label class="label">To</label><input type="date" wire:model="to_date" class="input">@error('to_date') <p class="error">{{ $message }}</p> @enderror</div>
            </div>

            <table class="table">
                <thead><tr><th>Ledger or group</th><th class="w-48">Cost centre</th><th class="w-36 text-right">Budget amount</th><th class="w-8"></th></tr></thead>
                <tbody>
                    @foreach ($lines as $i => $line)
                        <tr wire:key="bl-{{ $i }}">
                            <td>
                                <select wire:model="lines.{{ $i }}.account" class="input">
                                    <option value="">—</option>
                                    <optgroup label="Groups">
                                        @foreach ($groups as $g)<option value="g:{{ $g->id }}">{{ $g->name }}</option>@endforeach
                                    </optgroup>
                                    <optgroup label="Ledgers">
                                        @foreach ($ledgers as $l)<option value="l:{{ $l->id }}">{{ $l->name }}</option>@endforeach
                                    </optgroup>
                                </select>
                            </td>
                            <td>
                                <select wire:model="lines.{{ $i }}.cost_centre_id" class="input">
                                    <option value="">All</option>
                                    @foreach ($costCentres as $cc)<option value="{{ $cc->id }}">{{ $cc->name }}</option>@endforeach
                                </select>
                            </td>
                            <td>
                                <input wire:model="lines.{{ $i }}.amount" class="input text-right font-mono" placeholder="0.000">
                                @error("lines.$i.amount") <p class="error">{{ $message }}</p> @enderror
                            </td>
                            <td><button type="button" wire:click="removeLine({{ $i }})" class="text-slate-400 hover:text-red-600">✕</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <button type="button" wire:click="addLine" class="text-xs text-brand-700 hover:underline">+ Line</button>
            <p class="text-xs text-slate-500">Enter amounts as positive figures: expenses and assets are budgeted as debits, income and liabilities as credits.</p>

            <div class="flex justify-between">
                <button class="btn-primary" data-shortcut="Ctrl+A">Save <span class="kbd">Ctrl+A</span></button>
                @if ($budget)<button type="button" wire:click="delete" wire:confirm="Delete this budget?" class="btn-danger">Delete</button>@endif
            </div>
        </form>
    </div>
</div>
