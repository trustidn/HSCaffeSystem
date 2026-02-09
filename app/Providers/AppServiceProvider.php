<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Order;
use App\Models\User;
use App\Observers\OrderObserver;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Order::observe(OrderObserver::class);

        $this->configureDefaults();
        $this->configureRateLimiters();
        $this->configureGates();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null
        );
    }

    /**
     * Configure rate limiters for public endpoints.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('public-order', function (Request $request): Limit {
            return Limit::perMinute(30)->by($request->ip());
        });

        RateLimiter::for('public-track', function (Request $request): Limit {
            return Limit::perMinute(60)->by($request->ip());
        });
    }

    /**
     * Configure authorization gates.
     */
    protected function configureGates(): void
    {
        Gate::define('manage-tenants', function (User $user): bool {
            return $user->isSuperAdmin();
        });

        Gate::define('manage-menu', function (User $user): bool {
            return $user->hasRole(UserRole::SuperAdmin, UserRole::Owner, UserRole::Manager);
        });

        Gate::define('manage-tables', function (User $user): bool {
            return $user->hasRole(UserRole::SuperAdmin, UserRole::Owner, UserRole::Manager);
        });

        Gate::define('manage-staff', function (User $user): bool {
            return $user->hasRole(UserRole::SuperAdmin, UserRole::Owner, UserRole::Manager);
        });

        Gate::define('access-pos', function (User $user): bool {
            return $user->role->canAccessPos();
        });

        Gate::define('access-kitchen', function (User $user): bool {
            return $user->role->canAccessKitchen();
        });

        Gate::define('view-reports', function (User $user): bool {
            return $user->hasRole(UserRole::SuperAdmin, UserRole::Owner, UserRole::Manager);
        });

        Gate::define('manage-inventory', function (User $user): bool {
            return $user->hasRole(UserRole::SuperAdmin, UserRole::Owner, UserRole::Manager);
        });
    }
}
