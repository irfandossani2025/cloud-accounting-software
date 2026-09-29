<div class="mx-auto mt-16 max-w-sm">
    <form wire:submit="login" class="card space-y-4 p-6">
        <h1 class="text-lg font-semibold">Log in</h1>
        <div>
            <label class="label">Email</label>
            <input type="email" wire:model="email" class="input" autofocus autocomplete="username">
            @error('email') <p class="error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label class="label">Password</label>
            <input type="password" wire:model="password" class="input" autocomplete="current-password">
            @error('password') <p class="error">{{ $message }}</p> @enderror
        </div>
        <label class="flex items-center gap-2 text-sm">
            <input type="checkbox" wire:model="remember"> Remember me
        </label>
        <button class="btn-primary w-full justify-center">Log in</button>
    </form>
</div>
