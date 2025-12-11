@props([
    'label',
    'options' => [],
    'selected' => [],
    'wireModel' => null,
    'placeholder' => null,
    'allLabel' => null,
])

@php
    $placeholder ??= __('Search :label...', ['label' => strtolower($label)]);
    $selectedCount = is_array($selected) ? count($selected) : 0;
    $hasSelection = $selectedCount > 0;

    // Determine button label
    if (!$hasSelection) {
        $buttonLabel = $allLabel ?? __('All');
    } elseif ($selectedCount === 1) {
        $buttonLabel = $selected[0];
    } elseif ($selectedCount <= 2) {
        $buttonLabel = implode(', ', $selected);
    } else {
        $buttonLabel = $selectedCount . ' ' . __('selected');
    }
@endphp

<div
    x-data="{
        search: '',
        open: false,
        options: @js($options),
        selected: @entangle($wireModel).live,
        get filteredOptions() {
            if (!this.search) return this.options.slice(0, 20);
            const query = this.search.toLowerCase();
            return this.options.filter(opt => opt.toLowerCase().includes(query)).slice(0, 20);
        },
        toggle(value) {
            const index = this.selected.indexOf(value);
            if (index === -1) {
                this.selected.push(value);
            } else {
                this.selected.splice(index, 1);
            }
        },
        isSelected(value) {
            return this.selected.includes(value);
        },
        clearAll() {
            this.selected = [];
        }
    }"
    class="relative"
>
    <flux:dropdown position="bottom" align="start">
        <flux:button
            variant="{{ $hasSelection ? 'primary' : 'outline' }}"
            icon:trailing="chevron-down"
        >
            <span class="max-w-32 truncate">{{ $buttonLabel }}</span>
        </flux:button>

        <flux:menu class="w-72 p-0">
            {{-- Search Input --}}
            <div class="p-2 border-b border-zinc-200 dark:border-zinc-600">
                <input
                    type="text"
                    x-model="search"
                    placeholder="{{ $placeholder }}"
                    class="w-full rounded-md border-zinc-300 bg-white px-3 py-2 text-sm placeholder-zinc-400 focus:border-emerald-500 focus:ring-emerald-500 dark:border-zinc-600 dark:bg-zinc-800 dark:text-white dark:placeholder-zinc-500"
                    @click.stop
                />
            </div>

            {{-- Selected Tags --}}
            <template x-if="selected.length > 0">
                <div class="p-2 border-b border-zinc-200 dark:border-zinc-600 flex flex-wrap gap-1.5">
                    <template x-for="item in selected" :key="item">
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                            <span x-text="item" class="max-w-24 truncate"></span>
                            <button
                                type="button"
                                @click.stop="toggle(item)"
                                class="text-emerald-600 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-200"
                            >
                                <svg class="size-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </span>
                    </template>
                    <button
                        type="button"
                        @click.stop="clearAll()"
                        class="text-xs text-zinc-500 hover:text-zinc-700 dark:text-zinc-400 dark:hover:text-zinc-200"
                    >
                        {{ __('Clear all') }}
                    </button>
                </div>
            </template>

            {{-- Options List --}}
            <div class="max-h-48 overflow-y-auto p-1">
                <template x-for="option in filteredOptions" :key="option">
                    <label
                        class="flex items-center gap-2.5 rounded-md px-2 py-2 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-600 cursor-pointer transition-colors"
                        @click.stop
                    >
                        <input
                            type="checkbox"
                            :value="option"
                            :checked="isSelected(option)"
                            @change="toggle(option)"
                            class="size-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 focus:ring-offset-0 dark:border-zinc-500 dark:bg-zinc-700"
                        />
                        <span x-text="option"></span>
                    </label>
                </template>

                <template x-if="filteredOptions.length === 0">
                    <div class="px-2 py-3 text-sm text-zinc-500 dark:text-zinc-400 text-center">
                        {{ __('No results found') }}
                    </div>
                </template>

                <template x-if="!search && options.length > 20">
                    <div class="px-2 py-1 text-xs text-zinc-400 dark:text-zinc-500 text-center border-t border-zinc-100 dark:border-zinc-600 mt-1">
                        {{ __('Type to search more...') }}
                    </div>
                </template>
            </div>
        </flux:menu>
    </flux:dropdown>
</div>
