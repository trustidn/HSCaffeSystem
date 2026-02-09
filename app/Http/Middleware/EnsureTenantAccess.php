<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantAccess
{
    /**
     * Ensure the authenticated user has an active tenant.
     *
     * Super admins bypass this check.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return redirect()->route('login');
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (! $user->tenant_id || ! $user->tenant?->is_active) {
            abort(403, 'Your cafe account is inactive or not configured.');
        }

        return $next($request);
    }
}
