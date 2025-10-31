<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;

test('password can be updated', function () {
    $admin = User::factory()->admin()->create();

    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $user->forceFill([
        'password_forced_at' => now()->subDay(),
        'password_forced_by_id' => $admin->id,
    ])->save();

    $this->actingAs($user);

    $response = Volt::test('settings.password')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasNoErrors();

    $user->refresh();

    expect(Hash::check('new-password', $user->password))->toBeTrue();
    expect($user->password_forced_at)->toBeNull();
    expect($user->password_forced_by_id)->toBeNull();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $this->actingAs($user);

    $response = Volt::test('settings.password')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword');

    $response->assertHasErrors(['current_password']);
});
