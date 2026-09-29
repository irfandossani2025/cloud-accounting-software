<div>
    <x-page-header title="Groups" subtitle="Chart of accounts structure" :back="route('gateway')">
        <a href="{{ route('groups.create') }}" wire:navigate data-shortcut="Alt+C" class="btn-primary">Create <span class="kbd">Alt+C</span></a>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Name</th><th>Nature</th><th>Affects gross profit</th><th class="text-right">Ledgers</th></tr></thead>
            <tbody>
                @foreach ($rows as $row)
                    <tr class="hover:bg-slate-50">
                        <td style="padding-left: {{ 0.75 + $row['depth'] * 1.5 }}rem">
                            <a href="{{ route('groups.edit', $row['group']) }}" wire:navigate class="{{ $row['depth'] === 0 ? 'font-semibold' : '' }} hover:underline">{{ $row['group']->name }}</a>
                            @if ($row['group']->is_reserved)<span class="ml-1 text-xs text-slate-400">predefined</span>@endif
                        </td>
                        <td>{{ $row['group']->nature->label() }}</td>
                        <td>{{ $row['group']->affects_gross_profit ? 'Yes' : 'No' }}</td>
                        <td class="num">{{ $row['group']->ledgers_count ?: '' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
