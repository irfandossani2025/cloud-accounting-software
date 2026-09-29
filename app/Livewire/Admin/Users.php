<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Users')]
class Users extends Component
{
    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $email = '';

    public string $role = 'data_entry';

    public bool $is_active = true;

    public string $password = '';

    public function edit(int $id): void
    {
        $user = User::query()->findOrFail($id);
        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->is_active = $user->is_active;
        $this->password = '';
        $this->resetErrorBag();
    }

    public function resetForm(): void
    {
        $this->reset('editingId', 'name', 'email', 'password');
        $this->role = Role::DataEntry->value;
        $this->is_active = true;
        $this->resetErrorBag();
    }

    public function save(): void
    {
        $this->authorize('admin');

        $this->validate([
            'name' => 'required|max:255',
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => [$this->editingId ? 'nullable' : 'required', Password::min(8)],
        ]);

        // Never leave the company without an active administrator.
        if ($this->editingId === auth()->id() && ($this->role !== Role::Admin->value || ! $this->is_active)) {
            $this->addError('role', 'You cannot remove your own administrator access.');

            return;
        }

        $data = ['name' => $this->name, 'email' => $this->email, 'role' => $this->role, 'is_active' => $this->is_active];
        if ($this->password !== '') {
            $data['password'] = $this->password;
        }

        User::query()->updateOrCreate(['id' => $this->editingId], $data);
        session()->flash('status', "User {$this->email} saved.");
        $this->resetForm();
    }

    public function render()
    {
        return view('livewire.admin.users', [
            'users' => User::query()->orderBy('name')->get(),
            'roles' => Role::cases(),
        ]);
    }
}
