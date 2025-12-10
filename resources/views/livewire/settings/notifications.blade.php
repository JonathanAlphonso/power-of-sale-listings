<?php

use App\Models\SavedSearch;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component {
    public bool $emailNotifications = true;

    public function mount(): void
    {
        // Default to email notifications enabled
        // Could extend User model with notification preferences if needed
        $this->emailNotifications = true;
    }

    #[Computed]
    public function savedSearches()
    {
        return Auth::user()
            ->savedSearches()
            ->orderByDesc('created_at')
            ->get();
    }

    public function toggleSearchActive(int $searchId): void
    {
        $search = SavedSearch::find($searchId);

        if ($search === null || $search->user_id !== Auth::id()) {
            return;
        }

        $search->update([
            'is_active' => ! $search->is_active,
        ]);

        unset($this->savedSearches);
    }

    public function updateSearchChannel(int $searchId, string $channel): void
    {
        $search = SavedSearch::find($searchId);

        if ($search === null || $search->user_id !== Auth::id()) {
            return;
        }

        if (! in_array($channel, ['email', 'none'], true)) {
            return;
        }

        $search->update([
            'notification_channel' => $channel,
        ]);

        unset($this->savedSearches);
    }

    public function updateSearchFrequency(int $searchId, string $frequency): void
    {
        $search = SavedSearch::find($searchId);

        if ($search === null || $search->user_id !== Auth::id()) {
            return;
        }

        if (! in_array($frequency, ['instant', 'daily', 'weekly'], true)) {
            return;
        }

        $search->update([
            'notification_frequency' => $frequency,
        ]);

        unset($this->savedSearches);
    }

    public function pauseAllNotifications(): void
    {
        Auth::user()
            ->savedSearches()
            ->where('is_active', true)
            ->update(['is_active' => false]);

        unset($this->savedSearches);
        $this->dispatch('notifications-updated');
    }

    public function resumeAllNotifications(): void
    {
        Auth::user()
            ->savedSearches()
            ->where('is_active', false)
            ->update(['is_active' => true]);

        unset($this->savedSearches);
        $this->dispatch('notifications-updated');
    }
}; ?>

<section class="w-full">
    @include('partials.settings-heading')

    <x-settings.layout :heading="__('Notifications')" :subheading="__('Manage your email notification preferences')">
        <div class="my-6 w-full space-y-6">
            <!-- Global Actions -->
            @if ($this->savedSearches->isNotEmpty())
                <div class="flex flex-wrap items-center gap-3">
                    @if ($this->savedSearches->where('is_active', true)->isNotEmpty())
                        <flux:button
                            variant="outline"
                            size="sm"
                            icon="pause"
                            wire:click="pauseAllNotifications"
                        >
                            {{ __('Pause all') }}
                        </flux:button>
                    @endif

                    @if ($this->savedSearches->where('is_active', false)->isNotEmpty())
                        <flux:button
                            variant="outline"
                            size="sm"
                            icon="play"
                            wire:click="resumeAllNotifications"
                        >
                            {{ __('Resume all') }}
                        </flux:button>
                    @endif
                </div>
            @endif

            <!-- Saved Searches -->
            <div class="space-y-4">
                <flux:heading size="sm">{{ __('Saved Search Notifications') }}</flux:heading>
                <flux:text class="text-sm text-slate-600 dark:text-zinc-400">
                    {{ __('Configure how you receive notifications when new listings match your saved searches.') }}
                </flux:text>

                @if ($this->savedSearches->isEmpty())
                    <flux:callout>
                        <flux:callout.heading>{{ __('No saved searches') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('You haven\'t created any saved searches yet. Create one to start receiving notifications about new listings.') }}
                        </flux:callout.text>
                        <flux:button
                            variant="primary"
                            :href="route('saved-searches.create')"
                            wire:navigate
                            class="mt-4"
                        >
                            {{ __('Create saved search') }}
                        </flux:button>
                    </flux:callout>
                @else
                    <div class="space-y-4">
                        @foreach ($this->savedSearches as $search)
                            <div class="rounded-xl border border-slate-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-800/50">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2">
                                            <h4 class="font-medium text-slate-900 dark:text-white truncate">
                                                {{ $search->name }}
                                            </h4>
                                            @if ($search->is_active)
                                                <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                            @else
                                                <flux:badge color="zinc" size="sm">{{ __('Paused') }}</flux:badge>
                                            @endif
                                        </div>

                                        @if (! empty($search->filters))
                                            <div class="mt-2 flex flex-wrap gap-1">
                                                @foreach (collect($search->filters)->take(3) as $key => $value)
                                                    @if ($value)
                                                        <flux:badge color="blue" size="sm">
                                                            {{ ucfirst(str_replace('_', ' ', $key)) }}: {{ is_array($value) ? implode(', ', $value) : Str::limit((string) $value, 15) }}
                                                        </flux:badge>
                                                    @endif
                                                @endforeach
                                                @if (count($search->filters) > 3)
                                                    <flux:badge color="zinc" size="sm">
                                                        +{{ count($search->filters) - 3 }} {{ __('more') }}
                                                    </flux:badge>
                                                @endif
                                            </div>
                                        @endif
                                    </div>

                                    <flux:button
                                        variant="ghost"
                                        size="sm"
                                        icon="{{ $search->is_active ? 'pause' : 'play' }}"
                                        wire:click="toggleSearchActive({{ $search->id }})"
                                        :title="$search->is_active ? __('Pause notifications') : __('Resume notifications')"
                                    />
                                </div>

                                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <flux:select
                                            wire:change="updateSearchChannel({{ $search->id }}, $event.target.value)"
                                            :label="__('Notification method')"
                                            size="sm"
                                        >
                                            <flux:select.option value="email" :selected="$search->notification_channel === 'email'">
                                                {{ __('Email') }}
                                            </flux:select.option>
                                            <flux:select.option value="none" :selected="$search->notification_channel === 'none'">
                                                {{ __('None') }}
                                            </flux:select.option>
                                        </flux:select>
                                    </div>

                                    <div>
                                        <flux:select
                                            wire:change="updateSearchFrequency({{ $search->id }}, $event.target.value)"
                                            :label="__('Frequency')"
                                            size="sm"
                                        >
                                            <flux:select.option value="instant" :selected="$search->notification_frequency === 'instant'">
                                                {{ __('Instant') }}
                                            </flux:select.option>
                                            <flux:select.option value="daily" :selected="$search->notification_frequency === 'daily'">
                                                {{ __('Daily digest') }}
                                            </flux:select.option>
                                            <flux:select.option value="weekly" :selected="$search->notification_frequency === 'weekly'">
                                                {{ __('Weekly digest') }}
                                            </flux:select.option>
                                        </flux:select>
                                    </div>
                                </div>

                                <div class="mt-3 flex items-center justify-between text-xs text-slate-500 dark:text-zinc-400">
                                    <span>
                                        @if ($search->last_ran_at)
                                            {{ __('Last checked :time', ['time' => $search->last_ran_at->diffForHumans()]) }}
                                        @else
                                            {{ __('Not yet checked') }}
                                        @endif
                                    </span>

                                    <flux:button
                                        variant="ghost"
                                        size="xs"
                                        :href="route('saved-searches.edit', $search)"
                                        wire:navigate
                                    >
                                        {{ __('Edit search') }}
                                    </flux:button>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-slate-200 dark:border-zinc-700">
                        <flux:button
                            variant="outline"
                            size="sm"
                            icon="plus"
                            :href="route('saved-searches.create')"
                            wire:navigate
                        >
                            {{ __('New saved search') }}
                        </flux:button>

                        <flux:button
                            variant="ghost"
                            size="sm"
                            :href="route('saved-searches.index')"
                            wire:navigate
                        >
                            {{ __('Manage all searches') }}
                        </flux:button>
                    </div>
                @endif
            </div>

            <x-action-message class="me-3" on="notifications-updated">
                {{ __('Notification settings updated.') }}
            </x-action-message>
        </div>
    </x-settings.layout>
</section>
