{{-- $side: 'source' | 'destination', $lines, $showRate, $showBook, $qtyLabel --}}
<table class="table">
    <thead>
        <tr>
            <th class="min-w-56">Item</th>
            <th class="w-44">Godown</th>
            @if ($showBook)<th class="w-32 text-right">In books</th>@endif
            <th class="w-32 text-right">{{ $qtyLabel }}</th>
            @if ($showRate)<th class="w-32 text-right">Rate</th>@endif
            <th class="w-8"></th>
        </tr>
    </thead>
    <tbody>
        @foreach ($lines as $i => $line)
            <tr wire:key="{{ $side }}-{{ $i }}">
                <td>
                    <select wire:model.live="{{ $side }}.{{ $i }}.stock_item_id" class="input">
                        <option value="">— Item —</option>
                        @foreach ($items as $item)<option value="{{ $item->id }}">{{ $item->name }}</option>@endforeach
                    </select>
                </td>
                <td>
                    <select wire:model.live="{{ $side }}.{{ $i }}.godown_id" class="input">
                        @foreach ($godowns as $g)<option value="{{ $g->id }}">{{ $g->name }}</option>@endforeach
                    </select>
                </td>
                @if ($showBook)<td class="num text-slate-500">{{ $book[$i] ?? '' }}</td>@endif
                <td><input wire:model.blur="{{ $side }}.{{ $i }}.quantity" class="input text-right font-mono" inputmode="decimal"></td>
                @if ($showRate)<td><input wire:model.blur="{{ $side }}.{{ $i }}.rate" class="input text-right font-mono" placeholder="avg cost"></td>@endif
                <td><button type="button" wire:click="removeLine('{{ $side }}', {{ $i }})" class="text-slate-400 hover:text-red-600">✕</button></td>
            </tr>
        @endforeach
    </tbody>
</table>
<button type="button" wire:click="addLine('{{ $side }}')" class="mt-2 text-xs text-brand-700 hover:underline">+ Item</button>
