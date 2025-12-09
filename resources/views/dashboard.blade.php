<x-layouts.app :title="__('Dashboard')">
    @php
        $formatCount = static fn (int|float|null $value): string => number_format((int) ($value ?? 0));

        $hour = now()->hour;
        $greeting = match (true) {
            $hour < 12 => __('Good morning'),
            $hour < 17 => __('Good afternoon'),
            default => __('Good evening'),
        };

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

    <section class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
        <!-- Welcome Header -->
        <div class="mb-8">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-500 dark:text-zinc-400">
                        {{ $greeting }}, {{ auth()->user()?->name ?? __('Admin') }}
                    </p>
                    <h1 class="mt-1 text-2xl font-bold tracking-tight text-zinc-900 sm:text-3xl dark:text-white">
                        {{ __('Operations Dashboard') }}
                    </h1>
                    <p class="mt-2 max-w-2xl text-sm text-zinc-600 dark:text-zinc-400">
                        {{ __('Monitor your listings, track performance, and manage your team all in one place.') }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-3">
                    <flux:button
                        icon="plus"
                        variant="primary"
                        :href="route('admin.feeds.index')"
                    >
                        {{ __('Import data') }}
                    </flux:button>
                    <flux:button
                        icon="table-cells"
                        variant="filled"
                        :href="route('admin.listings.index')"
                    >
                        {{ __('View listings') }}
                    </flux:button>
                </div>
            </div>
        </div>

        <!-- Stats Grid -->
        <div class="mb-8 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <!-- Total Listings -->
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-gradient-to-br from-blue-500/10 to-cyan-500/10 blur-2xl transition-all group-hover:scale-150"></div>
                <div class="relative">
                    <div class="mb-4 inline-flex rounded-xl bg-blue-50 p-3 dark:bg-blue-500/10">
                        <flux:icon.building-office-2 class="h-6 w-6 text-blue-600 dark:text-blue-400" />
                    </div>
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">
                        {{ __('Total Listings') }}
                    </p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">
                        {{ $formatCount($totalListings) }}
                    </p>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-500">
                        {{ __('All records in database') }}
                    </p>
                </div>
            </div>

            <!-- Available Inventory -->
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-gradient-to-br from-emerald-500/10 to-green-500/10 blur-2xl transition-all group-hover:scale-150"></div>
                <div class="relative">
                    <div class="mb-4 inline-flex rounded-xl bg-emerald-50 p-3 dark:bg-emerald-500/10">
                        <flux:icon.check-badge class="h-6 w-6 text-emerald-600 dark:text-emerald-400" />
                    </div>
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">
                        {{ __('Available') }}
                    </p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-emerald-600 dark:text-emerald-400">
                        {{ $formatCount($availableListings) }}
                    </p>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-500">
                        {{ __('Active listings on market') }}
                    </p>
                </div>
            </div>

            <!-- Team Members -->
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-gradient-to-br from-violet-500/10 to-purple-500/10 blur-2xl transition-all group-hover:scale-150"></div>
                <div class="relative">
                    <div class="mb-4 inline-flex rounded-xl bg-violet-50 p-3 dark:bg-violet-500/10">
                        <flux:icon.user-group class="h-6 w-6 text-violet-600 dark:text-violet-400" />
                    </div>
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">
                        {{ __('Team Members') }}
                    </p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-violet-600 dark:text-violet-400">
                        {{ $formatCount($totalUsers) }}
                    </p>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-500">
                        {{ __('Registered users') }}
                    </p>
                </div>
            </div>

            <!-- Average Price -->
            <div class="group relative overflow-hidden rounded-2xl border border-zinc-200 bg-white p-6 shadow-sm transition-all hover:shadow-md dark:border-zinc-800 dark:bg-zinc-900">
                <div class="absolute -right-4 -top-4 h-24 w-24 rounded-full bg-gradient-to-br from-amber-500/10 to-orange-500/10 blur-2xl transition-all group-hover:scale-150"></div>
                <div class="relative">
                    <div class="mb-4 inline-flex rounded-xl bg-amber-50 p-3 dark:bg-amber-500/10">
                        <flux:icon.currency-dollar class="h-6 w-6 text-amber-600 dark:text-amber-400" />
                    </div>
                    <p class="text-sm font-medium text-zinc-600 dark:text-zinc-400">
                        {{ __('Avg. List Price') }}
                    </p>
                    <p class="mt-1 text-3xl font-bold tracking-tight text-zinc-900 dark:text-white">
                        {{ \App\Support\ListingPresentation::currency($averageListPrice) }}
                    </p>
                    <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-500">
                        {{ __('Mean asking price') }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Google Analytics Section -->
        <div class="mb-8 overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-800 dark:bg-zinc-900">
            <div class="flex flex-col gap-4 border-b border-zinc-100 bg-zinc-50/50 px-6 py-5 sm:flex-row sm:items-center sm:justify-between dark:border-zinc-800 dark:bg-zinc-800/30">
                <div class="flex items-center gap-3">
                    <div class="inline-flex rounded-lg bg-sky-50 p-2 dark:bg-sky-500/10">
                        <flux:icon.chart-bar class="h-5 w-5 text-sky-600 dark:text-sky-400" />
                    </div>
                    <div>
                        <h2 class="font-semibold text-zinc-900 dark:text-white">
                            {{ __('Google Analytics') }}
                        </h2>
                        <p class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Key engagement metrics for your GA4 property') }}
                        </p>
                    </div>
                </div>

                @if (! empty($analyticsSummary->rangeLabel))
                    <flux:badge color="sky" size="sm">
                        {{ $analyticsSummary->rangeLabel }}
                    </flux:badge>
                @endif
            </div>

            @if ($analyticsSummary->configured && ! empty($analyticsSummary->metrics))
                <div class="grid gap-4 p-6 sm:grid-cols-2 xl:grid-cols-4">
                    @foreach ($analyticsSummary->metrics as $metric => $value)
                        <div class="rounded-xl border border-zinc-100 bg-zinc-50/50 p-5 dark:border-zinc-800 dark:bg-zinc-800/30">
                            <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                {{ $metricLabels[$metric] ?? \Illuminate\Support\Str::title(str_replace('_', ' ', $metric)) }}
                            </p>
                            <p class="mt-2 text-2xl font-bold text-zinc-900 dark:text-white">
                                {{ $formatMetric($metric, $value) }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="p-6">
                    <div class="rounded-xl border border-dashed border-zinc-200 bg-zinc-50/50 p-8 text-center dark:border-zinc-700 dark:bg-zinc-800/20">
                        <div class="mx-auto mb-4 inline-flex rounded-full bg-zinc-100 p-3 dark:bg-zinc-800">
                            <flux:icon.chart-bar class="h-6 w-6 text-zinc-400 dark:text-zinc-500" />
                        </div>
                        <h3 class="font-medium text-zinc-900 dark:text-white">
                            {{ __('Analytics not configured') }}
                        </h3>
                        <p class="mx-auto mt-2 max-w-sm text-sm text-zinc-500 dark:text-zinc-400">
                            {{ $analyticsSummary->message ?? __('Connect Google Analytics to track engagement metrics for your dashboard.') }}
                        </p>
                        <div class="mt-6">
                            <flux:button :href="route('admin.settings.analytics')" variant="outline" icon="cog-6-tooth" wire:navigate>
                                {{ __('Configure analytics') }}
                            </flux:button>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        <!-- Two Column Layout -->
        <div class="grid gap-6 lg:grid-cols-5">
            <!-- Recent Listings - Takes 3 columns -->
            <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm lg:col-span-3 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="flex items-center justify-between border-b border-zinc-100 bg-zinc-50/50 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-800/30">
                    <div class="flex items-center gap-3">
                        <div class="inline-flex rounded-lg bg-indigo-50 p-2 dark:bg-indigo-500/10">
                            <flux:icon.clock class="h-5 w-5 text-indigo-600 dark:text-indigo-400" />
                        </div>
                        <h2 class="font-semibold text-zinc-900 dark:text-white">
                            {{ __('Recent Activity') }}
                        </h2>
                    </div>

                    <flux:button
                        size="sm"
                        variant="ghost"
                        icon-trailing="arrow-right"
                        :href="route('admin.listings.index')"
                    >
                        {{ __('View all') }}
                    </flux:button>
                </div>

                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($recentListings as $listing)
                        <div class="flex flex-col gap-4 px-6 py-4 transition-colors hover:bg-zinc-50/50 sm:flex-row sm:items-center sm:justify-between dark:hover:bg-zinc-800/30">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p class="truncate font-medium text-zinc-900 dark:text-white">
                                        {{ $listing->street_address ?? __('Address unavailable') }}
                                    </p>
                                    <flux:badge size="sm" color="{{ \App\Support\ListingPresentation::statusBadge($listing->display_status) }}">
                                        {{ $listing->display_status ?? __('Unknown') }}
                                    </flux:badge>
                                </div>
                                <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ collect([$listing->city ?? null, $listing->province ?? null])->filter()->implode(', ') }}
                                </p>
                                <div class="mt-2 flex items-center gap-4 text-xs text-zinc-400 dark:text-zinc-500">
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon.folder class="h-3.5 w-3.5" />
                                        {{ $listing->source?->name ?? __('Unknown') }}
                                    </span>
                                    <span class="inline-flex items-center gap-1">
                                        <flux:icon.clock class="h-3.5 w-3.5" />
                                        {{ optional($listing->modified_at)?->diffForHumans() ?? __('—') }}
                                    </span>
                                </div>
                            </div>

                            <div class="text-right">
                                <p class="text-lg font-bold text-zinc-900 dark:text-white">
                                    {{ \App\Support\ListingPresentation::currency($listing->list_price) }}
                                </p>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-12 text-center">
                            <div class="mx-auto mb-4 inline-flex rounded-full bg-zinc-100 p-3 dark:bg-zinc-800">
                                <flux:icon.building-office-2 class="h-6 w-6 text-zinc-400 dark:text-zinc-500" />
                            </div>
                            <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                {{ __('No listings have been updated recently.') }}
                            </p>
                            <p class="mt-1 text-xs text-zinc-400 dark:text-zinc-500">
                                {{ __('Import data to see activity here.') }}
                            </p>
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Team Snapshot - Takes 2 columns -->
            <div class="flex flex-col overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm lg:col-span-2 dark:border-zinc-800 dark:bg-zinc-900">
                <div class="border-b border-zinc-100 bg-zinc-50/50 px-6 py-4 dark:border-zinc-800 dark:bg-zinc-800/30">
                    <div class="flex items-center gap-3">
                        <div class="inline-flex rounded-lg bg-rose-50 p-2 dark:bg-rose-500/10">
                            <flux:icon.users class="h-5 w-5 text-rose-600 dark:text-rose-400" />
                        </div>
                        <h2 class="font-semibold text-zinc-900 dark:text-white">
                            {{ __('Team Snapshot') }}
                        </h2>
                    </div>
                </div>

                <div class="flex-1 divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($recentUsers as $user)
                        <div class="flex items-center gap-4 px-6 py-4 transition-colors hover:bg-zinc-50/50 dark:hover:bg-zinc-800/30">
                            <div class="flex h-10 w-10 flex-shrink-0 items-center justify-center rounded-full bg-gradient-to-br from-violet-500 to-purple-600 text-sm font-medium text-white">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="truncate font-medium text-zinc-900 dark:text-white">
                                    {{ $user->name }}
                                </p>
                                <p class="truncate text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ $user->email }}
                                </p>
                            </div>
                            <p class="flex-shrink-0 text-xs text-zinc-400 dark:text-zinc-500">
                                {{ optional($user->created_at)?->diffForHumans() ?? __('—') }}
                            </p>
                        </div>
                    @empty
                        <div class="flex flex-1 items-center justify-center px-6 py-12 text-center">
                            <div>
                                <div class="mx-auto mb-4 inline-flex rounded-full bg-zinc-100 p-3 dark:bg-zinc-800">
                                    <flux:icon.users class="h-6 w-6 text-zinc-400 dark:text-zinc-500" />
                                </div>
                                <p class="text-sm text-zinc-500 dark:text-zinc-400">
                                    {{ __('No users yet') }}
                                </p>
                            </div>
                        </div>
                    @endforelse
                </div>

                <div class="border-t border-zinc-100 bg-zinc-50/50 p-4 dark:border-zinc-800 dark:bg-zinc-800/30">
                    <flux:button
                        icon="user-plus"
                        variant="outline"
                        :href="route('admin.users.index')"
                        class="w-full justify-center"
                    >
                        {{ __('Manage team') }}
                    </flux:button>
                </div>
            </div>
        </div>

        <!-- Quick Actions Footer -->
        <div class="mt-8 rounded-2xl border border-zinc-200 bg-gradient-to-r from-zinc-50 to-zinc-100/50 p-6 dark:border-zinc-800 dark:from-zinc-900 dark:to-zinc-800/50">
            <div class="flex flex-col items-start justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h3 class="font-semibold text-zinc-900 dark:text-white">
                        {{ __('Quick Actions') }}
                    </h3>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ __('Jump to common tasks') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <flux:button size="sm" variant="ghost" icon="arrow-path" :href="route('admin.feeds.index')">
                        {{ __('Sync feeds') }}
                    </flux:button>
                    <flux:button size="sm" variant="ghost" icon="cog-6-tooth" :href="route('admin.settings.analytics')">
                        {{ __('Settings') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </section>
</x-layouts.app>
