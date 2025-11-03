<?php

use App\Models\AnalyticsSetting;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    public AnalyticsSetting $setting;

    /** @var array{client_enabled: bool, server_enabled: bool, property_id: string, measurement_id: string, property_name: string, credentials: string} */
    public array $form = [
        'client_enabled' => false,
        'server_enabled' => false,
        'property_id' => '',
        'measurement_id' => '',
        'property_name' => '',
        'credentials' => '',
    ];

    public bool $saved = false;

    public function mount(): void
    {
        Gate::authorize('manage-analytics-settings');

        $this->setting = AnalyticsSetting::current();

        $this->hydrateFormFromSetting();
    }

    public function save(): void
    {
        $this->saved = false;

        $validated = $this->validate([
            'form.client_enabled' => ['required', 'boolean'],
            'form.server_enabled' => ['required', 'boolean'],
            'form.property_id' => ['nullable', 'string', 'max:191', 'regex:/^\d+$/'],
            'form.measurement_id' => ['nullable', 'string', 'max:191', 'regex:/^G-[A-Z0-9]+$/i'],
            'form.property_name' => ['nullable', 'string', 'max:191'],
            'form.credentials' => ['nullable', 'string'],
        ]);

        $clientEnabled = (bool) $validated['form']['client_enabled'];
        $serverEnabled = (bool) $validated['form']['server_enabled'];
        $propertyId = trim((string) ($validated['form']['property_id'] ?? ''));

        if ($serverEnabled && $propertyId === '') {
            throw ValidationException::withMessages([
                'form.property_id' => __('Enter a valid Google Analytics property ID.'),
            ]);
        }

        $measurementId = trim((string) ($validated['form']['measurement_id'] ?? ''));
        $measurementId = $measurementId !== '' ? Str::upper($measurementId) : '';

        if (($clientEnabled || $serverEnabled) && $measurementId === '') {
            throw ValidationException::withMessages([
                'form.measurement_id' => __('Enter the GA4 measurement ID (for example, :example).', [
                    'example' => 'G-XXXXXXX',
                ]),
            ]);
        }

        $propertyName = trim((string) ($validated['form']['property_name'] ?? ''));

        $rawCredentials = trim((string) ($validated['form']['credentials'] ?? ''));

        $credentials = null;

        if ($rawCredentials !== '') {
            try {
                /** @var array<string, mixed>|null $decoded */
                $decoded = json_decode($rawCredentials, true, 512, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages([
                    'form.credentials' => __('Credentials must be valid JSON.'),
                ]);
            }

            if (! is_array($decoded) || Arr::get($decoded, 'type') !== 'service_account') {
                throw ValidationException::withMessages([
                    'form.credentials' => __('Provide the JSON file for a Google service account.'),
                ]);
            }

            foreach (['client_email', 'private_key', 'token_uri'] as $requiredKey) {
                $value = Arr::get($decoded, $requiredKey);

                if (! is_string($value) || trim($value) === '') {
                    throw ValidationException::withMessages([
                        'form.credentials' => __('The :field field is required in the credentials.', [
                            'field' => str_replace('_', ' ', $requiredKey),
                        ]),
                    ]);
                }
            }

            $credentials = $decoded;
        }

        if ($serverEnabled && $credentials === null) {
            throw ValidationException::withMessages([
                'form.credentials' => __('Paste the JSON credentials to enable analytics.'),
            ]);
        }

        $serverActive = $serverEnabled && $credentials !== null && $propertyId !== '' && $measurementId !== '';

        $this->setting->forceFill([
            'client_enabled' => $clientEnabled,
            'enabled' => $serverActive,
            'property_id' => $propertyId !== '' ? $propertyId : null,
            'measurement_id' => $measurementId !== '' ? $measurementId : null,
            'property_name' => $propertyName !== '' ? $propertyName : null,
            'service_account_credentials' => $credentials,
        ])->save();

        cache()->forget('analytics:summary:'.$this->setting->getKey());

        $this->setting->refresh();

        $this->hydrateFormFromSetting();

        $this->saved = true;

        $this->dispatch('analytics-settings-saved');
    }

    private function hydrateFormFromSetting(): void
    {
        $this->form = [
            'client_enabled' => $this->setting->client_enabled,
            'server_enabled' => $this->setting->enabled,
            'property_id' => (string) ($this->setting->property_id ?? ''),
            'measurement_id' => $this->setting->clientMeasurementId() ?? '',
            'property_name' => (string) ($this->setting->property_name ?? ''),
            'credentials' => $this->encodedCredentials(),
        ];
    }

    private function encodedCredentials(): string
    {
        $credentials = $this->setting->service_account_credentials;

        if ($credentials instanceof \ArrayObject) {
            $credentials = $credentials->getArrayCopy();
        }

        if (! is_array($credentials)) {
            return '';
        }

        try {
            return json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        } catch (\JsonException) {
            return '';
        }
    }
}; ?>

<div class="mx-auto flex max-w-4xl flex-col gap-8 px-6 py-10">
    <div class="flex flex-col gap-2">
        <flux:heading size="lg">
            {{ __('Google Analytics') }}
        </flux:heading>

        <flux:text class="max-w-3xl text-sm text-zinc-600 dark:text-zinc-300">
            {{ __('Choose how you want to integrate Google Analytics 4: load the tracking snippet on-site, pull key metrics into the dashboard, or both.') }}
        </flux:text>

        <div class="flex flex-wrap items-center gap-3 text-xs text-zinc-500 dark:text-zinc-400">
            <span>{{ __('Client tracking:') }}</span>

            @if ($form['client_enabled'])
                <flux:badge color="emerald">
                    {{ __('Enabled') }}
                </flux:badge>
            @else
                <flux:badge color="neutral">
                    {{ __('Disabled') }}
                </flux:badge>
            @endif

            <span class="ms-2">{{ __('Server metrics:') }}</span>

            @if ($form['server_enabled'])
                <flux:badge color="emerald">
                    {{ __('Enabled') }}
                </flux:badge>
            @else
                <flux:badge color="neutral">
                    {{ __('Disabled') }}
                </flux:badge>
            @endif

            @if ($setting->last_connected_at)
                <span>
                    {{ __('Last connected :time', ['time' => $setting->last_connected_at->diffForHumans()]) }}
                </span>
            @endif
        </div>
    </div>

    @if ($saved)
        <flux:callout icon="check-circle" variant="success">
            {{ __('Analytics settings saved.') }}
        </flux:callout>
    @endif

    <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-zinc-900/60">
        <form wire:submit="save" class="space-y-6 p-6">
            <div class="grid gap-4 lg:grid-cols-2">
                <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-zinc-900/60">
                    <flux:heading size="sm">
                        {{ __('Client-side tracking') }}
                    </flux:heading>

                    <flux:switch
                        class="mt-4"
                        wire:model.live="form.client_enabled"
                        :label="__('Load the GA4 gtag.js snippet')"
                        :description="__('Adds the measurement script to every page so Google Analytics records visits and events.')"
                    />

                    <flux:text class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Requires only the measurement ID. Ideal when you simply need tracking without dashboard metrics.') }}
                    </flux:text>
                </div>

                <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-zinc-900/60">
                    <flux:heading size="sm">
                        {{ __('Dashboard metrics (server-side)') }}
                    </flux:heading>

                    <flux:switch
                        class="mt-4"
                        wire:model.live="form.server_enabled"
                        :label="__('Show GA4 engagement metrics in the admin dashboard')"
                        :description="__('Uses the Analytics Data API and requires a Google Cloud service account JSON.')"
                    />

                    <flux:text class="mt-4 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Grant the service account Viewer access to your GA4 property so the dashboard can request metrics securely.') }}
                    </flux:text>
                </div>
            </div>

            @if ($form['client_enabled'] || $form['server_enabled'])
                <div class="grid gap-4 md:grid-cols-2">
                    <flux:input
                        wire:model="form.property_name"
                        :label="__('Property name (optional)')"
                        placeholder="{{ __('Power of Sale Workspace') }}"
                        autocomplete="off"
                    />

                    <flux:input
                        wire:model="form.measurement_id"
                        :label="__('Measurement ID')"
                        :required="$form['client_enabled'] || $form['server_enabled']"
                        placeholder="G-XXXXXXX"
                        autocomplete="off"
                    />
                </div>

                <flux:input
                    wire:model="form.property_id"
                    :label="__('Property ID')"
                    :required="$form['server_enabled']"
                    placeholder="123456789"
                    autocomplete="off"
                />
            @endif

            @if ($form['server_enabled'])
                <flux:textarea
                    wire:model="form.credentials"
                    :label="__('Service account credentials (server-side only)')"
                    placeholder="{{ __('Paste the JSON exported from your Google Cloud service account.') }}"
                    rows="10"
                />

                <flux:callout icon="information-circle" variant="neutral">
                    <flux:callout.heading>{{ __('Need help creating the service account JSON?') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Follow Google’s official guides:') }}
                        <div class="mt-3 space-y-2">
                            <p>
                                • <flux:link href="https://developers.google.com/analytics/devguides/reporting/data/v1/quickstart-client-libraries#service-account" target="_blank">
                                    {{ __('Analytics Data API service-account quickstart') }}
                                </flux:link>
                            </p>
                            <p>
                                • <flux:link href="https://developers.google.com/identity/protocols/oauth2/service-account#creatinganaccount" target="_blank">
                                    {{ __('Create and manage service account keys') }}
                                </flux:link>
                            </p>
                        </div>
                    </flux:callout.text>
                </flux:callout>

                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                    {{ __('Store credentials securely. Consider using dedicated service accounts per environment and rotate keys periodically.') }}
                </flux:text>
            @endif

            <div class="flex items-center justify-end gap-3">
                <flux:button variant="primary" type="submit">
                    {{ __('Save settings') }}
                </flux:button>

                <x-action-message on="analytics-settings-saved">
                    {{ __('Saved.') }}
                </x-action-message>
            </div>
        </form>
    </div>
</div>
