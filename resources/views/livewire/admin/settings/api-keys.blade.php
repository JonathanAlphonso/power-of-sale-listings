<?php

use App\Models\ApiKey;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    /** @var array<string, ApiKey> */
    public array $apiKeys = [];

    public ?string $editingSlug = null;

    public array $form = [
        'api_key' => '',
        'api_secret' => '',
        'is_enabled' => false,
    ];

    public bool $saved = false;

    public string $savedMessage = '';

    public function mount(): void
    {
        Gate::authorize('manage-api-keys');

        $this->loadApiKeys();
    }

    public function loadApiKeys(): void
    {
        $this->apiKeys = ApiKey::allConfigured();
    }

    public function edit(string $slug): void
    {
        $this->saved = false;
        $this->editingSlug = $slug;

        $apiKey = $this->apiKeys[$slug] ?? null;

        if ($apiKey) {
            $this->form = [
                'api_key' => $apiKey->api_key ?? '',
                'api_secret' => $apiKey->api_secret ?? '',
                'is_enabled' => $apiKey->is_enabled,
            ];
        }
    }

    public function cancelEdit(): void
    {
        $this->editingSlug = null;
        $this->form = [
            'api_key' => '',
            'api_secret' => '',
            'is_enabled' => false,
        ];
    }

    public function save(): void
    {
        if (! $this->editingSlug) {
            return;
        }

        $this->saved = false;

        $validated = $this->validate([
            'form.api_key' => ['nullable', 'string', 'max:1000'],
            'form.api_secret' => ['nullable', 'string', 'max:1000'],
            'form.is_enabled' => ['required', 'boolean'],
        ]);

        $apiKey = $this->apiKeys[$this->editingSlug] ?? null;

        if (! $apiKey) {
            return;
        }

        $isEnabled = (bool) $validated['form']['is_enabled'];
        $key = trim((string) ($validated['form']['api_key'] ?? ''));
        $secret = trim((string) ($validated['form']['api_secret'] ?? ''));

        if ($isEnabled && $key === '') {
            throw ValidationException::withMessages([
                'form.api_key' => __('An API key is required to enable this integration.'),
            ]);
        }

        $apiKey->forceFill([
            'api_key' => $key !== '' ? $key : null,
            'api_secret' => $secret !== '' ? $secret : null,
            'is_enabled' => $isEnabled,
        ])->save();

        $this->loadApiKeys();

        $this->savedMessage = __(':name settings saved.', ['name' => $apiKey->name]);
        $this->saved = true;

        $this->editingSlug = null;
        $this->form = [
            'api_key' => '',
            'api_secret' => '',
            'is_enabled' => false,
        ];

        $this->dispatch('api-keys-saved');
    }

    public function getProviderConfig(string $slug): array
    {
        return match ($slug) {
            'maptiler' => [
                'icon' => 'map-pin',
                'icon_bg' => 'bg-sky-100 dark:bg-sky-900/30',
                'icon_text' => 'text-sky-600 dark:text-sky-400',
                'description' => __('Used for displaying property location maps on listing pages.'),
                'docs_url' => 'https://docs.maptiler.com/',
                'has_secret' => false,
                'key_label' => __('API Key'),
                'key_placeholder' => __('Enter your MapTiler API key'),
            ],
            'google' => [
                'icon' => 'globe-alt',
                'icon_bg' => 'bg-red-100 dark:bg-red-900/30',
                'icon_text' => 'text-red-600 dark:text-red-400',
                'description' => __('Allow users to sign in with their Google account.'),
                'docs_url' => 'https://developers.google.com/identity/protocols/oauth2',
                'has_secret' => true,
                'key_label' => __('Client ID'),
                'secret_label' => __('Client Secret'),
                'key_placeholder' => __('Enter your Google OAuth Client ID'),
                'secret_placeholder' => __('Enter your Google OAuth Client Secret'),
            ],
            'discord' => [
                'icon' => 'chat-bubble-left-right',
                'icon_bg' => 'bg-indigo-100 dark:bg-indigo-900/30',
                'icon_text' => 'text-indigo-600 dark:text-indigo-400',
                'description' => __('Allow users to sign in with their Discord account.'),
                'docs_url' => 'https://discord.com/developers/docs/topics/oauth2',
                'has_secret' => true,
                'key_label' => __('Client ID'),
                'secret_label' => __('Client Secret'),
                'key_placeholder' => __('Enter your Discord OAuth Client ID'),
                'secret_placeholder' => __('Enter your Discord OAuth Client Secret'),
            ],
            'facebook' => [
                'icon' => 'user-group',
                'icon_bg' => 'bg-blue-100 dark:bg-blue-900/30',
                'icon_text' => 'text-blue-600 dark:text-blue-400',
                'description' => __('Allow users to sign in with their Facebook account.'),
                'docs_url' => 'https://developers.facebook.com/docs/facebook-login/',
                'has_secret' => true,
                'key_label' => __('App ID'),
                'secret_label' => __('App Secret'),
                'key_placeholder' => __('Enter your Facebook App ID'),
                'secret_placeholder' => __('Enter your Facebook App Secret'),
            ],
            default => [
                'icon' => 'key',
                'icon_bg' => 'bg-zinc-100 dark:bg-zinc-900/30',
                'icon_text' => 'text-zinc-600 dark:text-zinc-400',
                'description' => '',
                'docs_url' => null,
                'has_secret' => true,
                'key_label' => __('API Key'),
                'secret_label' => __('API Secret'),
                'key_placeholder' => __('Enter the API key'),
                'secret_placeholder' => __('Enter the API secret'),
            ],
        };
    }
}; ?>

<div class="mx-auto flex max-w-4xl flex-col gap-8 px-6 py-10">
    <div class="flex flex-col gap-2">
        <flux:heading size="lg">
            {{ __('API Keys') }}
        </flux:heading>

        <flux:text class="max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
            {{ __('Manage API keys for third-party integrations including maps, social logins, and other services.') }}
        </flux:text>

        <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
            <span>{{ __('Configured:') }}</span>
            <flux:badge color="emerald">
                {{ collect($apiKeys)->filter(fn($k) => $k->isConfigured())->count() }}
            </flux:badge>
            <span>{{ __('of') }}</span>
            <flux:badge color="zinc">
                {{ count($apiKeys) }}
            </flux:badge>
        </div>
    </div>

    @if ($saved)
        <flux:callout icon="check-circle" variant="success">
            {{ $savedMessage }}
        </flux:callout>
    @endif

    <div class="space-y-4">
        <flux:heading size="md">
            {{ __('Map Services') }}
        </flux:heading>

        @foreach (['maptiler'] as $slug)
            @php
                $apiKey = $apiKeys[$slug] ?? null;
                $config = $this->getProviderConfig($slug);
            @endphp
            @if ($apiKey)
                <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-zinc-900/60">
                    <div class="flex items-start justify-between p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl {{ $config['icon_bg'] }}">
                                <flux:icon :name="$config['icon']" class="h-6 w-6 {{ $config['icon_text'] }}" />
                            </div>
                            <div>
                                <flux:heading size="sm">{{ $apiKey->name }}</flux:heading>
                                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $config['description'] }}
                                </flux:text>
                                <div class="mt-2 flex items-center gap-2">
                                    @if ($apiKey->isConfigured())
                                        <flux:badge color="emerald" size="sm">{{ __('Configured') }}</flux:badge>
                                    @else
                                        <flux:badge color="amber" size="sm">{{ __('Not configured') }}</flux:badge>
                                    @endif

                                    @if ($apiKey->api_key)
                                        <span class="font-mono text-xs text-zinc-400">{{ $apiKey->getMaskedKey() }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <flux:button size="sm" wire:click="edit('{{ $slug }}')">
                            {{ __('Configure') }}
                        </flux:button>
                    </div>

                    @if ($editingSlug === $slug)
                        <div class="border-t border-neutral-200 p-6 dark:border-neutral-700">
                            <form wire:submit="save" class="space-y-4">
                                <flux:switch
                                    wire:model.live="form.is_enabled"
                                    :label="__('Enable this integration')"
                                />

                                <flux:input
                                    wire:model="form.api_key"
                                    :label="$config['key_label']"
                                    :placeholder="$config['key_placeholder']"
                                    type="password"
                                    autocomplete="off"
                                />

                                @if ($config['docs_url'])
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Need help?') }}
                                        <flux:link :href="$config['docs_url']" target="_blank">
                                            {{ __('View documentation') }}
                                        </flux:link>
                                    </flux:text>
                                @endif

                                <div class="flex items-center justify-end gap-3">
                                    <flux:button type="button" variant="ghost" wire:click="cancelEdit">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                    <flux:button type="submit" variant="primary">
                                        {{ __('Save') }}
                                    </flux:button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
    </div>

    <div class="space-y-4">
        <flux:heading size="md">
            {{ __('Social Login Providers') }}
        </flux:heading>

        <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
            {{ __('Configure OAuth credentials to allow users to sign in with their existing accounts.') }}
        </flux:text>

        @foreach (['google', 'discord', 'facebook'] as $slug)
            @php
                $apiKey = $apiKeys[$slug] ?? null;
                $config = $this->getProviderConfig($slug);
            @endphp
            @if ($apiKey)
                <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-zinc-900/60">
                    <div class="flex items-start justify-between p-6">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 items-center justify-center rounded-xl {{ $config['icon_bg'] }}">
                                <flux:icon :name="$config['icon']" class="h-6 w-6 {{ $config['icon_text'] }}" />
                            </div>
                            <div>
                                <flux:heading size="sm">{{ $apiKey->name }}</flux:heading>
                                <flux:text class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $config['description'] }}
                                </flux:text>
                                <div class="mt-2 flex items-center gap-2">
                                    @if ($apiKey->isConfigured())
                                        <flux:badge color="emerald" size="sm">{{ __('Configured') }}</flux:badge>
                                    @else
                                        <flux:badge color="amber" size="sm">{{ __('Not configured') }}</flux:badge>
                                    @endif

                                    @if ($apiKey->api_key)
                                        <span class="font-mono text-xs text-zinc-400">{{ $apiKey->getMaskedKey() }}</span>
                                    @endif
                                </div>
                            </div>
                        </div>
                        <flux:button size="sm" wire:click="edit('{{ $slug }}')">
                            {{ __('Configure') }}
                        </flux:button>
                    </div>

                    @if ($editingSlug === $slug)
                        <div class="border-t border-neutral-200 p-6 dark:border-neutral-700">
                            <form wire:submit="save" class="space-y-4">
                                <flux:switch
                                    wire:model.live="form.is_enabled"
                                    :label="__('Enable this integration')"
                                />

                                <div class="grid gap-4 md:grid-cols-2">
                                    <flux:input
                                        wire:model="form.api_key"
                                        :label="$config['key_label']"
                                        :placeholder="$config['key_placeholder']"
                                        type="password"
                                        autocomplete="off"
                                    />

                                    @if ($config['has_secret'])
                                        <flux:input
                                            wire:model="form.api_secret"
                                            :label="$config['secret_label']"
                                            :placeholder="$config['secret_placeholder']"
                                            type="password"
                                            autocomplete="off"
                                        />
                                    @endif
                                </div>

                                @if ($config['docs_url'])
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Need help?') }}
                                        <flux:link :href="$config['docs_url']" target="_blank">
                                            {{ __('View documentation') }}
                                        </flux:link>
                                    </flux:text>
                                @endif

                                <div class="flex items-center justify-end gap-3">
                                    <flux:button type="button" variant="ghost" wire:click="cancelEdit">
                                        {{ __('Cancel') }}
                                    </flux:button>
                                    <flux:button type="submit" variant="primary">
                                        {{ __('Save') }}
                                    </flux:button>
                                </div>
                            </form>
                        </div>
                    @endif
                </div>
            @endif
        @endforeach
    </div>

    <flux:callout icon="information-circle" variant="neutral">
        <flux:callout.heading>{{ __('Security Notice') }}</flux:callout.heading>
        <flux:callout.text>
            {{ __('All API keys and secrets are encrypted at rest. Never share your API credentials publicly.') }}
        </flux:callout.text>
    </flux:callout>
</div>
