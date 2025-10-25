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

                    <flux:badge color="{{ \App\Support\ListingPresentation::statusBadge($selectedListing->display_status) }}">
                        {{ $selectedListing->display_status ?? __('Unknown') }}
                    </flux:badge>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-900/70">
                        <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ __('Asking price') }}
                        </flux:text>
                        <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ \App\Support\ListingPresentation::currency($selectedListing->list_price) }}
                        </p>
                    </div>

                    <div class="rounded-xl bg-zinc-50 px-4 py-3 dark:bg-zinc-900/70">
                        <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ __('Sale type') }}
                        </flux:text>
                        <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ \App\Support\ListingPresentation::saleType($selectedListing->sale_type) }}
                        </p>
                    </div>
                </div>

                <div class="grid gap-4 rounded-xl border border-dashed border-zinc-200 p-4 dark:border-zinc-700 sm:grid-cols-4">
                    <div>
                        <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ __('Beds') }}
                        </flux:text>
                        <p class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ \App\Support\ListingPresentation::numeric($selectedListing->bedrooms) }}
                        </p>
                    </div>
                    <div>
                        <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ __('Baths') }}
                        </flux:text>
                        <p class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ \App\Support\ListingPresentation::numeric($selectedListing->bathrooms, 1) }}
                        </p>
                    </div>
                    <div>
                        <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ __('Square feet') }}
                        </flux:text>
                        <p class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ $selectedListing->square_feet_text ?? \App\Support\ListingPresentation::numeric($selectedListing->square_feet) }}
                        </p>
                    </div>
                    <div>
                        <flux:text class="text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                            {{ __('Days on market') }}
                        </flux:text>
                        <p class="text-base font-semibold text-zinc-900 dark:text-zinc-100">
                                    {{ \App\Support\ListingPresentation::numeric($selectedListing->days_on_market) }}
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
