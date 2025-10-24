@pure

@props([
    'columns' => 'grid-cols-[minmax(0,1.2fr)_minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)]',
])

@php
    $classes = Flux::classes()
        ->add('hidden px-4 text-xs font-semibold uppercase tracking-wide text-zinc-500 dark:text-zinc-400 sm:grid')
        ->add($columns)
        ->add('bg-zinc-50/90 dark:bg-zinc-900/70');
@endphp

<div {{ $attributes->class($classes)->merge(['data-slot' => 'header']) }}>
    {{ $slot }}
</div>
