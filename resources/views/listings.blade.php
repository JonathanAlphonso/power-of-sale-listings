<x-layouts.site :title="__('Current Listings')">
    <section class="mx-auto max-w-6xl px-6 py-12 lg:px-8">
        <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
            <div class="space-y-2">
                <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">
                    {{ __('Current listings') }}
                </h1>
                <p class="text-sm text-slate-600 dark:text-zinc-400">
                    {{ __('Explore available power-of-sale properties across Ontario, refreshed with the latest market activity.') }}
                </p>
            </div>

            <flux:badge color="blue" class="self-start">
                {{ trans_choice(':count listing|:count listings', $listings->total(), ['count' => number_format($listings->total())]) }}
            </flux:badge>
        </div>

        <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @forelse ($listings as $listing)
                @php
                    /** @var \App\Models\Listing $listing */
                    $primaryMedia = $listing->media->firstWhere('is_primary', true) ?? $listing->media->first();
                    $address = $listing->street_address ?? __('Address unavailable');
                    $location = collect([$listing->city, $listing->province, $listing->postal_code])->filter()->implode(', ');
                @endphp

                <a
                    href="{{ route('listings.show', $listing) }}"
                    class="group flex h-full flex-col overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 dark:border-zinc-800 dark:bg-zinc-900/70"
                >
                    @if ($primaryMedia !== null)
                        <img
                            src="{{ $primaryMedia->preview_url ?? $primaryMedia->url }}"
                            alt="{{ $primaryMedia->label ?? $address }}"
                            class="aspect-video w-full object-cover"
                            loading="lazy"
                        />
                    @else
                        <div class="flex aspect-video items-center justify-center bg-slate-100 text-4xl text-slate-300 dark:bg-zinc-800 dark:text-zinc-600">
                            <flux:icon name="photo" />
                        </div>
                    @endif

                    <div class="flex flex-1 flex-col gap-4 p-6">
                        <div class="flex items-start justify-between gap-3">
                            <div class="space-y-1">
                                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">
                                    {{ $address }}
                                </h2>

                                @if ($location !== '')
                                    <p class="text-sm text-slate-500 dark:text-zinc-400">
                                        {{ $location }}
                                    </p>
                                @endif
                            </div>

                            <flux:badge color="{{ \App\Support\ListingPresentation::statusBadge($listing->display_status) }}">
                                {{ $listing->display_status ?? __('Unknown') }}
                            </flux:badge>
                        </div>

                        <div class="grid gap-4 rounded-xl border border-dashed border-slate-200 p-4 dark:border-zinc-800 sm:grid-cols-2">
                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                    {{ __('List price') }}
                                </p>
                                <p class="text-base font-semibold text-slate-900 dark:text-white">
                                    {{ \App\Support\ListingPresentation::currency($listing->list_price) }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                    {{ __('Status') }}
                                </p>
                                <p class="text-base font-semibold text-slate-900 dark:text-white">
                                    {{ $listing->display_status ?? __('Unknown') }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                    {{ __('Bedrooms') }}
                                </p>
                                <p class="text-base font-semibold text-slate-900 dark:text-white">
                                    {{ \App\Support\ListingPresentation::numeric($listing->bedrooms) }}
                                </p>
                            </div>

                            <div>
                                <p class="text-xs uppercase tracking-wide text-slate-500 dark:text-zinc-400">
                                    {{ __('Bathrooms') }}
                                </p>
                                <p class="text-base font-semibold text-slate-900 dark:text-white">
                                    {{ \App\Support\ListingPresentation::numeric($listing->bathrooms, 1) }}
                                </p>
                            </div>
                        </div>

                        <div class="mt-auto space-y-2 text-xs text-slate-500 dark:text-zinc-400">
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ __('MLS number') }}</span>
                                <span>{{ $listing->mls_number ?? '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ __('Source') }}</span>
                                <span>{{ $listing->source?->name ?? '—' }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="font-medium">{{ __('Last modified') }}</span>
                                <span>{{ optional($listing->modified_at)?->diffForHumans() ?? __('Unknown') }}</span>
                            </div>
                        </div>
                    </div>
                </a>
            @empty
                <div class="sm:col-span-2 lg:col-span-3">
                    <flux:callout class="rounded-2xl">
                        <flux:callout.heading>{{ __('No listings available right now') }}</flux:callout.heading>
                        <flux:callout.text>
                            {{ __('Check back soon—new foreclosure opportunities will appear here as they are ingested from MLS feeds.') }}
                        </flux:callout.text>
                    </flux:callout>
                </div>
            @endforelse
        </div>

        <div class="mt-10">
            {{ $listings->onEachSide(1)->links() }}
        </div>
    </section>
</x-layouts.site>
