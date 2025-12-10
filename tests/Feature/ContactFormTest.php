<?php

use App\Mail\ContactFormSubmission;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Volt\Volt;

beforeEach(function (): void {
    Mail::fake();
    RateLimiter::clear('contact-form:' . request()->ip());
});

test('contact form can be submitted', function (): void {
    Volt::test('pages.contact')
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com')
        ->set('subject', 'Test Subject')
        ->set('message', 'This is a test message that is long enough to pass validation.')
        ->call('submit')
        ->assertSet('submitted', true);

    Mail::assertQueued(ContactFormSubmission::class, function (ContactFormSubmission $mail) {
        return $mail->senderName === 'John Doe'
            && $mail->senderEmail === 'john@example.com'
            && $mail->contactSubject === 'Test Subject';
    });
});

test('contact form pre-fills authenticated user data', function (): void {
    $user = User::factory()->create([
        'name' => 'Jane Smith',
        'email' => 'jane@example.com',
    ]);

    Volt::actingAs($user)
        ->test('pages.contact')
        ->assertSet('name', 'Jane Smith')
        ->assertSet('email', 'jane@example.com');
});

test('contact form validates required fields', function (): void {
    Volt::test('pages.contact')
        ->set('name', '')
        ->set('email', '')
        ->set('subject', '')
        ->set('message', '')
        ->call('submit')
        ->assertHasErrors(['name', 'email', 'subject', 'message']);
});

test('contact form validates email format', function (): void {
    Volt::test('pages.contact')
        ->set('name', 'John Doe')
        ->set('email', 'not-an-email')
        ->set('subject', 'Test Subject')
        ->set('message', 'This is a test message that is long enough.')
        ->call('submit')
        ->assertHasErrors(['email']);
});

test('contact form validates minimum message length', function (): void {
    Volt::test('pages.contact')
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com')
        ->set('subject', 'Test Subject')
        ->set('message', 'Too short')
        ->call('submit')
        ->assertHasErrors(['message']);
});

test('contact form is rate limited', function (): void {
    $component = Volt::test('pages.contact')
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com')
        ->set('subject', 'Test Subject')
        ->set('message', 'This is a test message that is long enough to pass validation.');

    // Submit 3 times successfully
    for ($i = 0; $i < 3; $i++) {
        $component->call('submit');
        $component->call('resetForm');
        $component->set('name', 'John Doe')
            ->set('email', 'john@example.com')
            ->set('subject', 'Test Subject ' . ($i + 1))
            ->set('message', 'This is a test message that is long enough to pass validation.');
    }

    // 4th submission should be rate limited
    $component->call('submit')
        ->assertHasErrors(['message']);
});

test('contact form can be reset after submission', function (): void {
    $component = Volt::test('pages.contact')
        ->set('name', 'John Doe')
        ->set('email', 'john@example.com')
        ->set('subject', 'Test Subject')
        ->set('message', 'This is a test message that is long enough to pass validation.')
        ->call('submit')
        ->assertSet('submitted', true);

    $component->call('resetForm')
        ->assertSet('submitted', false)
        ->assertSet('subject', '')
        ->assertSet('message', '');
});
