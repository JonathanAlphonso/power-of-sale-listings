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

new #[Layout('components.layouts.site')] class extends Component {
    use WithPagination;

    private const PER_PAGE = 12;

    protected string $paginationTheme = 'tailwind';

    // Search
    #[Url(as: 'q', except: '')]
    public string $search = '';

    // Status filter
    #[Url(as: 'status', except: '')]
    public string $status = '';

    // Property filters
    #[Url(as: 'class', except: '')]
    public string $propertyClass = '';

    #[Url(as: 'type', except: '')]
    public string $propertyType = '';

    // Price range
    #[Url(as: 'price_min', except: '')]
    public string $priceMin = '';

    #[Url(as: 'price_max', except: '')]
    public string $priceMax = '';

    // Beds & Baths (supports "2" for exact or "2+" for minimum)
    #[Url(as: 'beds', except: '')]
    public string $bedrooms = '';

    #[Url(as: 'baths', except: '')]
    public string $bathrooms = '';

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
    #[Url(as: 'age', except: '')]
    public string $approximateAge = '';

    // Location
    #[Url(as: 'city', except: '')]
    public string $city = '';

    #[Url(as: 'municipality', except: '')]
    public string $municipalityId = '';

    // Listed since
    #[Url(as: 'listed_since', except: '')]
    public string $listedSince = '';

    // Sort
    #[Url(as: 'sort', except: 'newest')]
    public string $sortBy = 'newest';

    public bool $showFilters = false;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updated($property): void
    {
        if ($property !== 'showFilters') {
            $this->resetPage();
        }
    }

    public function resetFilters(): void
    {
        $this->reset([
            'search',
            'status',
            'propertyClass',
            'propertyType',
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
            'approximateAge',
            'city',
            'municipalityId',
            'listedSince',
            'sortBy',
        ]);
        $this->resetPage();
    }

    private function parseRoomFilter(string $value): ?array
    {
        if ($value === '') {
            return null;
        }

        $isMinimum = str_ends_with($value, '+');
        $number = $isMinimum ? rtrim($value, '+') : $value;

        return [
            'value' => is_numeric($number) ? (float) $number : null,
            'operator' => $isMinimum ? '>=' : '=',
        ];
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
            ->when($this->status !== '', fn (Builder $q) => $q->where('display_status', $this->status))
            // Property class & type
            ->when($this->propertyClass !== '', fn (Builder $q) => $q->where('property_class', $this->propertyClass))
            ->when($this->propertyType !== '', fn (Builder $q) => $q->where('property_type', $this->propertyType))
            // Price range
            ->when($this->priceMin !== '', fn (Builder $q) => $q->where('list_price', '>=', (float) $this->priceMin))
            ->when($this->priceMax !== '', fn (Builder $q) => $q->where('list_price', '<=', (float) $this->priceMax))
            // Beds & Baths
            ->when($this->bedrooms !== '', function (Builder $q) {
                $filter = $this->parseRoomFilter($this->bedrooms);
                if ($filter && $filter['value'] !== null) {
                    $q->where('bedrooms', $filter['operator'], (int) $filter['value']);
                }
            })
            ->when($this->bathrooms !== '', function (Builder $q) {
                $filter = $this->parseRoomFilter($this->bathrooms);
                if ($filter && $filter['value'] !== null) {
                    $q->where('bathrooms', $filter['operator'], $filter['value']);
                }
            })
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
            ->when($this->approximateAge !== '', fn (Builder $q) => $q->where('approximate_age', $this->approximateAge))
            // Location
            ->when($this->city !== '', fn (Builder $q) => $q->where('city', $this->city))
            ->when($this->municipalityId !== '', fn (Builder $q) => $q->where('municipality_id', (int) $this->municipalityId))
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
        if ($this->status !== '') $count++;
        if ($this->propertyClass !== '') $count++;
        if ($this->propertyType !== '') $count++;
        if ($this->priceMin !== '' || $this->priceMax !== '') $count++;
        if ($this->bedrooms !== '') $count++;
        if ($this->bathrooms !== '') $count++;
        if ($this->sqftMin !== '' || $this->sqftMax !== '') $count++;
        if ($this->lotMin !== '' || $this->lotMax !== '') $count++;
        if ($this->storiesMin !== '' || $this->storiesMax !== '') $count++;
        if ($this->taxMax !== '') $count++;
        if ($this->feeMax !== '') $count++;
        if ($this->approximateAge !== '') $count++;
        if ($this->city !== '') $count++;
        if ($this->municipalityId !== '') $count++;
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
    public function availablePropertyTypes(): SupportCollection
    {
        return Listing::query()
            ->visible()
            ->distinct()
            ->whereNotNull('property_type')
            ->orderBy('property_type')
            ->pluck('property_type');
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

    #[Computed]
    public function municipalities(): Collection
    {
        return Municipality::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}; ?>

<x-slot:title>{{ __('Current Listings') }}</x-slot:title>

<section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
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
        </div>
    </div>

    {{-- Search & Filter Toggle --}}
    <div class="mt-6 flex flex-col gap-4 sm:flex-row sm:items-center">
        <div class="flex-1">
            <flux:input
                wire:model.live.debounce.400ms="search"
                icon="magnifying-glass"
                :placeholder="__('Search by MLS #, address, city, or keywords...')"
                clearable
            />
        </div>

        <div class="flex items-center gap-2">
            <flux:select wire:model.live="sortBy" class="w-44">
                <flux:select.option value="newest">{{ __('Newest first') }}</flux:select.option>
                <flux:select.option value="oldest">{{ __('Oldest first') }}</flux:select.option>
                <flux:select.option value="price_asc">{{ __('Price: Low to High') }}</flux:select.option>
                <flux:select.option value="price_desc">{{ __('Price: High to Low') }}</flux:select.option>
                <flux:select.option value="beds_desc">{{ __('Most Bedrooms') }}</flux:select.option>
                <flux:select.option value="sqft_desc">{{ __('Largest') }}</flux:select.option>
            </flux:select>

            <flux:button
                wire:click="$toggle('showFilters')"
                :variant="$showFilters ? 'primary' : 'outline'"
                icon="adjustments-horizontal"
            >
                {{ __('Filters') }}
                @if ($this->activeFilterCount > 0)
                    <flux:badge color="amber" size="sm" class="ml-1">{{ $this->activeFilterCount }}</flux:badge>
                @endif
            </flux:button>
        </div>
    </div>

    {{-- Filter Panel --}}
    @if ($showFilters)
        <div class="mt-4 rounded-xl border border-slate-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Status --}}
                <flux:select wire:model.live="status" :label="__('Status')">
                    <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                    @foreach ($this->availableStatuses as $statusOption)
                        <flux:select.option value="{{ $statusOption }}">{{ $statusOption }}</flux:select.option>
                    @endforeach
                </flux:select>

                {{-- Property Class --}}
                <flux:select wire:model.live="propertyClass" :label="__('Property Class')">
                    <flux:select.option value="">{{ __('All classes') }}</flux:select.option>
                    @foreach ($this->availablePropertyClasses as $classOption)
                        <flux:select.option value="{{ $classOption }}">{{ ucwords(strtolower($classOption)) }}</flux:select.option>
                    @endforeach
                </flux:select>

                {{-- Property Type --}}
                <flux:select wire:model.live="propertyType" :label="__('Property Type')">
                    <flux:select.option value="">{{ __('All types') }}</flux:select.option>
                    @foreach ($this->availablePropertyTypes as $typeOption)
                        <flux:select.option value="{{ $typeOption }}">{{ $typeOption }}</flux:select.option>
                    @endforeach
                </flux:select>

                {{-- City --}}
                <flux:select wire:model.live="city" :label="__('City')">
                    <flux:select.option value="">{{ __('All cities') }}</flux:select.option>
                    @foreach ($this->availableCities as $cityOption)
                        <flux:select.option value="{{ $cityOption }}">{{ $cityOption }}</flux:select.option>
                    @endforeach
                </flux:select>

                {{-- Price Range --}}
                <div class="space-y-1">
                    <flux:label>{{ __('Price Range') }}</flux:label>
                    <div class="flex items-center gap-2">
                        <flux:input wire:model.live.debounce.500ms="priceMin" type="number" placeholder="Min" />
                        <span class="text-slate-400">-</span>
                        <flux:input wire:model.live.debounce.500ms="priceMax" type="number" placeholder="Max" />
                    </div>
                </div>

                {{-- Bedrooms --}}
                <flux:select wire:model.live="bedrooms" :label="__('Bedrooms')">
                    <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                    @foreach ([1, 2, 3, 4, 5] as $beds)
                        <flux:select.option value="{{ $beds }}">{{ $beds }}</flux:select.option>
                        <flux:select.option value="{{ $beds }}+">{{ $beds }}+</flux:select.option>
                    @endforeach
                </flux:select>

                {{-- Bathrooms --}}
                <flux:select wire:model.live="bathrooms" :label="__('Bathrooms')">
                    <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                    @foreach ([1, 2, 3, 4] as $baths)
                        <flux:select.option value="{{ $baths }}">{{ $baths }}</flux:select.option>
                        <flux:select.option value="{{ $baths }}+">{{ $baths }}+</flux:select.option>
                    @endforeach
                </flux:select>

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

                {{-- Property Age --}}
                <flux:select wire:model.live="approximateAge" :label="__('Property Age')">
                    <flux:select.option value="">{{ __('Any age') }}</flux:select.option>
                    @foreach ($this->availableAges as $ageOption)
                        <flux:select.option value="{{ $ageOption }}">{{ $ageOption }}</flux:select.option>
                    @endforeach
                </flux:select>

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

                {{-- Municipality --}}
                <flux:select wire:model.live="municipalityId" :label="__('Municipality')">
                    <flux:select.option value="">{{ __('All municipalities') }}</flux:select.option>
                    @foreach ($this->municipalities as $municipality)
                        <flux:select.option value="{{ $municipality->id }}">{{ $municipality->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>

            {{-- Reset Filters --}}
            <div class="mt-4 flex justify-end">
                <flux:button wire:click="resetFilters" variant="subtle" icon="arrow-path" size="sm">
                    {{ __('Reset all filters') }}
                </flux:button>
            </div>
        </div>
    @endif

    {{-- Listings Grid --}}
    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3" wire:loading.class="opacity-50">
        @forelse ($this->listings as $listing)
            @php
                $primaryMedia = $listing->media->firstWhere('is_primary', true) ?? $listing->media->first();
                $address = $listing->street_address ?? __('Address unavailable');
                $location = collect([$listing->city, $listing->province, $listing->postal_code])->filter()->implode(', ');
            @endphp

            <a
                href="{{ route('listings.show', $listing) }}"
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
                    <div class="flex aspect-video items-center justify-center bg-slate-100 text-4xl text-slate-300 dark:bg-zinc-800 dark:text-zinc-600">
                        <flux:icon name="photo" />
                    </div>
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

                    {{-- Property Type --}}
                    @if ($listing->property_type)
                        <div class="text-xs text-slate-500 dark:text-zinc-500">
                            {{ $listing->property_type }}
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
</section>
