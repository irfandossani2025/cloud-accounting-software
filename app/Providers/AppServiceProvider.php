<?php

namespace App\Providers;

use App\Database\LegacyMariaDbConnection;
use App\Models\CompanySetting;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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

        View::composer(['layouts.app', 'layouts::app'], function ($view) {
            $view->with('company', rescue(fn () => CompanySetting::current(), null, false));
        });
    }
}
