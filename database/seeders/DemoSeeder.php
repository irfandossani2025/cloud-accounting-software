<?php

namespace Database\Seeders;

use App\Models\CompanySetting;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Local development only: php artisan db:seed --class=DemoSeeder
 * Login: admin@example.test / password
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ChartOfAccountsSeeder::class);

        CompanySetting::query()->firstOrCreate([], [
            'name' => 'Demo Trading LLC',
            'address' => 'Muscat, Sultanate of Oman',
            'vatin' => 'OM1100000000',
            'financial_year_start' => now()->startOfYear(),
            'books_begin_from' => now()->startOfYear(),
        ]);

        User::query()->firstOrCreate(['email' => 'admin@example.test'], [
            'name' => 'Admin',
            'password' => 'password',
            'role' => 'admin',
        ]);
    }
}
