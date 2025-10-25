<?php

use App\Models\Listing;
use App\Models\Municipality;
use App\Support\ListingPresentation;
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

    @include('livewire.admin.listings.partials.filters')

    <div class="mt-8 grid gap-6 xl:grid-cols-[minmax(0,1.9fr)_minmax(0,1fr)]">
        @include('livewire.admin.listings.partials.listings-table', ['selectedListing' => $selectedListing])
        @include('livewire.admin.listings.partials.preview', ['selectedListing' => $selectedListing])
    </div>
</section>
