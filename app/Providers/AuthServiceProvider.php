<?php

namespace App\Providers;

use App\Models\Listing;
use App\Models\User;
use App\Policies\ListingPolicy;
use App\Policies\UserPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Listing::class => ListingPolicy::class,
        User::class => UserPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();

        Gate::define('access-admin-area', function (User $user): bool {
            return $user->isAdmin() && ! $user->isSuspended();
        });

        Gate::define('view-admin-dashboard', function (User $user): bool {
            return Gate::forUser($user)->allows('access-admin-area');
        });
    }
}
