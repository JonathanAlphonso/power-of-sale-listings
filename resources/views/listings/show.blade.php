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
        <!-- Breadcrumb Navigation -->
        <nav class="mb-6" aria-label="Breadcrumb">
            <ol class="flex flex-wrap items-center gap-2 text-sm">
                <li>
                    <a href="{{ route('home') }}" class="text-slate-500 hover:text-emerald-600 dark:text-zinc-400 dark:hover:text-emerald-400">
                        {{ __('Home') }}
                    </a>
                </li>
                <li class="text-slate-400 dark:text-zinc-600">/</li>
                <li>
                    <a href="{{ route('listings.index') }}" class="text-slate-500 hover:text-emerald-600 dark:text-zinc-400 dark:hover:text-emerald-400">
                        {{ __('Listings') }}
                    </a>
                </li>
                @if ($listing->city)
                    <li class="text-slate-400 dark:text-zinc-600">/</li>
                    <li>
                        <a href="{{ route('listings.index', ['q' => $listing->city]) }}" class="text-slate-500 hover:text-emerald-600 dark:text-zinc-400 dark:hover:text-emerald-400">
                            {{ $listing->city }}
                        </a>
                    </li>
                @endif
                <li class="text-slate-400 dark:text-zinc-600">/</li>
                <li class="font-medium text-slate-900 dark:text-zinc-100 truncate max-w-[200px]" aria-current="page">
                    {{ $listing->street_address ?? __('Listing') }}
                </li>
            </ol>
        </nav>

        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:button
                as="a"
                :href="route('listings.index')"
                variant="ghost"
                icon="chevron-left"
            >
                {{ __('Back to listings') }}
            </flux:button>

            <div class="flex items-center gap-3">
                @auth
                    <livewire:favorites.toggle-button :listing-id="$listing->id" />
                @endauth

                <!-- Share dropdown -->
                <flux:dropdown>
                    <flux:button variant="ghost" icon="share" size="sm" />

                    <flux:menu class="w-48">
                        <flux:menu.heading>{{ __('Share listing') }}</flux:menu.heading>

                        <flux:menu.item
                            icon="link"
                            x-data
                            x-on:click.prevent="navigator.clipboard.writeText('{{ route('listings.show', $listing) }}').then(() => $flux.toast({ text: '{{ __('Link copied!') }}', variant: 'success' }))"
                        >
                            {{ __('Copy link') }}
                        </flux:menu.item>

                        <flux:menu.separator />

                        @php
                            $shareTitle = urlencode($listing->street_address . ' - ' . \App\Support\ListingPresentation::currency($listing->list_price));
                            $shareUrl = urlencode(route('listings.show', $listing));
                            $shareText = urlencode('Check out this power-of-sale listing: ' . $listing->street_address);
                        @endphp

                        <flux:menu.item
                            as="a"
                            href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ __('Share on Facebook') }}
                        </flux:menu.item>

                        <flux:menu.item
                            as="a"
                            href="https://twitter.com/intent/tweet?text={{ $shareText }}&url={{ $shareUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ __('Share on X') }}
                        </flux:menu.item>

                        <flux:menu.item
                            as="a"
                            href="https://www.linkedin.com/sharing/share-offsite/?url={{ $shareUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                        >
                            {{ __('Share on LinkedIn') }}
                        </flux:menu.item>

                        <flux:menu.item
                            as="a"
                            href="mailto:?subject={{ $shareTitle }}&body={{ $shareText }}%0A%0A{{ $shareUrl }}"
                        >
                            {{ __('Share via email') }}
                        </flux:menu.item>
                    </flux:menu>
                </flux:dropdown>

                <flux:badge color="{{ \App\Support\ListingPresentation::statusBadge($listing->display_status) }}" size="md">
                    {{ $listing->display_status ?? __('Unknown status') }}
                </flux:badge>
            </div>
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
                                {{ __('Price per sqft') }}
                            </dt>
                            <dd class="text-base font-semibold text-slate-900 dark:text-white">
                                {{ \App\Support\ListingPresentation::pricePerSqft($listing->list_price, $listing->square_feet) }}
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

        <!-- Related Listings Section -->
        @if (isset($relatedListings) && $relatedListings->isNotEmpty())
            <div class="mt-12 border-t border-slate-200 pt-12 dark:border-zinc-800">
                <flux:heading size="lg" class="mb-6">
                    {{ __('Similar properties') }}
                </flux:heading>

                <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-4">
                    @foreach ($relatedListings as $related)
                        @php
                            $relatedMedia = $related->media->firstWhere('is_primary', true) ?? $related->media->first();
                            $relatedAddress = $related->street_address ?? __('Address unavailable');
                            $relatedLocation = collect([$related->city, $related->province])->filter()->implode(', ');
                        @endphp

                        <a
                            href="{{ route('listings.show', $related) }}"
                            class="group flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg dark:border-zinc-800 dark:bg-zinc-900/70"
                        >
                            @if ($relatedMedia !== null)
                                <img
                                    src="{{ $relatedMedia->public_url }}"
                                    alt="{{ $relatedAddress }}"
                                    class="aspect-video w-full object-cover"
                                    loading="lazy"
                                />
                            @else
                                <div class="flex aspect-video items-center justify-center bg-slate-100 text-3xl text-slate-300 dark:bg-zinc-800 dark:text-zinc-600">
                                    <flux:icon name="photo" />
                                </div>
                            @endif

                            <div class="flex flex-1 flex-col gap-2 p-4">
                                <h3 class="text-sm font-semibold text-slate-900 group-hover:text-emerald-600 dark:text-white dark:group-hover:text-emerald-400 line-clamp-1">
                                    {{ $relatedAddress }}
                                </h3>

                                @if ($relatedLocation !== '')
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 line-clamp-1">
                                        {{ $relatedLocation }}
                                    </p>
                                @endif

                                <div class="mt-auto flex items-center justify-between pt-2">
                                    <span class="text-sm font-semibold text-emerald-600 dark:text-emerald-400">
                                        {{ \App\Support\ListingPresentation::currency($related->list_price) }}
                                    </span>
                                    <span class="text-xs text-slate-500 dark:text-zinc-400">
                                        {{ \App\Support\ListingPresentation::pricePerSqft($related->list_price, $related->square_feet) }}
                                    </span>
                                </div>
                            </div>
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </section>
</x-layouts.site>
