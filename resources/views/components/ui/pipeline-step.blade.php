@props(['number', 'title', 'variant' => 'default'])

@php
    $variants = [
        'red' => [
            'border' => 'border-red-100 dark:border-red-900/50',
            'shadow' => 'shadow-[0_18px_40px_rgba(217,4,41,0.08)] dark:shadow-none',
            'badge' => 'bg-red-100 text-red-600 dark:bg-red-900/50 dark:text-red-400',
        ],
        'blue' => [
            'border' => 'border-blue-100 dark:border-blue-900/50',
            'shadow' => 'shadow-[0_18px_40px_rgba(59,130,246,0.08)] dark:shadow-none',
            'badge' => 'bg-blue-100 text-blue-600 dark:bg-blue-900/50 dark:text-blue-400',
        ],
        'emerald' => [
            'border' => 'border-emerald-100 dark:border-emerald-900/50',
            'shadow' => 'shadow-[0_18px_40px_rgba(16,185,129,0.08)] dark:shadow-none',
            'badge' => 'bg-emerald-100 text-emerald-600 dark:bg-emerald-900/50 dark:text-emerald-400',
        ],
        'default' => [
            'border' => 'border-slate-200 dark:border-zinc-800',
            'shadow' => 'shadow-[0_18px_40px_rgba(15,23,42,0.08)] dark:shadow-none',
            'badge' => 'bg-slate-100 text-slate-700 dark:bg-zinc-800 dark:text-zinc-400',
        ],
    ];

    $config = $variants[$variant] ?? $variants['default'];
@endphp

<article class="group rounded-3xl border {{ $config['border'] }} bg-white p-6 {{ $config['shadow'] }} dark:bg-zinc-900">
    <span class="mb-4 flex h-10 w-10 items-center justify-center rounded-full {{ $config['badge'] }} text-sm font-semibold">
        {{ $number }}
    </span>
    <h3 class="text-lg font-semibold text-slate-900 dark:text-white">{{ $title }}</h3>
    <p class="mt-3 text-sm text-slate-600 dark:text-zinc-400">{{ $slot }}</p>
</article>
