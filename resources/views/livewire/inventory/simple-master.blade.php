<div class="mx-auto max-w-4xl">
    <x-page-header :title="$title" :back="route('gateway')" />

    <div class="grid gap-4 md:grid-cols-5">
        <div class="card overflow-x-auto md:col-span-3">
            <table class="table">
                <thead>
                    <tr>
                        @if ($kind === 'units')
                            <th>Symbol</th><th>Name</th><th class="text-right">Decimals</th>
                        @else
                            <th>Name</th><th>{{ $kind === 'stock-groups' ? 'Under' : 'Address' }}</th>
                        @endif
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($records as $record)
                        <tr class="{{ $editingId === $record->id ? 'bg-brand-50' : 'hover:bg-slate-50' }}">
                            @if ($kind === 'units')
                                <td class="font-medium">{{ $record->symbol }}</td><td>{{ $record->name }}</td><td class="num">{{ $record->decimal_places }}</td>
                            @else
                                <td class="font-medium">{{ $record->name }} @if ($record->name_ar)<span class="text-slate-400" dir="rtl">{{ $record->name_ar }}</span>@endif</td>
                                <td class="text-slate-500">{{ $kind === 'stock-groups' ? ($groups->firstWhere('id', $record->parent_id)?->name ?? 'Primary') : $record->address }}</td>
                            @endif
                            <td class="text-right whitespace-nowrap">
                                <button wire:click="edit({{ $record->id }})" class="text-xs text-brand-700 hover:underline">Alter</button>
                                @unless ($record->is_reserved ?? false)
                                    <button wire:click="delete({{ $record->id }})" wire:confirm="Delete?" class="ml-2 text-xs text-red-600 hover:underline">Delete</button>
                                @endunless
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-slate-400">None yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <form wire:submit="save" class="card space-y-3 self-start p-4 md:col-span-2">
            <h2 class="font-semibold">{{ $editingId ? 'Alter' : 'Create' }}</h2>
            @error('form') <p class="error">{{ $message }}</p> @enderror
            @if ($kind === 'units')
                <div><label class="label">Symbol</label><input wire:model="form.symbol" class="input" placeholder="Pcs">@error('form.symbol') <p class="error">{{ $message }}</p> @enderror</div>
                <div><label class="label">Formal name</label><input wire:model="form.name" class="input" placeholder="Pieces">@error('form.name') <p class="error">{{ $message }}</p> @enderror</div>
                <div><label class="label">Decimal places</label><input wire:model="form.decimal_places" class="input" inputmode="numeric">@error('form.decimal_places') <p class="error">{{ $message }}</p> @enderror</div>
            @else
                <div><label class="label">Name</label><input wire:model="form.name" class="input">@error('form.name') <p class="error">{{ $message }}</p> @enderror</div>
                <div><label class="label">Name (Arabic)</label><input wire:model="form.name_ar" class="input" dir="rtl"></div>
                @if ($kind === 'stock-groups')
                    <div>
                        <label class="label">Under</label>
                        <select wire:model="form.parent_id" class="input">
                            <option value="">Primary</option>
                            @foreach ($groups as $g)
                                @if ($g->id !== $editingId)<option value="{{ $g->id }}">{{ $g->name }}</option>@endif
                            @endforeach
                        </select>
                        @error('form.parent_id') <p class="error">{{ $message }}</p> @enderror
                    </div>
                @else
                    <div><label class="label">Address</label><input wire:model="form.address" class="input"></div>
                @endif
            @endif
            <div class="flex gap-2">
                <button class="btn-primary" data-shortcut="Ctrl+A">Save <span class="kbd">Ctrl+A</span></button>
                @if ($editingId)<button type="button" wire:click="resetForm" class="btn-secondary">New</button>@endif
            </div>
        </form>
    </div>
</div>
