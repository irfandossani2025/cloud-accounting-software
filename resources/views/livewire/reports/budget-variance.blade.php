@php use App\Support\Money; @endphp
<div>
    <x-page-header :title="'Budget vs actual: '.$budget->name" :subtitle="$budget->from_date->format('d-M-Y').' to '.$budget->to_date->format('d-M-Y')" :back="route('budgets.edit', $budget)">
        <button type="button" onclick="window.print()" class="btn-secondary">Print</button>
    </x-page-header>

    <div class="card overflow-x-auto">
        <table class="table">
            <thead><tr><th>Particulars</th><th>Cost centre</th><th class="text-right">Budget</th><th class="text-right">Actual</th><th class="text-right">Variance</th><th class="text-right">% used</th><th class="w-40"></th></tr></thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        // Over budget is bad for expenses, good for income.
                        $bad = $row->isExpense ? $row->variance > 0 : $row->variance < 0;
                        $pct = min(100, max(0, (int) ($row->percent ?? 0)));
                    @endphp
                    <tr>
                        <td>{{ $row->name }}</td>
                        <td class="text-slate-500">{{ $row->costCentre }}</td>
                        <td class="num">{{ Money::format($row->budget) }}</td>
                        <td class="num">{{ Money::format($row->actual) }}</td>
                        <td class="num {{ $row->variance === 0 ? '' : ($bad ? 'text-red-700' : 'text-green-700') }}">{{ Money::format($row->variance) }}</td>
                        <td class="num">{{ $row->percent !== null ? $row->percent.'%' : '' }}</td>
                        <td><div class="h-2 rounded bg-slate-100"><div class="h-2 rounded {{ $bad ? 'bg-red-500' : 'bg-brand-600' }}" style="width: {{ $pct }}%"></div></div></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="py-6 text-center text-slate-400">This budget has no lines.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="mt-2 text-xs text-slate-500">Actuals are net postings in the budget period. Red: expenses over budget or income under budget.</p>
</div>
