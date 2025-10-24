@pure

@props([
    'alignment' => 'start',
])

@php
    $classes = Flux::classes()
        ->add('flex flex-col gap-1 text-sm text-zinc-700 dark:text-zinc-200')
        ->add([
            'items-start text-left' => $alignment === 'start',
            'items-center text-center' => $alignment === 'center',
            'items-end text-right' => $alignment === 'end',
        ]);
@endphp

<div {{ $attributes->class($classes)->merge(['data-slot' => 'cell']) }}>
    {{ $slot }}
</div>
