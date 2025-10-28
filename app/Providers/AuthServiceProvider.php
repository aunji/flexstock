<?php

namespace App\Providers;

use App\Models\Customer;
use App\Models\CustomFieldDef;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\StockMovement;
use App\Policies\CustomerPolicy;
use App\Policies\CustomFieldDefPolicy;
use App\Policies\ProductPolicy;
use App\Policies\SaleOrderPolicy;
use App\Policies\StockMovementPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Product::class => ProductPolicy::class,
        Customer::class => CustomerPolicy::class,
        SaleOrder::class => SaleOrderPolicy::class,
        StockMovement::class => StockMovementPolicy::class,
        CustomFieldDef::class => CustomFieldDefPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        // Define gates for company-level access
        Gate::define('company.view', function ($user) {
            // All authenticated users can view their companies
            return $user !== null;
        });

        Gate::define('company.write', function ($user) {
            $role = app('current_user_role');
            // Admin and Cashier can write
            return in_array($role, ['admin', 'cashier']);
        });

        Gate::define('company.admin', function ($user) {
            $role = app('current_user_role');
            // Only Admin has admin access
            return $role === 'admin';
        });
    }
}
