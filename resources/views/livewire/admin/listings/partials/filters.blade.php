<div class="grid gap-4 rounded-2xl border border-zinc-200 bg-white p-4 shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60 sm:grid-cols-2 lg:grid-cols-3">
    <flux:input
        wire:model.live.debounce.400ms="search"
        :label="__('Search')"
        icon="magnifying-glass"
        :placeholder="__('MLS #, address, or board code')"
    />

    <flux:select
        wire:model.live="status"
        :label="__('Status')"
    >
        <flux:select.option value="">{{ __('All statuses') }}</flux:select.option>
        @foreach ($this->availableStatuses as $statusOption)
            <flux:select.option value="{{ $statusOption }}">{{ $statusOption }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:select
        wire:model.live="municipalityId"
        :label="__('Municipality')"
    >
        <flux:select.option value="">{{ __('All municipalities') }}</flux:select.option>
        @foreach ($this->municipalities as $municipality)
            <flux:select.option value="{{ $municipality->id }}">{{ $municipality->name }}</flux:select.option>
        @endforeach
    </flux:select>

    <flux:select
        wire:model.live="perPage"
        :label="__('Results per page')"
        class="sm:col-span-2 lg:col-span-1"
    >
        @foreach ($this->perPageOptions as $option)
            <flux:select.option value="{{ $option }}">{{ $option }}</flux:select.option>
        @endforeach
    </flux:select>

    <div class="flex items-center gap-2 sm:col-span-2 lg:col-span-1 sm:self-end">
        <flux:button
            variant="subtle"
            icon="arrow-path"
            wire:click="resetFilters"
        >
            {{ __('Reset filters') }}
        </flux:button>

        <flux:button
            variant="primary"
            icon="arrow-down-tray"
            wire:click="exportCsv"
            wire:loading.attr="disabled"
            wire:target="exportCsv"
        >
            <span wire:loading.remove wire:target="exportCsv">
                {{ __('Export CSV') }}
            </span>
            <span wire:loading wire:target="exportCsv">
                {{ __('Exporting...') }}
            </span>
        </flux:button>
    </div>
</div>
