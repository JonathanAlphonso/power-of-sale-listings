<?php

use App\Models\Listing;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Layout('components.layouts.site')] class extends Component {
    use WithPagination;

    private const PER_PAGE = 12;

    protected string $paginationTheme = 'tailwind';

    // Search
    #[Url(as: 'q', except: '')]
    public string $search = '';

    // Status filter (defaults to 'Active' only)
    #[Url(as: 'status', except: ['Active'])]
    public array $statuses = ['Active'];

    // Property filters
    // Property styles grouped by category
    private const RESIDENTIAL_STYLES = [
        'Att/Row/Townhouse',
        'Common Element Condo',
        'Condo Apartment',
        'Condo Townhouse',
        'Detached',
        'Duplex',
        'Farm',
        'Link',
        'Multiplex',
        'Other',
        'Rural Residential',
        'Semi-Detached',
        'Triplex',
        'Vacant Land',
        'Vacant Land Condo',
    ];

    private const COMMERCIAL_STYLES = [
        'Commercial Retail',
        'Industrial',
        'Investment',
        'Land',
        'Office',
        'Sale Of Business',
        'Store W Apt/Office',
    ];

    // Default to residential styles only (empty = residential, selected = custom filter)
    #[Url(as: 'style', except: [])]
    public array $propertyStyles = [];

    #[Url(as: 'class', except: [])]
    public array $propertyClasses = [];

    // Price range
    #[Url(as: 'price_min', except: '')]
    public string $priceMin = '';

    #[Url(as: 'price_max', except: '')]
    public string $priceMax = '';

    // Beds & Baths (supports multiple selections)
    #[Url(as: 'beds', except: [])]
    public array $bedrooms = [];

    #[Url(as: 'baths', except: [])]
    public array $bathrooms = [];

    // Square footage
    #[Url(as: 'sqft_min', except: '')]
    public string $sqftMin = '';

    #[Url(as: 'sqft_max', except: '')]
    public string $sqftMax = '';

    // Lot size
    #[Url(as: 'lot_min', except: '')]
    public string $lotMin = '';

    #[Url(as: 'lot_max', except: '')]
    public string $lotMax = '';

    // Stories
    #[Url(as: 'stories_min', except: '')]
    public string $storiesMin = '';

    #[Url(as: 'stories_max', except: '')]
    public string $storiesMax = '';

    // Financial filters
    #[Url(as: 'tax_max', except: '')]
    public string $taxMax = '';

    #[Url(as: 'fee_max', except: '')]
    public string $feeMax = '';

    // Age filter
    #[Url(as: 'age', except: [])]
    public array $approximateAges = [];

    // Location
    #[Url(as: 'city', except: [])]
    public array $cities = [];

    // Listed since
    #[Url(as: 'listed_since', except: '')]
    public string $listedSince = '';

    // Sort
    #[Url(as: 'sort', except: 'newest')]
    public string $sortBy = 'newest';

    // View mode (grid or map)
    #[Url(as: 'view', except: 'grid')]
    public string $viewMode = 'grid';

    // Initial map position (for "View in Map" links)
    #[Url(as: 'lat', except: '')]
    public string $initialLat = '';

    #[Url(as: 'lng', except: '')]
    public string $initialLng = '';

    #[Url(as: 'zoom', except: '')]
    public string $initialZoom = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updated($property): void
    {
        if ($property !== 'viewMode') {
            $this->resetPage();

            // Dispatch event for map refresh
            if ($this->viewMode === 'map') {
                $this->dispatch('filters-updated', params: $this->currentFilterParams());
            }
        }
    }

    public function currentFilterParams(): array
    {
        // If no styles selected, default to residential only
        $styles = ! empty($this->propertyStyles) ? $this->propertyStyles : self::RESIDENTIAL_STYLES;

        return array_filter([
            'q' => $this->search,
            'status' => $this->statuses,
            'class' => $this->propertyClasses,
            'style' => $styles,
            'price_min' => $this->priceMin,
            'price_max' => $this->priceMax,
            'beds' => $this->bedrooms,
            'baths' => $this->bathrooms,
            'sqft_min' => $this->sqftMin,
            'sqft_max' => $this->sqftMax,
            'lot_min' => $this->lotMin,
            'lot_max' => $this->lotMax,
            'tax_max' => $this->taxMax,
            'fee_max' => $this->feeMax,
            'age' => $this->approximateAges,
            'city' => $this->cities,
            'listed_since' => $this->listedSince,
        ], fn ($value) => $value !== '' && $value !== []);
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'statuses',
            'propertyClasses',
            'propertyStyles',
            'priceMin',
            'priceMax',
            'bedrooms',
            'bathrooms',
            'sqftMin',
            'sqftMax',
            'lotMin',
            'lotMax',
            'storiesMin',
            'storiesMax',
            'taxMax',
            'feeMax',
            'approximateAges',
            'cities',
            'listedSince',
            'sortBy',
        ]);
        $this->resetPage();
    }

    private function buildRoomQuery(Builder $query, string $column, array $values): void
    {
        if (empty($values)) {
            return;
        }

        $query->where(function (Builder $q) use ($column, $values): void {
            foreach ($values as $value) {
                $isMinimum = str_ends_with($value, '+');
                $number = $isMinimum ? rtrim($value, '+') : $value;

                if (is_numeric($number)) {
                    $q->orWhere($column, $isMinimum ? '>=' : '=', (int) $number);
                }
            }
        });
    }

    #[Computed]
    public function listings(): LengthAwarePaginator
    {
        return Listing::query()
            ->visible()
            ->with(['source:id,name', 'municipality:id,name', 'media'])
            // Search
            ->when($this->search !== '', function (Builder $query): void {
                $query->where(function (Builder $q): void {
                    $q->where('mls_number', 'like', '%' . $this->search . '%')
                        ->orWhere('street_address', 'like', '%' . $this->search . '%')
                        ->orWhere('city', 'like', '%' . $this->search . '%')
                        ->orWhere('public_remarks', 'like', '%' . $this->search . '%');
                });
            })
            // Status
            ->when(! empty($this->statuses), fn (Builder $q) => $q->whereIn('display_status', $this->statuses))
            // Property style (default to residential if nothing selected)
            ->where(function (Builder $q): void {
                $styles = ! empty($this->propertyStyles) ? $this->propertyStyles : self::RESIDENTIAL_STYLES;
                $q->whereIn('property_style', $styles);
            })
            // Property class
            ->when(! empty($this->propertyClasses), fn (Builder $q) => $q->whereIn('property_class', $this->propertyClasses))
            // Price range
            ->when($this->priceMin !== '', fn (Builder $q) => $q->where('list_price', '>=', (float) $this->priceMin))
            ->when($this->priceMax !== '', fn (Builder $q) => $q->where('list_price', '<=', (float) $this->priceMax))
            // Beds & Baths
            ->when(! empty($this->bedrooms), fn (Builder $q) => $this->buildRoomQuery($q, 'bedrooms', $this->bedrooms))
            ->when(! empty($this->bathrooms), fn (Builder $q) => $this->buildRoomQuery($q, 'bathrooms', $this->bathrooms))
            // Square footage
            ->when($this->sqftMin !== '', fn (Builder $q) => $q->where('square_feet', '>=', (int) $this->sqftMin))
            ->when($this->sqftMax !== '', fn (Builder $q) => $q->where('square_feet', '<=', (int) $this->sqftMax))
            // Lot size
            ->when($this->lotMin !== '', fn (Builder $q) => $q->where('lot_size_area', '>=', (float) $this->lotMin))
            ->when($this->lotMax !== '', fn (Builder $q) => $q->where('lot_size_area', '<=', (float) $this->lotMax))
            // Stories
            ->when($this->storiesMin !== '', fn (Builder $q) => $q->where('stories', '>=', (int) $this->storiesMin))
            ->when($this->storiesMax !== '', fn (Builder $q) => $q->where('stories', '<=', (int) $this->storiesMax))
            // Financial
            ->when($this->taxMax !== '', fn (Builder $q) => $q->where('tax_annual_amount', '<=', (float) $this->taxMax))
            ->when($this->feeMax !== '', fn (Builder $q) => $q->where('association_fee', '<=', (float) $this->feeMax))
            // Age
            ->when(! empty($this->approximateAges), fn (Builder $q) => $q->whereIn('approximate_age', $this->approximateAges))
            // Location
            ->when(! empty($this->cities), fn (Builder $q) => $q->whereIn('city', $this->cities))
            // Listed since
            ->when($this->listedSince !== '', fn (Builder $q) => $q->where('listed_at', '>=', $this->listedSince))
            // Sorting
            ->when($this->sortBy === 'newest', fn (Builder $q) => $q->latest('modified_at'))
            ->when($this->sortBy === 'oldest', fn (Builder $q) => $q->oldest('modified_at'))
            ->when($this->sortBy === 'price_asc', fn (Builder $q) => $q->orderBy('list_price', 'asc'))
            ->when($this->sortBy === 'price_desc', fn (Builder $q) => $q->orderBy('list_price', 'desc'))
            ->when($this->sortBy === 'beds_desc', fn (Builder $q) => $q->orderBy('bedrooms', 'desc'))
            ->when($this->sortBy === 'sqft_desc', fn (Builder $q) => $q->orderBy('square_feet', 'desc'))
            ->paginate(self::PER_PAGE);
    }

    #[Computed]
    public function activeFilterCount(): int
    {
        $count = 0;
        if ($this->search !== '') $count++;
        // Don't count status if it's just the default ['Active']
        if (! empty($this->statuses) && $this->statuses !== ['Active']) $count++;
        // Count property styles only if changed from default (residential only / empty)
        if (! empty($this->propertyStyles)) $count++;
        if (! empty($this->propertyClasses)) $count++;
        if ($this->priceMin !== '' || $this->priceMax !== '') $count++;
        if (! empty($this->bedrooms)) $count++;
        if (! empty($this->bathrooms)) $count++;
        if ($this->sqftMin !== '' || $this->sqftMax !== '') $count++;
        if ($this->lotMin !== '' || $this->lotMax !== '') $count++;
        if ($this->storiesMin !== '' || $this->storiesMax !== '') $count++;
        if ($this->taxMax !== '') $count++;
        if ($this->feeMax !== '') $count++;
        if (! empty($this->approximateAges)) $count++;
        if (! empty($this->cities)) $count++;
        if ($this->listedSince !== '') $count++;
        return $count;
    }

    #[Computed]
    public function availableStatuses(): SupportCollection
    {
        return Listing::query()
            ->visible()
            ->distinct()
            ->whereNotNull('display_status')
            ->orderBy('display_status')
            ->pluck('display_status');
    }

    #[Computed]
    public function availablePropertyClasses(): SupportCollection
    {
        return Listing::query()
            ->visible()
            ->distinct()
            ->whereNotNull('property_class')
            ->orderBy('property_class')
            ->pluck('property_class');
    }

    #[Computed]
    public function availablePropertyStyles(): SupportCollection
    {
        return Listing::query()
            ->visible()
            ->distinct()
            ->whereNotNull('property_style')
            ->orderBy('property_style')
            ->pluck('property_style')
            ->map(fn ($style) => trim($style));
    }

    #[Computed]
    public function availableCities(): SupportCollection
    {
        return Listing::query()
            ->visible()
            ->distinct()
            ->whereNotNull('city')
            ->orderBy('city')
            ->pluck('city');
    }

    #[Computed]
    public function availableAges(): SupportCollection
    {
        return Listing::query()
            ->visible()
            ->distinct()
            ->whereNotNull('approximate_age')
            ->orderBy('approximate_age')
            ->pluck('approximate_age');
    }

    public function toggleResidentialStyles(): void
    {
        $allSelected = count(array_intersect(self::RESIDENTIAL_STYLES, $this->propertyStyles)) === count(self::RESIDENTIAL_STYLES);

        if ($allSelected) {
            // Remove all residential styles
            $this->propertyStyles = array_values(array_diff($this->propertyStyles, self::RESIDENTIAL_STYLES));
        } else {
            // Add all residential styles
            $this->propertyStyles = array_values(array_unique([...$this->propertyStyles, ...self::RESIDENTIAL_STYLES]));
        }
        $this->resetPage();
    }

    public function toggleCommercialStyles(): void
    {
        $allSelected = count(array_intersect(self::COMMERCIAL_STYLES, $this->propertyStyles)) === count(self::COMMERCIAL_STYLES);

        if ($allSelected) {
            // Remove all commercial styles
            $this->propertyStyles = array_values(array_diff($this->propertyStyles, self::COMMERCIAL_STYLES));
        } else {
            // Add all commercial styles
            $this->propertyStyles = array_values(array_unique([...$this->propertyStyles, ...self::COMMERCIAL_STYLES]));
        }
        $this->resetPage();
    }

    public function selectAllStyles(): void
    {
        // Select all styles (residential + commercial)
        $this->propertyStyles = [...self::RESIDENTIAL_STYLES, ...self::COMMERCIAL_STYLES];
        $this->resetPage();
    }

    public function clearStyleFilters(): void
    {
        // Clear selection (defaults back to residential only)
        $this->propertyStyles = [];
        $this->resetPage();
    }

}; ?>

<x-slot:title>{{ __('Current Listings') }}</x-slot:title>

<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8" x-data="{ showFilters: false }">
    {{-- Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div class="space-y-1">
            <h1 class="text-2xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">
                {{ __('Current listings') }}
            </h1>
            <p class="text-sm text-slate-600 dark:text-zinc-400">
                {{ __('Explore available power-of-sale properties across Ontario.') }}
            </p>
        </div>

        <div class="flex items-center gap-3">
            <flux:badge color="blue">
                {{ trans_choice(':count listing|:count listings', $this->listings->total(), ['count' => number_format($this->listings->total())]) }}
            </flux:badge>

            {{-- View Toggle --}}
            <div class="flex items-center rounded-lg border border-slate-200 p-1 dark:border-zinc-700">
                <button
                    wire:click="$set('viewMode', 'grid')"
                    type="button"
                    class="rounded-md p-2 transition {{ $viewMode === 'grid' ? 'bg-slate-100 text-slate-900 dark:bg-zinc-700 dark:text-white' : 'text-slate-500 hover:text-slate-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
                    title="{{ __('Grid view') }}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                    </svg>
                </button>
                <button
                    wire:click="$set('viewMode', 'map')"
                    type="button"
                    class="rounded-md p-2 transition {{ $viewMode === 'map' ? 'bg-slate-100 text-slate-900 dark:bg-zinc-700 dark:text-white' : 'text-slate-500 hover:text-slate-700 dark:text-zinc-400 dark:hover:text-zinc-200' }}"
                    title="{{ __('Map view') }}"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    {{-- Search & Sort Row --}}
    <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.400ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search by MLS #, address, city, or keywords...')"
                clearable
            />
        </div>

        <flux:select wire:model.live="sortBy" class="w-44">
            <flux:select.option value="newest">{{ __('Newest first') }}</flux:select.option>
            <flux:select.option value="oldest">{{ __('Oldest first') }}</flux:select.option>
            <flux:select.option value="price_asc">{{ __('Price: Low to High') }}</flux:select.option>
            <flux:select.option value="price_desc">{{ __('Price: High to Low') }}</flux:select.option>
            <flux:select.option value="beds_desc">{{ __('Most Bedrooms') }}</flux:select.option>
            <flux:select.option value="sqft_desc">{{ __('Largest') }}</flux:select.option>
        </flux:select>
    </div>

    {{-- Primary Filter Dropdowns --}}
    <div class="mt-4 flex flex-wrap items-center gap-2">
        {{-- Status --}}
        <x-filter-dropdown
            :label="__('Status')"
            :options="$this->availableStatuses->toArray()"
            :selected="$statuses"
            wire-model="statuses"
            :all-label="__('All statuses')"
        />

        {{-- Property Type (Residential/Commercial styles) --}}
        @php
            $residentialStyles = [
                'Att/Row/Townhouse', 'Common Element Condo', 'Condo Apartment', 'Condo Townhouse',
                'Detached', 'Duplex', 'Farm', 'Link', 'Multiplex', 'Other', 'Rural Residential',
                'Semi-Detached', 'Triplex', 'Vacant Land', 'Vacant Land Condo',
            ];
            $commercialStyles = [
                'Commercial Retail', 'Industrial', 'Investment', 'Land', 'Office',
                'Sale Of Business', 'Store W Apt/Office',
            ];

            $selectedStyles = $propertyStyles;
            $isDefault = empty($selectedStyles); // Empty means residential-only default
            $allResidentialSelected = empty($selectedStyles) || count(array_intersect($residentialStyles, $selectedStyles)) === count($residentialStyles);
            $allCommercialSelected = count(array_intersect($commercialStyles, $selectedStyles)) === count($commercialStyles);
            $hasCommercial = count(array_intersect($commercialStyles, $selectedStyles)) > 0;
            $selectedCount = count($selectedStyles);

            if ($isDefault) {
                $typeButtonLabel = __('Residential');
            } elseif ($allResidentialSelected && $allCommercialSelected) {
                $typeButtonLabel = __('All types');
            } elseif ($allCommercialSelected && !$allResidentialSelected) {
                $typeButtonLabel = __('Commercial');
            } elseif ($allResidentialSelected && !$hasCommercial) {
                $typeButtonLabel = __('Residential');
            } elseif ($selectedCount === 1) {
                $typeButtonLabel = $selectedStyles[0];
            } else {
                $typeButtonLabel = $selectedCount . ' ' . __('selected');
            }

            // Highlight when changed from default
            $hasTypeSelection = !$isDefault;
        @endphp
        <flux:dropdown position="bottom" align="start">
            <flux:button
                variant="{{ $hasTypeSelection ? 'primary' : 'outline' }}"
                icon:trailing="chevron-down"
            >
                {{ $typeButtonLabel }}
            </flux:button>

            <flux:menu class="w-72 max-h-96 overflow-y-auto p-2">
                <div class="space-y-0.5">
                    {{-- Quick actions --}}
                    <div class="flex gap-2 px-2 py-1.5 mb-1">
                        <button type="button" wire:click="selectAllStyles" class="text-xs text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300">
                            {{ __('Select all') }}
                        </button>
                        <span class="text-zinc-300 dark:text-zinc-600">|</span>
                        <button type="button" wire:click="clearStyleFilters" class="text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-300">
                            {{ __('Residential only') }}
                        </button>
                    </div>

                    <div class="my-1.5 border-t border-zinc-200 dark:border-zinc-600"></div>

                    {{-- Residential group header --}}
                    <label class="flex items-center gap-2.5 rounded-md px-2 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-100 dark:text-white dark:hover:bg-zinc-600 cursor-pointer transition-colors">
                        <input
                            type="checkbox"
                            {{ $allResidentialSelected ? 'checked' : '' }}
                            wire:click="toggleResidentialStyles"
                            class="size-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 focus:ring-offset-0 dark:border-zinc-500 dark:bg-zinc-700"
                        />
                        {{ __('Residential') }}
                    </label>
                    {{-- Individual residential styles --}}
                    @foreach ($residentialStyles as $style)
                        <label class="flex items-center gap-2.5 rounded-md px-2 py-1.5 pl-6 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-600 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                value="{{ $style }}"
                                wire:model.live="propertyStyles"
                                class="size-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 focus:ring-offset-0 dark:border-zinc-500 dark:bg-zinc-700"
                            />
                            {{ $style }}
                        </label>
                    @endforeach

                    <div class="my-1.5 border-t border-zinc-200 dark:border-zinc-600"></div>

                    {{-- Commercial group header --}}
                    <label class="flex items-center gap-2.5 rounded-md px-2 py-2 text-sm font-semibold text-zinc-800 hover:bg-zinc-100 dark:text-white dark:hover:bg-zinc-600 cursor-pointer transition-colors">
                        <input
                            type="checkbox"
                            {{ $allCommercialSelected ? 'checked' : '' }}
                            wire:click="toggleCommercialStyles"
                            class="size-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 focus:ring-offset-0 dark:border-zinc-500 dark:bg-zinc-700"
                        />
                        {{ __('Commercial') }}
                    </label>
                    {{-- Individual commercial styles --}}
                    @foreach ($commercialStyles as $style)
                        <label class="flex items-center gap-2.5 rounded-md px-2 py-1.5 pl-6 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-600 cursor-pointer transition-colors">
                            <input
                                type="checkbox"
                                value="{{ $style }}"
                                wire:model.live="propertyStyles"
                                class="size-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 focus:ring-offset-0 dark:border-zinc-500 dark:bg-zinc-700"
                            />
                            {{ $style }}
                        </label>
                    @endforeach
                </div>
            </flux:menu>
        </flux:dropdown>

        {{-- City --}}
        <x-filter-search-dropdown
            :label="__('City')"
            :options="$this->availableCities->toArray()"
            :selected="$cities"
            wire-model="cities"
            :placeholder="__('Search cities...')"
            :all-label="__('All cities')"
        />

        {{-- Price Range Dropdown --}}
        <flux:dropdown position="bottom" align="start">
            <flux:button
                variant="{{ ($priceMin !== '' || $priceMax !== '') ? 'primary' : 'outline' }}"
                icon:trailing="chevron-down"
            >
                @if ($priceMin !== '' && $priceMax !== '')
                    ${{ number_format((int) $priceMin) }} - ${{ number_format((int) $priceMax) }}
                @elseif ($priceMin !== '')
                    ${{ number_format((int) $priceMin) }}+
                @elseif ($priceMax !== '')
                    {{ __('Up to') }} ${{ number_format((int) $priceMax) }}
                @else
                    {{ __('Price') }}
                @endif
            </flux:button>

            <flux:menu class="w-64 p-3">
                <div class="space-y-3">
                    <flux:input
                        wire:model.live.debounce.500ms="priceMin"
                        type="number"
                        :label="__('Min Price')"
                        placeholder="0"
                    />
                    <flux:input
                        wire:model.live.debounce.500ms="priceMax"
                        type="number"
                        :label="__('Max Price')"
                        placeholder="{{ __('No max') }}"
                    />
                </div>
            </flux:menu>
        </flux:dropdown>

        {{-- Bedrooms Dropdown --}}
        <flux:dropdown position="bottom" align="start">
            <flux:button
                variant="{{ !empty($bedrooms) ? 'primary' : 'outline' }}"
                icon:trailing="chevron-down"
            >
                @if (!empty($bedrooms))
                    {{ count($bedrooms) === 1 ? $bedrooms[0] . ' ' . __('bed') : count($bedrooms) . ' ' . __('selected') }}
                @else
                    {{ __('Beds') }}
                @endif
            </flux:button>

            <flux:menu class="w-48 p-2">
                <div class="space-y-1">
                    @foreach (['1', '2', '3', '4', '5', '5+'] as $beds)
                        <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-zinc-800 hover:bg-zinc-50 dark:text-white dark:hover:bg-zinc-600 cursor-pointer">
                            <input
                                type="checkbox"
                                value="{{ $beds }}"
                                wire:model.live="bedrooms"
                                class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 dark:border-zinc-600 dark:bg-zinc-800"
                            />
                            {{ $beds }} {{ __('bedroom') }}{{ $beds !== '1' && $beds !== '5+' ? 's' : '' }}
                        </label>
                    @endforeach
                </div>
            </flux:menu>
        </flux:dropdown>

        {{-- Bathrooms Dropdown --}}
        <flux:dropdown position="bottom" align="start">
            <flux:button
                variant="{{ !empty($bathrooms) ? 'primary' : 'outline' }}"
                icon:trailing="chevron-down"
            >
                @if (!empty($bathrooms))
                    {{ count($bathrooms) === 1 ? $bathrooms[0] . ' ' . __('bath') : count($bathrooms) . ' ' . __('selected') }}
                @else
                    {{ __('Baths') }}
                @endif
            </flux:button>

            <flux:menu class="w-48 p-2">
                <div class="space-y-1">
                    @foreach (['1', '2', '3', '4', '4+'] as $baths)
                        <label class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm text-zinc-800 hover:bg-zinc-50 dark:text-white dark:hover:bg-zinc-600 cursor-pointer">
                            <input
                                type="checkbox"
                                value="{{ $baths }}"
                                wire:model.live="bathrooms"
                                class="rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 dark:border-zinc-600 dark:bg-zinc-800"
                            />
                            {{ $baths }} {{ __('bathroom') }}{{ $baths !== '1' && $baths !== '4+' ? 's' : '' }}
                        </label>
                    @endforeach
                </div>
            </flux:menu>
        </flux:dropdown>

        {{-- More Filters Toggle --}}
        <button
            type="button"
            x-on:click="showFilters = !showFilters"
            :class="showFilters
                ? 'bg-zinc-800 text-white hover:bg-zinc-700 dark:bg-white dark:text-zinc-800 dark:hover:bg-zinc-200'
                : 'bg-white text-zinc-800 border border-zinc-200 hover:bg-zinc-50 dark:bg-zinc-800 dark:text-white dark:border-zinc-600 dark:hover:bg-zinc-700'"
            class="inline-flex items-center gap-2 rounded-lg px-3 py-2 text-sm font-medium transition"
        >
            <flux:icon name="adjustments-horizontal" class="size-4" />
            {{ __('More') }}
            @if ($this->activeFilterCount > 0)
                <flux:badge color="amber" size="sm" class="ml-1">{{ $this->activeFilterCount }}</flux:badge>
            @endif
        </button>

        {{-- Clear All (shown when filters are active) --}}
        @if ($this->activeFilterCount > 0)
            <flux:button wire:click="resetFilters" variant="ghost" size="sm" icon="x-mark">
                {{ __('Clear all') }}
            </flux:button>
        @endif
    </div>

    {{-- Extended Filter Panel --}}
    <div
        x-show="showFilters"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 -translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 -translate-y-2"
        class="mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60"
    >
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Property Class - only show if there are options --}}
                @if ($this->availablePropertyClasses->isNotEmpty())
                    <div class="space-y-2">
                        <flux:label>{{ __('Property Class') }}</flux:label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->availablePropertyClasses as $classOption)
                                <label class="inline-flex items-center gap-1.5 text-sm text-slate-700 dark:text-zinc-300">
                                    <input type="checkbox" wire:model.live="propertyClasses" value="{{ $classOption }}" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-zinc-600 dark:bg-zinc-800" />
                                    {{ ucwords(strtolower($classOption)) }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Property Age - only show if there are options --}}
                @if ($this->availableAges->isNotEmpty())
                    <div class="space-y-2">
                        <flux:label>{{ __('Property Age') }}</flux:label>
                        <div class="flex flex-wrap gap-2">
                            @foreach ($this->availableAges as $ageOption)
                                <label class="inline-flex items-center gap-1.5 text-sm text-slate-700 dark:text-zinc-300">
                                    <input type="checkbox" wire:model.live="approximateAges" value="{{ $ageOption }}" class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500 dark:border-zinc-600 dark:bg-zinc-800" />
                                    {{ $ageOption }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- Square Footage --}}
                <div class="space-y-1">
                    <flux:label>{{ __('Square Footage') }}</flux:label>
                    <div class="flex items-center gap-2">
                        <flux:input wire:model.live.debounce.500ms="sqftMin" type="number" placeholder="Min" />
                        <span class="text-slate-400">-</span>
                        <flux:input wire:model.live.debounce.500ms="sqftMax" type="number" placeholder="Max" />
                    </div>
                </div>

                {{-- Lot Size --}}
                <div class="space-y-1">
                    <flux:label>{{ __('Lot Size (sq ft)') }}</flux:label>
                    <div class="flex items-center gap-2">
                        <flux:input wire:model.live.debounce.500ms="lotMin" type="number" placeholder="Min" />
                        <span class="text-slate-400">-</span>
                        <flux:input wire:model.live.debounce.500ms="lotMax" type="number" placeholder="Max" />
                    </div>
                </div>

                {{-- Stories --}}
                <div class="space-y-1">
                    <flux:label>{{ __('Stories') }}</flux:label>
                    <div class="flex items-center gap-2">
                        <flux:input wire:model.live.debounce.500ms="storiesMin" type="number" placeholder="Min" />
                        <span class="text-slate-400">-</span>
                        <flux:input wire:model.live.debounce.500ms="storiesMax" type="number" placeholder="Max" />
                    </div>
                </div>

                {{-- Max Property Tax --}}
                <flux:input
                    wire:model.live.debounce.500ms="taxMax"
                    type="number"
                    :label="__('Max Yearly Tax')"
                    placeholder="e.g. 5000"
                />

                {{-- Max Association Fee --}}
                <flux:input
                    wire:model.live.debounce.500ms="feeMax"
                    type="number"
                    :label="__('Max Monthly Fee')"
                    placeholder="e.g. 500"
                />

                {{-- Listed Since --}}
                <flux:input
                    wire:model.live="listedSince"
                    type="date"
                    :label="__('Listed Since')"
                />
            </div>
    </div>

    <div wire:key="view-{{ $viewMode }}">
        @if ($viewMode === 'map')
            {{-- Map View --}}
            <div class="mt-8" wire:ignore.self>
                <x-maps.listing-map
                    :api-endpoint="route('api.map-listings', $this->currentFilterParams())"
                    height="calc(100vh - 280px)"
                    class="min-h-[500px]"
                    :initial-lat="$initialLat !== '' ? $initialLat : null"
                    :initial-lng="$initialLng !== '' ? $initialLng : null"
                    :initial-zoom="$initialZoom !== '' ? $initialZoom : null"
                />
            </div>
        @else
        {{-- Listings Grid --}}
        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
            @forelse ($this->listings as $listing)
                @php
                    $primaryMedia = $listing->media->firstWhere('is_primary', true) ?? $listing->media->first();
                    $address = $listing->street_address ?? __('Address unavailable');
                    $location = collect([$listing->city, $listing->province, $listing->postal_code])->filter()->implode(', ');
                @endphp

                <a
                    href="{{ $listing->url }}"
                    wire:key="listing-{{ $listing->id }}"
                    class="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 dark:border-zinc-800 dark:bg-zinc-900/70"
                >
                    @if ($primaryMedia !== null)
                        <img
                            src="{{ $primaryMedia->public_url }}"
                            alt="{{ $primaryMedia->label ?? $address }}"
                            class="aspect-video w-full object-cover"
                            loading="lazy"
                        />
                    @else
                        <x-listing.no-photo-placeholder size="small" class="aspect-video" />
                    @endif

                    <div class="flex flex-1 flex-col gap-4 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0 space-y-1">
                                <h2 class="truncate text-base font-semibold text-slate-900 dark:text-white">
                                    {{ $address }}
                                </h2>

                                @if ($location !== '')
                                    <p class="truncate text-sm text-slate-500 dark:text-zinc-400">
                                        {{ $location }}
                                    </p>
                                @endif
                            </div>

                            <flux:badge size="sm" color="{{ \App\Support\ListingPresentation::statusBadge($listing->display_status) }}">
                                {{ $listing->display_status ?? __('Unknown') }}
                            </flux:badge>
                        </div>

                        {{-- Price --}}
                        <div class="text-xl font-bold text-slate-900 dark:text-white">
                            {{ \App\Support\ListingPresentation::currency($listing->list_price) }}
                        </div>

                        {{-- Quick Stats --}}
                        <div class="flex flex-wrap items-center gap-3 text-sm text-slate-600 dark:text-zinc-400">
                            @if ($listing->bedrooms)
                                <span class="flex items-center gap-1">
                                    <flux:icon name="home" class="size-4" />
                                    {{ $listing->bedrooms }} {{ __('bed') }}
                                </span>
                            @endif
                            @if ($listing->bathrooms)
                                <span class="flex items-center gap-1">
                                    {{ \App\Support\ListingPresentation::numeric($listing->bathrooms, 1) }} {{ __('bath') }}
                                </span>
                            @endif
                            @if ($listing->square_feet)
                                <span>{{ number_format($listing->square_feet) }} {{ __('sqft') }}</span>
                            @endif
                        </div>

                        {{-- Property Style --}}
                        @if ($listing->property_style)
                            <div class="text-xs text-slate-500 dark:text-zinc-500">
                                {{ $listing->property_style }}
                            </div>
                        @endif

                        {{-- Footer --}}
                        <div class="mt-auto flex items-center justify-between border-t border-slate-100 pt-3 text-xs text-slate-500 dark:border-zinc-800 dark:text-zinc-500">
                            <span>{{ $listing->mls_number }}</span>
                            <span>{{ optional($listing->modified_at)?->diffForHumans() ?? __('Unknown') }}</span>
                        </div>
                    </div>
                </a>
            @empty
                <div class="sm:col-span-2 lg:col-span-3">
                    <flux:callout class="rounded-2xl">
                        <flux:callout.heading>{{ __('No listings found') }}</flux:callout.heading>
                        <flux:callout.text>
                            @if ($this->activeFilterCount > 0)
                                {{ __('Try adjusting your filters or search criteria to find more properties.') }}
                            @else
                                {{ __('Check back soon—new foreclosure opportunities will appear here as they are ingested from MLS feeds.') }}
                            @endif
                        </flux:callout.text>
                        @if ($this->activeFilterCount > 0)
                            <flux:button wire:click="resetFilters" variant="primary" size="sm" class="mt-3">
                                {{ __('Clear all filters') }}
                            </flux:button>
                        @endif
                    </flux:callout>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if ($this->listings->hasPages())
            <div class="mt-10">
                {{ $this->listings->onEachSide(1)->links() }}
            </div>
        @endif
        @endif
    </div>
</section>
