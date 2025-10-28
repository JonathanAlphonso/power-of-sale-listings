@props(['number', 'title', 'variant' => 'default'])

@php
    $variants = [
        'red' => [
            'border' => 'border-red-100',
            'shadow' => 'shadow-[0_18px_40px_rgba(217,4,41,0.08)]',
            'badge' => 'bg-red-100 text-red-600',
        ],
        'blue' => [
            'border' => 'border-blue-100',
            'shadow' => 'shadow-[0_18px_40px_rgba(59,130,246,0.08)]',
            'badge' => 'bg-blue-100 text-blue-600',
        ],
        'emerald' => [
            'border' => 'border-emerald-100',
            'shadow' => 'shadow-[0_18px_40px_rgba(16,185,129,0.08)]',
            'badge' => 'bg-emerald-100 text-emerald-600',
        ],
        'default' => [
            'border' => 'border-slate-200',
            'shadow' => 'shadow-[0_18px_40px_rgba(15,23,42,0.08)]',
            'badge' => 'bg-slate-100 text-slate-700',
        ],
    ];

    $config = $variants[$variant] ?? $variants['default'];
@endphp

<article class="group rounded-3xl border {{ $config['border'] }} bg-white p-6 {{ $config['shadow'] }}">
    <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-full {{ $config['badge'] }} text-sm font-semibold">
        {{ $number }}
    </span>
    <h3 class="text-lg font-semibold text-slate-900">{{ $title }}</h3>
    <p class="mt-3 text-sm text-slate-600">{{ $slot }}</p>
</article>
