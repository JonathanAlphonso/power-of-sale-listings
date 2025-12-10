<x-layouts.site :title="$listing->street_address ?? __('Listing Details')">
    @php
        $gallery = $listing->media;
        $primaryPhoto = $gallery->firstWhere('is_primary', true) ?? $gallery->first();
        $additionalMedia = $primaryPhoto !== null
            ? $gallery->reject(fn ($mediaItem) => $mediaItem->is($primaryPhoto))
            : $gallery;

        $cityLabel = $listing->city;

        if ($listing->neighbourhood !== null) {
            $cityLabel = $cityLabel
                ? sprintf('%s (%s)', $cityLabel, $listing->neighbourhood)
                : $listing->neighbourhood;
        } elseif ($listing->district !== null) {
            $cityLabel = $cityLabel
                ? sprintf('%s (%s)', $cityLabel, $listing->district)
                : $listing->district;
        }

        $provinceLine = trim(collect([$listing->province, $listing->postal_code])->filter()->implode(' '));
        $locationLine = collect([$cityLabel, $provinceLine])->filter()->implode(', ');
    @endphp

    <section class="mx-auto max-w-6xl px-6 py-12 lg:px-8">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:button
                as="a"
                :href="route('listings.index')"
                variant="ghost"
                icon="chevron-left"
            >
                {{ __('Back to listings') }}
            </flux:button>

            <flux:badge color="{{ \App\Support\ListingPresentation::statusBadge($listing->display_status) }}" size="md">
                {{ $listing->display_status ?? __('Unknown status') }}
            </flux:badge>
        </div>

        <div class="mt-6 flex flex-col gap-4">
            <div class="flex flex-col gap-1">
                <flux:text class="text-sm uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                    {{ __('Current List Price') }}
                </flux:text>

                <flux:heading size="xl" class="text-5xl font-semibold text-emerald-600 dark:text-emerald-400">
                    {{ \App\Support\ListingPresentation::currency($listing->list_price) }}
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

        <div class="mt-10 grid gap-8 lg:grid-cols-[minmax(0,1.65fr)_minmax(0,1fr)]">
            <div class="flex flex-col gap-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                    <flux:heading size="md" class="mb-4">
                        {{ __('Listing highlights') }}
                    </flux:heading>

                    <dl class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                {{ __('Status') }}
                            </dt>
                            <dd class="text-base font-semibold text-slate-900 dark:text-white">
                                {{ $listing->display_status ?? __('Unknown') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                {{ __('Original list price') }}
                            </dt>
                            <dd class="text-base font-semibold text-slate-900 dark:text-white">
                                {{ \App\Support\ListingPresentation::currency($listing->original_list_price) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                {{ __('Bedrooms') }}
                            </dt>
                            <dd class="text-base font-semibold text-slate-900 dark:text-white">
                                {{ \App\Support\ListingPresentation::numeric($listing->bedrooms) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                {{ __('Bathrooms') }}
                            </dt>
                            <dd class="text-base font-semibold text-slate-900 dark:text-white">
                                {{ \App\Support\ListingPresentation::numeric($listing->bathrooms, 1) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                {{ __('Square feet') }}
                            </dt>
                            <dd class="text-base font-semibold text-slate-900 dark:text-white">
                                {{ $listing->square_feet_text ?? \App\Support\ListingPresentation::numeric($listing->square_feet) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                {{ __('Days on market') }}
                            </dt>
                            <dd class="text-base font-semibold text-slate-900 dark:text-white">
                                {{ \App\Support\ListingPresentation::numeric($listing->days_on_market) }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                {{ __('Source') }}
                            </dt>
                            <dd class="text-base font-semibold text-slate-900 dark:text-white">
                                {{ $listing->source?->name ?? __('Unknown') }}
                            </dd>
                        </div>
                        <div>
                            <dt class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                {{ __('Last updated') }}
                            </dt>
                            <dd class="text-base font-semibold text-slate-900 dark:text-white">
                                {{ optional($listing->modified_at)?->timezone(config('app.timezone'))->format('M j, Y g:i a') ?? __('Unknown') }}
                            </dd>
                        </div>
                    </dl>
                </div>

                @if ($listing->public_remarks)
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                        <flux:heading size="md" class="mb-4">
                            {{ __('Property description') }}
                        </flux:heading>

                        <div class="prose prose-slate max-w-none dark:prose-invert prose-p:text-slate-600 dark:prose-p:text-zinc-300">
                            <p class="whitespace-pre-line text-sm leading-relaxed">{{ $listing->public_remarks }}</p>
                        </div>
                    </div>
                @endif

                @php
                    $statusHistory = $listing->statusHistory()->limit(10)->get();
                @endphp

                @if ($statusHistory->isNotEmpty())
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                        <flux:heading size="md" class="mb-4">
                            {{ __('Status history') }}
                        </flux:heading>

                        <div class="relative">
                            <div class="absolute top-0 bottom-0 left-3 w-0.5 bg-slate-200 dark:bg-zinc-700"></div>

                            <ul class="space-y-4">
                                @foreach ($statusHistory as $history)
                                    @php
                                        $statusColor = match(strtolower($history->status_label ?? $history->status_code ?? '')) {
                                            'active', 'available' => 'bg-green-500',
                                            'sold', 'closed' => 'bg-blue-500',
                                            'pending', 'conditional' => 'bg-yellow-500',
                                            'expired', 'withdrawn', 'cancelled', 'terminated' => 'bg-red-500',
                                            default => 'bg-slate-400 dark:bg-zinc-500',
                                        };
                                    @endphp
                                    <li class="relative flex items-start gap-4 pl-8">
                                        <span class="absolute left-1.5 top-1.5 h-3 w-3 rounded-full {{ $statusColor }} ring-2 ring-white dark:ring-zinc-900"></span>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex flex-wrap items-center gap-2">
                                                <span class="text-sm font-medium text-slate-900 dark:text-zinc-100">
                                                    {{ $history->status_label ?? $history->status_code ?? __('Status change') }}
                                                </span>
                                                @if ($history->changed_at)
                                                    <span class="text-xs text-slate-500 dark:text-zinc-400">
                                                        {{ $history->changed_at->timezone(config('app.timezone'))->format('M j, Y') }}
                                                    </span>
                                                @endif
                                            </div>
                                            @if ($history->notes)
                                                <p class="mt-1 text-sm text-slate-600 dark:text-zinc-300">
                                                    {{ $history->notes }}
                                                </p>
                                            @endif
                                        </div>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>

            <div class="flex flex-col gap-6">
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                    <flux:heading size="md" class="mb-4">
                        {{ __('Media gallery') }}
                    </flux:heading>

                    @if ($primaryPhoto !== null)
                        <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-zinc-800">
                            <img
                                src="{{ $primaryPhoto->public_url }}"
                                alt="{{ $primaryPhoto->label ?? $listing->street_address ?? __('Listing photo') }}"
                                class="aspect-video w-full object-cover"
                                loading="lazy"
                            />
                        </div>
                    @else
                        <div class="flex aspect-video items-center justify-center rounded-xl border border-dashed border-slate-200 bg-slate-50 text-4xl text-slate-300 dark:border-zinc-700 dark:bg-zinc-900/70 dark:text-zinc-600">
                            <flux:icon name="photo" />
                        </div>
                    @endif

                    @if ($additionalMedia->isNotEmpty())
                        <div class="mt-4 grid gap-4 sm:grid-cols-2">
                            @foreach ($additionalMedia as $mediaItem)
                                <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-zinc-800">
                                    <img
                                        src="{{ $mediaItem->public_url }}"
                                        alt="{{ $mediaItem->label ?? $listing->street_address ?? __('Listing photo') }}"
                                        class="aspect-video w-full object-cover"
                                        loading="lazy"
                                    />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
