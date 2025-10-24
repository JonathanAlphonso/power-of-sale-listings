<x-layouts.site :title="__('Dashboard')">
    @php
        $statusBadgeColor = static function (?string $status): string {
            $normalized = strtolower((string) $status);

            return match (true) {
                str_contains($normalized, 'available') => 'green',
                str_contains($normalized, 'conditional') => 'amber',
                str_contains($normalized, 'sold') => 'red',
                str_contains($normalized, 'suspend') => 'zinc',
                default => 'blue',
            };
        };

        $formatCount = static fn (int|float|null $value): string => number_format((int) ($value ?? 0));
        $formatCurrency = static fn (int|float|null $value): string => $value !== null
            ? '$' . number_format((float) $value, 0)
            : __('N/A');
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
                        {{ __('Rental opportunities') }}
                    </p>
                    <p class="mt-2 text-3xl font-semibold text-sky-600 dark:text-sky-400">
                        {{ $formatCount($rentalListings) }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Active records with a RENT sale type.') }}
                    </p>
                </div>

                <div class="rounded-2xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-700 dark:bg-zinc-900/60">
                    <p class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        {{ __('Average list price') }}
                    </p>
                    <p class="mt-2 text-3xl font-semibold text-zinc-900 dark:text-white">
                        {{ $formatCurrency($averageListPrice) }}
                    </p>
                    <p class="mt-1 text-xs text-zinc-500 dark:text-zinc-400">
                        {{ __('Mean asking price across all listings.') }}
                    </p>
                </div>
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
                                    <flux:badge color="{{ $statusBadgeColor($listing->display_status) }}">
                                        {{ $listing->display_status ?? __('Unknown') }}
                                    </flux:badge>

                                    <p class="text-sm font-semibold text-zinc-900 dark:text-zinc-100">
                                        {{ $formatCurrency($listing->list_price) }}
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
                        {{ __('Next steps') }}
                    </h2>

                    <div class="space-y-4 text-sm text-zinc-600 dark:text-zinc-300">
                        <p>
                            {{ __('Review the latest listing activity above, then dive into the admin workspace to filter, inspect media, and audit status changes.') }}
                        </p>
                        <p>
                            {{ __('Need to stage new ingestion jobs or tweak saved searches? Use the listings workspace to confirm records before promoting updates to stakeholders.') }}
                        </p>
                    </div>

                    <flux:button
                        icon="cursor-arrow-rays"
                        variant="outline"
                        :href="route('admin.listings.index')"
                        class="mt-auto"
                    >
                        {{ __('Go to listings admin') }}
                    </flux:button>
                </div>
            </div>
        </div>
    </section>
</x-layouts.site>
