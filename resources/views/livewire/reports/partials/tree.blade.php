{{-- Recursive group tree. Expects $nodes, $detailed, $depth, $columns ('single' | 'trial') --}}
@foreach ($nodes as $node)
    <tr class="{{ $depth === 0 ? 'font-semibold' : '' }}">
        <td style="padding-left: {{ 0.75 + $depth * 1.25 }}rem">{{ $node['group']->name }}</td>
        @if ($columns === 'trial')
            <td class="num">{{ $node['total'] > 0 ? \App\Support\Money::format($node['total']) : '' }}</td>
            <td class="num">{{ $node['total'] < 0 ? \App\Support\Money::format(-$node['total']) : '' }}</td>
        @else
            <td class="num"><x-amount :value="$node['total']" /></td>
        @endif
    </tr>
    @if ($detailed || $depth === 0)
        @if ($detailed)
            @include('livewire.reports.partials.tree', ['nodes' => $node['children'], 'depth' => $depth + 1])
        @else
            @foreach ($node['children'] as $child)
                <tr class="text-slate-600">
                    <td style="padding-left: {{ 0.75 + ($depth + 1) * 1.25 }}rem">{{ $child['group']->name }}</td>
                    @if ($columns === 'trial')
                        <td class="num">{{ $child['total'] > 0 ? \App\Support\Money::format($child['total']) : '' }}</td>
                        <td class="num">{{ $child['total'] < 0 ? \App\Support\Money::format(-$child['total']) : '' }}</td>
                    @else
                        <td class="num"><x-amount :value="$child['total']" /></td>
                    @endif
                </tr>
            @endforeach
        @endif
        @foreach ($node['ledgers'] as $row)
            @if ($detailed || $depth === 0)
                <tr class="text-slate-600 {{ $detailed ? '' : 'italic' }}">
                    <td style="padding-left: {{ 0.75 + ($depth + 1) * 1.25 }}rem">
                        <a href="{{ route('reports.ledger', $row->ledger) }}" wire:navigate class="hover:underline">{{ $row->ledger->name }}</a>
                    </td>
                    @if ($columns === 'trial')
                        <td class="num">{{ $row->value > 0 ? \App\Support\Money::format($row->value) : '' }}</td>
                        <td class="num">{{ $row->value < 0 ? \App\Support\Money::format(-$row->value) : '' }}</td>
                    @else
                        <td class="num"><x-amount :value="$row->value" /></td>
                    @endif
                </tr>
            @endif
        @endforeach
    @endif
@endforeach
