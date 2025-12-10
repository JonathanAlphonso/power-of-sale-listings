<?php

use App\Models\SavedSearch;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site', ['title' => 'Saved Searches'])] class extends Component {
    public ?int $deletingSearchId = null;

    public function mount(): void
    {
        Gate::authorize('viewAny', SavedSearch::class);
    }

    public function confirmDelete(int $searchId): void
    {
        $this->deletingSearchId = $searchId;
    }

    public function cancelDelete(): void
    {
        $this->deletingSearchId = null;
    }

    public function deleteSearch(): void
    {
        if ($this->deletingSearchId === null) {
            return;
        }

        $search = SavedSearch::find($this->deletingSearchId);

        if ($search === null) {
            $this->deletingSearchId = null;
            return;
        }

        Gate::authorize('delete', $search);

        $search->delete();
        $this->deletingSearchId = null;
        unset($this->savedSearches);
    }

    public function toggleActive(int $searchId): void
    {
        $search = SavedSearch::find($searchId);

        if ($search === null) {
            return;
        }

        Gate::authorize('update', $search);

        $search->update([
            'is_active' => ! $search->is_active,
        ]);

        unset($this->savedSearches);
    }

    #[Computed]
    public function savedSearches()
    {
        return auth()->user()
            ->savedSearches()
            ->orderByDesc('created_at')
            ->get();
    }
}; ?>

<section class="mx-auto max-w-4xl px-6 py-12 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">
                {{ __('Saved Searches') }}
            </h1>
            <p class="text-sm text-slate-600 dark:text-zinc-400">
                {{ __('Manage your saved searches and notification preferences.') }}
            </p>
        </div>

        <flux:button
            variant="primary"
            icon="plus"
            :href="route('saved-searches.create')"
            wire:navigate
        >
            {{ __('New search') }}
        </flux:button>
    </div>

    <div class="mt-8">
        @if ($this->savedSearches->isEmpty())
            <flux:callout class="rounded-2xl">
                <flux:callout.heading>{{ __('No saved searches yet') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Create a saved search to receive notifications when new listings match your criteria.') }}
                </flux:callout.text>
                <flux:button
                    variant="primary"
                    :href="route('listings.index')"
                    wire:navigate
                    class="mt-4"
                >
                    {{ __('Browse listings') }}
                </flux:button>
            </flux:callout>
        @else
            <div class="space-y-4">
                @foreach ($this->savedSearches as $search)
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <div class="flex items-center gap-2">
                                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">
                                        {{ $search->name }}
                                    </h3>
                                    @if ($search->is_active)
                                        <flux:badge color="green" size="sm">{{ __('Active') }}</flux:badge>
                                    @else
                                        <flux:badge color="zinc" size="sm">{{ __('Paused') }}</flux:badge>
                                    @endif
                                </div>

                                <p class="text-sm text-slate-500 dark:text-zinc-400">
                                    {{ __('Created :time', ['time' => $search->created_at->diffForHumans()]) }}
                                    @if ($search->last_ran_at)
                                        &middot; {{ __('Last checked :time', ['time' => $search->last_ran_at->diffForHumans()]) }}
                                    @endif
                                </p>
                            </div>

                            <div class="flex items-center gap-2">
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="{{ $search->is_active ? 'pause' : 'play' }}"
                                    wire:click="toggleActive({{ $search->id }})"
                                    :title="$search->is_active ? __('Pause notifications') : __('Resume notifications')"
                                />
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="pencil"
                                    :href="route('saved-searches.edit', $search)"
                                    wire:navigate
                                />
                                <flux:button
                                    variant="ghost"
                                    size="sm"
                                    icon="trash"
                                    wire:click="confirmDelete({{ $search->id }})"
                                />
                            </div>
                        </div>

                        @if (!empty($search->filters))
                            <div class="mt-4 flex flex-wrap gap-2">
                                @foreach ($search->filters as $key => $value)
                                    @if ($value)
                                        <flux:badge color="blue" size="sm">
                                            {{ ucfirst(str_replace('_', ' ', $key)) }}: {{ is_array($value) ? implode(', ', $value) : $value }}
                                        </flux:badge>
                                    @endif
                                @endforeach
                            </div>
                        @endif

                        <div class="mt-4 flex items-center gap-4 text-sm text-slate-500 dark:text-zinc-400">
                            <span class="flex items-center gap-1">
                                <flux:icon name="bell" class="h-4 w-4" />
                                {{ ucfirst($search->notification_frequency ?? 'instant') }}
                            </span>
                            <span class="flex items-center gap-1">
                                <flux:icon name="envelope" class="h-4 w-4" />
                                {{ ucfirst($search->notification_channel ?? 'email') }}
                            </span>
                            @if ($search->last_matched_at)
                                <span class="flex items-center gap-1">
                                    <flux:icon name="check-circle" class="h-4 w-4" />
                                    {{ __('Last match :time', ['time' => $search->last_matched_at->diffForHumans()]) }}
                                </span>
                            @endif
                        </div>

                        <div class="mt-4">
                            <flux:button
                                variant="outline"
                                size="sm"
                                icon="magnifying-glass"
                                :href="route('listings.index', $search->filters ?? [])"
                                wire:navigate
                            >
                                {{ __('View matching listings') }}
                            </flux:button>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Delete Confirmation Modal -->
    <flux:modal :show="$deletingSearchId !== null" wire:model="deletingSearchId" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Delete saved search') }}</flux:heading>
            <flux:text>
                {{ __('Are you sure you want to delete this saved search? You will no longer receive notifications for matching listings.') }}
            </flux:text>

            <div class="flex items-center justify-end gap-2">
                <flux:button variant="outline" wire:click="cancelDelete">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="danger" wire:click="deleteSearch">
                    {{ __('Delete') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
