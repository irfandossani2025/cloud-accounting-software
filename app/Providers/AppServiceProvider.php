<?php

namespace App\Providers;

use App\Database\LegacyMariaDbConnection;
use App\Enums\Role;
use App\Http\Middleware\EnsureUserIsActive;
use App\Models\CompanySetting;
use App\Models\Ledger;
use App\Models\StockItem;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherType;
use App\Support\GatewayMenu;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Production runs MariaDB 10.1 through the "mysql" driver.
        Connection::resolverFor('mysql', function ($connection, $database, $prefix, $config) {
            return new LegacyMariaDbConnection($connection, $database, $prefix, $config);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // MariaDB 10.1 limits utf8mb4 index keys to 767 bytes.
        Schema::defaultStringLength(191);

        Model::shouldBeStrict(! $this->app->isProduction());

        $this->definePermissions();
        Livewire::addPersistentMiddleware([EnsureUserIsActive::class]);

        View::composer(['layouts.app', 'layouts::app'], function ($view) {
            $view->with('company', rescue(fn () => CompanySetting::current(), null, false));

            $user = auth()->user();
            $view->with('voucherKeys', $user ? VoucherType::query()->where('is_active', true)->where('is_reserved', true)->orderBy('id')->get() : collect());
            $view->with('goTo', $user ? rescue(fn () => array_merge(
                GatewayMenu::destinations($user),
                Ledger::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($l) => ['label' => $l->name, 'group' => 'Ledger', 'url' => route('reports.ledger', $l)])->all(),
                StockItem::query()->orderBy('name')->get(['id', 'name'])
                    ->map(fn ($i) => ['label' => $i->name, 'group' => 'Stock item', 'url' => route('reports.stock-item', $i)])->all(),
            ), [], false) : []);
        });
    }

    /**
     * Role permissions. Admin: everything. Accountant: all accounting work. Data entry: vouchers only
     * (and only their own for alteration). Viewer: reports.
     */
    private function definePermissions(): void
    {
        Gate::define('admin', fn (User $user) => $user->hasRole(Role::Admin));
        Gate::define('manage-masters', fn (User $user) => $user->hasRole(Role::Admin, Role::Accountant));
        Gate::define('reconcile', fn (User $user) => $user->hasRole(Role::Admin, Role::Accountant));
        Gate::define('cancel-vouchers', fn (User $user) => $user->hasRole(Role::Admin, Role::Accountant));
        Gate::define('enter-vouchers', fn (User $user) => $user->hasRole(Role::Admin, Role::Accountant, Role::DataEntry));
        Gate::define('alter-voucher', fn (User $user, Voucher $voucher) => $user->hasRole(Role::Admin, Role::Accountant)
            || ($user->hasRole(Role::DataEntry) && $voucher->created_by === $user->id));
    }
}
