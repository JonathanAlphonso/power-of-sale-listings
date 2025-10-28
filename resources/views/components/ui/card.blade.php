@props(['variant' => 'default', 'padding' => 'p-8'])

@php
    $variants = [
        'default' => 'border-slate-200 shadow-[0_20px_55px_rgba(15,23,42,0.08)]',
        'red' => 'border-red-100 shadow-[0_20px_55px_rgba(217,4,41,0.12)]',
        'blue' => 'border-blue-100 shadow-[0_20px_55px_rgba(59,130,246,0.12)]',
        'emerald' => 'border-emerald-100 shadow-[0_20px_55px_rgba(16,185,129,0.12)]',
        'slate' => 'border-slate-200 shadow-[0_20px_55px_rgba(15,23,42,0.12)]',
    ];

    $classes = $variants[$variant] ?? $variants['default'];
@endphp

<div {{ $attributes->merge(['class' => "rounded-3xl border bg-white {$padding} {$classes}"]) }}>
    {{ $slot }}
</div>
