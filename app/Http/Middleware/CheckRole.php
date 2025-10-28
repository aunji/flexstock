<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * CheckRole Middleware - Role-Based Access Control
 *
 * Usage: Route::middleware(['auth:sanctum', 'tenant', 'role:admin,cashier'])
 *
 * Roles:
 * - admin: Full access to all operations
 * - cashier: Can create/confirm orders, manage payments
 * - viewer: Read-only access
 */
class CheckRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles Allowed roles (admin, cashier, viewer)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Authentication required',
            ], 401);
        }

        // Get company from request (set by TenantResolver middleware)
        $companyId = $request->get('company_id');

        if (!$companyId) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Company context required',
            ], 403);
        }

        // Get user's role for this company
        $companyUser = $user->companies()
            ->where('companies.id', $companyId)
            ->first();

        if (!$companyUser) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => 'Access denied to this company',
            ], 403);
        }

        $userRole = $companyUser->pivot->role;

        // Check if user has one of the required roles
        if (!in_array($userRole, $roles)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => "This action requires one of the following roles: " . implode(', ', $roles),
            ], 403);
        }

        // Attach role to request for later use
        $request->merge(['user_role' => $userRole]);

        return $next($request);
    }
}
