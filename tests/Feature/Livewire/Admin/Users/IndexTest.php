<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Livewire\Volt\Volt;

test('guests are redirected from the users index', function (): void {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));
});

test('subscribers cannot access the admin users index', function (): void {
    User::factory()->admin()->create();

    $subscriber = User::factory()->create();

    $this->actingAs($subscriber)
        ->get(route('admin.users.index'))
        ->assertForbidden();
});

test('admins can browse, update roles, and remove subscriber accounts', function (): void {
    $admin = User::factory()->admin()->create([
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
        ->set('form.role', UserRole::Admin->value)
        ->call('saveUser')
        ->assertHasNoErrors()
        ->assertDispatched('user-saved');

    expect($teammate->refresh()->name)->toBe('Casey Updated');
    expect($teammate->email)->toBe('casey.updated@example.com');
    expect($teammate->role)->toBe(UserRole::Admin);

    $component
        ->call('selectUser', $teammate->id)
        ->call('confirmDeleteUser')
        ->assertSet('confirmingDeletion', true)
        ->call('deleteUser')
        ->assertDispatched('user-deleted')
        ->assertSet('confirmingDeletion', false);

    expect(User::query()->whereKey($teammate->id)->exists())->toBeFalse();
});

test('administrators cannot delete their own accounts via the admin workspace', function (): void {
    $admin = User::factory()->admin()->create([
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

test('admins can invite new subscribers and send reset links', function (): void {
    Notification::fake();

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin);

    Volt::test('admin.users.index')
        ->set('inviteForm.name', 'Invited User')
        ->set('inviteForm.email', 'invited@example.com')
        ->set('inviteForm.role', UserRole::Subscriber->value)
        ->call('inviteUser')
        ->assertHasNoErrors()
        ->assertDispatched('user-invited');

    $invited = User::query()->where('email', 'invited@example.com')->first();

    expect($invited)->not()->toBeNull();
    expect($invited->role)->toBe(UserRole::Subscriber);
    expect($invited->invited_by_id)->toBe($admin->id);
    expect($invited->suspended_at)->toBeNull();

    Notification::assertSentTo($invited, ResetPassword::class);
});

test('admins can suspend and reactivate users', function (): void {
    $admin = User::factory()->admin()->create();
    $subscriber = User::factory()->create();

    $this->actingAs($admin);

    $component = Volt::test('admin.users.index')
        ->call('selectUser', $subscriber->id)
        ->call('toggleSuspension')
        ->assertDispatched('user-suspended');

    expect($subscriber->refresh()->isSuspended())->toBeTrue();

    $component
        ->call('toggleSuspension')
        ->assertDispatched('user-activated');

    expect($subscriber->refresh()->isSuspended())->toBeFalse();
});

test('the final active admin cannot be demoted or suspended via the workspace', function (): void {
    $admin = User::factory()->admin()->create([
        'name' => 'Primary Admin',
        'email' => 'primary@example.com',
    ]);

    $this->actingAs($admin);

    $component = Volt::test('admin.users.index')
        ->call('selectUser', $admin->id)
        ->set('form.role', UserRole::Subscriber->value)
        ->call('saveUser')
        ->assertHasErrors(['form.role' => __('At least one active admin is required.')]);

    $component
        ->call('toggleSuspension')
        ->assertHasErrors(['form.email' => __('You cannot suspend your own account.')]);
});

test('the first authenticated user is promoted to admin when no admins exist', function (): void {
    $user = User::factory()->create([
        'name' => 'First User',
        'email' => 'first@example.com',
    ]);

    expect($user->role)->toBe(UserRole::Subscriber);

    $this->actingAs($user)
        ->get(route('admin.users.index'))
        ->assertOk();

    expect($user->refresh()->role)->toBe(UserRole::Admin);
});
