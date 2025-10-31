<div class="flex flex-col gap-4">
    @if ($selectedListing !== null)
        <div class="overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60">
            @php
                $primaryMedia = $selectedListing->media->firstWhere('is_primary', true) ?? $selectedListing->media->first();
                $suppressionAvailable = $this->suppressionAvailable ?? false;
                $currentSuppression = $suppressionAvailable ? ($selectedListing->currentSuppression ?? null) : null;
                $suppressionHistory = $suppressionAvailable ? $this->suppressionHistory : collect();
                $appTimezone = config('app.timezone');
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

                    <div class="flex flex-col items-end gap-2">
                        <flux:badge color="{{ \App\Support\ListingPresentation::statusBadge($selectedListing->display_status) }}">
                            {{ $selectedListing->display_status ?? __('Unknown') }}
                        </flux:badge>

                        @if ($suppressionAvailable && $selectedListing->isSuppressed())
                            <flux:badge color="red" size="sm">
                                {{ __('Suppressed') }}
                            </flux:badge>
                        @endif
                    </div>
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
                            {{ __('Status') }}
                        </flux:text>
                        <p class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">
                            {{ $selectedListing->display_status ?? __('Unknown') }}
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
                            {{ optional($selectedListing->modified_at)?->timezone($appTimezone)->format('M j, Y g:i a') ?? '—' }}
                        </span>
                    </div>
                </div>

                <div class="rounded-2xl border border-zinc-200 bg-white/60 p-5 shadow-inner dark:border-zinc-700 dark:bg-zinc-900/40">
                    <div class="flex flex-col gap-1 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <flux:heading size="sm">{{ __('Suppression controls') }}</flux:heading>
                            <flux:text class="text-sm text-zinc-600 dark:text-zinc-400">
                                {{ __('Soft-unpublish listings while preserving audit history and automated cleanup pathways.') }}
                            </flux:text>
                        </div>

                        <flux:badge color="{{ $suppressionAvailable && $selectedListing->isSuppressed() ? 'red' : 'zinc' }}" size="sm">
                            {{ $suppressionAvailable && $selectedListing->isSuppressed() ? __('Active suppression') : __('Visible') }}
                        </flux:badge>
                    </div>

                    @if (! $suppressionAvailable)
                        <flux:callout class="mt-4 rounded-xl">
                            <flux:callout.heading>{{ __('Migrations required') }}</flux:callout.heading>
                            <flux:callout.text>
                                {{ __('Run the latest database migrations to enable suppression controls in this environment.') }}
                            </flux:callout.text>
                        </flux:callout>
                    @elseif ($selectedListing->isSuppressed() && $currentSuppression !== null)
                        <div class="mt-4 space-y-4">
                            <div class="rounded-xl border border-dashed border-red-300/70 bg-red-50/60 p-4 text-sm text-red-700 dark:border-red-700/40 dark:bg-red-900/30 dark:text-red-200">
                                <div class="flex flex-col gap-1">
                                    <span class="font-semibold">{{ $currentSuppression->reason }}</span>
                                    @if ($currentSuppression->notes)
                                        <span>{{ $currentSuppression->notes }}</span>
                                    @endif
                                </div>

                                <dl class="mt-3 grid gap-3 text-xs text-red-800 dark:text-red-200 sm:grid-cols-2">
                                    <div class="flex flex-col">
                                        <dt class="font-semibold uppercase tracking-wide">{{ __('Suppressed by') }}</dt>
                                        <dd>{{ $currentSuppression->user?->name ?? __('System') }}</dd>
                                    </div>
                                    <div class="flex flex-col">
                                        <dt class="font-semibold uppercase tracking-wide">{{ __('Suppressed at') }}</dt>
                                        <dd>
                                            {{ optional($currentSuppression->suppressed_at)?->timezone($appTimezone)?->format('M j, Y g:i a') ?? '—' }}
                                        </dd>
                                    </div>
                                    <div class="flex flex-col">
                                        <dt class="font-semibold uppercase tracking-wide">{{ __('Expires') }}</dt>
                                        <dd>
                                            @if ($currentSuppression->expires_at === null)
                                                {{ __('No expiry') }}
                                            @else
                                                {{ $currentSuppression->expires_at->timezone($appTimezone)->format('M j, Y g:i a') }}
                                                <span class="block text-[11px] uppercase tracking-wide text-red-600/70 dark:text-red-200/70">
                                                    {{ $currentSuppression->expires_at->diffForHumans() }}
                                                </span>
                                            @endif
                                        </dd>
                                    </div>
                                    <div class="flex flex-col">
                                        <dt class="font-semibold uppercase tracking-wide">{{ __('Recorded by') }}</dt>
                                        <dd>{{ $selectedListing->suppressedBy?->name ?? __('System') }}</dd>
                                    </div>
                                </dl>
                            </div>

                            <form wire:submit="unsuppressSelected" class="space-y-4">
                                <flux:input
                                    wire:model.defer="unsuppressionForm.reason"
                                    :label="__('Release reason (optional)')"
                                    type="text"
                                />

                                <flux:textarea
                                    wire:model.defer="unsuppressionForm.notes"
                                    :label="__('Release notes (optional)')"
                                    rows="3"
                                />

                                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                        {{ __('Unsuppressing returns the listing to public experience immediately.') }}
                                    </flux:text>

                                    <flux:button type="submit" variant="primary">
                                        {{ __('Unsuppress listing') }}
                                    </flux:button>
                                </div>
                            </form>
                        </div>
                    @else
                        <form wire:submit="suppressSelected" class="mt-4 space-y-4">
                            <flux:input
                                wire:model.defer="suppressionForm.reason"
                                :label="__('Suppression reason')"
                                type="text"
                                required
                            />

                            <flux:textarea
                                wire:model.defer="suppressionForm.notes"
                                :label="__('Notes for audit trail (optional)')"
                                rows="3"
                            />

                            <flux:input
                                wire:model.defer="suppressionForm.expires_at"
                                :label="__('Expires at (optional)')"
                                type="datetime-local"
                                min="{{ now()->timezone($appTimezone)->format('Y-m-d\TH:i') }}"
                            />

                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                                <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                                    {{ __('Suppressed listings stay hidden until manual release or expiry. Hard deletes remain automated only.') }}
                                </flux:text>

                                <flux:button type="submit" variant="outline">
                                    {{ __('Suppress listing') }}
                                </flux:button>
                            </div>
                        </form>
                    @endif

                    @if ($suppressionAvailable && $suppressionHistory->isNotEmpty())
                        <div class="mt-6 space-y-3">
                            <flux:text class="text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                {{ __('Recent suppression events') }}
                            </flux:text>

                            @foreach ($suppressionHistory as $record)
                                <div class="rounded-xl border border-zinc-200 bg-white/80 p-4 text-sm text-zinc-600 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/50 dark:text-zinc-300">
                                    <div class="flex items-start justify-between gap-3">
                                        <span class="font-semibold text-zinc-900 dark:text-zinc-100">
                                            {{ $record->reason }}
                                        </span>

                                        <flux:badge color="{{ $record->released_at !== null ? 'green' : 'zinc' }}" size="xs">
                                            {{ $record->released_at !== null ? __('Released') : __('Suppressed') }}
                                        </flux:badge>
                                    </div>

                                    @if ($record->notes)
                                        <p class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
                                            {{ $record->notes }}
                                        </p>
                                    @endif

                                    <dl class="mt-3 grid gap-3 text-[11px] uppercase tracking-wide text-zinc-500 dark:text-zinc-400 sm:grid-cols-2">
                                        <div>
                                            <dt>{{ __('Suppressed by') }}</dt>
                                            <dd class="text-zinc-700 dark:text-zinc-200">
                                                {{ $record->user?->name ?? __('System') }}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt>{{ __('Suppressed at') }}</dt>
                                            <dd class="text-zinc-700 dark:text-zinc-200">
                                                {{ optional($record->suppressed_at)?->timezone($appTimezone)?->format('M j, Y g:i a') ?? '—' }}
                                            </dd>
                                        </div>

                                        <div>
                                            <dt>{{ __('Expires') }}</dt>
                                            <dd class="text-zinc-700 dark:text-zinc-200">
                                                @if ($record->expires_at === null)
                                                    {{ __('No expiry') }}
                                                @else
                                                    {{ $record->expires_at->timezone($appTimezone)->format('M j, Y g:i a') }}
                                                @endif
                                            </dd>
                                        </div>

                                        <div>
                                            <dt>{{ __('Released') }}</dt>
                                            <dd class="text-zinc-700 dark:text-zinc-200">
                                                @if ($record->released_at !== null)
                                                    {{ $record->released_at->timezone($appTimezone)->format('M j, Y g:i a') }}
                                                    <span class="block text-[10px] normal-case text-zinc-500 dark:text-zinc-400">
                                                        {{ $record->releaseUser?->name ?? __('System') }}
                                                    </span>
                                                @else
                                                    {{ __('Pending') }}
                                                @endif
                                            </dd>
                                        </div>

                                        @if ($record->release_reason)
                                            <div class="sm:col-span-2 normal-case">
                                                <dt>{{ __('Release reason') }}</dt>
                                                <dd class="text-zinc-700 dark:text-zinc-200">
                                                    {{ $record->release_reason }}
                                                </dd>
                                            </div>
                                        @endif

                                        @if ($record->release_notes)
                                            <div class="sm:col-span-2 normal-case">
                                                <dt>{{ __('Release notes') }}</dt>
                                                <dd class="text-zinc-700 dark:text-zinc-200">
                                                    {{ $record->release_notes }}
                                                </dd>
                                            </div>
                                        @endif
                                    </dl>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>
            </div>

            <div class="mt-4">
                <flux:button
                    :href="route('admin.listings.show', $selectedListing)"
                    icon="arrow-top-right-on-square"
                    variant="outline"
                    class="w-full"
                    wire:navigate
                >
                    {{ __('Open detail view') }}
                </flux:button>
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
