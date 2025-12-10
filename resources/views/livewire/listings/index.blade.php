<?php

use App\Models\Listing;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.site', ['title' => 'Current Listings'])] class extends Component {
    use WithPagination;

    private const PER_PAGE_OPTIONS = [12, 24, 48];

    private const SORT_OPTIONS = [
        'modified_at_desc' => 'Recently Updated',
        'listed_at_desc' => 'Recently Listed',
        'price_asc' => 'Price: Low to High',
        'price_desc' => 'Price: High to Low',
        'days_on_market_asc' => 'Days on Market: Low to High',
        'days_on_market_desc' => 'Days on Market: High to Low',
    ];

    protected string $paginationTheme = 'tailwind';

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'municipality', except: '')]
    public string $municipalityId = '';

    #[Url(as: 'type', except: '')]
    public string $propertyType = '';

    #[Url(as: 'min_price', except: '')]
    public string $minPrice = '';

    #[Url(as: 'max_price', except: '')]
    public string $maxPrice = '';

    #[Url(as: 'beds', except: '')]
    public string $minBedrooms = '';

    #[Url(as: 'baths', except: '')]
    public string $minBathrooms = '';

    #[Url(as: 'sort', except: 'modified_at_desc')]
    public string $sortBy = 'modified_at_desc';

    #[Url(as: 'per', except: '12')]
    public string $perPage = '12';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function updatedMunicipalityId(): void
    {
        $this->resetPage();
    }

    public function updatedPropertyType(): void
    {
        $this->resetPage();
    }

    public function updatedMinPrice(): void
    {
        $this->resetPage();
    }

    public function updatedMaxPrice(): void
    {
        $this->resetPage();
    }

    public function updatedMinBedrooms(): void
    {
        $this->resetPage();
    }

    public function updatedMinBathrooms(): void
    {
        $this->resetPage();
    }

    public function updatedSortBy(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = (string) $this->resolvePerPage((int) $this->perPage);
        $this->resetPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->municipalityId = '';
        $this->propertyType = '';
        $this->minPrice = '';
        $this->maxPrice = '';
        $this->minBedrooms = '';
        $this->minBathrooms = '';
        $this->sortBy = 'modified_at_desc';
        $this->perPage = (string) self::PER_PAGE_OPTIONS[0];
        $this->resetPage();
    }

    #[Computed]
    public function listings(): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage((int) $this->perPage);
        $municipalityId = $this->municipalityFilter();
        $minPrice = $this->parsePrice($this->minPrice);
        $maxPrice = $this->parsePrice($this->maxPrice);
        $minBedrooms = $this->parseInteger($this->minBedrooms);
        $minBathrooms = $this->parseFloat($this->minBathrooms);

        $query = Listing::query()
            ->visible()
            ->with(['source:id,name', 'municipality:id,name', 'media'])
            ->when($this->search !== '', function (Builder $builder): void {
                $builder->where(function (Builder $query): void {
                    $query
                        ->where('mls_number', 'like', '%' . $this->search . '%')
                        ->orWhere('street_address', 'like', '%' . $this->search . '%')
                        ->orWhere('city', 'like', '%' . $this->search . '%')
                        ->orWhere('postal_code', 'like', '%' . $this->search . '%');
                });
            })
            ->when($this->status !== '', fn (Builder $builder): Builder => $builder->where('display_status', $this->status))
            ->when($municipalityId !== null, fn (Builder $builder): Builder => $builder->where('municipality_id', $municipalityId))
            ->when($this->propertyType !== '', fn (Builder $builder): Builder => $builder->where('property_type', $this->propertyType))
            ->when($minPrice !== null, fn (Builder $builder): Builder => $builder->where('list_price', '>=', $minPrice))
            ->when($maxPrice !== null, fn (Builder $builder): Builder => $builder->where('list_price', '<=', $maxPrice))
            ->when($minBedrooms !== null, fn (Builder $builder): Builder => $builder->where('bedrooms', '>=', $minBedrooms))
            ->when($minBathrooms !== null, fn (Builder $builder): Builder => $builder->where('bathrooms', '>=', $minBathrooms));

        $this->applySorting($query);

        return $query->paginate($perPage);
    }

    #[Computed]
    public function availableStatuses(): SupportCollection
    {
        return Listing::query()
            ->visible()
            ->select('display_status')
            ->distinct()
            ->whereNotNull('display_status')
            ->orderBy('display_status')
            ->pluck('display_status');
    }

    #[Computed]
    public function municipalities(): Collection
    {
        return Municipality::query()
            ->whereHas('listings', fn (Builder $query) => $query->visible())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function propertyTypes(): SupportCollection
    {
        return Listing::query()
            ->visible()
            ->select('property_type')
            ->distinct()
            ->whereNotNull('property_type')
            ->orderBy('property_type')
            ->pluck('property_type');
    }

    #[Computed]
    public function sortOptions(): array
    {
        return self::SORT_OPTIONS;
    }

    #[Computed]
    public function perPageOptions(): array
    {
        return self::PER_PAGE_OPTIONS;
    }

    #[Computed]
    public function hasActiveFilters(): bool
    {
        return $this->search !== ''
            || $this->status !== ''
            || $this->municipalityId !== ''
            || $this->propertyType !== ''
            || $this->minPrice !== ''
            || $this->maxPrice !== ''
            || $this->minBedrooms !== ''
            || $this->minBathrooms !== '';
    }

    #[Computed]
    public function currentFilters(): array
    {
        return array_filter([
            'q' => $this->search,
            'status' => $this->status,
            'municipality' => $this->municipalityId,
            'type' => $this->propertyType,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'beds' => $this->minBedrooms,
            'baths' => $this->minBathrooms,
        ], fn ($value) => $value !== '');
    }

    private function municipalityFilter(): ?int
    {
        if ($this->municipalityId === null || $this->municipalityId === '') {
            return null;
        }

        return (int) $this->municipalityId;
    }

    private function resolvePerPage(int $value): int
    {
        foreach (self::PER_PAGE_OPTIONS as $option) {
            if ($option === $value) {
                return $option;
            }
        }

        return self::PER_PAGE_OPTIONS[0];
    }

    private function parsePrice(string $value): ?float
    {
        if ($value === '') {
            return null;
        }

        $cleaned = preg_replace('/[^0-9.]/', '', $value);

        if ($cleaned === '' || $cleaned === null) {
            return null;
        }

        $price = (float) $cleaned;

        return $price > 0 ? $price : null;
    }

    private function parseInteger(string $value): ?int
    {
        if ($value === '') {
            return null;
        }

        $int = (int) $value;

        return $int > 0 ? $int : null;
    }

    private function parseFloat(string $value): ?float
    {
        if ($value === '') {
            return null;
        }

        $float = (float) $value;

        return $float > 0 ? $float : null;
    }

    private function applySorting(Builder $query): void
    {
        match ($this->sortBy) {
            'listed_at_desc' => $query->orderByDesc('listed_at')->orderByDesc('id'),
            'price_asc' => $query->orderBy('list_price')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('list_price')->orderByDesc('id'),
            'days_on_market_asc' => $query->orderByDesc('listed_at')->orderByDesc('id'),
            'days_on_market_desc' => $query->orderBy('listed_at')->orderByDesc('id'),
            default => $query->orderByDesc('modified_at')->orderByDesc('id'),
        };
    }
}; ?>

<section class="mx-auto max-w-7xl px-6 py-12 lg:px-8">
    <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-2">
            <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">
                {{ __('Current listings') }}
            </h1>
            <p class="text-sm text-slate-600 dark:text-zinc-400">
                {{ __('Explore available power-of-sale properties across Ontario, refreshed with the latest market activity.') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <flux:badge color="blue">
                {{ trans_choice(':count listing|:count listings', $this->listings->total(), ['count' => number_format($this->listings->total())]) }}
            </flux:badge>

            @auth
                <flux:button
                    variant="primary"
                    icon="bell"
                    size="sm"
                    :href="route('saved-searches.create', $this->currentFilters)"
                    wire:navigate
                >
                    {{ __('Save this search') }}
                </flux:button>
            @endauth
        </div>
    </div>

    <!-- Filters -->
    <div class="mt-8 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
        <div class="flex items-center justify-between mb-4">
            <flux:heading size="sm">{{ __('Filter listings') }}</flux:heading>

            @if ($this->hasActiveFilters)
                <flux:button
                    variant="subtle"
                    size="sm"
                    icon="x-mark"
                    wire:click="resetFilters"
                >
                    {{ __('Clear filters') }}
                </flux:button>
            @endif
        </div>

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input
                wire:model.live.debounce.400ms="search"
                :label="__('Search')"
                icon="magnifying-glass"
                :placeholder="__('Address, city, or MLS #')"
            />

            <flux:select
                wire:model.live="municipalityId"
                :label="__('Municipality')"
            >
                <flux:select.option value="">{{ __('All municipalities') }}</flux:select.option>
                @foreach ($this->municipalities as $municipality)
                    <flux:select.option value="{{ $municipality->id }}">{{ $municipality->name }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select
                wire:model.live="propertyType"
                :label="__('Property type')"
            >
                <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                @foreach ($this->propertyTypes as $type)
                    <flux:select.option value="{{ $type }}">{{ $type }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select
                wire:model.live="status"
                :label="__('Status')"
            >
                <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                @foreach ($this->availableStatuses as $statusOption)
                    <flux:select.option value="{{ $statusOption }}">{{ $statusOption }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <flux:input
                wire:model.live.debounce.500ms="minPrice"
                :label="__('Min price')"
                icon="currency-dollar"
                :placeholder="__('e.g. 200000')"
                inputmode="numeric"
            />

            <flux:input
                wire:model.live.debounce.500ms="maxPrice"
                :label="__('Max price')"
                icon="currency-dollar"
                :placeholder="__('e.g. 500000')"
                inputmode="numeric"
            />

            <flux:select
                wire:model.live="minBedrooms"
                :label="__('Bedrooms')"
            >
                <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                @foreach ([1, 2, 3, 4, 5] as $beds)
                    <flux:select.option value="{{ $beds }}">{{ $beds }}+</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select
                wire:model.live="minBathrooms"
                :label="__('Bathrooms')"
            >
                <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                @foreach ([1, 1.5, 2, 2.5, 3, 4] as $baths)
                    <flux:select.option value="{{ $baths }}">{{ $baths }}+</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        <div class="mt-4 flex flex-wrap items-center justify-between gap-4 border-t border-slate-200 pt-4 dark:border-zinc-800">
            <flux:select
                wire:model.live="sortBy"
                :label="__('Sort by')"
                class="w-full sm:w-auto sm:min-w-[200px]"
            >
                @foreach ($this->sortOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ __($label) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:select
                wire:model.live="perPage"
                :label="__('Per page')"
                class="w-full sm:w-auto"
            >
                @foreach ($this->perPageOptions as $option)
                    <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
    </div>

    <!-- Listings Grid -->
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
        @forelse ($this->listings as $listing)
            @php
                /** @var \App\Models\Listing $listing */
                $primaryMedia = $listing->media->firstWhere('is_primary', true) ?? $listing->media->first();
                $address = $listing->street_address ?? __('Address unavailable');
                $location = collect([$listing->city, $listing->province, $listing->postal_code])->filter()->implode(', ');
            @endphp

            <a
                href="{{ route('listings.show', $listing) }}"
                class="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 dark:border-zinc-800 dark:bg-zinc-900/70"
                wire:navigate
            >
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
                            <p class="text-base font-semibold text-slate-900 dark:text-white">
                                {{ \App\Support\ListingPresentation::currency($listing->list_price) }}
                            </p>
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
                            <span class="font-medium">{{ __('MLS number') }}</span>
                            <span>{{ $listing->mls_number ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ __('Days on market') }}</span>
                            <span>{{ $listing->days_on_market ?? '—' }}</span>
                        </div>
                        <div class="flex items-center justify-between">
                            <span class="font-medium">{{ __('Last modified') }}</span>
                            <span>{{ optional($listing->modified_at)?->diffForHumans() ?? __('Unknown') }}</span>
                        </div>
                    </div>
                </div>
            </a>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <flux:callout class="rounded-2xl">
                    <flux:callout.heading>{{ __('No listings match your filters') }}</flux:callout.heading>
                    <flux:callout.text>
                        @if ($this->hasActiveFilters)
                            {{ __('Try adjusting your search criteria or clearing the filters to see more results.') }}
                        @else
                            {{ __('Check back soon—new foreclosure opportunities will appear here as they are ingested from MLS feeds.') }}
                        @endif
                    </flux:callout.text>
                    @if ($this->hasActiveFilters)
                        <flux:button variant="primary" wire:click="resetFilters" class="mt-4">
                            {{ __('Clear all filters') }}
                        </flux:button>
                    @endif
                </flux:callout>
            </div>
        @endforelse
    </div>

    <div class="mt-10">
        {{ $this->listings->onEachSide(1)->links() }}
    </div>
</section>
