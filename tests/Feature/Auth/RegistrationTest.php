<?php

use Laravel\Fortify\Features;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    config()->set('fortify.features', [
        Features::registration(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
        ]),
    ]);
});

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
