<?php

use App\Mail\ContactFormSubmission;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site', ['title' => 'Contact Us'])] class extends Component {
    #[Validate('required|string|min:2|max:100')]
    public string $name = '';

    #[Validate('required|email|max:255')]
    public string $email = '';

    #[Validate('required|string|min:3|max:100')]
    public string $subject = '';

    #[Validate('required|string|min:10|max:5000')]
    public string $message = '';

    public bool $submitted = false;

    public function mount(): void
    {
        if (auth()->check()) {
            $this->name = auth()->user()->name;
            $this->email = auth()->user()->email;
        }
    }

    public function submit(): void
    {
        $this->validate();

        $key = 'contact-form:' . (auth()->id() ?? request()->ip());

        if (RateLimiter::tooManyAttempts($key, 3)) {
            $seconds = RateLimiter::availableIn($key);
            $this->addError('message', __('Too many submissions. Please try again in :seconds seconds.', [
                'seconds' => $seconds,
            ]));
            return;
        }

        RateLimiter::hit($key, 3600); // 1 hour decay

        // Send notification to admin
        Mail::to(config('mail.from.address'))
            ->send(new ContactFormSubmission(
                senderName: $this->name,
                senderEmail: $this->email,
                subject: $this->subject,
                messageContent: $this->message,
            ));

        $this->submitted = true;
    }

    public function resetForm(): void
    {
        $this->reset(['name', 'email', 'subject', 'message', 'submitted']);

        if (auth()->check()) {
            $this->name = auth()->user()->name;
            $this->email = auth()->user()->email;
        }
    }
}; ?>

<section class="mx-auto max-w-2xl px-6 py-12 lg:px-8">
    <div class="space-y-2">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">
            {{ __('Contact Us') }}
        </h1>
        <p class="text-sm text-slate-600 dark:text-zinc-400">
            {{ __('Have a question or feedback? We\'d love to hear from you.') }}
        </p>
    </div>

    @if ($submitted)
        <div class="mt-8 rounded-2xl border border-green-200 bg-green-50 p-6 dark:border-green-900 dark:bg-green-950/50">
            <div class="flex items-start gap-4">
                <div class="flex-shrink-0">
                    <flux:icon name="check-circle" class="h-6 w-6 text-green-600 dark:text-green-400" />
                </div>
                <div>
                    <flux:heading size="sm" class="text-green-900 dark:text-green-100">
                        {{ __('Message sent successfully') }}
                    </flux:heading>
                    <flux:text class="mt-2 text-green-800 dark:text-green-200">
                        {{ __('Thank you for reaching out. We\'ll get back to you as soon as possible.') }}
                    </flux:text>
                    <flux:button
                        variant="outline"
                        wire:click="resetForm"
                        class="mt-4"
                    >
                        {{ __('Send another message') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @else
        <form wire:submit="submit" class="mt-8 space-y-6">
            <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                <div class="space-y-4">
                    <flux:input
                        wire:model="name"
                        :label="__('Your name')"
                        :placeholder="__('John Doe')"
                        required
                    />

                    <flux:input
                        wire:model="email"
                        type="email"
                        :label="__('Email address')"
                        :placeholder="__('you@example.com')"
                        required
                    />

                    <flux:input
                        wire:model="subject"
                        :label="__('Subject')"
                        :placeholder="__('What is your message about?')"
                        required
                    />

                    <flux:textarea
                        wire:model="message"
                        :label="__('Message')"
                        :placeholder="__('Please provide as much detail as possible...')"
                        rows="6"
                        required
                    />
                </div>
            </div>

            <div class="flex items-center justify-between">
                <flux:text class="text-xs text-slate-500 dark:text-zinc-400">
                    {{ __('We typically respond within 1-2 business days.') }}
                </flux:text>

                <flux:button type="submit" variant="primary">
                    {{ __('Send message') }}
                </flux:button>
            </div>
        </form>
    @endif

    <div class="mt-12 grid gap-6 sm:grid-cols-2">
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
            <flux:icon name="question-mark-circle" class="h-8 w-8 text-slate-400 dark:text-zinc-500" />
            <flux:heading size="sm" class="mt-4 text-slate-900 dark:text-white">
                {{ __('Frequently Asked Questions') }}
            </flux:heading>
            <flux:text class="mt-2 text-slate-600 dark:text-zinc-300">
                {{ __('Find answers to common questions about power of sale properties and our platform.') }}
            </flux:text>
            <flux:button
                variant="ghost"
                :href="route('pages.faq')"
                wire:navigate
                class="mt-4"
            >
                {{ __('View FAQ') }}
            </flux:button>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
            <flux:icon name="shield-check" class="h-8 w-8 text-slate-400 dark:text-zinc-500" />
            <flux:heading size="sm" class="mt-4 text-slate-900 dark:text-white">
                {{ __('Privacy & Terms') }}
            </flux:heading>
            <flux:text class="mt-2 text-slate-600 dark:text-zinc-300">
                {{ __('Learn about how we handle your data and our terms of service.') }}
            </flux:text>
            <div class="mt-4 flex gap-2">
                <flux:button
                    variant="ghost"
                    :href="route('pages.privacy')"
                    wire:navigate
                >
                    {{ __('Privacy') }}
                </flux:button>
                <flux:button
                    variant="ghost"
                    :href="route('pages.terms')"
                    wire:navigate
                >
                    {{ __('Terms') }}
                </flux:button>
            </div>
        </div>
    </div>
</section>
