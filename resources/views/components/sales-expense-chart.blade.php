@props(['months'])
@php
    use App\Support\Money;
    // Geometry: one bar pair per month on a shared axis (same unit, so one y-scale).
    $w = 720; $h = 240; $left = 56; $right = 8; $top = 12; $bottom = 28;
    $plotW = $w - $left - $right; $plotH = $h - $top - $bottom;
    $max = max(1, ...array_map(fn ($m) => max($m['sales'], $m['expenses']), $months));
    // Round the axis maximum up to a "nice" number of OMR.
    $maxOmr = $max / 1000;
    $magnitude = 10 ** floor(log10(max($maxOmr, 1)));
    $niceMax = collect([1, 2, 2.5, 5, 10])->map(fn ($f) => $f * $magnitude)->first(fn ($v) => $v >= $maxOmr) ?? 10 * $magnitude;
    $ticks = [0, $niceMax / 4, $niceMax / 2, 3 * $niceMax / 4, $niceMax];
    $slot = $plotW / count($months);
    $barW = max(3, min(18, ($slot - 10) / 2));
    $y = fn (float $omr) => $top + $plotH - ($omr / $niceMax) * $plotH;
    // Bar with rounded data end (top) and square baseline.
    $bar = function (float $x, float $omr) use ($y, $top, $plotH, $barW) {
        $y0 = $top + $plotH; $y1 = min($y0 - 1, $y(max($omr, 0)));
        $r = min(4, $barW / 2, ($y0 - $y1));
        return sprintf('M%.1f %.1f V%.1f Q%.1f %.1f %.1f %.1f H%.1f Q%.1f %.1f %.1f %.1f V%.1f Z',
            $x, $y0, $y1 + $r, $x, $y1, $x + $r, $y1, $x + $barW - $r, $x + $barW, $y1, $x + $barW, $y1 + $r, $y0);
    };
    $fmtTick = fn ($v) => $v >= 1000 ? number_format($v / 1000, $v % 1000 ? 1 : 0).'k' : number_format($v);
@endphp
<div class="viz-root" x-data="{ tip: null }" style="--series-1:#2a78d6;--series-2:#eb6834;">
    <div class="mb-2 flex items-center gap-4 text-xs text-slate-600">
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm" style="background:var(--series-1)"></span>Sales</span>
        <span class="flex items-center gap-1.5"><span class="inline-block h-2.5 w-2.5 rounded-sm" style="background:var(--series-2)"></span>Expenses</span>
        <span class="ml-auto text-slate-400">OMR, last 12 months</span>
    </div>
    <div class="relative">
        <svg viewBox="0 0 {{ $w }} {{ $h }}" class="h-auto w-full" role="img" aria-label="Monthly sales and expenses, last 12 months">
            @foreach ($ticks as $t)
                <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $y($t) }}" y2="{{ $y($t) }}" stroke="#e2e8f0" stroke-width="1" />
                <text x="{{ $left - 6 }}" y="{{ $y($t) + 3 }}" text-anchor="end" font-size="10" fill="#64748b">{{ $fmtTick($t) }}</text>
            @endforeach
            @foreach ($months as $i => $m)
                @php $x0 = $left + $i * $slot + ($slot - 2 * $barW - 2) / 2; @endphp
                {{-- No mark for zero (or net negative) months: a stub would suggest a value. --}}
                @if ($m['sales'] > 0)<path d="{{ $bar($x0, $m['sales'] / 1000) }}" fill="var(--series-1)" />@endif
                @if ($m['expenses'] > 0)<path d="{{ $bar($x0 + $barW + 2, $m['expenses'] / 1000) }}" fill="var(--series-2)" />@endif
                <text x="{{ $left + $i * $slot + $slot / 2 }}" y="{{ $h - 10 }}" text-anchor="middle" font-size="10" fill="#64748b">{{ $m['month']->format('M') }}</text>
                {{-- Hit target: the whole month column --}}
                <rect x="{{ $left + $i * $slot }}" y="{{ $top }}" width="{{ $slot }}" height="{{ $plotH }}" fill="transparent"
                      x-on:mouseenter="tip = { i: {{ $i }}, label: @js($m['month']->format('F Y')), sales: @js(Money::format($m['sales'])), expenses: @js(Money::format($m['expenses'])), left: {{ round(($left + $i * $slot + $slot / 2) / $w * 100, 2) }} }"
                      x-on:mouseleave="tip = null"><title>{{ $m['month']->format('F Y') }}: sales {{ Money::format($m['sales']) }}, expenses {{ Money::format($m['expenses']) }}</title></rect>
            @endforeach
            <line x1="{{ $left }}" x2="{{ $w - $right }}" y1="{{ $top + $plotH }}" y2="{{ $top + $plotH }}" stroke="#94a3b8" stroke-width="1" />
        </svg>
        <div x-show="tip" x-cloak class="pointer-events-none absolute top-0 z-10 -translate-x-1/2 rounded border border-tally-line bg-white px-2.5 py-1.5 text-xs shadow"
             :style="tip && `left:${tip.left}%`">
            <div class="font-semibold text-slate-700" x-text="tip?.label"></div>
            <div class="flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-sm" style="background:var(--series-1)"></span>Sales <span class="ml-auto pl-3 font-mono" x-text="tip?.sales"></span></div>
            <div class="flex items-center gap-1.5"><span class="inline-block h-2 w-2 rounded-sm" style="background:var(--series-2)"></span>Expenses <span class="ml-auto pl-3 font-mono" x-text="tip?.expenses"></span></div>
        </div>
    </div>
    <details class="mt-1 text-xs">
        <summary class="cursor-pointer text-slate-500">Show as table</summary>
        <table class="table mt-1">
            <thead><tr><th>Month</th><th class="text-right">Sales</th><th class="text-right">Expenses</th></tr></thead>
            <tbody>
                @foreach ($months as $m)
                    <tr><td>{{ $m['month']->format('M Y') }}</td><td class="num">{{ Money::format($m['sales']) }}</td><td class="num">{{ Money::format($m['expenses']) }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </details>
</div>
