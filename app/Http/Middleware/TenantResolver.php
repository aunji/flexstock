<?php

namespace App\Http\Middleware;

use App\Models\Company;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TenantResolver
{
    public function handle(Request $request, Closure $next): Response
    {
        $companySlug = $request->route('company');

        if (!$companySlug) {
            return response()->json(['error' => 'Company slug is required'], 400);
        }

        $company = Company::where('slug', $companySlug)
            ->where('is_active', true)
            ->first();

        if (!$company) {
            return response()->json(['error' => 'Company not found or inactive'], 404);
        }

        // Check if user has access to this company
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        $membership = $user->companies()->where('companies.id', $company->id)->first();
        if (!$membership) {
            return response()->json(['error' => 'Access denied to this company'], 403);
        }

        // Set current company in app container
        app()->instance('current_company_id', $company->id);
        app()->instance('current_company', $company);
        app()->instance('current_user_role', $membership->pivot->role);

        return $next($request);
    }
}
