@pure

@props([
    'padding' => 'py-3',
])

@php
    $classes = Flux::classes()
        ->add('overflow-hidden rounded-2xl border border-zinc-200 bg-white shadow-sm dark:border-zinc-700 dark:bg-zinc-900/60')
        ->add('flex flex-col')
        ->add('min-w-0');
@endphp

<div {{ $attributes->class($classes)->merge(['data-flux-table' => true]) }}>
    <div class="min-w-full divide-y divide-zinc-100 dark:divide-zinc-800">
        {{ $slot }}
    </div>
</div>
