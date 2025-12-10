<?php

use App\Models\Listing;
use App\Models\Municipality;
use App\Models\SavedSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site', ['title' => 'Create Saved Search'])] class extends Component {
    public string $name = '';
    public string $notification_channel = 'email';
    public string $notification_frequency = 'daily';
    public bool $is_active = true;

    // Filter fields
    public string $search = '';
    public string $status = '';
    public string $municipalityId = '';
    public string $propertyType = '';
    public string $minPrice = '';
    public string $maxPrice = '';
    public string $minBedrooms = '';
    public string $minBathrooms = '';

    public function mount(): void
    {
        Gate::authorize('create', SavedSearch::class);

        // Pre-populate filters from query string
        $this->search = request()->query('q', '');
        $this->status = request()->query('status', '');
        $this->municipalityId = request()->query('municipality', '');
        $this->propertyType = request()->query('type', '');
        $this->minPrice = request()->query('min_price', '');
        $this->maxPrice = request()->query('max_price', '');
        $this->minBedrooms = request()->query('beds', '');
        $this->minBathrooms = request()->query('baths', '');

        // Generate a default name based on filters
        $this->name = $this->generateDefaultName();
    }

    public function save(): void
    {
        Gate::authorize('create', SavedSearch::class);

        $validated = $this->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'notification_channel' => ['required', Rule::in(['email', 'none'])],
            'notification_frequency' => ['required', Rule::in(['instant', 'daily', 'weekly'])],
            'is_active' => ['boolean'],
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'string', 'max:50'],
            'municipalityId' => ['nullable', 'integer', 'exists:municipalities,id'],
            'propertyType' => ['nullable', 'string', 'max:100'],
            'minPrice' => ['nullable', 'numeric', 'min:0'],
            'maxPrice' => ['nullable', 'numeric', 'min:0'],
            'minBedrooms' => ['nullable', 'integer', 'min:0', 'max:20'],
            'minBathrooms' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ]);

        $filters = array_filter([
            'q' => $this->search,
            'status' => $this->status,
            'municipality' => $this->municipalityId,
            'type' => $this->propertyType,
            'min_price' => $this->minPrice,
            'max_price' => $this->maxPrice,
            'beds' => $this->minBedrooms,
            'baths' => $this->minBathrooms,
        ], fn ($value) => $value !== '' && $value !== null);

        $savedSearch = auth()->user()->savedSearches()->create([
            'name' => $validated['name'],
            'notification_channel' => $validated['notification_channel'],
            'notification_frequency' => $validated['notification_frequency'],
            'is_active' => $validated['is_active'],
            'filters' => $filters,
        ]);

        $this->redirect(route('saved-searches.index'), navigate: true);
    }

    #[Computed]
    public function availableStatuses()
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
    public function municipalities()
    {
        return Municipality::query()
            ->whereHas('listings', fn (Builder $query) => $query->visible())
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    #[Computed]
    public function propertyTypes()
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
    public function matchingCount(): int
    {
        return $this->buildQuery()->count();
    }

    private function buildQuery(): Builder
    {
        $municipalityId = $this->municipalityId !== '' ? (int) $this->municipalityId : null;
        $minPrice = $this->minPrice !== '' ? (float) preg_replace('/[^0-9.]/', '', $this->minPrice) : null;
        $maxPrice = $this->maxPrice !== '' ? (float) preg_replace('/[^0-9.]/', '', $this->maxPrice) : null;
        $minBedrooms = $this->minBedrooms !== '' ? (int) $this->minBedrooms : null;
        $minBathrooms = $this->minBathrooms !== '' ? (float) $this->minBathrooms : null;

        return Listing::query()
            ->visible()
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
            ->when($minPrice !== null && $minPrice > 0, fn (Builder $builder): Builder => $builder->where('list_price', '>=', $minPrice))
            ->when($maxPrice !== null && $maxPrice > 0, fn (Builder $builder): Builder => $builder->where('list_price', '<=', $maxPrice))
            ->when($minBedrooms !== null && $minBedrooms > 0, fn (Builder $builder): Builder => $builder->where('bedrooms', '>=', $minBedrooms))
            ->when($minBathrooms !== null && $minBathrooms > 0, fn (Builder $builder): Builder => $builder->where('bathrooms', '>=', $minBathrooms));
    }

    private function generateDefaultName(): string
    {
        $parts = [];

        if ($this->propertyType !== '') {
            $parts[] = $this->propertyType;
        }

        if ($this->municipalityId !== '') {
            $municipality = Municipality::find($this->municipalityId);
            if ($municipality) {
                $parts[] = 'in ' . $municipality->name;
            }
        }

        if ($this->minPrice !== '' || $this->maxPrice !== '') {
            $priceRange = [];
            if ($this->minPrice !== '') {
                $priceRange[] = '$' . number_format((float) preg_replace('/[^0-9.]/', '', $this->minPrice));
            }
            if ($this->maxPrice !== '') {
                $priceRange[] = '$' . number_format((float) preg_replace('/[^0-9.]/', '', $this->maxPrice));
            }
            $parts[] = implode(' - ', $priceRange);
        }

        if (empty($parts)) {
            return 'All Listings';
        }

        return implode(' ', $parts);
    }
}; ?>

<section class="mx-auto max-w-2xl px-6 py-12 lg:px-8">
    <div class="space-y-2">
        <flux:button
            variant="ghost"
            icon="chevron-left"
            :href="route('saved-searches.index')"
            wire:navigate
            class="mb-4"
        >
            {{ __('Back to saved searches') }}
        </flux:button>

        <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">
            {{ __('Create Saved Search') }}
        </h1>
        <p class="text-sm text-slate-600 dark:text-zinc-400">
            {{ __('Set up a saved search to receive notifications when new listings match your criteria.') }}
        </p>
    </div>

    <form wire:submit="save" class="mt-8 space-y-6">
        <!-- Basic Info -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
            <flux:heading size="sm" class="mb-4">{{ __('Search Details') }}</flux:heading>

            <div class="space-y-4">
                <flux:input
                    wire:model="name"
                    :label="__('Search name')"
                    :placeholder="__('e.g. Downtown condos under $500k')"
                    required
                />

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select
                        wire:model="notification_channel"
                        :label="__('Notification method')"
                    >
                        <flux:select.option value="email">{{ __('Email') }}</flux:select.option>
                        <flux:select.option value="none">{{ __('None (manual check)') }}</flux:select.option>
                    </flux:select>

                    <flux:select
                        wire:model="notification_frequency"
                        :label="__('Notification frequency')"
                    >
                        <flux:select.option value="instant">{{ __('Instant') }}</flux:select.option>
                        <flux:select.option value="daily">{{ __('Daily digest') }}</flux:select.option>
                        <flux:select.option value="weekly">{{ __('Weekly digest') }}</flux:select.option>
                    </flux:select>
                </div>

                <flux:checkbox
                    wire:model="is_active"
                    :label="__('Enable notifications')"
                    :description="__('Receive alerts when new listings match this search')"
                />
            </div>
        </div>

        <!-- Search Filters -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
            <div class="flex items-center justify-between mb-4">
                <flux:heading size="sm">{{ __('Search Filters') }}</flux:heading>
                <flux:badge color="blue">
                    {{ trans_choice(':count matching listing|:count matching listings', $this->matchingCount, ['count' => number_format($this->matchingCount)]) }}
                </flux:badge>
            </div>

            <div class="space-y-4">
                <flux:input
                    wire:model.live.debounce.400ms="search"
                    :label="__('Keyword search')"
                    icon="magnifying-glass"
                    :placeholder="__('Address, city, or MLS #')"
                />

                <div class="grid gap-4 sm:grid-cols-2">
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
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select
                        wire:model.live="status"
                        :label="__('Status')"
                    >
                        <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
                        @foreach ($this->availableStatuses as $statusOption)
                            <flux:select.option value="{{ $statusOption }}">{{ $statusOption }}</flux:select.option>
                        @endforeach
                    </flux:select>

                    <div></div>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
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
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:select
                        wire:model.live="minBedrooms"
                        :label="__('Minimum bedrooms')"
                    >
                        <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                        @foreach ([1, 2, 3, 4, 5] as $beds)
                            <flux:select.option value="{{ $beds }}">{{ $beds }}+</flux:select.option>
                        @endforeach
                    </flux:select>

                    <flux:select
                        wire:model.live="minBathrooms"
                        :label="__('Minimum bathrooms')"
                    >
                        <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                        @foreach ([1, 1.5, 2, 2.5, 3, 4] as $baths)
                            <flux:select.option value="{{ $baths }}">{{ $baths }}+</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-end gap-3">
            <flux:button
                variant="ghost"
                :href="route('saved-searches.index')"
                wire:navigate
            >
                {{ __('Cancel') }}
            </flux:button>

            <flux:button type="submit" variant="primary">
                {{ __('Create saved search') }}
            </flux:button>
        </div>
    </form>
</section>
