<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register MikrotikService as singleton to maintain connection pool
        $this->app->singleton(\App\Services\MikrotikService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers for multi-tenancy
        \App\Models\Service::observe(\App\Observers\ServiceObserver::class);
        \App\Models\Invoice::observe(\App\Observers\InvoiceObserver::class);
        \App\Models\Ticket::observe(\App\Observers\TicketObserver::class);

        // Register Blade directives for permission checking
        \Illuminate\Support\Facades\Blade::if('permission', function (string $permission) {
            return auth()->check() && auth()->user()->hasPermission($permission);
        });

        \Illuminate\Support\Facades\Blade::if('role', function (string ...$roles) {
            return auth()->check() && auth()->user()->hasAnyRole($roles);
        });

        \Illuminate\Support\Facades\Blade::if('superadmin', function () {
            return auth()->check() && auth()->user()->isSuperAdmin();
        });

        \Illuminate\Support\Facades\Blade::if('admin', function () {
            return auth()->check() && auth()->user()->isAdmin();
        });
    }
}
