<?php

namespace App\Providers;

use App\Http\Responses\LoginResponse;
use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LoginResponseContract::class, LoginResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Fortify::verifyEmailView(fn () => view('livewire.auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('livewire.auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('livewire.auth.confirm-password'));

        Fortify::authenticateUsing(function (Request $request): ?User {
            $email = (string) $request->string('email');
            $password = (string) $request->string('password');

            /** @var \App\Models\User|null $user */
            $user = User::query()
                ->where('email', $email)
                ->first();

            if ($user === null) {
                return null;
            }

            if ($user->isSuspended()) {
                throw ValidationException::withMessages([
                    Fortify::username() => __('Your account has been suspended.'),
                ]);
            }

            if (! Hash::check($password, $user->password)) {
                if ($user->password_forced_at !== null) {
                    throw ValidationException::withMessages([
                        Fortify::username() => __('Your password has been reset. Check your email to finish signing in.'),
                    ]);
                }

                return null;
            }

            return $user;
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });
    }
}
