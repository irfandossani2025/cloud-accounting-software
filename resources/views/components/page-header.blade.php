@props(['title', 'back' => null, 'subtitle' => null])

<div class="mb-4 flex flex-wrap items-end justify-between gap-3">
    <div>
        <h1 class="text-xl font-semibold text-slate-900">{{ $title }}</h1>
        @if ($subtitle)
            <p class="text-sm text-slate-500">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="no-print flex flex-wrap items-center gap-2">
        {{ $slot }}
        @if ($back)
            <a href="{{ $back }}" wire:navigate data-shortcut="Escape" class="btn-secondary">Back <span class="kbd">Esc</span></a>
        @endif
    </div>
</div>
