<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
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

        if ($user->isAdmin()) {
            return $next($request);
        }

        $hasActiveAdmins = User::query()
            ->admins()
            ->active()
            ->exists();

        if (! $hasActiveAdmins) {
            $user->forceFill([
                'role' => UserRole::Admin,
            ])->save();

            return $next($request);
        }

        abort(403);
    }
}
