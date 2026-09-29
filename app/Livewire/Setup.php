<?php

namespace App\Livewire;

use App\Models\CompanySetting;
use App\Models\User;
use Database\Seeders\ChartOfAccountsSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * First-run installer. The host has no SSH, so this page can also run the migrations.
 * It is locked once an administrator exists.
 */
#[Title('Setup')]
class Setup extends Component
{
    public bool $databaseReady = false;

    #[Locked]
    public bool $authorized = false;

    public string $companyName = '';

    public string $companyNameAr = '';

    public string $address = '';

    public string $vatin = '';

    public string $crNumber = '';

    public string $financialYearStart = '';

    public string $booksBeginFrom = '';

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount()
    {
        // Optional SETUP_TOKEN in .env: the installer then only opens at /setup?token=...
        $token = (string) config('app.setup_token');
        abort_unless($token === '' || hash_equals($token, (string) request()->query('token')), 403);
        $this->authorized = true;

        $this->databaseReady = $this->databaseReady();

        if ($this->databaseReady && User::query()->exists()) {
            return redirect()->route('login');
        }

        $this->financialYearStart = now()->startOfYear()->toDateString();
        $this->booksBeginFrom = $this->financialYearStart;
    }

    public function install(): void
    {
        abort_if(! $this->authorized || ($this->databaseReady() && User::query()->exists()), 403);

        Artisan::call('migrate', ['--force' => true]);
        $this->databaseReady = $this->databaseReady();
    }

    public function save()
    {
        abort_if(! $this->authorized || ! $this->databaseReady() || User::query()->exists(), 403);

        $this->validate([
            'companyName' => 'required|string|max:255',
            'companyNameAr' => 'nullable|string|max:255',
            'vatin' => ['nullable', 'string', 'max:30'],
            'crNumber' => 'nullable|string|max:50',
            'financialYearStart' => 'required|date',
            'booksBeginFrom' => 'required|date|after_or_equal:financialYearStart',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = DB::transaction(function () {
            (new ChartOfAccountsSeeder)->run();

            CompanySetting::query()->create([
                'name' => $this->companyName,
                'name_ar' => $this->companyNameAr ?: null,
                'address' => $this->address ?: null,
                'vatin' => $this->vatin ?: null,
                'cr_number' => $this->crNumber ?: null,
                'financial_year_start' => $this->financialYearStart,
                'books_begin_from' => $this->booksBeginFrom,
                'vat_registered' => $this->vatin !== '',
            ]);

            return User::query()->create([
                'name' => $this->name,
                'email' => $this->email,
                'password' => $this->password,
                'role' => 'admin',
            ]);
        });

        Auth::login($user);

        return redirect()->route('gateway');
    }

    private function databaseReady(): bool
    {
        try {
            return Schema::hasTable('company_settings') && Schema::hasTable('users');
        } catch (\Throwable) {
            return false;
        }
    }
}
