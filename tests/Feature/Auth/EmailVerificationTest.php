<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('email verification screen can be rendered', function (): void {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertStatus(200);
});

test('email verification screen redirects if already verified', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertRedirect(route('dashboard'));
});

test('email can be verified with valid signature', function (): void {
    $user = User::factory()->unverified()->create();

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('profile.edit', absolute: false).'?verified=1');
});

test('email is not verified with invalid hash', function (): void {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email verification fails with expired link', function (): void {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->subMinutes(1),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $response = $this->actingAs($user)->get($verificationUrl);

    $response->assertStatus(403);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('email verification fails with tampered signature', function (): void {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $tamperedUrl = str_replace('signature=', 'signature=tampered', $verificationUrl);

    $response = $this->actingAs($user)->get($tamperedUrl);

    $response->assertStatus(403);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('already verified user visiting verification link is redirected without firing event again', function (): void {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect(route('profile.edit', absolute: false).'?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertNotDispatched(Verified::class);
});

test('resend verification email button sends notification', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    Livewire\Volt\Volt::test('auth.verify-email')
        ->call('sendVerification')
        ->assertHasNoErrors();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('verification notification contains correct URL path', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();

    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, function ($notification, $channels) use ($user) {
        $mailMessage = $notification->toMail($user);
        $actionUrl = $mailMessage->actionUrl;

        expect($actionUrl)->toContain('/email/verify/');
        expect($actionUrl)->toContain((string) $user->id);
        expect($actionUrl)->toContain(sha1($user->email));
        expect($actionUrl)->toContain('signature=');

        return true;
    });
});

test('user can logout from verification page', function (): void {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user);

    Livewire\Volt\Volt::test('auth.verify-email')
        ->call('logout')
        ->assertRedirect('/');

    $this->assertGuest();
});

test('unverified user is redirected to verification notice on protected routes', function (): void {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertRedirect(route('verification.notice'));
});

test('verified user can access protected routes', function (): void {
    $user = User::factory()->admin()->create([
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($user)->get(route('dashboard'));

    $response->assertStatus(200);
});

test('verification URL uses correct route path', function (): void {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    // URL should use /email/verify/ path (matching the route)
    expect($verificationUrl)->toContain('/email/verify/');
});

test('email can be verified using actual notification URL end-to-end', function (): void {
    $user = User::factory()->unverified()->create();
    $capturedUrl = null;

    // Capture the actual URL from the notification
    Notification::fake();
    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, function ($notification) use ($user, &$capturedUrl) {
        $mailMessage = $notification->toMail($user);
        $capturedUrl = $mailMessage->actionUrl;

        return true;
    });

    // Stop faking to allow real request
    Notification::swap(app('events'));

    Event::fake();

    // Use the actual URL from the notification
    $response = $this->actingAs($user)->get($capturedUrl);

    Event::assertDispatched(Verified::class);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    $response->assertRedirect(route('profile.edit', absolute: false).'?verified=1');
});

test('notification URL does not contain HTML entities', function (): void {
    Notification::fake();

    $user = User::factory()->unverified()->create();
    $user->sendEmailVerificationNotification();

    Notification::assertSentTo($user, VerifyEmail::class, function ($notification) use ($user) {
        $mailMessage = $notification->toMail($user);
        $actionUrl = $mailMessage->actionUrl;

        // URL should not contain HTML-encoded ampersands
        expect($actionUrl)->not->toContain('&amp;');
        // URL should contain proper query string separators
        expect($actionUrl)->toContain('?');
        expect($actionUrl)->toContain('&');

        return true;
    });
});

test('verification fails when user is not authenticated', function (): void {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    // Not logged in - should redirect to login
    $response = $this->get($verificationUrl);

    $response->assertRedirect(route('login'));
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verification fails when logged in as different user', function (): void {
    $user = User::factory()->unverified()->create();
    $otherUser = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    // Logged in as different user - should get 403
    $response = $this->actingAs($otherUser)->get($verificationUrl);

    $response->assertStatus(403);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    expect($otherUser->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('verification URL signature is validated', function (): void {
    $user = User::factory()->unverified()->create();

    // Manually construct URL without proper signature
    $unsignedUrl = route('verification.verify', [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    $response = $this->actingAs($user)->get($unsignedUrl);

    $response->assertStatus(403);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});
