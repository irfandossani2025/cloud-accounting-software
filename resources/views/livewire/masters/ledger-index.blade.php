<div>
    <x-page-header title="Ledgers" :back="route('gateway')">
        @can('manage-masters')
        <a href="{{ route('ledgers.create') }}" wire:navigate data-shortcut="Alt+C" class="btn-primary">Create <span class="kbd">Alt+C</span></a>
        @endcan
    </x-page-header>

    <div class="no-print mb-3 flex flex-wrap gap-2">
        <input wire:model.live.debounce.250ms="search" placeholder="Search ledgers…" class="input max-w-xs" autofocus>
        <select wire:model.live="group" class="input max-w-xs">
            <option value="">All groups</option>
            @foreach ($groups as $g)
                <option value="{{ $g->id }}">{{ $g->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Name</th><th>Under</th><th>VAT</th><th class="text-right">Closing balance (today)</th><th class="no-print"></th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr class="hover:bg-slate-50">
                        <td>
                            <a href="{{ route('reports.ledger', $row->ledger) }}" wire:navigate class="hover:underline">{{ $row->ledger->name }}</a>
                            @if ($row->ledger->alias)<span class="text-xs text-slate-400">({{ $row->ledger->alias }})</span>@endif
                        </td>
                        <td class="text-slate-600">{{ $row->ledger->group->name }}</td>
                        <td class="text-xs text-slate-500">{{ $row->ledger->tax_role?->label() ?? $row->ledger->vat_category?->label() }}</td>
                        <td class="num"><x-amount :value="$row->closing" drcr /></td>
                        <td class="no-print text-right">@can('manage-masters')<a href="{{ route('ledgers.edit', $row->ledger) }}" wire:navigate class="text-xs text-brand-700 hover:underline">Alter</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-400">No ledgers found.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
