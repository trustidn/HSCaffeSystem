<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureActiveSubscription
{
    /**
     * Ensure the tenant has an active subscription.
     *
     * Super admins and the dashboard/settings routes bypass this check.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || $user->isSuperAdmin()) {
            return $next($request);
        }

        if (! $user->tenant_id) {
            return $next($request);
        }

        $tenant = $user->tenant;

        if ($tenant && ! $tenant->hasActiveSubscription()) {
            return redirect()->route('dashboard')
                ->with('subscription_expired', true);
        }

        return $next($request);
    }
}
