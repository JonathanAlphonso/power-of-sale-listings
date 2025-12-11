@props([
    'size' => 'default', // 'small' for cards, 'default' for galleries
])

@php
    $iconSize = $size === 'small' ? 'h-10 w-10' : 'h-16 w-16';
    $textSize = $size === 'small' ? 'text-xs' : 'text-sm';
@endphp

<div {{ $attributes->merge(['class' => 'relative flex items-center justify-center bg-gradient-to-br from-slate-100 to-slate-200 dark:from-zinc-800 dark:to-zinc-900 overflow-hidden']) }}>
    {{-- Subtle pattern background --}}
    <div class="absolute inset-0 opacity-[0.03] dark:opacity-[0.05]">
        <svg class="h-full w-full" xmlns="http://www.w3.org/2000/svg">
            <defs>
                <pattern id="house-pattern" x="0" y="0" width="60" height="60" patternUnits="userSpaceOnUse">
                    <path fill="currentColor" d="M30 5L10 20v25h40V20L30 5zm0 8l12 9v15H18V22l12-9z"/>
                </pattern>
            </defs>
            <rect width="100%" height="100%" fill="url(#house-pattern)" class="text-slate-900 dark:text-white"/>
        </svg>
    </div>

    {{-- Main content --}}
    <div class="relative z-10 flex flex-col items-center gap-3 text-slate-400 dark:text-zinc-500">
        {{-- House with camera icon --}}
        <div class="relative">
            <svg class="{{ $iconSize }} text-slate-300 dark:text-zinc-600" viewBox="0 0 48 48" fill="currentColor">
                {{-- House shape --}}
                <path d="M24 6L6 20h6v18h24V20h6L24 6zm0 6l10 8v14H14V20l10-8z" opacity="0.6"/>
                {{-- Door --}}
                <rect x="20" y="28" width="8" height="10" rx="1" opacity="0.4"/>
                {{-- Window --}}
                <rect x="28" y="22" width="6" height="5" rx="0.5" opacity="0.4"/>
            </svg>
            {{-- Camera slash overlay --}}
            <div class="absolute -bottom-1 -right-1 rounded-full bg-slate-200 p-1 dark:bg-zinc-700">
                <svg class="h-4 w-4 text-slate-400 dark:text-zinc-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 015.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 002.25 2.25h15A2.25 2.25 0 0021.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 00-1.134-.175 2.31 2.31 0 01-1.64-1.055l-.822-1.316a2.192 2.192 0 00-1.736-1.039 48.774 48.774 0 00-5.232 0 2.192 2.192 0 00-1.736 1.039l-.821 1.316z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 11-9 0 4.5 4.5 0 019 0zM18.75 10.5h.008v.008h-.008V10.5z" />
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 3l18 18" />
                </svg>
            </div>
        </div>

        <span class="{{ $textSize }} font-medium">{{ __('No photos available') }}</span>
    </div>
</div>
