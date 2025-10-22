<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton('current_company_id', function () {
            return null;
        });

        $this->app->singleton('current_company', function () {
            return null;
        });

        $this->app->singleton('current_user_role', function () {
            return null;
        });
    }

    public function boot(): void
    {
        //
    }
}
