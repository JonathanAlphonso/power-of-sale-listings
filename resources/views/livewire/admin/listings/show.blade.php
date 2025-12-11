<?php

use App\Models\Listing;
use App\Support\ListingPresentation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Volt\Component;

new #[Layout('components.layouts.app')] class extends Component {
    #[Locked]
    public int $listingId;

    public bool $suppressionAvailable = false;

    public function mount(Listing $listing): void
    {
        Gate::authorize('view', $listing);

        $this->listingId = $listing->getKey();
        $this->suppressionAvailable = Listing::suppressionSchemaAvailable();
    }

    #[Computed]
    public function listing(): Listing
    {
        $with = [
            'media' => fn ($query) => $query->orderBy('position'),
            'source:id,name',
            'municipality:id,name',
            'statusHistory' => fn ($query) => $query
                ->with('source:id,name')
                ->orderByDesc('changed_at'),
        ];

        if ($this->suppressionAvailable) {
            $with[] = 'suppressedBy:id,name';
            $with['currentSuppression'] = fn ($query) => $query->with(['user:id,name', 'releaseUser:id,name']);
            $with['suppressions'] = fn ($query) => $query
                ->with(['user:id,name', 'releaseUser:id,name'])
                ->orderByDesc('suppressed_at');
        }

        return Listing::query()
            ->with($with)
            ->findOrFail($this->listingId);
    }

    #[Computed]
    public function metadataPanels(): array
    {
        $listing = $this->listing;
        $timezone = Config::get('app.timezone');
        $suppressionAvailable = $this->suppressionAvailable;

        return [
            [
                'title' => __('Overview'),
                'items' => [
                    [
                        'label' => __('MLS number'),
                        'value' => $listing->mls_number ?? '—',
                    ],
                    [
                        'label' => __('Display status'),
                        'value' => $listing->display_status ?? '—',
                    ],
                    [
                        'label' => __('Suppressed'),
                        'value' => match (true) {
                            ! $suppressionAvailable => __('Unavailable — run the latest migrations'),
                            $listing->suppressed_at !== null => __('Yes — since :date (:diff)', [
                                'date' => $listing->suppressed_at->timezone($timezone)->format('M j, Y g:i a'),
                                'diff' => $listing->suppressed_at->diffForHumans(),
                            ]),
                            default => __('No'),
                        },
                    ],
                    [
                        'label' => __('Suppression expires'),
                        'value' => $suppressionAvailable
                            ? ($listing->suppression_expires_at !== null
                                ? $listing->suppression_expires_at->timezone($timezone)->format('M j, Y g:i a')
                                : __('No expiry'))
                            : __('Unavailable'),
                    ],
                    [
                        'label' => __('Suppression reason'),
                        'value' => $suppressionAvailable ? ($listing->suppression_reason ?? '—') : __('Unavailable'),
                    ],
                    [
                        'label' => __('List price'),
                        'value' => ListingPresentation::currency($listing->list_price),
                    ],
                    [
                        'label' => __('Original list price'),
                        'value' => ListingPresentation::currency($listing->original_list_price),
                    ],
                    [
                        'label' => __('Price change'),
                        'value' => ListingPresentation::numeric($listing->price_change),
                    ],
                    [
                        'label' => __('Price direction'),
                        'value' => $this->priceChangeDirectionLabel($listing->price_change_direction),
                    ],
                    [
                        'label' => __('Last modified'),
                        'value' => $listing->modified_at?->timezone($timezone)?->format('M j, Y g:i a') ?? '—',
                    ],
                ],
            ],
            [
                'title' => __('Location & source'),
                'items' => [
                    [
                        'label' => __('Municipality'),
                        'value' => $listing->municipality?->name ?? '—',
                    ],
                    [
                        'label' => __('City'),
                        'value' => $listing->city ?? '—',
                    ],
                    [
                        'label' => __('Board code'),
                        'value' => $listing->board_code ?? '—',
                    ],
                    [
                        'label' => __('Source'),
                        'value' => $listing->source?->name ?? '—',
                    ],
                    [
                        'label' => __('Ingestion batch'),
                        'value' => $listing->ingestion_batch_id ?? '—',
                    ],
                    [
                        'label' => __('Parcel ID'),
                        'value' => $listing->parcel_id ?? '—',
                    ],
                    [
                        'label' => __('Address visibility'),
                        'value' => $this->booleanLabel($listing->is_address_public),
                    ],
                    [
                        'label' => __('Coordinates'),
                        'value' => $this->formattedCoordinates($listing),
                    ],
                ],
            ],
            [
                'title' => __('Property facts'),
                'items' => [
                    [
                        'label' => __('Property class'),
                        'value' => $listing->property_class ?? '—',
                    ],
                    [
                        'label' => __('Property type'),
                        'value' => $listing->property_type ?? '—',
                    ],
                    [
                        'label' => __('Property style'),
                        'value' => $listing->property_style ?? '—',
                    ],
                    [
                        'label' => __('Bedrooms'),
                        'value' => ListingPresentation::numeric($listing->bedrooms),
                    ],
                    [
                        'label' => __('Possible bedrooms'),
                        'value' => ListingPresentation::numeric($listing->bedrooms_possible),
                    ],
                    [
                        'label' => __('Bathrooms'),
                        'value' => ListingPresentation::numeric($listing->bathrooms, 1),
                    ],
                    [
                        'label' => __('Square feet'),
                        'value' => $listing->square_feet_text ?? ListingPresentation::numeric($listing->square_feet),
                    ],
                    [
                        'label' => __('Days on market'),
                        'value' => ListingPresentation::numeric($listing->days_on_market),
                    ],
                ],
            ],
        ];
    }

    #[Computed]
    public function gallery(): Collection
    {
        return $this->listing->media;
    }

    #[Computed]
    public function history(): Collection
    {
        return $this->listing->statusHistory->take(15)->values();
    }

    private function priceChangeDirectionLabel(?int $direction): string
    {
        return match ($direction) {
            -1 => __('Decreased'),
            0 => __('No change'),
            1 => __('Increased'),
            default => '—',
        };
    }

    private function booleanLabel(?bool $value): string
    {
        return match ($value) {
            true => __('Public'),
            false => __('Private'),
            default => '—',
        };
    }

    private function formattedCoordinates(Listing $listing): string
    {
        if ($listing->latitude === null || $listing->longitude === null) {
            return '—';
        }

        $lat = number_format((float) $listing->latitude, 4);
        $lng = number_format((float) $listing->longitude, 4);

        return "{$lat}, {$lng}";
    }
}; ?>

@php
    /** @var \App\Models\Listing $listing */
    $listing = $this->listing;

    $metadataPanels = $this->metadataPanels;
    $gallery = $this->gallery;
    $history = $this->history;
    $suppressionAvailable = $this->suppressionAvailable;
    $currentSuppression = $suppressionAvailable ? $listing->currentSuppression : null;
    $suppressionHistory = $suppressionAvailable ? $listing->suppressions : collect();
    $appTimezone = Config::get('app.timezone');

    $primaryPhoto = $gallery->firstWhere('is_primary', true) ?? $gallery->first();
    $cityLabel = $listing->city ?? null;

    if ($listing->neighbourhood !== null) {
        $cityLabel = trim(($cityLabel ?? '') === '' ? $listing->neighbourhood : sprintf('%s (%s)', $cityLabel, $listing->neighbourhood));
    } elseif ($listing->district !== null) {
        $cityLabel = trim(($cityLabel ?? '') === '' ? $listing->district : sprintf('%s (%s)', $cityLabel, $listing->district));
    }

    $provinceLine = trim(collect([$listing->province, $listing->postal_code])->filter()->implode(' '));
    $locationLine = collect([$cityLabel, $provinceLine])->filter()->implode(', ');
@endphp

<section class="mx-auto w-full max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <flux:button
            as="a"
            :href="route('admin.listings.index')"
            variant="ghost"
            icon="chevron-left"
            wire:navigate
        >
            {{ __('Back to listings') }}
        </flux:button>

        <div class="flex items-center gap-2">
            <flux:badge color="{{ ListingPresentation::statusBadge($listing->display_status) }}" size="md">
                {{ $listing->display_status ?? __('Unknown status') }}
            </flux:badge>

            @if ($suppressionAvailable && $listing->isSuppressed())
                <flux:badge color="red" size="sm">
                    {{ __('Suppressed') }}
                </flux:badge>
            @endif
        </div>
    </div>

    <div class="mt-6 flex flex-col gap-4">
        <div class="flex flex-col gap-1">
            <flux:text class="text-sm uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                {{ __('Current List Price') }}
            </flux:text>

            <flux:heading size="xl" class="text-5xl font-semibold text-emerald-600 dark:text-emerald-400">
                {{ ListingPresentation::currency($listing->list_price) }}
            </flux:heading>
        </div>

        <div class="flex flex-col gap-1">
            <flux:heading size="lg" class="font-semibold text-slate-900 uppercase tracking-tight dark:text-zinc-100">
                {{ strtoupper($listing->street_address ?? __('Address unavailable')) }}
            </flux:heading>

            <flux:text class="text-base text-slate-600 dark:text-zinc-300">
                {{ $locationLine !== '' ? $locationLine : __('No additional address context is available.') }}
            </flux:text>
        </div>

        @if ($listing->mls_number)
            <flux:text class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                {{ __('MLS® Number: :mls', ['mls' => $listing->mls_number]) }}
            </flux:text>
        @endif
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-[minmax(0,2fr)_minmax(0,1.15fr)]">
        <div class="flex flex-col gap-6">
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                <div class="flex flex-col gap-2">
                    <flux:heading size="md">{{ __('Listing metadata') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Key facts, pricing, and location data pulled from the canonical record.') }}
                    </flux:text>
                </div>

                <div class="mt-6 flex flex-col gap-6">
                    @foreach ($metadataPanels as $panel)
                        <div class="flex flex-col gap-3">
                            <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                {{ $panel['title'] }}
                            </flux:text>

                            <dl class="grid gap-4 sm:grid-cols-2">
                                @foreach ($panel['items'] as $item)
                                    <div class="rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-900/70">
                                        <dt class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                            {{ $item['label'] }}
                                        </dt>
                                        <dd class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                            {{ $item['value'] }}
                                        </dd>
                                    </div>
                                @endforeach
                            </dl>
                        </div>
                    @endforeach
                </div>
            </div>

            @if ($listing->public_remarks)
                <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                    <div class="flex flex-col gap-2">
                        <flux:heading size="md">{{ __('Public remarks') }}</flux:heading>
                        <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                            {{ __('The full listing description as provided by the MLS feed.') }}
                        </flux:text>
                    </div>

                    <div class="mt-4 rounded-xl bg-zinc-50 p-4 dark:bg-zinc-900/70">
                        <p class="whitespace-pre-line text-sm leading-relaxed text-zinc-700 dark:text-zinc-300">{{ $listing->public_remarks }}</p>
                    </div>
                </div>
            @endif

            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                <div class="flex flex-col gap-2">
                    <flux:heading size="md">{{ __('Change history') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Review the most recent status updates observed for this listing.') }}
                    </flux:text>
                </div>

                @if ($history->isNotEmpty())
                    <div class="mt-6">
                        <flux:table class="!border-none !bg-transparent !shadow-none">
                            <flux:table.header>
                                <span>{{ __('Status') }}</span>
                                <span>{{ __('Code') }}</span>
                                <span class="text-center">{{ __('Changed') }}</span>
                                <span class="text-center">{{ __('Source') }}</span>
                                <span>{{ __('Notes') }}</span>
                            </flux:table.header>

                            <flux:table.rows>
                                @foreach ($history as $record)
                                    <flux:table.row wire:key="history-record-{{ $record->id }}">
                                        <flux:table.cell>
                                            <span class="font-medium text-zinc-900 dark:text-white">
                                                {{ $record->status_label ?? '—' }}
                                            </span>
                                        </flux:table.cell>

                                        <flux:table.cell>
                                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                                                {{ $record->status_code ?? '—' }}
                                            </flux:text>
                                        </flux:table.cell>

                                        <flux:table.cell alignment="center">
                                            <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                                                {{ $record->changed_at?->timezone(Config::get('app.timezone'))?->format('M j, Y g:i a') ?? __('—') }}
                                            </span>
                                            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                                {{ $record->changed_at?->diffForHumans() ?? __('No timestamp') }}
                                            </flux:text>
                                        </flux:table.cell>

                                        <flux:table.cell alignment="center">
                                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                                                {{ $record->source?->name ?? '—' }}
                                            </flux:text>
                                        </flux:table.cell>

                                        <flux:table.cell>
                                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                                                {{ $record->notes ?? '—' }}
                                            </flux:text>
                                        </flux:table.cell>
                                    </flux:table.row>
                                @endforeach
                            </flux:table.rows>
                        </flux:table>
                    </div>
                @else
                    <flux:callout class="mt-6 rounded-2xl">
                        <flux:callout.heading>{{ __('No change history available yet') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('Status transitions will surface here as soon as sync jobs begin recording updates for this record.') }}
                        </flux:callout.text>
                    </flux:callout>
                @endif
            </div>
        </div>

        <div class="flex flex-col gap-6">
            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                <div class="flex flex-col gap-2">
                    <flux:heading size="md">{{ __('Suppression status') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Track manual suppressions and expirations recorded against this listing.') }}
                    </flux:text>
                </div>

                @if (! $suppressionAvailable)
                    <flux:callout class="mt-4 rounded-xl">
                        <flux:callout.heading>{{ __('Migrations required') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('Run the latest database migrations to enable suppression insights in this environment.') }}
                        </flux:callout.text>
                    </flux:callout>
                @elseif ($listing->isSuppressed() && $currentSuppression !== null)
                    <div class="mt-4 space-y-3 rounded-xl border border-dashed border-red-300/60 bg-red-50/60 p-4 text-sm text-red-700 dark:border-red-700/40 dark:bg-red-900/30 dark:text-red-200">
                        <div class="flex items-start justify-between gap-2">
                            <span class="font-semibold">{{ $currentSuppression->reason }}</span>
                            <flux:badge color="red" size="xs">
                                {{ __('Active') }}
                            </flux:badge>
                        </div>

                        @if ($currentSuppression->notes)
                            <p class="text-xs">{{ $currentSuppression->notes }}</p>
                        @endif

                        <dl class="mt-3 grid gap-2 text-[11px] uppercase tracking-wide text-red-800 dark:text-red-200">
                            <div class="flex flex-col">
                                <dt>{{ __('Suppressed by') }}</dt>
                                <dd class="text-red-700 dark:text-red-100">
                                    {{ $currentSuppression->user?->name ?? __('System') }}
                                </dd>
                            </div>
                            <div class="flex flex-col">
                                <dt>{{ __('Suppressed at') }}</dt>
                                <dd class="text-red-700 dark:text-red-100">
                                    {{ optional($currentSuppression->suppressed_at)?->timezone($appTimezone)?->format('M j, Y g:i a') ?? '—' }}
                                </dd>
                            </div>
                            <div class="flex flex-col">
                                <dt>{{ __('Expires') }}</dt>
                                <dd class="text-red-700 dark:text-red-100">
                                    @if ($currentSuppression->expires_at === null)
                                        {{ __('No expiry') }}
                                    @else
                                        {{ $currentSuppression->expires_at->timezone($appTimezone)->format('M j, Y g:i a') }}
                                        <span class="block text-[10px] normal-case">
                                            {{ $currentSuppression->expires_at->diffForHumans() }}
                                        </span>
                                    @endif
                                </dd>
                            </div>
                            <div class="flex flex-col">
                                <dt>{{ __('Recorded by') }}</dt>
                                <dd class="text-red-700 dark:text-red-100">
                                    {{ $listing->suppressedBy?->name ?? __('System') }}
                                </dd>
                            </div>
                        </dl>
                    </div>
                @else
                    <flux:callout class="mt-4 rounded-xl">
                        <flux:callout.heading>{{ __('Not currently suppressed') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('This listing is eligible for public exposure. Use the admin listings workspace to initiate a manual suppression when needed.') }}
                        </flux:callout.text>
                    </flux:callout>
                @endif

                @if ($suppressionAvailable && $suppressionHistory->isNotEmpty())
                    <div class="mt-6 space-y-3">
                        <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ __('Suppression events') }}
                        </flux:text>

                        @foreach ($suppressionHistory as $record)
                            <div class="rounded-xl border border-zinc-200 bg-white/80 p-4 text-sm text-zinc-600 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-300">
                                <div class="flex items-start justify-between gap-3">
                                    <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $record->reason }}
                                    </span>
                                    <flux:badge color="{{ $record->released_at !== null ? 'green' : 'zinc' }}" size="xs">
                                        {{ $record->released_at !== null ? __('Released') : __('Suppressed') }}
                                    </flux:badge>
                                </div>

                                @if ($record->notes)
                                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $record->notes }}
                                    </p>
                                @endif

                                <dl class="mt-3 grid gap-3 text-[11px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    <div>
                                        <dt>{{ __('Suppressed at') }}</dt>
                                        <dd class="text-zinc-700 dark:text-zinc-200">
                                            {{ optional($record->suppressed_at)?->timezone($appTimezone)?->format('M j, Y g:i a') ?? '—' }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>{{ __('Suppressed by') }}</dt>
                                        <dd class="text-zinc-700 dark:text-zinc-200">
                                            {{ $record->user?->name ?? __('System') }}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>{{ __('Expires') }}</dt>
                                        <dd class="text-zinc-700 dark:text-zinc-200">
                                            @if ($record->expires_at === null)
                                                {{ __('No expiry') }}
                                            @else
                                                {{ $record->expires_at->timezone($appTimezone)->format('M j, Y g:i a') }}
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>{{ __('Released at') }}</dt>
                                        <dd class="text-zinc-700 dark:text-zinc-200">
                                            @if ($record->released_at !== null)
                                                {{ $record->released_at->timezone($appTimezone)->format('M j, Y g:i a') }}
                                                <span class="block text-[10px] normal-case">
                                                    {{ $record->releaseUser?->name ?? __('System') }}
                                                </span>
                                            @else
                                                {{ __('Pending') }}
                                            @endif
                                        </dd>
                                    </div>

                                    @if ($record->release_reason)
                                        <div class="normal-case">
                                            <dt>{{ __('Release reason') }}</dt>
                                            <dd class="text-zinc-700 dark:text-zinc-200">
                                                {{ $record->release_reason }}
                                            </dd>
                                        </div>
                                    @endif

                                    @if ($record->release_notes)
                                        <div class="normal-case">
                                            <dt>{{ __('Release notes') }}</dt>
                                            <dd class="text-zinc-700 dark:text-zinc-200">
                                                {{ $record->release_notes }}
                                            </dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <div class="rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
                <div class="flex flex-col gap-2">
                    <flux:heading size="md">{{ __('Media gallery') }}</flux:heading>
                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Preview photos associated with the listing payload.') }}
                    </flux:text>
                </div>

                @if ($gallery->isNotEmpty())
                    <div class="mt-6 space-y-4">
                        @if ($primaryPhoto !== null)
                            <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                                <img
                                    src="{{ $primaryPhoto->public_url }}"
                                    alt="{{ $primaryPhoto->label ?? __('Listing photo') }}"
                                    class="aspect-video w-full object-cover"
                                    loading="lazy"
                                />
                            </div>
                        @endif

                        @if ($gallery->count() > 1)
                            <div class="grid gap-4 sm:grid-cols-2">
                                @foreach ($gallery->filter(fn ($mediaItem) => $primaryPhoto === null || $mediaItem->is($primaryPhoto) === false) as $mediaItem)
                                    <div class="overflow-hidden rounded-xl border border-zinc-200 dark:border-zinc-700">
                                        <img
                                            src="{{ $mediaItem->public_url }}"
                                            alt="{{ $mediaItem->label ?? __('Listing photo') }}"
                                            class="aspect-video w-full object-cover"
                                            loading="lazy"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @else
                    <x-listing.no-photo-placeholder class="mt-6 aspect-video rounded-xl" />
                @endif
            </div>
        </div>
    </div>
</section>
