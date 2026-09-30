@php
    $selectable = array_values(array_filter(array_keys($items), fn ($i) => isset($items[$i]['url'])));
@endphp
<div class="grid gap-6 lg:grid-cols-[1fr_minmax(0,24rem)_1fr]">
    {{-- Company panel, as on the left of Tally's Gateway --}}
    <aside class="tally-panel self-start text-sm">
        <div class="grid grid-cols-2 gap-x-4 border-b border-tally-line pb-3">
            <div>
                <div class="tally-caption">Current period</div>
                <div class="font-semibold">{{ $periodFrom->format('j-M-y') }} to {{ $periodTo->format('j-M-y') }}</div>
            </div>
            <div>
                <div class="tally-caption">Current date</div>
                <div class="font-semibold">{{ now()->format('l, j-M-Y') }}</div>
            </div>
        </div>
        <div class="grid grid-cols-2 gap-x-4 pt-3">
            <div>
                <div class="tally-caption">Name of company</div>
                <div class="font-semibold">{{ $company?->name }}</div>
                @if ($company?->name_ar)<div dir="rtl" class="text-slate-600">{{ $company->name_ar }}</div>@endif
            </div>
            <div>
                <div class="tally-caption">Date of last entry</div>
                <div class="font-semibold">{{ $lastEntry ? \Illuminate\Support\Carbon::parse($lastEntry)->format('j-M-y') : 'No vouchers entered' }}</div>
            </div>
        </div>

        @if ($cashBank->isNotEmpty())
            <div class="mt-4 border-t border-tally-line pt-3">
                <div class="tally-caption mb-1">Cash &amp; bank balances</div>
                <table class="w-full">
                    @foreach ($cashBank as $row)
                        <tr>
                            <td class="py-0.5"><a href="{{ route('reports.ledger', $row->ledger) }}" wire:navigate class="hover:underline">{{ $row->ledger->name }}</a></td>
                            <td class="num"><x-amount :value="$row->closing" drcr /></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif
    </aside>

    {{-- The menu --}}
    <nav
        x-data="tallyMenu({{ json_encode($selectable) }})"
        x-on:keydown.window="onKey($event)"
        class="tally-menu self-start"
        aria-label="{{ $title }}"
    >
        <div class="tally-menu-title">{{ $title }}</div>
        @if ($menu !== 'gateway')
            <a href="{{ route('gateway') }}" wire:navigate data-shortcut="Escape" class="hidden">Back</a>
        @endif
        <ul class="py-2">
            @foreach ($items as $i => $item)
                @if (isset($item['section']))
                    <li class="tally-section">{{ $item['section'] }}</li>
                @else
                    @php
                        // Underline the hotkey letter inside the label, as Tally does.
                        $label = e($item['label']);
                        $key = $item['key'];
                        if (empty($item['fkey']) && ($pos = stripos($item['label'], $key)) !== false) {
                            $label = e(substr($item['label'], 0, $pos)).'<span class="tally-hotkey">'.e(substr($item['label'], $pos, 1)).'</span>'.e(substr($item['label'], $pos + 1));
                        }
                    @endphp
                    <li>
                        <a href="{{ $item['url'] }}" wire:navigate
                           data-index="{{ $i }}"
                           @if (empty($item['fkey'])) data-hotkey="{{ strtoupper($key) }}" @else data-shortcut="{{ $key }}" @endif
                           x-bind:class="current === {{ $i }} ? 'tally-item-active' : ''"
                           x-on:mouseenter="current = {{ $i }}"
                           class="tally-item">
                            <span>{!! $label !!}</span>
                            @if (! empty($item['fkey']))<span class="kbd">{{ $key }}</span>@endif
                        </a>
                    </li>
                @endif
            @endforeach
        </ul>
        @if ($menu !== 'gateway')
            <div class="border-t border-tally-line px-4 py-2 text-xs">
                <a href="{{ route('gateway') }}" wire:navigate class="text-slate-500 hover:underline">Esc: Back to Gateway</a>
            </div>
        @endif
    </nav>

    {{-- At a glance (full view: Dashboard, hotkey O) --}}
    @if ($glance)
        @php $m = fn ($v) => \App\Support\Money::format($v); @endphp
        <aside class="space-y-2 self-start text-sm">
            <a href="{{ route('dashboard') }}" wire:navigate class="tally-panel block !p-3 hover:border-tally-top">
                <div class="tally-caption">Receivables</div>
                <div class="font-mono text-base font-semibold">{{ $m($glance['receivables']) }}</div>
                @if ($glance['receivablesOverdue'] > 0)<div class="text-xs text-red-700">⚠ {{ $m($glance['receivablesOverdue']) }} overdue</div>@endif
            </a>
            <a href="{{ route('dashboard') }}" wire:navigate class="tally-panel block !p-3 hover:border-tally-top">
                <div class="tally-caption">Payables</div>
                <div class="font-mono text-base font-semibold">{{ $m($glance['payables']) }}</div>
                @if ($glance['payablesDueSoon']->isNotEmpty())<div class="text-xs text-amber-700">{{ $glance['payablesDueSoon']->count() }} bill(s) due within 14 days</div>@endif
            </a>
            <a href="{{ route('dashboard') }}" wire:navigate class="tally-panel block !p-3 hover:border-tally-top">
                <div class="tally-caption">VAT {{ $glance['vatDue'] >= 0 ? 'payable' : 'refundable' }} this quarter</div>
                <div class="font-mono text-base font-semibold">{{ $m(abs($glance['vatDue'])) }}</div>
            </a>
            <a href="{{ route('dashboard') }}" wire:navigate class="tally-panel block !p-3 hover:border-tally-top">
                <div class="tally-caption">Sales this month</div>
                <div class="font-mono text-base font-semibold">{{ $m($glance['salesThisMonth']) }}</div>
            </a>
            @if ($glance['lowStock']->isNotEmpty() || $glance['postDated']->isNotEmpty())
                <a href="{{ route('dashboard') }}" wire:navigate class="tally-panel block !p-3 text-xs text-amber-800 hover:border-tally-top">
                    @if ($glance['lowStock']->isNotEmpty())<div>⚠ {{ $glance['lowStock']->count() }} item(s) at reorder level</div>@endif
                    @if ($glance['postDated']->isNotEmpty())<div>⚠ {{ $glance['postDated']->count() }} post-dated cheque(s) this week</div>@endif
                </a>
            @endif
        </aside>
    @else
        <div class="hidden lg:block"></div>
    @endif
</div>
