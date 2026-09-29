<div class="no-print mb-3 flex flex-wrap items-end gap-2">
    @unless ($asOfOnly ?? false)
        <div><label class="label">From</label><input type="date" wire:model.live="from" class="input"></div>
    @endunless
    <div><label class="label">{{ ($asOfOnly ?? false) ? 'As on' : 'To' }}</label><input type="date" wire:model.live="to" class="input"></div>
    @if ($showDetailed ?? true)
        <button type="button" wire:click="toggleDetailed" data-shortcut="Alt+F1" class="btn-secondary">{{ $detailed ? 'Condensed' : 'Detailed' }} <span class="kbd">Alt+F1</span></button>
    @endif
    <button type="button" onclick="window.print()" class="btn-secondary" data-shortcut="Ctrl+P">Print <span class="kbd">Ctrl+P</span></button>
</div>
