<section id="idx-live-feed" class="scroll-mt-28 border-t border-slate-200 bg-white px-6 py-24 lg:px-8">
    <div class="mx-auto max-w-6xl">
        <div class="flex flex-wrap items-center justify-between gap-6">
            <div>
                <x-ui.section-badge>IDX API</x-ui.section-badge>
                <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">Live IDX feed</h2>
                <p class="mt-2 text-sm text-slate-600">A snapshot of the most recent listings streaming directly from Amplify&apos;s IDX API.</p>
            </div>

            @php
                $statusClasses = 'text-xs font-semibold uppercase tracking-[0.35em]';

                if (! $idxFeedEnabled) {
                    $statusClasses .= ' text-red-500';
                    $statusText = __('IDX credentials required');
                } elseif ($idxListings->isEmpty()) {
                    $statusClasses .= ' text-amber-500';
                    $statusText = __('No live listings returned');
                } else {
                    $statusClasses .= ' text-emerald-500';
                    $statusText = __('Connected to IDX');
                }
            @endphp

            <span class="{{ $statusClasses }}">{{ $statusText }}</span>
        </div>

        @if ($idxListings->isNotEmpty())
            <div class="mt-10 grid gap-6 md:grid-cols-2 xl:grid-cols-4">
                @foreach ($idxListings as $listing)
                    @php
                        /** @var array<string, mixed> $listing */
                        $price = $listing['list_price'] ?? null;
                        $status = $listing['status'] ?? null;
                        $statusColor = match (is_string($status) ? strtolower($status) : null) {
                            'active', 'available', 'new' => 'green',
                            'sold', 'leased', 'off market' => 'rose',
                            default => 'sky',
                        };
                        $cityLine = collect([
                            $listing['city'] ?? null,
                            $listing['state'] ?? null,
                            $listing['postal_code'] ?? null,
                        ])->filter()->implode(', ');
                        $remarks = $listing['remarks'] ?? null;
                        $modifiedAt = ($listing['modified_at'] ?? null) instanceof \Carbon\CarbonInterface
                            ? $listing['modified_at']->diffForHumans()
                            : null;
                        $propertySummary = collect([
                            $listing['property_type'] ?? null,
                            $listing['property_sub_type'] ?? null,
                        ])->filter()->implode(' • ');
                        $listOffice = $listing['list_office_name'] ?? null;
                        $virtualTourUrl = $listing['virtual_tour_url'] ?? null;
                    @endphp

                    <article class="flex h-full flex-col overflow-hidden rounded-3xl border border-slate-200 bg-slate-50 p-6 shadow-[0_18px_40px_rgba(15,23,42,0.08)]">
                        <div class="flex items-start justify-between gap-4">
                            <div>
                                <p class="text-xs font-semibold uppercase tracking-[0.3em] text-slate-500">{{ __('List price') }}</p>
                                <p class="mt-1 text-2xl font-semibold text-slate-900">
                                    {{ is_numeric($price) ? '$'.number_format((float) $price, 0) : __('N/A') }}
                                </p>
                            </div>
                            <flux:badge color="{{ $statusColor }}">
                                {{ $status ?? __('Unknown') }}
                            </flux:badge>
                        </div>

                        @if (is_string($listing['image_url'] ?? null) && $listing['image_url'] !== '')
                            <div class="mt-6">
                                <img
                                    src="{{ $listing['image_url'] }}"
                                    alt="{{ $listing['address'] ?? __('Listing photo') }}"
                                    class="aspect-[4/3] w-full rounded-2xl border border-slate-200 object-cover"
                                    loading="lazy"
                                />
                            </div>
                        @endif

                        <div class="mt-6 space-y-2 text-sm text-slate-600">
                            <h3 class="text-lg font-semibold text-slate-900">
                                {{ $listing['address'] ?? __('Address unavailable') }}
                            </h3>
                            @if ($cityLine !== '')
                                <p>{{ $cityLine }}</p>
                            @endif

                            @if ($propertySummary !== '')
                                <p class="text-xs font-semibold uppercase tracking-[0.25em] text-slate-500">
                                    {{ $propertySummary }}
                                </p>
                            @endif

                            @if ($listOffice)
                                <p class="text-xs uppercase tracking-[0.25em] text-slate-500">
                                    {{ __('Brokerage: :name', ['name' => $listOffice]) }}
                                </p>
                            @endif
                        </div>

                        @if (is_string($remarks) && $remarks !== '')
                            <p class="mt-6 flex-1 text-sm leading-relaxed text-slate-600">{{ $remarks }}</p>
                        @endif

                        <div class="mt-6 flex items-center justify-between text-xs uppercase tracking-[0.25em] text-slate-500">
                            <span>{{ $listing['listing_key'] ?? __('Listing key unavailable') }}</span>
                            <span>
                                {{ $modifiedAt ? __('Updated :time', ['time' => $modifiedAt]) : __('Updated recently') }}
                            </span>
                        </div>

                        @if (is_string($virtualTourUrl) && $virtualTourUrl !== '')
                            <a
                                href="{{ $virtualTourUrl }}"
                                class="mt-4 inline-flex items-center text-sm font-semibold text-sky-600 hover:text-sky-500"
                                target="_blank"
                                rel="noopener"
                            >
                                {{ __('View virtual tour') }}
                            </a>
                        @endif
                    </article>
                @endforeach
            </div>
        @else
            <div class="mt-10 rounded-3xl border border-dashed border-slate-200 bg-slate-50 p-6 text-sm text-slate-600">
                {{ $idxFeedEnabled
                    ? __('IDX connection is available, but no listings were returned. Try broadening the query or confirm recent activity in Amplify.')
                    : __('Add your IDX credentials to the environment to preview live listings from Amplify.') }}
            </div>
        @endif
    </div>
</section>
