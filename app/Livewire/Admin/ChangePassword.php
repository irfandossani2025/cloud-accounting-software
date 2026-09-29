<?php

namespace App\Livewire\Admin;

use App\Support\Audit;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Change password')]
class ChangePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function save()
    {
        $this->validate([
            'current_password' => 'required|current_password',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        auth()->user()->update(['password' => $this->password]);
        Audit::log('altered', 'User', auth()->id(), 'Password changed');
        session()->flash('status', 'Password changed.');

        return $this->redirectRoute('gateway', navigate: true);
    }

    public function render()
    {
        return view('livewire.admin.change-password');
    }
}
