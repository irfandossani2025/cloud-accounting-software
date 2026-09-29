<div class="mx-auto max-w-sm">
    <x-page-header title="Change password" :back="route('gateway')" />
    <form data-enter-submits wire:submit="save" class="card space-y-3 p-6">
        <div><label class="label">Current password</label><input type="password" wire:model="current_password" class="input" autocomplete="current-password">@error('current_password') <p class="error">{{ $message }}</p> @enderror</div>
        <div><label class="label">New password</label><input type="password" wire:model="password" class="input" autocomplete="new-password">@error('password') <p class="error">{{ $message }}</p> @enderror</div>
        <div><label class="label">Confirm new password</label><input type="password" wire:model="password_confirmation" class="input" autocomplete="new-password"></div>
        <button class="btn-primary">Change password</button>
    </form>
</div>
