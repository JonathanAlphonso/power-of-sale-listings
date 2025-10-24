@pure

@php
    $classes = Flux::classes()
        ->add('flex items-center justify-center px-6 py-12 text-sm text-zinc-500 dark:text-zinc-400');
@endphp

<div {{ $attributes->class($classes)->merge(['data-slot' => 'empty']) }}>
    {{ $slot }}
</div>
