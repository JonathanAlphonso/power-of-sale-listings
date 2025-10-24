@pure

@props([
    'columns' => 'sm:grid-cols-[minmax(0,1.2fr)_minmax(0,2fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1.2fr)]',
])

@php
    $classes = Flux::classes()
        ->add('flex flex-col')
        ->add('divide-y divide-zinc-100 dark:divide-zinc-800');
@endphp

<div {{ $attributes->class($classes)->merge(['data-slot' => 'rows']) }}>
    {{ $slot }}
</div>
