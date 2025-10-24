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

new #[Layout('components.layouts.app')] class extends Component {
    use WithPagination;

    private const PER_PAGE_OPTIONS = [15, 30, 60];

    protected string $paginationTheme = 'tailwind';

    #[Url(as: 'search', except: '')]
    public string $search = '';

    #[Url(as: 'status', except: '')]
    public string $status = '';

    #[Url(as: 'municipality', except: '')]
    public string $municipalityId = '';

    #[Url(as: 'sale', except: '')]
    public string $saleType = '';

    #[Url(as: 'per', except: '15')]
    public string $perPage = '15';

    public ?int $selectedListingId = null;

    /**
     * Prime component defaults.
     */
    public function mount(): void
    {
        $this->perPage = (string) $this->resolvePerPage((int) $this->perPage);

        $this->selectedListingId ??= Listing::query()
            ->latest('modified_at')
            ->value('id');
    }

    public function updatedSearch(): void
    {
        $this->resetFiltersState();
    }

    public function updatedStatus(): void
    {
        $this->resetFiltersState();
    }

    public function updatedMunicipalityId(): void
    {
        $this->resetFiltersState();
    }

    public function updatedSaleType(): void
    {
        $this->resetFiltersState();
    }

    public function updatedPerPage(): void
    {
        $this->perPage = (string) $this->resolvePerPage((int) $this->perPage);
        $this->resetPage();
    }

    public function selectListing(int $listingId): void
    {
        $this->selectedListingId = $listingId;
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->municipalityId = '';
        $this->saleType = '';
        $this->perPage = (string) self::PER_PAGE_OPTIONS[0];

        $this->resetFiltersState();
    }

    #[Computed]
    public function listings(): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage((int) $this->perPage);
        $municipalityId = $this->municipalityFilter();

        $paginator = Listing::query()
            ->with(['source', 'municipality'])
            ->when($this->search !== '', function (Builder $builder): void {
                $builder->where(function (Builder $query): void {
                    $query
                        ->where('mls_number', 'like', '%'.$this->search.'%')
                        ->orWhere('street_address', 'like', '%'.$this->search.'%')
                        ->orWhere('city', 'like', '%'.$this->search.'%')
                        ->orWhere('board_code', 'like', '%'.$this->search.'%');
                });
            })
            ->when($this->status !== '', fn (Builder $builder): Builder => $builder->where('display_status', $this->status))
            ->when($municipalityId !== null, fn (Builder $builder): Builder => $builder->where('municipality_id', $municipalityId))
            ->when($this->saleType !== '', fn (Builder $builder): Builder => $builder->where('sale_type', $this->saleType))
            ->orderByDesc('modified_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        $collection = $paginator->getCollection();

        if ($collection->isEmpty()) {
            $this->selectedListingId = null;

            return $paginator;
        }

        if ($this->selectedListingId === null || $collection->doesntContain('id', $this->selectedListingId)) {
            $this->selectedListingId = (int) $collection->first()->id;
        }

        return $paginator;
    }

    #[Computed]
    public function availableStatuses(): SupportCollection
    {
        return Listing::query()
            ->select('display_status')
            ->distinct()
            ->whereNotNull('display_status')
            ->orderBy('display_status')
            ->pluck('display_status');
    }

    #[Computed]
    public function availableSaleTypes(): SupportCollection
    {
        return Listing::query()
            ->select('sale_type')
            ->distinct()
            ->whereNotNull('sale_type')
            ->orderBy('sale_type')
            ->pluck('sale_type');
    }

    #[Computed]
    public function municipalities(): Collection
    {
        return Municipality::query()
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function perPageOptions(): array
    {
        return self::PER_PAGE_OPTIONS;
    }

    #[Computed]
    public function selectedListing(): ?Listing
    {
        if ($this->selectedListingId === null) {
            return null;
        }

        $listing = Listing::query()
            ->with(['media', 'source', 'municipality'])
            ->find($this->selectedListingId);

        if ($listing === null) {
            $this->selectedListingId = null;
        }

        return $listing;
    }

    public function saleTypeLabel(?string $saleType): string
    {
        return match ($saleType) {
            'RENT' => __('For Rent'),
            'SALE' => __('For Sale'),
            default => __('Unknown'),
        };
    }

    public function statusBadgeColor(?string $status): string
    {
        $normalized = strtolower((string) $status);

        return match (true) {
            str_contains($normalized, 'available') => 'green',
            str_contains($normalized, 'conditional') => 'amber',
            str_contains($normalized, 'sold') => 'red',
            str_contains($normalized, 'suspend') => 'zinc',
            default => 'blue',
        };
    }

    public function formatCurrency(float|int|string|null $value): string
    {
        if ($value === null || $value === '') {
            return __('N/A');
        }

        return '$'.number_format((float) $value, 0);
    }

    public function formatNumericValue(float|int|string|null $value, int $decimals = 0): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $number = (float) $value;

        if ($decimals === 0) {
            return number_format((int) round($number));
        }

        $formatted = number_format($number, $decimals);

        return rtrim(rtrim($formatted, '0'), '.');
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

    private function resetFiltersState(): void
    {
        $this->resetPage();
        $this->selectedListingId = null;
    }
}; ?>

@php
    /** @var \App\Models\Listing|null $selectedListing */
    $selectedListing = $this->selectedListing;
@endphp

<section class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-col gap-2 pb-6">
        <flux:heading size="xl">{{ __('Listings') }}</flux:heading>

        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
            {{ __('Browse, filter, and preview canonical listing records ingested into the platform.') }}
        </flux:text>
    </div>

    <div class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60 sm:grid-cols-2 lg:grid-cols-4">
        <flux:input
            wire:model.live.debounce.400ms="search"
            :label="__('Search')"
            icon="magnifying-glass"
            :placeholder="__('MLS #, address, or board code')"
        />

        <flux:select
            wire:model.live="status"
            :label="__('Status')"
        >
            <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
            @foreach ($this->availableStatuses as $statusOption)
                <flux:select.option value="{{ $statusOption }}">{{ $statusOption }}</flux:select.option>
            @endforeach
        </flux:select>

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
            wire:model.live="saleType"
            :label="__('Sale Type')"
        >
            <flux:select.option value="">{{ __('All sale types') }}</flux:select.option>
            @foreach ($this->availableSaleTypes as $saleTypeOption)
                <flux:select.option value="{{ $saleTypeOption }}">
                    {{ $this->saleTypeLabel($saleTypeOption) }}
                </flux:select.option>
            @endforeach
        </flux:select>

        <flux:select
            wire:model.live="perPage"
            :label="__('Results per page')"
            class="sm:col-span-2 lg:col-span-1"
        >
            @foreach ($this->perPageOptions as $option)
                <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
            @endforeach
        </flux:select>

        <flux:button
            variant="subtle"
            icon="arrow-path"
            class="sm:col-span-2 lg:col-span-1 sm:self-end"
            wire:click="resetFilters"
        >
            {{ __('Reset filters') }}
        </flux:button>
    </div>

    <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1.9fr)_minmax(0,1fr)]">
        <div class="flex flex-col gap-4">
            <flux:table>
                <flux:table.header>
                    <span>{{ __('MLS') }}</span>
                    <span>{{ __('Address') }}</span>
                    <span class="text-center">{{ __('Status') }}</span>
                    <span class="text-center">{{ __('Sale') }}</span>
                    <span class="text-right">{{ __('Updated') }}</span>
                </flux:table.header>

                <flux:table.rows>
                    @forelse ($this->listings as $listing)
                        <flux:table.row
                            wire:key="listing-row-{{ $listing->id }}"
                            wire:click="selectListing({{ $listing->id }})"
                            :selected="$selectedListing && $selectedListing->id === $listing->id"
                            :interactive="true"
                        >
                            <flux:table.cell>
                                <span class="font-semibold text-zinc-900 dark:text-white">{{ $listing->mls_number ?? __('Unknown') }}</span>
                                <flux:text class="text-xs uppercase text-zinc-500 dark:text-zinc-400">
                                    {{ $listing->board_code ?? '—' }}
                                </flux:text>
                            </flux:table.cell>

                            <flux:table.cell>
                                <span class="font-medium text-zinc-900 dark:text-white">{{ $listing->street_address ?? __('Address unavailable') }}</span>

                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ collect([$listing->city, $listing->province])->filter()->implode(', ') }}
                                </flux:text>
                            </flux:table.cell>

                            <flux:table.cell alignment="center">
                                <flux:badge
                                    color="{{ $this->statusBadgeColor($listing->display_status) }}"
                                    size="sm"
                                >
                                    {{ $listing->display_status ?? __('Unknown') }}
                                </flux:badge>
                            </flux:table.cell>

                            <flux:table.cell alignment="center">
                                <flux:text class="font-medium text-zinc-800 dark:text-zinc-200">
                                    {{ $this->saleTypeLabel($listing->sale_type) }}
                                </flux:text>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ $this->formatCurrency($listing->list_price) }}
                                </flux:text>
                            </flux:table.cell>

                            <flux:table.cell alignment="end">
                                <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                    {{ optional($listing->modified_at)?->timezone(config('app.timezone'))->format('M j, Y g:i a') ?? __('—') }}
                                </span>
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ optional($listing->modified_at)?->diffForHumans() ?? __('No timestamp') }}
                                </flux:text>
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.empty>
                            {{ __('No listings match the current filters.') }}
                        </flux:table.empty>
                    @endforelse
                </flux:table.rows>
            </flux:table>

            <div class="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-600 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
                <span>
                    {{ trans_choice(':count listing|:count listings', $this->listings->total(), ['count' => number_format($this->listings->total())]) }}
                </span>

                <div>
                    {{ $this->listings->links() }}
                </div>
            </div>
        </div>

        <div class="flex flex-col gap-4">
            @if ($selectedListing !== null)
                <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                    @php
                        $primaryMedia = $selectedListing->media->firstWhere('is_primary', true) ?? $selectedListing->media->first();
                    @endphp

                    @if ($primaryMedia !== null)
                        <img
                            src="{{ $primaryMedia->preview_url ?? $primaryMedia->url }}"
                            alt="{{ $primaryMedia->label ?? $selectedListing->street_address ?? __('Listing image') }}"
                            class="aspect-video w-full object-cover"
                            loading="lazy"
                        />
                    @else
                        <div class="flex aspect-video items-center justify-center bg-zinc-100 text-5xl text-zinc-300 dark:bg-zinc-800 dark:text-zinc-600">
                            <flux:icon name="photo" />
                        </div>
                    @endif

                    <div class="space-y-6 p-6">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="space-y-1">
                                <flux:heading size="lg">
                                    {{ $selectedListing->street_address ?? __('Address unavailable') }}
                                </flux:heading>

                                <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ collect([$selectedListing->city, $selectedListing->province, $selectedListing->postal_code])->filter()->implode(', ') }}
                                </flux:text>
                            </div>

                            <flux:badge color="{{ $this->statusBadgeColor($selectedListing->display_status) }}">
                                {{ $selectedListing->display_status ?? __('Unknown') }}
                            </flux:badge>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-900/70">
                                <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    {{ __('Asking price') }}
                                </flux:text>
                                <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $this->formatCurrency($selectedListing->list_price) }}
                                </p>
                            </div>

                            <div class="rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-900/70">
                                <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    {{ __('Sale type') }}
                                </flux:text>
                                <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $this->saleTypeLabel($selectedListing->sale_type) }}
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 rounded-xl border border-dashed border-zinc-200 p-4 dark:border-zinc-700 sm:grid-cols-4">
                            <div>
                                <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    {{ __('Beds') }}
                                </flux:text>
                                <p class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $this->formatNumericValue($selectedListing->bedrooms) }}
                                </p>
                            </div>
                            <div>
                                <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    {{ __('Baths') }}
                                </flux:text>
                                <p class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $this->formatNumericValue($selectedListing->bathrooms, 1) }}
                                </p>
                            </div>
                            <div>
                                <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    {{ __('Square feet') }}
                                </flux:text>
                                <p class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $selectedListing->square_feet_text ?? $this->formatNumericValue($selectedListing->square_feet) }}
                                </p>
                            </div>
                            <div>
                                <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    {{ __('Days on market') }}
                                </flux:text>
                                <p class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $this->formatNumericValue($selectedListing->days_on_market) }}
                                </p>
                            </div>
                        </div>

                        <div class="space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ __('MLS number') }}</span>
                                <span>{{ $selectedListing->mls_number ?? '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ __('Board code') }}</span>
                                <span>{{ $selectedListing->board_code ?? '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ __('Source') }}</span>
                                <span>{{ $selectedListing->source?->name ?? '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ __('Municipality') }}</span>
                                <span>{{ $selectedListing->municipality?->name ?? '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ __('Last modified') }}</span>
                                <span>
                                    {{ optional($selectedListing->modified_at)?->timezone(config('app.timezone'))->format('M j, Y g:i a') ?? '—' }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <flux:callout class="rounded-2xl">
                    <flux:callout.heading>{{ __('Select a listing to preview details') }}</flux:callout.heading>
                    <flux:callout.text>
                        {{ __('Use the filters or table to choose a listing and see key metrics, pricing, and source information at a glance.') }}
                    </flux:callout.text>
                </flux:callout>
            @endif
        </div>
    </div>
</section>
