<?php

use App\Models\Listing;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site', ['title' => 'Compare Listings'])] class extends Component {
    private const MAX_COMPARE = 4;

    #[Url(as: 'ids', except: '')]
    public string $listingIds = '';

    public function removeFromCompare(int $id): void
    {
        $ids = $this->parseIds();
        $ids = array_filter($ids, fn ($listingId) => $listingId !== $id);
        $this->listingIds = implode(',', $ids);
    }

    public function clearAll(): void
    {
        $this->listingIds = '';
    }

    #[Computed]
    public function listings(): Collection
    {
        $ids = $this->parseIds();

        if (empty($ids)) {
            return new Collection();
        }

        return Listing::query()
            ->visible()
            ->with(['source:id,name', 'municipality:id,name', 'media'])
            ->whereIn('id', $ids)
            ->get();
    }

    #[Computed]
    public function hasListings(): bool
    {
        return $this->listings->isNotEmpty();
    }

    #[Computed]
    public function comparisonFields(): array
    {
        return [
            ['key' => 'list_price', 'label' => __('List price'), 'type' => 'currency'],
            ['key' => 'original_list_price', 'label' => __('Original price'), 'type' => 'currency'],
            ['key' => 'price_change', 'label' => __('Price change'), 'type' => 'currency_change'],
            ['key' => 'price_per_sqft', 'label' => __('Price per sqft'), 'type' => 'price_per_sqft'],
            ['key' => 'property_type', 'label' => __('Property type'), 'type' => 'text'],
            ['key' => 'bedrooms', 'label' => __('Bedrooms'), 'type' => 'numeric'],
            ['key' => 'bathrooms', 'label' => __('Bathrooms'), 'type' => 'numeric'],
            ['key' => 'square_feet', 'label' => __('Square feet'), 'type' => 'numeric'],
            ['key' => 'days_on_market', 'label' => __('Days on market'), 'type' => 'numeric'],
            ['key' => 'display_status', 'label' => __('Status'), 'type' => 'status'],
            ['key' => 'city', 'label' => __('City'), 'type' => 'text'],
            ['key' => 'municipality.name', 'label' => __('Municipality'), 'type' => 'relation'],
            ['key' => 'source.name', 'label' => __('Source'), 'type' => 'relation'],
        ];
    }

    /**
     * @return array<int>
     */
    private function parseIds(): array
    {
        if ($this->listingIds === '') {
            return [];
        }

        $ids = array_map('intval', array_filter(explode(',', $this->listingIds)));

        return array_slice($ids, 0, self::MAX_COMPARE);
    }
}; ?>

<section class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">
                {{ __('Compare listings') }}
            </h1>
            <p class="text-sm text-slate-600 dark:text-zinc-400">
                {{ __('Side-by-side comparison of selected properties.') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <flux:button
                variant="ghost"
                icon="chevron-left"
                :href="route('listings.index')"
                wire:navigate
            >
                {{ __('Back to listings') }}
            </flux:button>

            @if ($this->hasListings)
                <flux:button
                    variant="subtle"
                    size="sm"
                    icon="x-mark"
                    wire:click="clearAll"
                >
                    {{ __('Clear all') }}
                </flux:button>
            @endif
        </div>
    </div>

    @if ($this->hasListings)
        <div class="mt-8 overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 dark:divide-zinc-800">
                <thead>
                    <tr>
                        <th class="sticky left-0 z-10 bg-white py-4 pr-4 text-left text-sm font-semibold text-slate-900 dark:bg-zinc-950 dark:text-zinc-100">
                            {{ __('Property') }}
                        </th>
                        @foreach ($this->listings as $listing)
                            @php
                                $primaryMedia = $listing->media->firstWhere('is_primary', true) ?? $listing->media->first();
                            @endphp
                            <th class="px-4 py-4 text-center min-w-[200px]">
                                <div class="space-y-3">
                                    <div class="relative">
                                        @if ($primaryMedia)
                                            <img
                                                src="{{ $primaryMedia->public_url }}"
                                                alt="{{ $listing->street_address }}"
                                                class="aspect-video w-full rounded-lg object-cover"
                                            />
                                        @else
                                            <div class="flex aspect-video w-full items-center justify-center rounded-lg bg-slate-100 text-2xl text-slate-300 dark:bg-zinc-800 dark:text-zinc-600">
                                                <flux:icon name="photo" />
                                            </div>
                                        @endif
                                        <button
                                            wire:click="removeFromCompare({{ $listing->id }})"
                                            class="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-500 text-white shadow-lg hover:bg-red-600"
                                            title="{{ __('Remove from comparison') }}"
                                        >
                                            <flux:icon name="x-mark" class="h-4 w-4" />
                                        </button>
                                    </div>
                                    <div class="space-y-1">
                                        <a
                                            href="{{ route('listings.show', $listing) }}"
                                            class="block text-sm font-semibold text-slate-900 hover:text-emerald-600 dark:text-zinc-100 dark:hover:text-emerald-400"
                                            wire:navigate
                                        >
                                            {{ $listing->street_address ?? __('Address unavailable') }}
                                        </a>
                                        <p class="text-xs text-slate-500 dark:text-zinc-400">
                                            {{ $listing->city }}, {{ $listing->province }}
                                        </p>
                                        <p class="text-xs text-slate-400 dark:text-zinc-500">
                                            MLS# {{ $listing->mls_number }}
                                        </p>
                                    </div>
                                </div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-200 dark:divide-zinc-800">
                    @foreach ($this->comparisonFields as $field)
                        <tr class="hover:bg-slate-50 dark:hover:bg-zinc-900/50">
                            <td class="sticky left-0 z-10 bg-white py-3 pr-4 text-sm font-medium text-slate-700 dark:bg-zinc-950 dark:text-zinc-300">
                                {{ $field['label'] }}
                            </td>
                            @foreach ($this->listings as $listing)
                                @php
                                    $value = $field['type'] === 'relation'
                                        ? data_get($listing, $field['key'])
                                        : $listing->{$field['key']};

                                    if ($field['key'] === 'price_change') {
                                        $value = $listing->list_price && $listing->original_list_price
                                            ? $listing->list_price - $listing->original_list_price
                                            : null;
                                    }
                                @endphp
                                <td class="px-4 py-3 text-center text-sm text-slate-900 dark:text-zinc-100">
                                    @switch($field['type'])
                                        @case('currency')
                                            @if ($value !== null)
                                                <span class="font-semibold">
                                                    {{ \App\Support\ListingPresentation::currency($value) }}
                                                </span>
                                            @else
                                                <span class="text-slate-400 dark:text-zinc-500">—</span>
                                            @endif
                                            @break
                                        @case('currency_change')
                                            @if ($value !== null)
                                                @if ($value < 0)
                                                    <span class="inline-flex items-center font-semibold text-green-600 dark:text-green-400">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="mr-1 h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181" /></svg>
                                                        {{ \App\Support\ListingPresentation::currency(abs($value)) }}
                                                    </span>
                                                @elseif ($value > 0)
                                                    <span class="inline-flex items-center font-semibold text-red-600 dark:text-red-400">
                                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="mr-1 h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" /></svg>
                                                        +{{ \App\Support\ListingPresentation::currency(abs($value)) }}
                                                    </span>
                                                @else
                                                    <span class="text-slate-500 dark:text-zinc-400">{{ __('No change') }}</span>
                                                @endif
                                            @else
                                                <span class="text-slate-400 dark:text-zinc-500">—</span>
                                            @endif
                                            @break
                                        @case('numeric')
                                            <span class="font-medium">
                                                {{ \App\Support\ListingPresentation::numeric($value) }}
                                            </span>
                                            @break
                                        @case('price_per_sqft')
                                            <span class="font-semibold">
                                                {{ \App\Support\ListingPresentation::pricePerSqft($listing->list_price, $listing->square_feet) }}
                                            </span>
                                            @break
                                        @case('status')
                                            @if ($value)
                                                <flux:badge color="{{ \App\Support\ListingPresentation::statusBadge($value) }}" size="sm">
                                                    {{ $value }}
                                                </flux:badge>
                                            @else
                                                <span class="text-slate-400 dark:text-zinc-500">—</span>
                                            @endif
                                            @break
                                        @default
                                            @if ($value !== null && $value !== '')
                                                {{ $value }}
                                            @else
                                                <span class="text-slate-400 dark:text-zinc-500">—</span>
                                            @endif
                                    @endswitch
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td class="sticky left-0 z-10 bg-white py-4 pr-4 dark:bg-zinc-950"></td>
                        @foreach ($this->listings as $listing)
                            <td class="px-4 py-4 text-center">
                                <flux:button
                                    variant="primary"
                                    size="sm"
                                    :href="route('listings.show', $listing)"
                                    wire:navigate
                                >
                                    {{ __('View details') }}
                                </flux:button>
                            </td>
                        @endforeach
                    </tr>
                </tfoot>
            </table>
        </div>
    @else
        <div class="mt-12">
            <flux:callout class="rounded-2xl">
                <flux:callout.heading>{{ __('No listings selected for comparison') }}</flux:callout.heading>
                <flux:callout.text>
                    {{ __('Browse listings and click the compare button to add properties to your comparison.') }}
                </flux:callout.text>
                <flux:button variant="primary" :href="route('listings.index')" class="mt-4" wire:navigate>
                    {{ __('Browse listings') }}
                </flux:button>
            </flux:callout>
        </div>
    @endif
</section>
