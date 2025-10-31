<?php

namespace App\Http\Responses;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): RedirectResponse
    {
        $redirectTo = $this->determineRedirectRoute($request);

        return redirect()->intended($redirectTo);
    }

    protected function determineRedirectRoute(Request $request): string
    {
        $user = $request->user();

        if ($user instanceof User && $user->isAdmin()) {
            return route('dashboard', absolute: false);
        }

        return route('profile.edit', absolute: false);
    }
}
