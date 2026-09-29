<div class="mx-auto max-w-5xl">
    <x-page-header title="Users & roles" :back="route('gateway')" />

    <div class="grid gap-4 md:grid-cols-5">
        <div class="card overflow-x-auto md:col-span-3">
            <table class="table">
                <thead><tr><th>Name</th><th>Role</th><th>Status</th><th></th></tr></thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr class="{{ $editingId === $user->id ? 'bg-brand-50' : 'hover:bg-slate-50' }}">
                            <td>{{ $user->name }}<div class="text-xs text-slate-500">{{ $user->email }}</div></td>
                            <td>{{ $user->role->label() }}</td>
                            <td>{!! $user->is_active ? '<span class="text-green-700">Active</span>' : '<span class="text-slate-400">Disabled</span>' !!}</td>
                            <td class="text-right"><button wire:click="edit({{ $user->id }})" class="text-xs text-brand-700 hover:underline">Alter</button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <form wire:submit="save" class="card space-y-3 self-start p-4 md:col-span-2">
            <h2 class="font-semibold">{{ $editingId ? 'Alter user' : 'Create user' }}</h2>
            <div><label class="label">Name</label><input wire:model="name" class="input">@error('name') <p class="error">{{ $message }}</p> @enderror</div>
            <div><label class="label">Email (login)</label><input type="email" wire:model="email" class="input" autocomplete="off">@error('email') <p class="error">{{ $message }}</p> @enderror</div>
            <div>
                <label class="label">Role</label>
                <select wire:model.live="role" class="input">
                    @foreach ($roles as $r)<option value="{{ $r->value }}">{{ $r->label() }}</option>@endforeach
                </select>
                <p class="mt-1 text-xs text-slate-500">{{ \App\Enums\Role::from($role)->description() }}</p>
                @error('role') <p class="error">{{ $message }}</p> @enderror
            </div>
            <div>
                <label class="label">{{ $editingId ? 'New password (leave blank to keep)' : 'Password' }}</label>
                <input type="password" wire:model="password" class="input" autocomplete="new-password">
                @error('password') <p class="error">{{ $message }}</p> @enderror
            </div>
            <label class="flex items-center gap-2 text-sm"><input type="checkbox" wire:model="is_active"> Active (can sign in)</label>
            <div class="flex gap-2">
                <button class="btn-primary" data-shortcut="Ctrl+A">Save <span class="kbd">Ctrl+A</span></button>
                @if ($editingId)<button type="button" wire:click="resetForm" class="btn-secondary">New</button>@endif
            </div>
        </form>
    </div>
</div>
