<section id="listings-demo" class="scroll-mt-28 lg:scroll-mt-32 border-t border-slate-200 bg-slate-50 px-6 py-24 lg:px-8 dark:border-zinc-800 dark:bg-zinc-800/50">
    <div class="mx-auto max-w-6xl">
        <div class="flex flex-wrap items-center justify-between gap-6">
            <div>
                <x-ui.section-badge>Live Demo</x-ui.section-badge>
                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl dark:text-white">Recent demo listings</h2>
                <p class="mt-2 text-sm text-slate-600 dark:text-zinc-400">Pulled directly from the application database to verify ingestion pipelines end-to-end.</p>
            </div>
            <span class="text-xs font-semibold uppercase tracking-[0.35em] {{ $sampleListings->isNotEmpty() ? 'text-emerald-500 dark:text-emerald-400' : 'text-red-500 dark:text-red-400' }}">
                {{ $sampleListings->isNotEmpty() ? 'Rendering from database' : 'Unable to display listings' }}
            </span>
        </div>

        @php
            $fallbackImage = 'https://live-images.stratuscollab.com/ZxJdIMfbzmv_nD9_6Fc-YiOnigxaucskCqQMXAwKSuQ/rs:fill:1900:1900:0/g:no/cb:MjAyNS0xMC0xM1QyMjoyMDowOS4wMDBa/aHR0cHM6Ly90cnJlYi1pbWFnZS5hbXByZS5jYS9GVS1HeS1SZldMdU9oRE5Jc3NnWTBMVEFubXFxNlZMbGVBYjZLTUUxcVhzL3JzOmZpdC93OjE5MDAvaDoxOTAwL2c6Y2Uvd206LjU6c286MDo1MDouNC93bXNoOjEwL3dtdDpQSE53WVc0Z1ptOXlaV2R5YjNWdVpEMG5kMmhwZEdVbklHWnZiblE5SnpZNEp6NUlUMDFGVEVsR1JTQkdVazlPVkVsRlVpQlNSVUZNVkZrZ1NVNURMaXdnUW5KdmEyVnlZV2RsUEM5emNHRnVQZy9MM1J5Y21WaUwyeHBjM1JwYm1kekx6UXlMekkzTHpjMEx6QTRMM0F2TXpRMk9UVTJNRFF0TUdRellpMDBaV1JsTFRoa1kySXROakZrTUROak5UUTJNRFpsTG1wd1p3LmpwZw.jpg';
        @endphp

        @if ($sampleListings->isNotEmpty())
            <div class="mt-10 grid gap-6 md:grid-cols-3">
                @foreach ($sampleListings as $listing)
                    @php
                        $primaryMedia = $listing->media->firstWhere('is_primary', true) ?? $listing->media->first();
                        $previewUrl = $primaryMedia?->preview_url ?? $primaryMedia?->url ?? $fallbackImage;
                    @endphp

                    <article class="flex h-full flex-col overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-[0_18px_40px_rgba(15,23,42,0.08)] dark:border-zinc-800 dark:bg-zinc-900 dark:shadow-none">
                        <img src="{{ $previewUrl }}" alt="{{ $listing->street_address }}" class="h-48 w-full object-cover">
                        <div class="flex flex-1 flex-col space-y-4 p-6">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $listing->street_address ?? __('Address unavailable') }}</h3>
                                    <p class="text-sm text-slate-600 dark:text-zinc-400">
                                        {{ collect([$listing->city, $listing->province, $listing->postal_code])->filter()->implode(', ') }}
                                    </p>
                                </div>
                                <flux:badge color="{{ $listing->display_status === 'Available' ? 'green' : 'sky' }}">
                                    {{ $listing->display_status ?? __('Unknown') }}
                                </flux:badge>
                            </div>
                            <dl class="grid gap-3 sm:grid-cols-2">
                                <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-zinc-800 dark:text-zinc-400">
                                    <dt class="uppercase tracking-[0.3em] text-xs text-slate-500 dark:text-zinc-500">{{ __('List price') }}</dt>
                                    <dd class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">
                                        {{ $listing->list_price !== null ? '$'.number_format((float) $listing->list_price, 0) : __('N/A') }}
                                    </dd>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-zinc-800 dark:text-zinc-400">
                                    <dt class="uppercase tracking-[0.3em] text-xs text-slate-500 dark:text-zinc-500">{{ __('Bedrooms') }}</dt>
                                    <dd class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">
                                        {{ $listing->bedrooms ?? __('N/A') }}
                                    </dd>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-zinc-800 dark:text-zinc-400">
                                    <dt class="uppercase tracking-[0.3em] text-xs text-slate-500 dark:text-zinc-500">{{ __('Bathrooms') }}</dt>
                                    <dd class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">
                                        {{ $listing->bathrooms ?? __('N/A') }}
                                    </dd>
                                </div>
                                <div class="rounded-xl bg-slate-50 p-3 text-sm text-slate-600 dark:bg-zinc-800 dark:text-zinc-400">
                                    <dt class="uppercase tracking-[0.3em] text-xs text-slate-500 dark:text-zinc-500">{{ __('Updated') }}</dt>
                                    <dd class="mt-2 text-lg font-semibold text-slate-900 dark:text-white">
                                        {{ optional($listing->modified_at)?->diffForHumans() ?? __('Unknown') }}
                                    </dd>
                                </div>
                            </dl>
                            <div class="flex items-center justify-between text-xs font-semibold uppercase tracking-[0.3em] text-slate-500 dark:text-zinc-500">
                                <span>{{ $listing->mls_number ?? __('MLS Unknown') }}</span>
                                <span>{{ $listing->board_code ?? __('Board Unknown') }}</span>
                            </div>
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="mt-10 rounded-3xl border border-slate-200 bg-white p-6 text-sm text-slate-600 dark:border-zinc-800 dark:bg-zinc-900 dark:text-zinc-400">
                {{ __('No demo listings are available yet. Run the database seeder to populate representative data.') }}
            </div>
        @endif
    </div>
</section>
