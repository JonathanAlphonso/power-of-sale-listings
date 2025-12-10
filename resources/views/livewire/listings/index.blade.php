<?php

use App\Models\Listing;
use App\Models\Municipality;
use App\Support\MarketStatistics;
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
    private const MAX_COMPARE = 4;

    private const SORT_OPTIONS = [
        'modified_at_desc' => 'Recently Updated',
        'listed_at_desc' => 'Recently Listed',
        'price_asc' => 'Price: Low to High',
        'price_desc' => 'Price: High to Low',
        'days_on_market_asc' => 'Days on Market: Low to High',
        'days_on_market_desc' => 'Days on Market: High to Low',
    ];

    private const QUICK_FILTERS = [
        'new_this_week' => [
            'label' => 'New This Week',
            'icon' => 'sparkles',
            'color' => 'emerald',
        ],
        'price_reduced' => [
            'label' => 'Price Reduced',
            'icon' => 'arrow-trending-down',
            'color' => 'green',
        ],
        'under_300k' => [
            'label' => 'Under $300K',
            'icon' => 'currency-dollar',
            'color' => 'blue',
        ],
        'under_500k' => [
            'label' => 'Under $500K',
            'icon' => 'currency-dollar',
            'color' => 'violet',
        ],
        'houses_only' => [
            'label' => 'Houses Only',
            'icon' => 'home',
            'color' => 'amber',
        ],
        'condos_only' => [
            'label' => 'Condos Only',
            'icon' => 'building-office',
            'color' => 'sky',
        ],
    ];

    protected string $paginationTheme = 'tailwind';

    /** @var array<int> */
    public array $compareList = [];

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

    #[Url(as: 'quick', except: '')]
    public string $quickFilter = '';

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
        $this->quickFilter = '';
        $this->resetPage();
    }

    public function applyQuickFilter(string $filter): void
    {
        // Toggle off if same filter clicked
        if ($this->quickFilter === $filter) {
            $this->quickFilter = '';
            $this->resetFilters();
            return;
        }

        // Reset all filters first
        $this->search = '';
        $this->status = '';
        $this->municipalityId = '';
        $this->propertyType = '';
        $this->minPrice = '';
        $this->maxPrice = '';
        $this->minBedrooms = '';
        $this->minBathrooms = '';
        $this->sortBy = 'modified_at_desc';

        // Apply specific quick filter
        $this->quickFilter = $filter;

        match ($filter) {
            'new_this_week' => $this->sortBy = 'listed_at_desc',
            'price_reduced' => null, // Handled in query
            'under_300k' => $this->maxPrice = '300000',
            'under_500k' => $this->maxPrice = '500000',
            'houses_only' => $this->propertyType = 'Detached',
            'condos_only' => $this->propertyType = 'Condo Apt',
            default => null,
        };

        $this->resetPage();
    }

    public function toggleCompare(int $id): void
    {
        if (in_array($id, $this->compareList, true)) {
            $this->compareList = array_values(array_filter(
                $this->compareList,
                fn ($listingId) => $listingId !== $id
            ));
        } elseif (count($this->compareList) < self::MAX_COMPARE) {
            $this->compareList[] = $id;
        }
    }

    public function clearCompareList(): void
    {
        $this->compareList = [];
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $municipalityId = $this->municipalityFilter();
        $minPrice = $this->parsePrice($this->minPrice);
        $maxPrice = $this->parsePrice($this->maxPrice);
        $minBedrooms = $this->parseInteger($this->minBedrooms);
        $minBathrooms = $this->parseFloat($this->minBathrooms);

        $query = Listing::query()
            ->visible()
            ->with(['source:id,name', 'municipality:id,name'])
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

        // Limit export to 500 listings for performance
        $listings = $query->limit(500)->get();

        $filename = 'power-of-sale-listings-' . now()->format('Y-m-d') . '.csv';

        return response()->streamDownload(function () use ($listings) {
            $handle = fopen('php://output', 'w');

            // CSV header
            fputcsv($handle, [
                'MLS Number',
                'Address',
                'City',
                'Province',
                'Postal Code',
                'List Price',
                'Original Price',
                'Price Change',
                'Price/Sqft',
                'Bedrooms',
                'Bathrooms',
                'Square Feet',
                'Property Type',
                'Status',
                'Days on Market',
                'Municipality',
                'Source',
                'Listed Date',
                'Last Updated',
                'URL',
            ]);

            foreach ($listings as $listing) {
                $priceChange = null;
                if ($listing->list_price && $listing->original_list_price) {
                    $priceChange = $listing->list_price - $listing->original_list_price;
                }

                $pricePerSqft = null;
                if ($listing->list_price && $listing->square_feet && $listing->square_feet > 0) {
                    $pricePerSqft = round($listing->list_price / $listing->square_feet, 2);
                }

                fputcsv($handle, [
                    $listing->mls_number,
                    $listing->street_address,
                    $listing->city,
                    $listing->province,
                    $listing->postal_code,
                    $listing->list_price,
                    $listing->original_list_price,
                    $priceChange,
                    $pricePerSqft,
                    $listing->bedrooms,
                    $listing->bathrooms,
                    $listing->square_feet,
                    $listing->property_type,
                    $listing->display_status,
                    $listing->days_on_market,
                    $listing->municipality?->name,
                    $listing->source?->name,
                    $listing->listed_at?->format('Y-m-d'),
                    $listing->modified_at?->format('Y-m-d H:i:s'),
                    route('listings.show', $listing),
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    #[Computed]
    public function compareUrl(): string
    {
        return route('listings.compare', ['ids' => implode(',', $this->compareList)]);
    }

    #[Computed]
    public function canAddToCompare(): bool
    {
        return count($this->compareList) < self::MAX_COMPARE;
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

        // Apply special quick filter conditions
        $this->applyQuickFilterConditions($query);

        $this->applySorting($query);

        return $query->paginate($perPage);
    }

    private function applyQuickFilterConditions(Builder $query): void
    {
        if ($this->quickFilter === '') {
            return;
        }

        match ($this->quickFilter) {
            'new_this_week' => $query->where('listed_at', '>=', now()->subWeek()),
            'price_reduced' => $query->whereColumn('list_price', '<', 'original_list_price')
                                     ->whereNotNull('original_list_price'),
            default => null,
        };
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
    public function quickFilterOptions(): array
    {
        return self::QUICK_FILTERS;
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
            || $this->minBathrooms !== ''
            || $this->quickFilter !== '';
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

    #[Computed]
    public function statistics(): array
    {
        return MarketStatistics::getStatistics($this->currentFiltersForStats());
    }

    #[Computed]
    public function weeklyTrends(): array
    {
        return MarketStatistics::getWeeklyTrends($this->currentFiltersForStats());
    }

    #[Computed]
    public function dataFreshness(): array
    {
        return MarketStatistics::getDataFreshness();
    }

    private function currentFiltersForStats(): array
    {
        return array_filter([
            'search' => $this->search,
            'status' => $this->status,
            'municipality_id' => $this->municipalityFilter(),
            'property_type' => $this->propertyType,
            'min_price' => $this->parsePrice($this->minPrice),
            'max_price' => $this->parsePrice($this->maxPrice),
            'min_bedrooms' => $this->parseInteger($this->minBedrooms),
            'min_bathrooms' => $this->parseFloat($this->minBathrooms),
        ], fn ($value) => $value !== null && $value !== '');
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

            <flux:button
                variant="subtle"
                icon="arrow-down-tray"
                size="sm"
                wire:click="exportCsv"
                title="{{ __('Export up to 500 listings as CSV') }}"
            >
                {{ __('Export CSV') }}
            </flux:button>

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

    <!-- Quick Filters -->
    <div class="mt-6 flex flex-wrap items-center gap-2">
        <span class="text-sm font-medium text-slate-600 dark:text-zinc-400">{{ __('Quick filters:') }}</span>
        @foreach ($this->quickFilterOptions as $key => $filter)
            @php
                $isActive = $this->quickFilter === $key;
                $colors = [
                    'emerald' => $isActive ? 'bg-emerald-500 text-white border-emerald-500' : 'border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-900/30',
                    'green' => $isActive ? 'bg-green-500 text-white border-green-500' : 'border-green-200 text-green-700 hover:bg-green-50 dark:border-green-800 dark:text-green-400 dark:hover:bg-green-900/30',
                    'blue' => $isActive ? 'bg-blue-500 text-white border-blue-500' : 'border-blue-200 text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-900/30',
                    'violet' => $isActive ? 'bg-violet-500 text-white border-violet-500' : 'border-violet-200 text-violet-700 hover:bg-violet-50 dark:border-violet-800 dark:text-violet-400 dark:hover:bg-violet-900/30',
                    'amber' => $isActive ? 'bg-amber-500 text-white border-amber-500' : 'border-amber-200 text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-900/30',
                    'sky' => $isActive ? 'bg-sky-500 text-white border-sky-500' : 'border-sky-200 text-sky-700 hover:bg-sky-50 dark:border-sky-800 dark:text-sky-400 dark:hover:bg-sky-900/30',
                ];
                $colorClass = $colors[$filter['color']] ?? $colors['blue'];
            @endphp
            <button
                wire:click="applyQuickFilter('{{ $key }}')"
                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition {{ $colorClass }}"
            >
                <flux:icon name="{{ $filter['icon'] }}" class="h-4 w-4" />
                {{ __($filter['label']) }}
                @if ($isActive)
                    <flux:icon name="x-mark" class="h-3.5 w-3.5 ml-0.5" />
                @endif
            </button>
        @endforeach
    </div>

    <!-- Filters -->
    <div class="mt-4 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
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

    <!-- Statistics Summary -->
    @if ($this->statistics['total'] > 0)
        <div class="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4 xl:grid-cols-6">
            <div class="rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm dark:border-zinc-800 dark:from-zinc-900 dark:to-zinc-900/70">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-violet-100 text-violet-600 dark:bg-violet-900/30 dark:text-violet-400">
                        <flux:icon name="home" class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Total listings') }}</p>
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                {{ number_format($this->statistics['total']) }}
                            </p>
                            @if ($this->statistics['new_this_week'] > 0)
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-1.5 py-0.5 text-xs font-medium text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400">
                                    +{{ $this->statistics['new_this_week'] }} {{ __('this week') }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm dark:border-zinc-800 dark:from-zinc-900 dark:to-zinc-900/70">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-100 text-blue-600 dark:bg-blue-900/30 dark:text-blue-400">
                        <flux:icon name="chart-bar" class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Median price') }}</p>
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                {{ $this->statistics['median_price'] ? \App\Support\ListingPresentation::currency($this->statistics['median_price']) : '—' }}
                            </p>
                            @if ($this->weeklyTrends['price_change_percent'] !== null)
                                @if ($this->weeklyTrends['price_change_percent'] < 0)
                                    <span class="inline-flex items-center rounded-full bg-green-100 px-1.5 py-0.5 text-xs font-medium text-green-700 dark:bg-green-900/30 dark:text-green-400" title="{{ __('Week over week change') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="mr-0.5 h-3 w-3"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181" /></svg>
                                        {{ number_format(abs($this->weeklyTrends['price_change_percent']), 1) }}%
                                    </span>
                                @elseif ($this->weeklyTrends['price_change_percent'] > 0)
                                    <span class="inline-flex items-center rounded-full bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400" title="{{ __('Week over week change') }}">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="mr-0.5 h-3 w-3"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" /></svg>
                                        {{ number_format(abs($this->weeklyTrends['price_change_percent']), 1) }}%
                                    </span>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm dark:border-zinc-800 dark:from-zinc-900 dark:to-zinc-900/70">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-100 text-emerald-600 dark:bg-emerald-900/30 dark:text-emerald-400">
                        <flux:icon name="currency-dollar" class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Price range') }}</p>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">
                            @if ($this->statistics['min_price'] && $this->statistics['max_price'])
                                {{ \App\Support\ListingPresentation::currencyShort($this->statistics['min_price']) }} – {{ \App\Support\ListingPresentation::currencyShort($this->statistics['max_price']) }}
                            @else
                                —
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm dark:border-zinc-800 dark:from-zinc-900 dark:to-zinc-900/70">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                        <flux:icon name="clock" class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Avg. days on market') }}</p>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $this->statistics['avg_days_on_market'] !== null ? $this->statistics['avg_days_on_market'] . ' ' . __('days') : '—' }}
                        </p>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm dark:border-zinc-800 dark:from-zinc-900 dark:to-zinc-900/70">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-green-100 text-green-600 dark:bg-green-900/30 dark:text-green-400">
                        <flux:icon name="arrow-trending-down" class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Price reductions') }}</p>
                        <div class="flex items-center gap-2">
                            <p class="text-sm font-semibold text-slate-900 dark:text-white">
                                {{ number_format($this->statistics['price_reductions']) }}
                            </p>
                            @if ($this->statistics['avg_price_reduction_percent'])
                                <span class="text-xs text-slate-500 dark:text-zinc-400">
                                    ({{ __('avg') }} {{ $this->statistics['avg_price_reduction_percent'] }}%)
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-4 shadow-sm dark:border-zinc-800 dark:from-zinc-900 dark:to-zinc-900/70">
                <div class="flex items-center gap-3">
                    @php
                        $freshnessStatus = $this->dataFreshness['status'];
                        $freshnessColors = [
                            'fresh' => ['bg' => 'bg-emerald-100 dark:bg-emerald-900/30', 'text' => 'text-emerald-600 dark:text-emerald-400'],
                            'recent' => ['bg' => 'bg-blue-100 dark:bg-blue-900/30', 'text' => 'text-blue-600 dark:text-blue-400'],
                            'stale' => ['bg' => 'bg-amber-100 dark:bg-amber-900/30', 'text' => 'text-amber-600 dark:text-amber-400'],
                            'outdated' => ['bg' => 'bg-red-100 dark:bg-red-900/30', 'text' => 'text-red-600 dark:text-red-400'],
                            'unknown' => ['bg' => 'bg-slate-100 dark:bg-zinc-800', 'text' => 'text-slate-500 dark:text-zinc-400'],
                        ];
                        $colors = $freshnessColors[$freshnessStatus] ?? $freshnessColors['unknown'];
                    @endphp
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg {{ $colors['bg'] }} {{ $colors['text'] }}">
                        <flux:icon name="arrow-path" class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Data freshness') }}</p>
                        <p class="text-sm font-semibold text-slate-900 dark:text-white">
                            {{ $this->dataFreshness['label'] }}
                        </p>
                    </div>
                </div>
            </div>
        </div>
    @endif

    <!-- Compare Bar (sticky) -->
    @if (count($this->compareList) > 0)
        <div class="sticky top-0 z-20 mt-6 rounded-xl border border-emerald-200 bg-emerald-50 p-4 shadow-lg dark:border-emerald-800 dark:bg-emerald-900/30">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <flux:icon name="scale" class="h-5 w-5 text-emerald-600 dark:text-emerald-400" />
                    <span class="font-medium text-emerald-800 dark:text-emerald-200">
                        {{ trans_choice(':count listing selected|:count listings selected', count($this->compareList), ['count' => count($this->compareList)]) }}
                    </span>
                    @if (!$this->canAddToCompare)
                        <span class="text-sm text-emerald-600 dark:text-emerald-400">
                            ({{ __('max :max', ['max' => 4]) }})
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    <flux:button
                        variant="subtle"
                        size="sm"
                        icon="x-mark"
                        wire:click="clearCompareList"
                    >
                        {{ __('Clear') }}
                    </flux:button>
                    <flux:button
                        variant="primary"
                        size="sm"
                        icon="scale"
                        :href="$this->compareUrl"
                        wire:navigate
                    >
                        {{ __('Compare') }}
                    </flux:button>
                </div>
            </div>
        </div>
    @endif

    <!-- Listings Grid -->
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
        @forelse ($this->listings as $listing)
            @php
                /** @var \App\Models\Listing $listing */
                $primaryMedia = $listing->media->firstWhere('is_primary', true) ?? $listing->media->first();
                $address = $listing->street_address ?? __('Address unavailable');
                $location = collect([$listing->city, $listing->province, $listing->postal_code])->filter()->implode(', ');
                $isInCompare = in_array($listing->id, $this->compareList, true);
            @endphp

            <div class="group relative flex h-full flex-col overflow-hidden rounded-2xl border {{ $isInCompare ? 'border-emerald-400 ring-2 ring-emerald-400/50' : 'border-slate-200' }} bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900/70 {{ $isInCompare ? 'dark:border-emerald-600 dark:ring-emerald-600/50' : '' }}">
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

                <!-- Action buttons -->
                <div class="absolute top-3 right-3 flex items-center gap-2">
                    @auth
                        <livewire:favorites.toggle-button :listing-id="$listing->id" :key="'fav-'.$listing->id" />
                    @endauth

                    <!-- Compare checkbox -->
                    <button
                        wire:click="toggleCompare({{ $listing->id }})"
                        class="flex h-8 w-8 items-center justify-center rounded-full {{ $isInCompare ? 'bg-emerald-500 text-white' : 'bg-white/90 text-slate-600 hover:bg-emerald-500 hover:text-white' }} shadow-lg transition dark:bg-zinc-800/90 dark:text-zinc-300 {{ $isInCompare ? '' : 'dark:hover:bg-emerald-500 dark:hover:text-white' }}"
                        title="{{ $isInCompare ? __('Remove from comparison') : __('Add to comparison') }}"
                        @if (!$this->canAddToCompare && !$isInCompare) disabled @endif
                    >
                        @if ($isInCompare)
                            <flux:icon name="check" class="h-5 w-5" />
                        @else
                            <flux:icon name="scale" class="h-4 w-4" />
                        @endif
                    </button>
                </div>

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
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="mr-0.5 h-3 w-3"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6 9 12.75l4.286-4.286a11.948 11.948 0 0 1 4.306 6.43l.776 2.898m0 0 3.182-5.511m-3.182 5.51-5.511-3.181" /></svg>
                                            {{ number_format(abs($percentChange), 0) }}%
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-red-100 px-1.5 py-0.5 text-xs font-medium text-red-700 dark:bg-red-900/30 dark:text-red-400" title="{{ __('Increased from') }} {{ \App\Support\ListingPresentation::currency($listing->original_list_price) }}">
                                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="mr-0.5 h-3 w-3"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" /></svg>
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
            </div>
        @empty
            <div class="sm:col-span-2 lg:col-span-3">
                <div class="rounded-2xl border border-slate-200 bg-gradient-to-br from-slate-50 to-white p-8 text-center dark:border-zinc-800 dark:from-zinc-900 dark:to-zinc-900/70">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-slate-100 dark:bg-zinc-800">
                        <flux:icon name="magnifying-glass" class="h-8 w-8 text-slate-400 dark:text-zinc-500" />
                    </div>

                    <h3 class="mt-4 text-lg font-semibold text-slate-900 dark:text-white">
                        {{ __('No listings match your filters') }}
                    </h3>

                    <p class="mt-2 text-sm text-slate-600 dark:text-zinc-400">
                        @if ($this->hasActiveFilters)
                            {{ __('Try adjusting your search criteria or explore one of these popular searches:') }}
                        @else
                            {{ __('Check back soon—new foreclosure opportunities will appear here as they are ingested from MLS feeds.') }}
                        @endif
                    </p>

                    @if ($this->hasActiveFilters)
                        <!-- Quick Filter Suggestions -->
                        <div class="mt-6">
                            <p class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                {{ __('Try these searches') }}
                            </p>
                            <div class="flex flex-wrap justify-center gap-2">
                                @foreach ($this->quickFilterOptions as $key => $filter)
                                    @if ($this->quickFilter !== $key)
                                        @php
                                            $colors = [
                                                'emerald' => 'border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-900/30',
                                                'green' => 'border-green-200 text-green-700 hover:bg-green-50 dark:border-green-800 dark:text-green-400 dark:hover:bg-green-900/30',
                                                'blue' => 'border-blue-200 text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-900/30',
                                                'violet' => 'border-violet-200 text-violet-700 hover:bg-violet-50 dark:border-violet-800 dark:text-violet-400 dark:hover:bg-violet-900/30',
                                                'amber' => 'border-amber-200 text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-900/30',
                                                'sky' => 'border-sky-200 text-sky-700 hover:bg-sky-50 dark:border-sky-800 dark:text-sky-400 dark:hover:bg-sky-900/30',
                                            ];
                                            $colorClass = $colors[$filter['color']] ?? $colors['blue'];
                                        @endphp
                                        <button
                                            wire:click="applyQuickFilter('{{ $key }}')"
                                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition {{ $colorClass }}"
                                        >
                                            <flux:icon name="{{ $filter['icon'] }}" class="h-4 w-4" />
                                            {{ __($filter['label']) }}
                                        </button>
                                    @endif
                                @endforeach
                            </div>
                        </div>

                        <div class="mt-6 flex flex-col items-center gap-3 sm:flex-row sm:justify-center">
                            <flux:button variant="primary" wire:click="resetFilters">
                                <flux:icon name="x-mark" class="mr-1.5 h-4 w-4" />
                                {{ __('Clear all filters') }}
                            </flux:button>

                            @auth
                                <flux:button variant="ghost" :href="route('saved-searches.create', $this->currentFilters)" wire:navigate>
                                    <flux:icon name="bell" class="mr-1.5 h-4 w-4" />
                                    {{ __('Get notified when matching listings appear') }}
                                </flux:button>
                            @endauth
                        </div>
                    @else
                        <div class="mt-6">
                            <p class="mb-3 text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                {{ __('Browse by category') }}
                            </p>
                            <div class="flex flex-wrap justify-center gap-2">
                                @foreach ($this->quickFilterOptions as $key => $filter)
                                    @php
                                        $colors = [
                                            'emerald' => 'border-emerald-200 text-emerald-700 hover:bg-emerald-50 dark:border-emerald-800 dark:text-emerald-400 dark:hover:bg-emerald-900/30',
                                            'green' => 'border-green-200 text-green-700 hover:bg-green-50 dark:border-green-800 dark:text-green-400 dark:hover:bg-green-900/30',
                                            'blue' => 'border-blue-200 text-blue-700 hover:bg-blue-50 dark:border-blue-800 dark:text-blue-400 dark:hover:bg-blue-900/30',
                                            'violet' => 'border-violet-200 text-violet-700 hover:bg-violet-50 dark:border-violet-800 dark:text-violet-400 dark:hover:bg-violet-900/30',
                                            'amber' => 'border-amber-200 text-amber-700 hover:bg-amber-50 dark:border-amber-800 dark:text-amber-400 dark:hover:bg-amber-900/30',
                                            'sky' => 'border-sky-200 text-sky-700 hover:bg-sky-50 dark:border-sky-800 dark:text-sky-400 dark:hover:bg-sky-900/30',
                                        ];
                                        $colorClass = $colors[$filter['color']] ?? $colors['blue'];
                                    @endphp
                                    <button
                                        wire:click="applyQuickFilter('{{ $key }}')"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-sm font-medium transition {{ $colorClass }}"
                                    >
                                        <flux:icon name="{{ $filter['icon'] }}" class="h-4 w-4" />
                                        {{ __($filter['label']) }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endforelse
    </div>

    <div class="mt-10">
        {{ $this->listings->onEachSide(1)->links() }}
    </div>
</section>
