<div>
    <x-page-header :title="($voucher ? 'Alter ' : '').$type->name" :subtitle="'No. '.$nextNumber" :back="route('gateway')" />

    @if (\App\Support\PeriodLock::isLocked($voucher?->date ?? $date))
        <div class="mb-3 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
            The books are locked up to {{ \App\Support\PeriodLock::lockedUntil()->format('d-M-Y') }}. Entries on or before that date cannot be saved or cancelled.
        </div>
    @endif

    <form wire:submit="save" class="card space-y-4 p-4 sm:p-6">
        <div class="max-w-xs">
            <label class="label">Date</label>
            <input type="date" wire:model.live="date" class="input">
            @error('date') <p class="error">{{ $message }}</p> @enderror
        </div>

        @if ($isStockJournal)
            <div class="space-y-6">
                <div>
                    <h2 class="mb-1 font-semibold">Source <span class="text-sm font-normal text-slate-500">(consumption / transfer out)</span></h2>
                    @include('livewire.inventory.partials.lines', ['side' => 'source', 'lines' => $source, 'showRate' => false, 'showBook' => true, 'qtyLabel' => 'Quantity'])
                </div>
                <div>
                    <h2 class="mb-1 font-semibold">Destination <span class="text-sm font-normal text-slate-500">(production / transfer in)</span></h2>
                    @include('livewire.inventory.partials.lines', ['side' => 'destination', 'lines' => $destination, 'showRate' => true, 'showBook' => false, 'qtyLabel' => 'Quantity'])
                </div>
            </div>
            <p class="text-xs text-slate-500">The same item on both sides is a godown transfer and does not change its cost. A produced item takes the rate you enter, or its current average cost if left blank.</p>
        @else
            <div>
                <h2 class="mb-1 font-semibold">Counted stock</h2>
                @include('livewire.inventory.partials.lines', ['side' => 'source', 'lines' => $source, 'showRate' => false, 'showBook' => true, 'qtyLabel' => 'Actual qty'])
                <p class="mt-1 text-xs text-slate-500">The difference between the actual and book quantity is recorded as a stock adjustment on this date.</p>
            </div>
        @endif
        @error('lines') <p class="error text-sm">{{ $message }}</p> @enderror

        <div>
            <label class="label">Narration</label>
            <textarea wire:model="narration" rows="2" class="input"></textarea>
        </div>

        <div class="flex justify-between">
            <button class="btn-primary" data-shortcut="Ctrl+A" wire:loading.attr="disabled">Accept <span class="kbd">Ctrl+A</span></button>
            @if ($voucher && auth()->user()->can('cancel-vouchers'))
                <button type="button" wire:click="cancelVoucher" wire:confirm="Cancel this voucher?" class="btn-danger">Cancel voucher</button>
            @endif
        </div>
    </form>
</div>
