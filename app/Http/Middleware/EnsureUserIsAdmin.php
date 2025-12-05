<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            abort(403);
        }

        if ($user->isSuspended()) {
            abort(403);
        }

        if (! $user->isAdmin()) {
            $hasActiveAdmins = User::query()
                ->admins()
                ->active()
                ->exists();

            if ($hasActiveAdmins) {
                abort(403);
            }

            if (config('app.debug') && config('app.env') === 'local') {
                $user->forceFill([
                    'role' => UserRole::Admin,
                ])->save();
            } else {
                abort(403);
            }
        }

        Gate::authorize('access-admin-area');

        return $next($request);
    }
}
