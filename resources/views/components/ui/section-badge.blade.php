@props(['variant' => 'default'])

@php
    $variants = [
        'default' => 'border-slate-200 bg-white text-slate-500',
        'red' => 'border-red-100 bg-white text-red-500',
        'blue' => 'border-blue-100 bg-white text-blue-600',
        'emerald' => 'border-emerald-100 bg-white text-emerald-600',
        'success' => 'border-emerald-200 bg-emerald-50 text-emerald-600',
        'danger' => 'border-red-200 bg-red-50 text-red-600',
    ];

    $classes = $variants[$variant] ?? $variants['default'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center gap-2 rounded-full border px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em] {$classes}"]) }}>
    {{ $slot }}
</span>
