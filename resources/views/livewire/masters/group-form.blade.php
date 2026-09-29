<div class="mx-auto max-w-xl">
    <x-page-header :title="$group ? 'Alter group' : 'Create group'" :back="route('groups.index')" />

    <form wire:submit="save" class="card space-y-4 p-6">
        <div>
            <label class="label">Name</label>
            <input wire:model="name" class="input" autofocus>
            @error('name') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">Name (Arabic)</label>
            <input wire:model="name_ar" class="input" dir="rtl">
        </div>
        @if (! $group?->is_reserved)
            <div>
                <label class="label">Under</label>
                <select wire:model="parent_id" class="input">
                    <option value="">— Select group —</option>
                    @foreach ($parents as $parent)
                        <option value="{{ $parent->id }}">{{ $parent->name }}</option>
                    @endforeach
                </select>
                @error('parent_id') <p class="error">{{ $message }}</p> @enderror
            </div>
        @else
            <p class="text-sm text-slate-500">Predefined group under <strong>{{ $group->parent?->name ?? 'Primary' }}</strong> — it can be renamed but not moved or deleted.</p>
        @endif

        <div class="flex justify-between">
            <button class="btn-primary" data-shortcut="Ctrl+A">Save <span class="kbd">Ctrl+A</span></button>
            @if ($group && ! $group->is_reserved)
                <button type="button" wire:click="delete" wire:confirm="Delete this group?" class="btn-danger">Delete</button>
            @endif
        </div>
    </form>
</div>
