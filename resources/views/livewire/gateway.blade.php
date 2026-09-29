<div>
    <h1 class="mb-4 text-xl font-semibold">Gateway</h1>

    <div class="grid gap-4 md:grid-cols-3">
        <section class="card p-4">
            <h2 class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Masters</h2>
            <ul class="space-y-1 text-sm">
                <li><a href="{{ route('groups.index') }}" wire:navigate data-shortcut="Alt+G" class="flex justify-between rounded px-2 py-1 hover:bg-brand-50"><span>Groups</span><span class="kbd">Alt+G</span></a></li>
                <li><a href="{{ route('ledgers.index') }}" wire:navigate data-shortcut="Alt+L" class="flex justify-between rounded px-2 py-1 hover:bg-brand-50"><span>Ledgers</span><span class="kbd">Alt+L</span></a></li>
                <li><a href="{{ route('ledgers.create') }}" wire:navigate data-shortcut="Alt+C" class="flex justify-between rounded px-2 py-1 hover:bg-brand-50"><span>Create ledger</span><span class="kbd">Alt+C</span></a></li>
            </ul>

            <h2 class="mt-4 mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Inventory masters</h2>
            <ul class="space-y-1 text-sm">
                <li><a href="{{ route('stock-items.index') }}" wire:navigate data-shortcut="Alt+I" class="flex justify-between rounded px-2 py-1 hover:bg-brand-50"><span>Stock items</span><span class="kbd">Alt+I</span></a></li>
                <li><a href="{{ route('inventory.masters', 'stock-groups') }}" wire:navigate class="block rounded px-2 py-1 hover:bg-brand-50">Stock groups</a></li>
                <li><a href="{{ route('inventory.masters', 'units') }}" wire:navigate class="block rounded px-2 py-1 hover:bg-brand-50">Units of measure</a></li>
                <li><a href="{{ route('inventory.masters', 'godowns') }}" wire:navigate class="block rounded px-2 py-1 hover:bg-brand-50">Godowns</a></li>
            </ul>

            <h2 class="mt-4 mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Banking &amp; control</h2>
            <ul class="space-y-1 text-sm">
                <li><a href="{{ route('reports.bank-reconciliation') }}" wire:navigate data-shortcut="Alt+K" class="flex justify-between rounded px-2 py-1 hover:bg-brand-50"><span>Bank reconciliation</span><span class="kbd">Alt+K</span></a></li>
                <li><a href="{{ route('reports.post-dated') }}" wire:navigate class="block rounded px-2 py-1 hover:bg-brand-50">Post-dated cheques</a></li>
                <li><a href="{{ route('inventory.masters', 'cost-centres') }}" wire:navigate class="block rounded px-2 py-1 hover:bg-brand-50">Cost centres</a></li>
                <li><a href="{{ route('budgets.index') }}" wire:navigate class="block rounded px-2 py-1 hover:bg-brand-50">Budgets</a></li>
                <li><a href="{{ route('currencies.index') }}" wire:navigate class="block rounded px-2 py-1 hover:bg-brand-50">Currencies &amp; rates</a></li>
            </ul>

            <h2 class="mt-4 mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Utilities</h2>
            <ul class="space-y-1 text-sm">
                <li><a href="{{ route('company.edit') }}" wire:navigate class="block rounded px-2 py-1 hover:bg-brand-50">Company &amp; VAT details</a></li>
            </ul>
        </section>

        <section class="card p-4">
            <h2 class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Vouchers</h2>
            <ul class="space-y-1 text-sm">
                @foreach ($voucherTypes as $type)
                    <li>
                        <a href="{{ $type->createUrl() }}" wire:navigate
                           @if ($type->is_reserved) data-shortcut="{{ $type->base_type->shortcut() }}" @endif
                           class="flex justify-between rounded px-2 py-1 hover:bg-brand-50">
                            <span>{{ $type->name }}</span>
                            @if ($type->is_reserved)<span class="kbd">{{ $type->base_type->shortcut() }}</span>@endif
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>

        <section class="card p-4">
            <h2 class="mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Reports</h2>
            <ul class="space-y-1 text-sm">
                <li><a href="{{ route('reports.balance-sheet') }}" wire:navigate data-shortcut="Alt+B" class="flex justify-between rounded px-2 py-1 hover:bg-brand-50"><span>Balance Sheet</span><span class="kbd">Alt+B</span></a></li>
                <li><a href="{{ route('reports.profit-loss') }}" wire:navigate data-shortcut="Alt+P" class="flex justify-between rounded px-2 py-1 hover:bg-brand-50"><span>Profit &amp; Loss A/c</span><span class="kbd">Alt+P</span></a></li>
                <li><a href="{{ route('reports.trial-balance') }}" wire:navigate data-shortcut="Alt+T" class="flex justify-between rounded px-2 py-1 hover:bg-brand-50"><span>Trial Balance</span><span class="kbd">Alt+T</span></a></li>
                <li><a href="{{ route('reports.day-book') }}" wire:navigate data-shortcut="Alt+D" class="flex justify-between rounded px-2 py-1 hover:bg-brand-50"><span>Day Book</span><span class="kbd">Alt+D</span></a></li>
                <li><a href="{{ route('reports.outstanding', 'receivables') }}" wire:navigate data-shortcut="Alt+R" class="flex justify-between rounded px-2 py-1 hover:bg-brand-50"><span>Bills Receivable</span><span class="kbd">Alt+R</span></a></li>
                <li><a href="{{ route('reports.outstanding', 'payables') }}" wire:navigate data-shortcut="Alt+Y" class="flex justify-between rounded px-2 py-1 hover:bg-brand-50"><span>Bills Payable</span><span class="kbd">Alt+Y</span></a></li>
                <li><a href="{{ route('reports.stock-summary') }}" wire:navigate data-shortcut="Alt+S" class="flex justify-between rounded px-2 py-1 hover:bg-brand-50"><span>Stock Summary</span><span class="kbd">Alt+S</span></a></li>
                <li><a href="{{ route('reports.cost-centres') }}" wire:navigate class="block rounded px-2 py-1 hover:bg-brand-50">Cost Centre Break-up</a></li>
                <li><a href="{{ route('reports.forex') }}" wire:navigate class="block rounded px-2 py-1 hover:bg-brand-50">Forex Gain/Loss</a></li>
                <li><a href="{{ route('reports.vat-return') }}" wire:navigate data-shortcut="Alt+X" class="flex justify-between rounded px-2 py-1 hover:bg-brand-50"><span>VAT Return (Oman)</span><span class="kbd">Alt+X</span></a></li>
            </ul>

            <h2 class="mt-4 mb-2 text-xs font-semibold tracking-wide text-slate-500 uppercase">Cash &amp; bank today</h2>
            <table class="w-full text-sm">
                @forelse ($cashBank as $row)
                    <tr>
                        <td class="py-0.5"><a href="{{ route('reports.ledger', $row->ledger) }}" wire:navigate class="hover:underline">{{ $row->ledger->name }}</a></td>
                        <td class="num"><x-amount :value="$row->closing" drcr /></td>
                    </tr>
                @empty
                    <tr><td class="text-slate-400">No cash or bank ledgers.</td></tr>
                @endforelse
            </table>
        </section>
    </div>
</div>
