<?php

use App\Models\AuditLog;
use App\Models\Listing;
use App\Models\Municipality;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
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

    #[Url(as: 'per', except: '15')]
    public string $perPage = '15';

    public ?int $selectedListingId = null;

    public bool $suppressionAvailable = false;

    public bool $confirmingPurge = false;
    public bool $seeding = false;

    /** @var array{reason: string, notes: string, expires_at: string} */
    public array $suppressionForm = [
        'reason' => '',
        'notes' => '',
        'expires_at' => '',
    ];

    /** @var array{reason: string, notes: string} */
    public array $unsuppressionForm = [
        'reason' => '',
        'notes' => '',
    ];

    /**
     * Prime component defaults.
     */
    public function mount(): void
    {
        Gate::authorize('viewAny', Listing::class);

        $this->perPage = (string) $this->resolvePerPage((int) $this->perPage);

        $this->selectedListingId ??= Listing::query()
            ->latest('modified_at')
            ->value('id');

        $this->suppressionAvailable = Listing::suppressionSchemaAvailable();
        $this->resetSuppressionForms();
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

    public function updatedPerPage(): void
    {
        $this->perPage = (string) $this->resolvePerPage((int) $this->perPage);
        $this->resetPage();
    }

    public function confirmPurge(): void
    {
        Gate::authorize('purge', Listing::class);
        $this->confirmingPurge = true;
    }

    public function purgeAllListings(): void
    {
        Gate::authorize('purge', Listing::class);

        // Delete in chunks to respect FKs and avoid long-running queries.
        Listing::query()
            ->select('id')
            ->orderBy('id')
            ->chunkById(500, function (\Illuminate\Support\Collection $chunk): void {
                Listing::query()->whereIn('id', $chunk->pluck('id'))->delete();
            });

        $this->confirmingPurge = false;
        $this->resetFiltersState();
        $this->dispatch('listings-purged');
        $this->dispatch('$refresh');
    }

    public function seedFakeListings(int $count = 50): void
    {
        Gate::authorize('seed', Listing::class);

        $count = max(1, min($count, 500));

        \App\Models\Listing::factory()->count($count)->create();

        $this->dispatch('listings-seeded', count: $count);
        $this->dispatch('$refresh');
    }

    public function selectListing(int $listingId): void
    {
        $this->selectedListingId = $listingId;
        $this->resetSuppressionForms();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->status = '';
        $this->municipalityId = '';
        $this->perPage = (string) self::PER_PAGE_OPTIONS[0];

        $this->resetFiltersState();
        $this->resetSuppressionForms();
    }

    public function suppressSelected(): void
    {
        $listing = $this->selectedListing;

        if ($listing === null) {
            return;
        }

        Gate::authorize('suppress', $listing);

        if (! $this->suppressionAvailable) {
            $this->addError('suppressionForm.reason', __('Suppression controls are unavailable until the latest database migrations have been run.'));

            return;
        }

        if ($listing->isSuppressed()) {
            $this->addError('suppressionForm.reason', __('This listing is already suppressed.'));

            return;
        }

        $validated = $this->validate([
            'suppressionForm.reason' => ['required', 'string', 'max:255'],
            'suppressionForm.notes' => ['nullable', 'string', 'max:1000'],
            'suppressionForm.expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $form = $validated['suppressionForm'];
        $form['reason'] = trim($form['reason']);
        $form['notes'] = is_string($form['notes'] ?? null) ? trim((string) $form['notes']) : $form['notes'];
        $form['expires_at'] = is_string($form['expires_at'] ?? null) ? trim((string) $form['expires_at']) : $form['expires_at'];

        $expiresAt = $this->parseSuppressionExpiry($form['expires_at'] ?? null);
        $originalState = [
            'suppressed_at' => $listing->suppressed_at,
            'suppression_expires_at' => $listing->suppression_expires_at,
            'suppression_reason' => $listing->suppression_reason,
            'suppression_notes' => $listing->suppression_notes,
        ];

        DB::transaction(function () use ($listing, $form, $expiresAt, $originalState): void {
            $suppression = $listing->suppressions()->create([
                'user_id' => auth()->id(),
                'reason' => $form['reason'],
                'notes' => $form['notes'] !== '' ? $form['notes'] : null,
                'suppressed_at' => now(),
                'expires_at' => $expiresAt,
            ]);

            $listing->forceFill([
                'suppressed_at' => $suppression->suppressed_at,
                'suppression_expires_at' => $suppression->expires_at,
                'suppressed_by_user_id' => auth()->id(),
                'suppression_reason' => $suppression->reason,
                'suppression_notes' => $suppression->notes,
            ])->save();

            AuditLog::query()->create([
                'action' => 'listing.suppressed',
                'auditable_type' => Listing::class,
                'auditable_id' => $listing->id,
                'user_id' => auth()->id(),
                'old_values' => [
                    'suppressed_at' => $originalState['suppressed_at']?->toIso8601String(),
                    'suppression_expires_at' => $originalState['suppression_expires_at']?->toIso8601String(),
                    'suppression_reason' => $originalState['suppression_reason'],
                    'suppression_notes' => $originalState['suppression_notes'],
                ],
                'new_values' => [
                    'suppressed_at' => $suppression->suppressed_at?->toIso8601String(),
                    'suppression_expires_at' => $suppression->expires_at?->toIso8601String(),
                    'suppression_reason' => $suppression->reason,
                    'suppression_notes' => $suppression->notes,
                ],
                'meta' => [
                    'suppression_id' => $suppression->id,
                ],
            ]);
        });

        $this->resetSuppressionForms();
        unset($this->selectedListing, $this->suppressionHistory);
        $this->dispatch('listing-suppressed', listingId: $listing->id);
        $this->dispatch('$refresh');
    }

    public function unsuppressSelected(): void
    {
        $listing = $this->selectedListing;

        if ($listing === null || ! $listing->isSuppressed()) {
            return;
        }

        Gate::authorize('unsuppress', $listing);

        if (! $this->suppressionAvailable) {
            $this->addError('unsuppressionForm.reason', __('Suppression controls are unavailable until the latest database migrations have been run.'));

            return;
        }

        $validated = $this->validate([
            'unsuppressionForm.reason' => ['nullable', 'string', 'max:255'],
            'unsuppressionForm.notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $form = $validated['unsuppressionForm'];
        $form['reason'] = is_string($form['reason'] ?? null) ? trim((string) $form['reason']) : $form['reason'];
        $form['notes'] = is_string($form['notes'] ?? null) ? trim((string) $form['notes']) : $form['notes'];

        $currentSuppression = $listing->currentSuppression;
        $originalState = [
            'suppressed_at' => $listing->suppressed_at,
            'suppression_expires_at' => $listing->suppression_expires_at,
            'suppression_reason' => $listing->suppression_reason,
            'suppression_notes' => $listing->suppression_notes,
        ];

        DB::transaction(function () use ($listing, $currentSuppression, $form, $originalState): void {
            $releasedAt = now();

            if ($currentSuppression !== null && $currentSuppression->released_at === null) {
                $currentSuppression->forceFill([
                    'released_at' => $releasedAt,
                    'release_user_id' => auth()->id(),
                    'release_reason' => $form['reason'] !== '' ? $form['reason'] : null,
                    'release_notes' => $form['notes'] !== '' ? $form['notes'] : null,
                ])->save();
            }

            $listing->forceFill([
                'suppressed_at' => null,
                'suppression_expires_at' => null,
                'suppressed_by_user_id' => null,
                'suppression_reason' => null,
                'suppression_notes' => null,
            ])->save();

            AuditLog::query()->create([
                'action' => 'listing.unsuppressed',
                'auditable_type' => Listing::class,
                'auditable_id' => $listing->id,
                'user_id' => auth()->id(),
                'old_values' => [
                    'suppressed_at' => $originalState['suppressed_at']?->toIso8601String(),
                    'suppression_expires_at' => $originalState['suppression_expires_at']?->toIso8601String(),
                    'suppression_reason' => $originalState['suppression_reason'],
                    'suppression_notes' => $originalState['suppression_notes'],
                ],
                'new_values' => [
                    'suppressed_at' => null,
                    'suppression_expires_at' => null,
                    'suppression_reason' => null,
                    'suppression_notes' => null,
                ],
                'meta' => [
                    'suppression_id' => $currentSuppression?->id,
                ],
            ]);
        });

        $this->resetSuppressionForms();
        unset($this->selectedListing, $this->suppressionHistory);
        $this->dispatch('listing-unsuppressed', listingId: $listing->id);
        $this->dispatch('$refresh');
    }

    #[Computed]
    public function listings(): LengthAwarePaginator
    {
        $perPage = $this->resolvePerPage((int) $this->perPage);
        $municipalityId = $this->municipalityFilter();

        $with = [
            'source',
            'municipality',
        ];

        if ($this->suppressionAvailable) {
            $with[] = 'currentSuppression';
        }

        $paginator = Listing::query()
            ->with($with)
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
            ->with(
                collect([
                    'media',
                    'source',
                    'municipality',
                ])->when($this->suppressionAvailable, function ($collection) {
                    return $collection->merge([
                        'suppressedBy:id,name',
                        'currentSuppression' => fn ($query) => $query->with(['user:id,name', 'releaseUser:id,name']),
                        'suppressions' => fn ($query) => $query
                            ->with(['user:id,name', 'releaseUser:id,name'])
                            ->orderByDesc('suppressed_at')
                            ->limit(10),
                    ]);
                })->all()
            )
            ->find($this->selectedListingId);

        if ($listing === null) {
            $this->selectedListingId = null;
        }

        return $listing;
    }

    #[Computed]
    public function suppressionHistory(): SupportCollection
    {
        $listing = $this->selectedListing;

        if ($listing === null) {
            return collect();
        }

        if (! $this->suppressionAvailable) {
            return collect();
        }

        return $listing->suppressions;
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

    private function parseSuppressionExpiry(?string $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        try {
            return Carbon::parse($trimmed, config('app.timezone'));
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'suppressionForm.expires_at' => [__('Provide a valid expiration date.')],
            ]);
        }
    }

    private function resetSuppressionForms(): void
    {
        $this->suppressionForm = [
            'reason' => '',
            'notes' => '',
            'expires_at' => '',
        ];

        $this->unsuppressionForm = [
            'reason' => '',
            'notes' => '',
        ];
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

    <!-- Accordion: Danger zone (purge all listings) -->
    <div x-data="{ open: false }" class="mt-10">
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900/60">
            <button type="button" class="flex w-full items-center justify-between px-4 py-3" x-on:click="open = ! open">
                <span class="text-sm font-semibold text-red-800 dark:text-red-200">{{ __('Danger zone') }}</span>
                <span class="text-zinc-500" x-text="open ? '–' : '+'"></span>
            </button>

            <div x-show="open" x-collapse class="border-t border-zinc-200 px-4 py-4 dark:border-zinc-700">
                <div class="flex flex-col gap-4">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <flux:text class="text-xs text-zinc-600 dark:text-zinc-400">
                            {{ __('Generate fake listings for testing. These records are created with factories and can be safely purged.') }}
                        </flux:text>
                        <div class="flex items-center gap-2">
                            <flux:button
                                variant="primary"
                                icon="plus"
                                wire:click="seedFakeListings(50)"
                                wire:loading.attr="disabled"
                                wire:target="seedFakeListings"
                            >
                                <span wire:loading.remove wire:target="seedFakeListings">
                                    {{ __('Generate 50 listings') }}
                                </span>
                                <span wire:loading wire:target="seedFakeListings" class="inline-flex items-center gap-2">
                                    <flux:icon name="arrow-path" class="animate-spin" />
                                    {{ __('Generating…') }}
                                </span>
                            </flux:button>
                            <x-action-message on="listings-seeded" class="text-sm text-emerald-700 dark:text-emerald-300">
                                {{ __('Fake listings generated.') }}
                            </x-action-message>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <flux:text class="text-xs text-red-700/90 dark:text-red-300/90">
                            {{ __('Permanently remove all listings and related media and status history. This action cannot be undone.') }}
                        </flux:text>
                        <div class="flex items-center gap-2">
                            <flux:button variant="danger" icon="trash" wire:click="confirmPurge" wire:loading.attr="disabled">
                                {{ __('Purge all listings') }}
                            </flux:button>
                            <x-action-message on="listings-purged" class="text-sm text-red-700 dark:text-red-300">
                                {{ __('Listings purged.') }}
                            </x-action-message>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <flux:modal wire:model="confirmingPurge" class="max-w-md">
        <div class="space-y-4">
            <flux:heading size="lg">{{ __('Purge all listings') }}</flux:heading>
            <flux:text>
                {{ __('This will permanently delete all listings and their related media and status history. This action cannot be undone.') }}
            </flux:text>

        <div class="flex items-center justify-end gap-2">
            <flux:button variant="outline" wire:click="$set('confirmingPurge', false)">
                {{ __('Cancel') }}
            </flux:button>

                            <flux:button
                                variant="danger"
                                wire:click="purgeAllListings"
                                wire:loading.attr="disabled"
                                wire:target="purgeAllListings"
                            >
                                <span wire:loading.remove wire:target="purgeAllListings">
                                    {{ __('Purge listings') }}
                                </span>
                                <span wire:loading wire:target="purgeAllListings" class="inline-flex items-center gap-2">
                                    <flux:icon name="arrow-path" class="animate-spin" />
                                    {{ __('Purging…') }}
                                </span>
                            </flux:button>
        </div>
    </div>
    </flux:modal>
</section>
