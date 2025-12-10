<?php

use App\Models\Listing;
use App\Models\UserFavorite;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site', ['title' => 'My Favorites'])] class extends Component {
    public ?int $removingFavoriteId = null;

    public function confirmRemove(int $listingId): void
    {
        $this->removingFavoriteId = $listingId;
    }

    public function cancelRemove(): void
    {
        $this->removingFavoriteId = null;
    }

    public function removeFavorite(): void
    {
        if ($this->removingFavoriteId === null) {
            return;
        }

        auth()->user()->favorites()
            ->where('listing_id', $this->removingFavoriteId)
            ->delete();

        $this->removingFavoriteId = null;
        unset($this->favorites);
    }

    public function removeAll(): void
    {
        auth()->user()->favorites()->delete();
        unset($this->favorites);
    }

    #[Computed]
    public function favorites(): Collection
    {
        return auth()->user()
            ->favoriteListings()
            ->with(['source:id,name', 'municipality:id,name', 'media'])
            ->get();
    }

    #[Computed]
    public function totalValue(): ?float
    {
        $prices = $this->favorites->pluck('list_price')->filter();

        if ($prices->isEmpty()) {
            return null;
        }

        return $prices->sum();
    }
}; ?>

<section class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">
                {{ __('My Favorites') }}
            </h1>
            <p class="text-sm text-slate-600 dark:text-zinc-400">
                {{ __('Keep track of listings you\'re interested in.') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            @if ($this->favorites->isNotEmpty())
                <flux:badge color="blue">
                    {{ trans_choice(':count listing|:count listings', $this->favorites->count(), ['count' => $this->favorites->count()]) }}
                </flux:badge>
                @if ($this->totalValue)
                    <flux:badge color="emerald">
                        {{ __('Total:') }} {{ \App\Support\ListingPresentation::currency($this->totalValue) }}
                    </flux:badge>
                @endif
            @endif
        </div>
    </div>

    <div class="mt-8">
        @if ($this->favorites->isEmpty())
            <flux:callout class="rounded-2xl">
                <flux:callout.heading>{{ __('No favorites yet') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Save listings you\'re interested in by clicking the heart icon on any listing card.') }}
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
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($this->favorites as $listing)
                    @php
                        $primaryMedia = $listing->media->firstWhere('is_primary', true) ?? $listing->media->first();
                        $address = $listing->street_address ?? __('Address unavailable');
                        $location = collect([$listing->city, $listing->province, $listing->postal_code])->filter()->implode(', ');
                    @endphp

                    <div class="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900/70">
                        <a href="{{ route('listings.show', $listing) }}" class="block" wire:navigate>
                            @if ($primaryMedia !== null)
                                <img
                                    src="{{ $primaryMedia->public_url }}"
                                    alt="{{ $primaryMedia->label ?? $address }}"
                                    class="aspect-video w-full object-cover"
                                    loading="lazy"
                                />
                            @else
                                <div class="flex aspect-video items-center justify-center bg-slate-100 text-4xl text-slate-300 dark:bg-zinc-800 dark:text-zinc-600">
                                    <flux:icon name="photo" />
                                </div>
                            @endif
                        </a>

                        <!-- Remove from favorites button -->
                        <button
                            wire:click="confirmRemove({{ $listing->id }})"
                            class="absolute top-3 right-3 flex h-8 w-8 items-center justify-center rounded-full bg-red-500 text-white shadow-lg transition hover:bg-red-600"
                            title="{{ __('Remove from favorites') }}"
                        >
                            <flux:icon name="heart" class="h-5 w-5" variant="solid" />
                        </button>

                        <div class="flex flex-1 flex-col gap-4 p-6">
                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1">
                                    <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                                        {{ $address }}
                                    </h2>

                                    @if ($location !== '')
                                        <p class="text-sm text-slate-500 dark:text-zinc-400">
                                            {{ $location }}
                                        </p>
                                    @endif
                                </div>

                                <flux:badge color="{{ \App\Support\ListingPresentation::statusBadge($listing->display_status) }}">
                                    {{ $listing->display_status ?? __('Unknown') }}
                                </flux:badge>
                            </div>

                            <div class="grid gap-4 rounded-xl border border-dashed border-slate-200 p-4 dark:border-zinc-800 sm:grid-cols-2">
                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                        {{ __('List price') }}
                                    </p>
                                    <div class="flex items-center gap-2">
                                        <p class="text-base font-semibold text-slate-900 dark:text-white">
                                            {{ \App\Support\ListingPresentation::currency($listing->list_price) }}
                                        </p>
                                        @if ($listing->original_list_price && $listing->list_price && $listing->original_list_price != $listing->list_price)
                                            @php
                                                $priceDiff = $listing->list_price - $listing->original_list_price;
                                                $percentChange = ($priceDiff / $listing->original_list_price) * 100;
                                            @endphp
                                            @if ($priceDiff < 0)
                                                <span class="inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400" title="{{ __('Reduced from') }} {{ \App\Support\ListingPresentation::currency($listing->original_list_price) }}">
                                                    {{ number_format(abs($percentChange), 0) }}%
                                                </span>
                                            @endif
                                        @endif
                                    </div>
                                </div>

                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                        {{ __('Property type') }}
                                    </p>
                                    <p class="text-sm font-semibold text-slate-900 dark:text-white truncate">
                                        {{ $listing->property_type ?? __('Unknown') }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                        {{ __('Bedrooms') }}
                                    </p>
                                    <p class="text-base font-semibold text-slate-900 dark:text-white">
                                        {{ \App\Support\ListingPresentation::numeric($listing->bedrooms) }}
                                    </p>
                                </div>

                                <div>
                                    <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                        {{ __('Bathrooms') }}
                                    </p>
                                    <p class="text-base font-semibold text-slate-900 dark:text-white">
                                        {{ \App\Support\ListingPresentation::numeric($listing->bathrooms, 1) }}
                                    </p>
                                </div>
                            </div>

                            <div class="mt-auto space-y-2 text-xs text-slate-500 dark:text-zinc-400">
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">{{ __('Days on market') }}</span>
                                    <span>{{ $listing->days_on_market ?? '—' }}</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="font-medium">{{ __('Saved') }}</span>
                                    <span>{{ $listing->pivot->created_at->diffForHumans() }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($this->favorites->count() > 1)
                <div class="mt-8 flex justify-center">
                    <flux:button
                        variant="subtle"
                        icon="trash"
                        wire:click="removeAll"
                        wire:confirm="{{ __('Are you sure you want to remove all favorites?') }}"
                    >
                        {{ __('Remove all favorites') }}
                    </flux:button>
                </div>
            @endif
        @endif
    </div>

    <!-- Remove Confirmation Modal -->
    <flux:modal :show="$removingFavoriteId !== null" wire:model="removingFavoriteId" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Remove from favorites') }}</flux:heading>
            <flux:text>
                {{ __('Are you sure you want to remove this listing from your favorites?') }}
            </flux:text>

            <div class="flex items-center justify-end gap-2">
                <flux:button variant="outline" wire:click="cancelRemove">
                    {{ __('Cancel') }}
                </flux:button>
                <flux:button variant="danger" wire:click="removeFavorite">
                    {{ __('Remove') }}
                </flux:button>
            </div>
        </div>
    </flux:modal>
</section>
