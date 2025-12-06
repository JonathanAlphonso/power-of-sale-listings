<?php

use App\Models\User;
use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

test('registration screen can be rendered', function (): void {
    $response = $this->get(route('register'));

    $response->assertStatus(200);
});

test('new users can register', function (): void {
    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response
        ->assertHasNoErrors()
        ->assertRedirect(route('profile.edit', absolute: false));

    $this->assertAuthenticated();

    $user = User::where('email', 'test@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Test User');
});

test('registration validates required fields', function (): void {
    $response = Volt::test('auth.register')
        ->set('name', '')
        ->set('email', '')
        ->set('password', '')
        ->set('password_confirmation', '')
        ->call('register');

    $response->assertHasErrors(['name', 'email', 'password']);
});

test('registration validates email format', function (): void {
    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'invalid-email')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response->assertHasErrors(['email']);
});

test('registration validates password confirmation', function (): void {
    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'different-password')
        ->call('register');

    $response->assertHasErrors(['password']);
});

test('registration prevents duplicate emails', function (): void {
    User::factory()->create(['email' => 'existing@example.com']);

    $response = Volt::test('auth.register')
        ->set('name', 'Test User')
        ->set('email', 'existing@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $response->assertHasErrors(['email']);
});

test('registration is blocked when the Fortify registration feature is disabled', function (): void {
    config()->set('fortify.features', [
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ]);

    $response = $this->get(route('register'));
    $response->assertStatus(200);

    $component = Volt::test('auth.register')
        ->set('name', 'Disabled User')
        ->set('email', 'disabled@example.com')
        ->set('password', 'password')
        ->set('password_confirmation', 'password')
        ->call('register');

    $component->assertStatus(404);
});
