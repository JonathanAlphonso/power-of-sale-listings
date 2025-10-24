@pure

@props([
    'interactive' => true,
    'selected' => false,
    'columns' => 'grid-cols-[minmax(0,1.2fr)_minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)]',
])

@php
    $classes = Flux::classes()
        ->add('grid grid-cols-1 items-start gap-3 px-4 py-4 text-sm transition sm:grid')
        ->add('bg-white dark:bg-zinc-900/60')
        ->add('sm:' . $columns)
        ->add([
            'hover:bg-zinc-50/75 dark:hover:bg-zinc-900/70 cursor-pointer' => $interactive,
            'ring-1 ring-blue-400/60 bg-blue-50/70 dark:bg-blue-500/10 dark:ring-blue-500/40' => $selected,
        ]);
@endphp

<div {{ $attributes->class($classes)->merge(['data-slot' => 'row']) }}>
    {{ $slot }}
</div>
