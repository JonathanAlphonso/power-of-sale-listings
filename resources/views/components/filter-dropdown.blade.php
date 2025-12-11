@props([
    'label',
    'options' => [],
    'selected' => [],
    'wireModel' => null,
    'allLabel' => null,
    'valueKey' => null,
    'labelKey' => null,
])

@php
    $allLabel ??= __('All :label', ['label' => strtolower($label)]);
    $selectedCount = is_array($selected) ? count($selected) : 0;
    $hasSelection = $selectedCount > 0;

    // Determine button label
    if (!$hasSelection) {
        $buttonLabel = $allLabel;
    } elseif ($selectedCount === 1) {
        $firstSelected = $selected[0];
        if ($valueKey && $labelKey) {
            $match = collect($options)->first(fn($opt) => $opt[$valueKey] == $firstSelected);
            $buttonLabel = $match ? $match[$labelKey] : $firstSelected;
        } else {
            $buttonLabel = $firstSelected;
        }
    } else {
        $buttonLabel = $selectedCount . ' ' . __('selected');
    }
@endphp

<flux:dropdown position="bottom" align="start">
    <flux:button
        variant="{{ $hasSelection ? 'primary' : 'outline' }}"
        icon:trailing="chevron-down"
    >
        {{ $buttonLabel }}
    </flux:button>

    <flux:menu class="w-64 max-h-80 overflow-y-auto p-2">
        <div class="space-y-0.5">
            {{-- "All" option to clear selection --}}
            <label class="flex items-center gap-2.5 rounded-md px-2 py-2 text-sm font-medium text-zinc-800 hover:bg-zinc-100 dark:text-white dark:hover:bg-zinc-600 cursor-pointer transition-colors">
                <input
                    type="checkbox"
                    {{ !$hasSelection ? 'checked' : '' }}
                    wire:click="$set('{{ $wireModel }}', [])"
                    class="size-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 focus:ring-offset-0 dark:border-zinc-500 dark:bg-zinc-700"
                />
                {{ $allLabel }}
            </label>

            <div class="my-1.5 border-t border-zinc-200 dark:border-zinc-600"></div>

            @foreach ($options as $option)
                @php
                    $value = $valueKey ? $option[$valueKey] : $option;
                    $displayLabel = $labelKey ? $option[$labelKey] : $option;
                    $isChecked = in_array($value, $selected);
                @endphp
                <label class="flex items-center gap-2.5 rounded-md px-2 py-2 text-sm text-zinc-700 hover:bg-zinc-100 dark:text-zinc-200 dark:hover:bg-zinc-600 cursor-pointer transition-colors">
                    <input
                        type="checkbox"
                        value="{{ $value }}"
                        wire:model.live="{{ $wireModel }}"
                        class="size-4 rounded border-zinc-300 text-emerald-600 focus:ring-emerald-500 focus:ring-offset-0 dark:border-zinc-500 dark:bg-zinc-700"
                    />
                    {{ $displayLabel }}
                </label>
            @endforeach
        </div>
    </flux:menu>
</flux:dropdown>
