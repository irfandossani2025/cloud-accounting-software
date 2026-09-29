@php use App\Support\Money; use App\Services\StockService; @endphp
<div>
    <x-page-header title="Stock Items" :back="route('gateway')">
        @can('manage-masters')
        <a href="{{ route('stock-items.create') }}" wire:navigate data-shortcut="Alt+C" class="btn-primary">Create <span class="kbd">Alt+C</span></a>
        @endcan
    </x-page-header>

    <div class="no-print mb-3 flex flex-wrap gap-2">
        <input wire:model.live.debounce.250ms="search" placeholder="Search name, alias, part no…" class="input max-w-xs" autofocus>
        <select wire:model.live="group" class="input max-w-xs">
            <option value="">All stock groups</option>
            @foreach ($groups as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
        </select>
    </div>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Name</th><th>Group</th><th class="text-right">In stock</th><th class="text-right">Avg. cost</th><th class="text-right">Value</th><th class="no-print"></th></tr></thead>
            <tbody>
                @forelse ($positions as $p)
                    <tr class="hover:bg-slate-50">
                        <td>
                            <a href="{{ route('reports.stock-item', $p->item) }}" wire:navigate class="hover:underline">{{ $p->item->name }}</a>
                            @if ($p->item->part_no)<span class="text-xs text-slate-400">{{ $p->item->part_no }}</span>@endif
                            @if ($p->item->reorder_level && $p->qty <= Money::toBaisa($p->item->reorder_level))
                                <span class="ml-1 rounded bg-amber-100 px-1 text-xs text-amber-800">reorder</span>
                            @endif
                        </td>
                        <td class="text-slate-600">{{ $p->item->group?->name }}</td>
                        <td class="num {{ $p->qty < 0 ? 'text-red-700' : '' }}">{{ StockService::qty($p->qty) }} {{ $p->item->unit->symbol }}</td>
                        <td class="num">{{ Money::format($p->rate, true) }}</td>
                        <td class="num">{{ Money::format($p->value, true) }}</td>
                        <td class="no-print text-right">@can('manage-masters')<a href="{{ route('stock-items.edit', $p->item) }}" wire:navigate class="text-xs text-brand-700 hover:underline">Alter</a>@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-400">No stock items yet.</td></tr>
                @endforelse
            </tbody>
            <tfoot><tr class="font-semibold"><td colspan="4">Total</td><td class="num">{{ Money::format($positions->sum('value')) }}</td><td></td></tr></tfoot>
        </table>
    </div>
</div>
