@php use App\Support\Money; @endphp
<div class="mx-auto max-w-4xl">
    <x-page-header title="Year-end & period lock" :back="route('gateway')" />

    <div class="card mb-4 p-4 text-sm text-slate-600">
        <p>As in Tally, no closing entries are needed. Income and expense ledgers start again from zero each financial year, and each year's profit moves into the <strong>Profit &amp; Loss A/c</strong> automatically. Balance sheet ledgers and stock carry forward.</p>
        <p class="mt-2">To <strong>close a year</strong>, finalise its entries, download a backup, then lock it. Locked periods cannot be posted to, altered or cancelled by anyone until an administrator moves the lock date.</p>
    </div>

    <div class="card mb-4 overflow-x-auto">
        <table class="table">
            <thead><tr><th>Financial year</th><th class="text-right">Net profit / (loss)</th><th>Status</th><th></th></tr></thead>
            <tbody>
                @foreach ($years as $year)
                    <tr>
                        <td>{{ $year->start->format('d-M-Y') }} to {{ $year->end->format('d-M-Y') }} @if ($year->current)<span class="ml-1 rounded bg-brand-100 px-1.5 text-xs text-brand-800">current</span>@endif</td>
                        <td class="num"><x-amount :value="$year->netProfit" :blank="false" /></td>
                        <td>{!! $year->locked ? '<span class="text-slate-500">🔒 Locked</span>' : '<span class="text-green-700">Open</span>' !!}</td>
                        <td class="text-right">
                            @if (! $year->locked && ! $year->current)
                                <button wire:click="lockUntil('{{ $year->end->toDateString() }}')" wire:confirm="Lock all entries up to {{ $year->end->format('d-M-Y') }}? Download a backup first." class="btn-secondary">Close &amp; lock year</button>
                            @endif
                            <a href="{{ route('reports.balance-sheet', ['to' => $year->end->min(now())->toDateString()]) }}" wire:navigate class="ml-2 text-xs text-brand-700 hover:underline">Balance sheet</a>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="grid gap-4 md:grid-cols-2">
        <form wire:submit="lockUntil" class="card space-y-3 p-4">
            <h2 class="font-semibold">Lock date</h2>
            <p class="text-sm text-slate-600">Lock individual months too, e.g. after filing the quarterly VAT return.</p>
            <div class="flex gap-2">
                <input type="date" wire:model="lockDate" class="input">
                <button class="btn-primary">Save</button>
            </div>
            @error('lockDate') <p class="error">{{ $message }}</p> @enderror
            @if ($company->locked_until)
                <p class="text-sm">Currently locked up to <strong>{{ $company->locked_until->format('d-M-Y') }}</strong>.
                    <button type="button" wire:click="lockUntil('')" wire:confirm="Remove the period lock?" class="text-red-600 hover:underline">Remove lock</button></p>
            @endif
        </form>

        <div class="card space-y-3 p-4">
            <h2 class="font-semibold">Backup</h2>
            <p class="text-sm text-slate-600">Downloads all company data as an SQL file. Keep it somewhere safe; Plesk's own backups are the first line of defence.</p>
            <a href="{{ route('backup.download') }}" class="btn-secondary">Download backup (.sql)</a>
        </div>
    </div>
</div>
