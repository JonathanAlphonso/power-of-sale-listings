<x-layouts.site :title="$listing->street_address ?? __('Listing Details')">
    @php
        $gallery = $listing->media;

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

        // Helper for displaying array values
        $formatArray = fn($arr) => is_array($arr) && count($arr) > 0 ? implode(', ', $arr) : null;

        // Helper for lot dimensions
        $lotDimensions = null;
        if ($listing->lot_width && $listing->lot_depth) {
            $lotDimensions = number_format($listing->lot_width, 1) . ' x ' . number_format($listing->lot_depth, 1) . ' FT';
        } elseif ($listing->lot_size_area) {
            $lotDimensions = number_format($listing->lot_size_area, 2) . ' ' . ($listing->lot_size_units ?? 'sqft');
        }
    @endphp

    <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        {{-- Back Button --}}
        <div class="mb-6">
            <flux:button
                as="a"
                :href="route('listings.index')"
                variant="ghost"
                icon="chevron-left"
                size="sm"
            >
                {{ __('Back to listings') }}
            </flux:button>
        </div>

        {{-- Main Content Grid --}}
        <div class="grid gap-8 lg:grid-cols-[1fr_380px]">
            {{-- Left Column: Gallery + Details --}}
            <div class="space-y-6">
                {{-- Image Gallery --}}
                <x-listing.image-gallery
                    :images="$gallery"
                    :alt="$listing->street_address ?? __('Property photo')"
                />

                {{-- Price & Address Header --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="space-y-2">
                            <flux:heading size="xl" class="text-4xl font-bold text-emerald-600 dark:text-emerald-400 sm:text-5xl">
                                {{ \App\Support\ListingPresentation::currency($listing->list_price) }}
                            </flux:heading>

                            <flux:heading size="lg" class="font-semibold uppercase tracking-tight text-slate-900 dark:text-zinc-100">
                                {{ strtoupper($listing->street_address ?? __('Address unavailable')) }}
                            </flux:heading>

                            <flux:text class="text-base text-slate-600 dark:text-zinc-300">
                                {{ $locationLine !== '' ? $locationLine : __('No additional address context available.') }}
                            </flux:text>

                            @if ($listing->mls_number)
                                <flux:text class="text-sm text-slate-500 dark:text-zinc-400">
                                    {{ __('MLS® Number: :mls', ['mls' => $listing->mls_number]) }}
                                </flux:text>
                            @endif
                        </div>

                        <flux:badge color="{{ \App\Support\ListingPresentation::statusBadge($listing->display_status) }}" size="lg">
                            {{ $listing->display_status ?? __('Unknown status') }}
                        </flux:badge>
                    </div>

                    {{-- Key Stats Row --}}
                    <div class="mt-6 flex flex-wrap items-center gap-6 border-t border-slate-200 pt-6 dark:border-zinc-700">
                        @if($listing->bedrooms)
                            <div class="flex items-center gap-2">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 dark:bg-zinc-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-600 dark:text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ $listing->bedrooms }}</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Bedrooms') }}</p>
                                </div>
                            </div>
                        @endif

                        @if($listing->bathrooms)
                            <div class="flex items-center gap-2">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 dark:bg-zinc-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-600 dark:text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-slate-900 dark:text-white">{{ \App\Support\ListingPresentation::numeric($listing->bathrooms, 1) }}</p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Bathrooms') }}</p>
                                </div>
                            </div>
                        @endif

                        @if($listing->square_feet || $listing->square_feet_text)
                            <div class="flex items-center gap-2">
                                <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 dark:bg-zinc-800">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-600 dark:text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v2a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 13a1 1 0 011-1h6a1 1 0 011 1v6a1 1 0 01-1 1H5a1 1 0 01-1-1v-6zM16 13a1 1 0 011-1h2a1 1 0 011 1v6a1 1 0 01-1 1h-2a1 1 0 01-1-1v-6z" />
                                    </svg>
                                </div>
                                <div>
                                    <p class="text-2xl font-bold text-slate-900 dark:text-white">
                                        {{ $listing->square_feet_text ?? number_format($listing->square_feet) }}
                                    </p>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400">{{ __('Square Feet') }}</p>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Property Description --}}
                @if ($listing->public_remarks)
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                        <flux:heading size="md" class="mb-4 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            {{ __('Listing Description') }}
                        </flux:heading>
                        <div class="prose prose-slate max-w-none dark:prose-invert">
                            <p class="whitespace-pre-line text-sm leading-relaxed text-slate-600 dark:text-zinc-300">{{ $listing->public_remarks }}</p>
                        </div>
                    </div>
                @endif

                {{-- Property Summary --}}
                <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                    <flux:heading size="md" class="mb-4 flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                        </svg>
                        {{ __('Property Summary') }}
                    </flux:heading>

                    <dl class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @if($listing->property_type)
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/50">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Property Type') }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $listing->property_type }}</dd>
                            </div>
                        @endif

                        @if($listing->property_style)
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/50">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Property Style') }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $listing->property_style }}</dd>
                            </div>
                        @endif

                        @if($listing->structure_type)
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/50">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Building Type') }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $listing->structure_type }}</dd>
                            </div>
                        @endif

                        @if($listing->stories)
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/50">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Storeys') }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $listing->stories }}</dd>
                            </div>
                        @endif

                        @if($listing->square_feet || $listing->square_feet_text)
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/50">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Square Footage') }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $listing->square_feet_text ?? number_format($listing->square_feet) . ' sqft' }}</dd>
                            </div>
                        @endif

                        @if($lotDimensions)
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/50">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Land Size') }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $lotDimensions }}</dd>
                            </div>
                        @endif

                        @if($listing->tax_annual_amount)
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/50">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Annual Property Taxes') }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">
                                    {{ \App\Support\ListingPresentation::currency($listing->tax_annual_amount) }}
                                    @if($listing->tax_year)
                                        <span class="text-xs font-normal text-slate-500">({{ $listing->tax_year }})</span>
                                    @endif
                                </dd>
                            </div>
                        @endif

                        @if($listing->association_fee)
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/50">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Condo/Maintenance Fee') }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ \App\Support\ListingPresentation::currency($listing->association_fee) }}/mo</dd>
                            </div>
                        @endif

                        @if($listing->zoning)
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/50">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Zoning') }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $listing->zoning }}</dd>
                            </div>
                        @endif

                        @if($listing->approximate_age)
                            <div class="rounded-lg bg-slate-50 p-3 dark:bg-zinc-800/50">
                                <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Approximate Age') }}</dt>
                                <dd class="mt-1 text-sm font-semibold text-slate-900 dark:text-white">{{ $listing->approximate_age }}</dd>
                            </div>
                        @endif
                    </dl>
                </div>

                {{-- Building Details --}}
                @if($listing->basement || $listing->foundation_details || $listing->heating_type || $listing->cooling || $listing->fireplace_yn)
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                        <flux:heading size="md" class="mb-4 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                            </svg>
                            {{ __('Building') }}
                        </flux:heading>

                        <dl class="grid gap-4 sm:grid-cols-2">
                            @if($formatArray($listing->basement))
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Basement Type') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $formatArray($listing->basement) }}</dd>
                                </div>
                            @endif

                            @if($formatArray($listing->foundation_details))
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Foundation Type') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $formatArray($listing->foundation_details) }}</dd>
                                </div>
                            @endif

                            @if($listing->heating_type)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Heating Type') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $listing->heating_type }}</dd>
                                </div>
                            @endif

                            @if($formatArray($listing->cooling))
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Cooling') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $formatArray($listing->cooling) }}</dd>
                                </div>
                            @endif

                            @if($listing->fireplace_yn)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Fireplace') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                                        {{ __('Yes') }}
                                        @if($listing->fireplaces_total)
                                            ({{ $listing->fireplaces_total }})
                                        @endif
                                        @if($formatArray($listing->fireplace_features))
                                            - {{ $formatArray($listing->fireplace_features) }}
                                        @endif
                                    </dd>
                                </div>
                            @endif

                            @if($formatArray($listing->construction_materials))
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Construction Materials') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $formatArray($listing->construction_materials) }}</dd>
                                </div>
                            @endif

                            @if($formatArray($listing->roof))
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Roof') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $formatArray($listing->roof) }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                @endif

                {{-- Exterior & Parking --}}
                @if($listing->exterior_features || $listing->pool_features || $listing->garage_type || $listing->parking_total)
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                        <flux:heading size="md" class="mb-4 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z" />
                            </svg>
                            {{ __('Exterior Features') }}
                        </flux:heading>

                        <dl class="grid gap-4 sm:grid-cols-2">
                            @if($formatArray($listing->exterior_features))
                                <div class="sm:col-span-2">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Exterior Finish') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $formatArray($listing->exterior_features) }}</dd>
                                </div>
                            @endif

                            @if($formatArray($listing->pool_features))
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Pool') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $formatArray($listing->pool_features) }}</dd>
                                </div>
                            @endif

                            @if($listing->garage_type || $listing->garage_parking_spaces)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Garage') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">
                                        {{ $listing->garage_type ?? __('Yes') }}
                                        @if($listing->garage_parking_spaces)
                                            ({{ $listing->garage_parking_spaces }} {{ __('spaces') }})
                                        @endif
                                    </dd>
                                </div>
                            @endif

                            @if($listing->parking_total)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Total Parking Spaces') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $listing->parking_total }}</dd>
                                </div>
                            @endif

                            @if($formatArray($listing->parking_features))
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Parking Type') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $formatArray($listing->parking_features) }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                @endif

                {{-- Utilities --}}
                @if($listing->water || $listing->sewer)
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                        <flux:heading size="md" class="mb-4 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                            </svg>
                            {{ __('Utilities') }}
                        </flux:heading>

                        <dl class="grid gap-4 sm:grid-cols-2">
                            @if($listing->water)
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Water') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $listing->water }}</dd>
                                </div>
                            @endif

                            @if($formatArray($listing->sewer))
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500 dark:text-zinc-400">{{ __('Sewer') }}</dt>
                                    <dd class="mt-1 text-sm text-slate-900 dark:text-white">{{ $formatArray($listing->sewer) }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                @endif

                {{-- Interior Features --}}
                @if($formatArray($listing->interior_features))
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                        <flux:heading size="md" class="mb-4 flex items-center gap-2">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                            </svg>
                            {{ __('Interior Features') }}
                        </flux:heading>

                        <div class="flex flex-wrap gap-2">
                            @foreach($listing->interior_features ?? [] as $feature)
                                <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-sm text-slate-700 dark:bg-zinc-800 dark:text-zinc-300">
                                    {{ $feature }}
                                </span>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- Right Column: Sidebar --}}
            <div class="space-y-6">
                {{-- Listing Highlights Card --}}
                <div class="sticky top-24 space-y-6">
                    <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                        <flux:heading size="md" class="mb-4">
                            {{ __('Listing Highlights') }}
                        </flux:heading>

                        <dl class="space-y-3">
                            <div class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-zinc-800">
                                <dt class="text-sm text-slate-500 dark:text-zinc-400">{{ __('Status') }}</dt>
                                <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ $listing->display_status ?? __('Unknown') }}</dd>
                            </div>

                            @if($listing->original_list_price && $listing->original_list_price != $listing->list_price)
                                <div class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-zinc-800">
                                    <dt class="text-sm text-slate-500 dark:text-zinc-400">{{ __('Original Price') }}</dt>
                                    <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ \App\Support\ListingPresentation::currency($listing->original_list_price) }}</dd>
                                </div>
                            @endif

                            <div class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-zinc-800">
                                <dt class="text-sm text-slate-500 dark:text-zinc-400">{{ __('Days on Market') }}</dt>
                                <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ \App\Support\ListingPresentation::numeric($listing->days_on_market) }}</dd>
                            </div>

                            @if($listing->listed_at)
                                <div class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-zinc-800">
                                    <dt class="text-sm text-slate-500 dark:text-zinc-400">{{ __('Listed Date') }}</dt>
                                    <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ $listing->listed_at->format('M j, Y') }}</dd>
                                </div>
                            @endif

                            @if($listing->price_per_square_foot)
                                <div class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-zinc-800">
                                    <dt class="text-sm text-slate-500 dark:text-zinc-400">{{ __('Price/sqft') }}</dt>
                                    <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ \App\Support\ListingPresentation::currency($listing->price_per_square_foot) }}</dd>
                                </div>
                            @endif

                            <div class="flex items-center justify-between border-b border-slate-100 pb-3 dark:border-zinc-800">
                                <dt class="text-sm text-slate-500 dark:text-zinc-400">{{ __('Source') }}</dt>
                                <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ $listing->source?->name ?? __('Unknown') }}</dd>
                            </div>

                            @if($listing->modified_at)
                                <div class="flex items-center justify-between">
                                    <dt class="text-sm text-slate-500 dark:text-zinc-400">{{ __('Last Updated') }}</dt>
                                    <dd class="text-sm font-semibold text-slate-900 dark:text-white">{{ $listing->modified_at->timezone(config('app.timezone'))->format('M j, Y') }}</dd>
                                </div>
                            @endif
                        </dl>
                    </div>

                    {{-- Virtual Tour Button --}}
                    @if($listing->virtual_tour_url)
                        <flux:button as="a" href="{{ $listing->virtual_tour_url }}" target="_blank" variant="primary" class="w-full" icon="video-camera">
                            {{ __('Virtual Tour') }}
                        </flux:button>
                    @endif

                    {{-- Listing Office Card --}}
                    @if($listing->list_office_name || $listing->list_aor)
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                            <flux:heading size="sm" class="mb-3">
                                {{ __('Listing Provided By') }}
                            </flux:heading>

                            @if($listing->list_office_name)
                                <p class="text-sm font-semibold text-slate-900 dark:text-white">{{ $listing->list_office_name }}</p>
                            @endif

                            @if($listing->list_office_phone)
                                <p class="mt-1 text-sm text-slate-600 dark:text-zinc-400">{{ $listing->list_office_phone }}</p>
                            @endif

                            @if($listing->list_aor)
                                <p class="mt-2 text-xs text-slate-500 dark:text-zinc-500">{{ __('Data provided by: :board', ['board' => $listing->list_aor]) }}</p>
                            @endif
                        </div>
                    @endif

                    {{-- Location Card --}}
                    @if ($listing->latitude && $listing->longitude)
                        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
                            <div class="mb-4 flex items-center justify-between">
                                <flux:heading size="md" class="flex items-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                    </svg>
                                    {{ __('Location') }}
                                </flux:heading>

                                <a
                                    href="{{ route('listings.index', ['view' => 'map', 'lat' => $listing->latitude, 'lng' => $listing->longitude, 'zoom' => 16]) }}"
                                    class="inline-flex items-center gap-1.5 text-sm font-medium text-blue-600 hover:text-blue-700 dark:text-blue-400 dark:hover:text-blue-300"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                                    </svg>
                                    {{ __('View in Map') }}
                                </a>
                            </div>

                            @if($listing->cross_street)
                                <p class="mb-3 text-sm text-slate-600 dark:text-zinc-400">
                                    <span class="font-medium">{{ __('Cross Street:') }}</span> {{ $listing->cross_street }}
                                </p>
                            @endif

                            <x-maps.listing-map
                                :listings="[[
                                    'id' => $listing->id,
                                    'lat' => (float) $listing->latitude,
                                    'lng' => (float) $listing->longitude,
                                    'price' => $listing->list_price,
                                    'priceFormatted' => \App\Support\ListingPresentation::currency($listing->list_price),
                                    'priceShort' => $listing->list_price >= 1000000
                                        ? '$' . number_format($listing->list_price / 1000000, 1) . 'M'
                                        : '$' . number_format($listing->list_price / 1000, 0) . 'K',
                                    'typeCode' => \App\Support\PropertyTypeAbbreviations::get($listing->property_type),
                                    'status' => $listing->display_status,
                                    'statusColor' => \App\Support\ListingPresentation::statusBadge($listing->display_status),
                                    'propertyType' => $listing->property_type,
                                    'address' => $listing->street_address,
                                    'city' => $listing->city,
                                    'beds' => $listing->bedrooms,
                                    'baths' => $listing->bathrooms,
                                    'sqft' => $listing->square_feet,
                                    'mlsNumber' => $listing->mls_number,
                                    'listedAt' => $listing->listed_at?->format('M j, Y'),
                                    'url' => $listing->url,
                                    'thumbnail' => $gallery->first()?->public_url,
                                ]]"
                                height="250px"
                            />
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
