<x-layouts.app :title="__('Dashboard')">
    @php
        $formatCount = static fn (int|float|null $value): string => number_format((int) ($value ?? 0));

        /** @var array<string, string> $metricLabels */
        $metricLabels = [
            'totalUsers' => __('Total users'),
            'newUsers' => __('New users'),
            'sessions' => __('Sessions'),
            'engagementRate' => __('Engagement rate'),
        ];

        $formatMetric = static function (string $metric, mixed $value): string {
            if ($value === null) {
                return '—';
            }

            if ($metric === 'engagementRate') {
                return number_format((float) $value * 100, 1).'%';
            }

            if (is_numeric($value)) {
                return number_format((int) $value);
            }

            return (string) $value;
        };
    @endphp

    <section class="mx-auto max-w-6xl px-6 py-12 lg:px-8">
        <div class="flex flex-col gap-8">
            <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                <div>
                    <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">
                        {{ __('Operations dashboard') }}
                    </h1>
                    <p class="mt-2 text-sm text-slate-600 dark:text-zinc-400">
                        {{ __('Monitor listing activity and jump directly into the admin workspace to take action.') }}
                    </p>
                </div>

                <flux:button
                    icon="table-cells"
                    variant="primary"
                    :href="route('admin.listings.index')"
                >
                    {{ __('Open listings workspace') }}
                </flux:button>
            </div>

            <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-zinc-900/60">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        {{ __('Total listings') }}
                    </p>
                    <p class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-white">
                        {{ $formatCount($totalListings) }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Canonical records currently stored in the platform.') }}
                    </p>
                </div>

                <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-zinc-900/60">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        {{ __('Available inventory') }}
                    </p>
                    <p class="mt-2 text-3xl font-semibold text-emerald-600 dark:text-emerald-400">
                        {{ $formatCount($availableListings) }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Listings currently flagged as Available.') }}
                    </p>
                </div>

                <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-zinc-900/60">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        {{ __('Team members') }}
                    </p>
                    <p class="mt-2 text-3xl font-semibold text-indigo-600 dark:text-indigo-400">
                        {{ $formatCount($totalUsers) }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Registered user accounts with platform access.') }}
                    </p>
                </div>

                <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-zinc-900/60">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        {{ __('Average list price') }}
                    </p>
                    <p class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-white">
                        {{ \App\Support\ListingPresentation::currency($averageListPrice) }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Mean asking price across all listings.') }}
                    </p>
                </div>
            </div>

            <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-zinc-900/60">
                <div class="flex flex-col gap-4 border-b border-neutral-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between dark:border-neutral-700">
                    <div>
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ __('Google Analytics') }}
                        </h2>
                        <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Key engagement metrics for the connected GA4 property.') }}
                        </p>
                    </div>

                    @if (! empty($analyticsSummary->rangeLabel))
                        <flux:badge color="sky">
                            {{ $analyticsSummary->rangeLabel }}
                        </flux:badge>
                    @endif
                </div>

                @if ($analyticsSummary->configured && ! empty($analyticsSummary->metrics))
                    <div class="grid gap-4 p-6 sm:grid-cols-2 xl:grid-cols-4">
                        @foreach ($analyticsSummary->metrics as $metric => $value)
                            <div class="rounded-xl border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-zinc-900/70">
                                <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    {{ $metricLabels[$metric] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $metric)) }}
                                </p>
                                <p class="mt-2 text-2xl font-semibold text-zinc-900 dark:text-white">
                                    {{ $formatMetric($metric, $value) }}
                                </p>
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="p-6">
                        <flux:callout class="rounded-xl">
                            <flux:callout.heading>{{ __('Analytics data unavailable') }}</flux:callout.heading>
                            <flux:callout.text>
                                {{ $analyticsSummary->message ?? __('Connect Google Analytics to begin tracking engagement for the dashboard.') }}
                            </flux:callout.text>
                            <div class="mt-4">
                                <flux:button :href="route('admin.settings.analytics')" variant="outline" icon="cog-6-tooth" wire:navigate>
                                    {{ __('Manage analytics settings') }}
                                </flux:button>
                            </div>
                        </flux:callout>
                    </div>
                @endif
            </div>

            <div class="grid gap-6 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <div class="rounded-2xl border border-neutral-200 bg-white shadow-sm dark:border-neutral-700 dark:bg-zinc-900/60">
                    <div class="flex items-center justify-between border-b border-neutral-200 px-6 py-4 dark:border-neutral-700">
                        <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ __('Recently updated listings') }}
                        </h2>

                        <flux:link
                            class="text-sm font-medium text-sky-600 hover:text-sky-500 dark:text-sky-400"
                            :href="route('admin.listings.index')"
                        >
                            {{ __('View all') }}
                        </flux:link>
                    </div>

                    <div class="divide-y divide-neutral-200 dark:divide-neutral-800">
                        @forelse ($recentListings as $listing)
                            <div class="flex flex-col gap-3 px-6 py-4 md:flex-row md:items-center md:justify-between">
                                <div>
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                                        {{ $listing->street_address ?? __('Address unavailable') }}
                                    </p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ collect([$listing->city ?? null, $listing->province ?? null, $listing->postal_code ?? null])->filter()->implode(', ') }}
                                    </p>
                                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Source: :source', ['source' => $listing->source?->name ?? __('Unknown')]) }}
                                    </p>
                                </div>

                                <div class="flex flex-col items-start gap-2 text-sm md:items-end">
                                    <flux:badge color="{{ \App\Support\ListingPresentation::statusBadge($listing->display_status) }}">
                                        {{ $listing->display_status ?? __('Unknown') }}
                                    </flux:badge>

                                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ \App\Support\ListingPresentation::currency($listing->list_price) }}
                                    </p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ optional($listing->modified_at)?->diffForHumans() ?? __('No update timestamp') }}
                                    </p>
                                </div>
                            </div>
                        @empty
                            <div class="px-6 py-10 text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('No listings have been updated recently. Once data is ingested, the freshest activity will appear here.') }}
                            </div>
                        @endforelse
                    </div>
                </div>

                <div class="flex h-full flex-col gap-4 rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-zinc-900/60">
                    <h2 class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                        {{ __('Team snapshot') }}
                    </h2>

                    <flux:text class="text-sm text-zinc-600 dark:text-zinc-300">
                        {{ __('Monitor recent sign-ups and jump into the user workspace to update permissions or contact details.') }}
                    </flux:text>

                    <div class="space-y-4">
                        @forelse ($recentUsers as $user)
                            <div class="flex items-start justify-between gap-4">
                                <div class="space-y-1">
                                    <p class="text-sm font-semibold text-zinc-900 dark:text-white">
                                        {{ $user->name }}
                                    </p>
                                    <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ $user->email }}
                                    </p>
                                </div>

                                <p class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ optional($user->created_at)?->diffForHumans() ?? __('Unknown') }}
                                </p>
                            </div>
                        @empty
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('No user accounts have been created yet.') }}
                            </p>
                        @endforelse
                    </div>

                    <flux:button
                        icon="users"
                        variant="outline"
                        :href="route('admin.users.index')"
                        class="mt-auto"
                    >
                        {{ __('Manage users') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
