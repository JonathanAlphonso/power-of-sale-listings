@props(['title', 'description' => null])

<div>
    <x-ui.section-badge {{ $badge ?? '' }}>
        {{ $badgeText ?? 'Section' }}
    </x-ui.section-badge>
    <h2 class="mt-4 text-3xl font-semibold tracking-tight text-slate-900 sm:text-4xl">{{ $title }}</h2>
    @if ($description)
        <p class="mt-3 text-lg text-slate-600">{{ $description }}</p>
    @endif
</div>
