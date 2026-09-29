@props(['list', 'model', 'placeholder' => ''])
{{-- Type a code or words; pick from the official Oman list. Binds to a Livewire property. --}}
<div x-data="{
        open: false, results: [], timer: null,
        search(q) {
            clearTimeout(this.timer);
            if (q.trim().length < 2) { this.results = []; this.open = false; return; }
            this.timer = setTimeout(async () => {
                const r = await fetch('{{ route('codes.search', $list) }}?q=' + encodeURIComponent(q), { headers: { Accept: 'application/json' } });
                this.results = await r.json();
                this.open = this.results.length > 0;
            }, 200);
        },
        pick(code) {
            this.$refs.input.value = code;
            this.$refs.input.dispatchEvent(new Event('input', { bubbles: true }));
            this.$refs.input.dispatchEvent(new Event('change', { bubbles: true }));
            this.open = false;
        },
    }" class="relative" x-on:click.outside="open = false">
    <input x-ref="input" wire:model.blur="{{ $model }}" x-on:input="search($event.target.value)" x-on:keydown.escape.stop="open = false"
           {{ $attributes->merge(['class' => 'input font-mono']) }} placeholder="{{ $placeholder }}" autocomplete="off">
    <ul x-show="open" x-cloak class="absolute z-20 mt-1 max-h-64 w-[32rem] max-w-[90vw] overflow-y-auto rounded border border-tally-line bg-white text-xs shadow-lg">
        <template x-for="row in results" :key="row.code">
            <li><button type="button" x-on:click="pick(row.code)" class="flex w-full gap-2 px-2 py-1 text-left hover:bg-tally-select">
                <span class="font-mono font-semibold" x-text="row.code"></span><span class="text-slate-600" x-text="row.description"></span>
            </button></li>
        </template>
    </ul>
</div>
