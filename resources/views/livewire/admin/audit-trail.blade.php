@php
    $fmt = fn ($v) => match (true) {
        is_array($v) => implode("\n", array_map(fn ($x) => is_scalar($x) ? (string) $x : json_encode($x), $v)),
        $v === null => '—',
        is_bool($v) => $v ? 'yes' : 'no',
        is_scalar($v) => (string) $v,
        default => json_encode($v),
    };
@endphp
<div>
    <x-page-header title="Audit trail" subtitle="Who created, altered, cancelled or deleted what, and when." :back="route('gateway')">
        <button type="button" data-export-csv class="btn-secondary">Excel</button>
    </x-page-header>

    <div class="no-print mb-3 flex flex-wrap items-end gap-2">
        <div><label class="label">User</label><select wire:model.live="user" class="input"><option value="">All</option>@foreach ($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select></div>
        <div><label class="label">Record</label><select wire:model.live="type" class="input"><option value="">All</option>@foreach ($types as $t)<option>{{ $t }}</option>@endforeach</select></div>
        <div><label class="label">Action</label><select wire:model.live="action" class="input"><option value="">All</option>@foreach ($actions as $a)<option>{{ $a }}</option>@endforeach</select></div>
        <div><label class="label">From</label><input type="date" wire:model.live="from" class="input"></div>
        <div><label class="label">To</label><input type="date" wire:model.live="to" class="input"></div>
        <div><label class="label">Search</label><input wire:model.live.debounce.300ms="search" class="input" placeholder="e.g. INV-12"></div>
    </div>

    <div class="card overflow-x-auto" data-report>
        <table class="table">
            <thead><tr><th>When</th><th>User</th><th>Action</th><th>Description</th><th class="no-print"></th></tr></thead>
            <tbody>
                @forelse ($logs as $log)
                    <tr class="hover:bg-slate-50">
                        <td class="whitespace-nowrap">{{ $log->created_at->format('d-M-Y H:i') }}</td>
                        <td>{{ $log->user?->name ?? 'System' }}</td>
                        <td><span class="rounded px-1.5 py-0.5 text-xs {{ match ($log->action) { 'created' => 'bg-green-100 text-green-800', 'cancelled', 'deleted' => 'bg-red-100 text-red-800', 'altered' => 'bg-amber-100 text-amber-800', default => 'bg-slate-100 text-slate-700' } }}">{{ $log->action }}</span></td>
                        <td>{{ $log->description }}</td>
                        <td class="no-print text-right">
                            @if ($log->old_values || $log->new_values)
                                <button wire:click="toggle({{ $log->id }})" class="text-xs text-brand-700 hover:underline">{{ $expanded === $log->id ? 'Hide' : 'Details' }}</button>
                            @endif
                        </td>
                    </tr>
                    @if ($expanded === $log->id)
                        <tr>
                            <td colspan="5" class="bg-slate-50">
                                <div class="grid gap-4 text-xs md:grid-cols-2">
                                    @foreach (['Before' => $log->old_values, 'After' => $log->new_values] as $label => $values)
                                        <div>
                                            <div class="mb-1 font-semibold text-slate-600">{{ $label }}</div>
                                            @if ($values)
                                                <dl class="space-y-0.5">
                                                    @foreach ($values as $key => $value)
                                                        <div class="flex gap-2"><dt class="w-28 shrink-0 text-slate-500">{{ $key }}</dt><dd class="font-mono break-all whitespace-pre-line">{{ $fmt($value) }}</dd></div>
                                                    @endforeach
                                                </dl>
                                            @else
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="5" class="py-6 text-center text-slate-400">No activity recorded.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-3">{{ $logs->links() }}</div>
</div>
