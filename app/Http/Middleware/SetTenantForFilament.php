<?php

namespace App\Http\Middleware;

use App\Models\Company;
use App\Models\Customer;
use App\Models\Product;
use App\Models\SaleOrder;
use App\Models\StockMovement;
use App\Models\CustomFieldDef;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * SetTenantForFilament Middleware
 *
 * Resolves company context for Filament admin panel
 * - Uses session-based company selection
 * - Applies global scopes to tenant-scoped models
 * - Provides company switcher functionality
 */
class SetTenantForFilament
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth()->user();

        if (!$user) {
            return $next($request);
        }

        // Get current company ID from session, or pick first available
        $companyId = session('current_company_id');

        if (!$companyId) {
            // Get first company user has access to
            $firstCompany = $user->companies()->where('is_active', true)->first();

            if ($firstCompany) {
                $companyId = $firstCompany->id;
                session(['current_company_id' => $companyId]);
            }
        }

        // Verify user still has access to the selected company
        if ($companyId) {
            $company = $user->companies()
                ->where('companies.id', $companyId)
                ->where('is_active', true)
                ->first();

            if (!$company) {
                // Company not accessible, clear and pick first available
                session()->forget('current_company_id');
                $company = $user->companies()->where('is_active', true)->first();

                if ($company) {
                    $companyId = $company->id;
                    session(['current_company_id' => $companyId]);
                }
            }

            if ($company) {
                // Store in app container for easy access
                app()->instance('current_company_id', $companyId);
                app()->instance('current_company', $company);
                app()->instance('current_user_role', $company->pivot->role);

                // Apply global scopes to all tenant-scoped models
                $this->applyTenantScopes($companyId);
            }
        }

        return $next($request);
    }

    /**
     * Apply tenant global scopes to all models
     */
    protected function applyTenantScopes(int $companyId): void
    {
        // Products
        Product::addGlobalScope('tenant', function (Builder $builder) use ($companyId) {
            $builder->where('company_id', $companyId);
        });

        // Customers
        Customer::addGlobalScope('tenant', function (Builder $builder) use ($companyId) {
            $builder->where('company_id', $companyId);
        });

        // Sale Orders
        SaleOrder::addGlobalScope('tenant', function (Builder $builder) use ($companyId) {
            $builder->where('company_id', $companyId);
        });

        // Stock Movements
        StockMovement::addGlobalScope('tenant', function (Builder $builder) use ($companyId) {
            $builder->where('company_id', $companyId);
        });

        // Custom Field Definitions
        CustomFieldDef::addGlobalScope('tenant', function (Builder $builder) use ($companyId) {
            $builder->where('company_id', $companyId);
        });
    }
}
