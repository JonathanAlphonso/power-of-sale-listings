<?php

use App\Models\User;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt as LivewireVolt;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertStatus(200);
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->withoutTwoFactor()->create();

    $response = LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('profile.edit', absolute: false));

    $this->assertAuthenticated();
});

test('admins are redirected to the dashboard after login', function () {
    $admin = User::factory()->admin()->withoutTwoFactor()->create();

    $response = LivewireVolt::test('auth.login')
        ->set('email', $admin->email)
        ->set('password', 'password')
        ->call('login');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('login');

    $response->assertHasErrors('email');

    $this->assertGuest();
});

test('suspended users can not authenticate', function (): void {
    $user = User::factory()->suspended()->create();

    $response = LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    $response->assertHasErrors([
        'email' => __('Your account has been suspended.'),
    ]);

    $this->assertGuest();
});

test('users forced to rotate their password receive a reset alert on failed login', function (): void {
    $admin = User::factory()->admin()->create();
    $user = User::factory()->create();

    $user->forceFill([
        'password' => 'temporary-password',
        'password_forced_at' => now(),
        'password_forced_by_id' => $admin->id,
    ])->save();

    $response = LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    $response->assertHasErrors([
        'email' => __('Your password has been reset. Check your email to finish signing in.'),
    ]);

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->create();

    $user->forceFill([
        'two_factor_secret' => encrypt('test-secret'),
        'two_factor_recovery_codes' => encrypt(json_encode(['code1', 'code2'])),
        'two_factor_confirmed_at' => now(),
    ])->save();

    $response = LivewireVolt::test('auth.login')
        ->set('email', $user->email)
        ->set('password', 'password')
        ->call('login');

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    $this->assertAuthenticated();

    // Call the logout action directly
    $logout = app(\App\Livewire\Actions\Logout::class);
    $response = $logout();

    // Verify redirect to home (root URL)
    expect(parse_url($response->getTargetUrl(), PHP_URL_PATH) ?? '/')->toBe('/');
    $this->assertGuest();
});
