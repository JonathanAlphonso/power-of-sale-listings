<div class="flex flex-col gap-4">
    <flux:table>
        <flux:table.header>
            <span>{{ __('MLS') }}</span>
            <span>{{ __('Address') }}</span>
            <span class="text-center">{{ __('Status') }}</span>
            <span class="text-center">{{ __('Price') }}</span>
            <span class="text-right">{{ __('Updated') }}</span>
        </flux:table.header>

        <flux:table.rows>
            @forelse ($this->listings as $listing)
                <flux:table.row
                    wire:key="listing-row-{{ $listing->id }}"
                    wire:click="selectListing({{ $listing->id }})"
                    :selected="$selectedListing && $selectedListing->id === $listing->id"
                    :interactive="true"
                >
                    <flux:table.cell>
                        <span class="font-semibold text-zinc-900 dark:text-white">{{ $listing->mls_number ?? __('Unknown') }}</span>
                        <flux:text class="text-xs uppercase text-zinc-500 dark:text-zinc-400">
                            {{ $listing->board_code ?? '—' }}
                        </flux:text>
                    </flux:table.cell>

                    <flux:table.cell>
                        <span class="font-medium text-zinc-900 dark:text-white">{{ $listing->street_address ?? __('Address unavailable') }}</span>

                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ collect([$listing->city, $listing->province])->filter()->implode(', ') }}
                        </flux:text>
                    </flux:table.cell>

                    <flux:table.cell alignment="center">
                        <flux:badge
                            color="{{ \App\Support\ListingPresentation::statusBadge($listing->display_status) }}"
                            size="sm"
                        >
                            {{ $listing->display_status ?? __('Unknown') }}
                        </flux:badge>
                    </flux:table.cell>

                    <flux:table.cell alignment="center">
                        <flux:text class="font-medium text-zinc-800 dark:text-zinc-200">
                            {{ \App\Support\ListingPresentation::currency($listing->list_price) }}
                        </flux:text>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ __('Original: :price', ['price' => \App\Support\ListingPresentation::currency($listing->original_list_price)]) }}
                        </flux:text>
                    </flux:table.cell>

                    <flux:table.cell alignment="end">
                        <span class="text-sm font-medium text-zinc-900 dark:text-zinc-100">
                            {{ optional($listing->modified_at)?->timezone(config('app.timezone'))->format('M j, Y g:i a') ?? __('—') }}
                        </span>
                        <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                            {{ optional($listing->modified_at)?->diffForHumans() ?? __('No timestamp') }}
                        </flux:text>
                    </flux:table.cell>
                </flux:table.row>
            @empty
                <flux:table.empty>
                    {{ __('No listings match the current filters.') }}
                </flux:table.empty>
            @endforelse
        </flux:table.rows>
    </flux:table>

    <div class="flex flex-col gap-3 rounded-2xl border border-zinc-200 bg-white px-4 py-3 text-sm text-zinc-600 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60 dark:text-zinc-400 sm:flex-row sm:items-center sm:justify-between">
        <span>
            {{ trans_choice(':count listing|:count listings', $this->listings->total(), ['count' => number_format($this->listings->total())]) }}
        </span>

        <div>
            {{ $this->listings->links() }}
        </div>
    </div>
</div>
