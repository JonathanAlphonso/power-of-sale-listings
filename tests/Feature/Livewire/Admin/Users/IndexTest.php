<?php

use App\Models\User;
use Livewire\Volt\Volt;

test('guests are redirected from the users index', function (): void {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('authenticated users can browse, update, and remove accounts', function (): void {
    $admin = User::factory()->create([
        'name' => 'Admin Example',
        'email' => 'admin@example.com',
    ]);

    $teammate = User::factory()->create([
        'name' => 'Casey Manager',
        'email' => 'casey@example.com',
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.users.index')
        ->assertSee($admin->name)
        ->assertSee($teammate->name)
        ->set('search', 'Casey')
        ->assertSee('casey@example.com')
        ->assertDontSee('admin@example.com')
        ->set('search', '')
        ->call('selectUser', $teammate->id)
        ->set('form.name', 'Casey Updated')
        ->set('form.email', 'casey.updated@example.com')
        ->call('saveUser')
        ->assertHasNoErrors()
        ->assertDispatched('user-saved');

    expect($teammate->refresh()->name)->toBe('Casey Updated');
    expect($teammate->email)->toBe('casey.updated@example.com');

    $component
        ->call('confirmDeleteUser')
        ->assertSet('confirmingDeletion', true)
        ->call('deleteUser')
        ->assertDispatched('user-deleted')
        ->assertSet('confirmingDeletion', false);

    expect(User::query()->whereKey($teammate->id)->exists())->toBeFalse();
});

test('administrators cannot delete their own accounts via the admin workspace', function (): void {
    $admin = User::factory()->create([
        'name' => 'Self Admin',
        'email' => 'self@example.com',
    ]);

    $this->actingAs($admin);

    Volt::test('admin.users.index')
        ->call('selectUser', $admin->id)
        ->call('confirmDeleteUser')
        ->assertHasErrors(['form.email' => __('You cannot delete your own account from this workspace.')])
        ->assertSet('confirmingDeletion', false);
});
