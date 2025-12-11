@props([
    'listings' => null,
    'apiEndpoint' => null,
    'height' => '400px',
    'showControls' => true,
    'initialLat' => null,
    'initialLng' => null,
    'initialZoom' => null,
])

@php
    $maptilerKey = \App\Models\ApiKey::maptiler();
    $apiKey = $maptilerKey->isConfigured() ? $maptilerKey->api_key : config('services.maptiler.key');
@endphp

<div
    x-data="listingMap({
        listings: @js($listings),
        apiEndpoint: @js($apiEndpoint),
        maptilerKey: @js($apiKey),
        initialLat: @js($initialLat),
        initialLng: @js($initialLng),
        initialZoom: @js($initialZoom),
    })"
    x-init="init()"
    x-on:destroy.window="destroy()"
    {{ $attributes->merge(['class' => 'relative rounded-xl overflow-hidden border border-slate-200 dark:border-zinc-800']) }}
>
    <div x-ref="mapContainer" style="height: {{ $height }};" class="w-full z-0"></div>

    @if($showControls)
        <div class="absolute top-3 right-3 z-[1000] flex flex-col gap-2">
            <button
                type="button"
                x-on:click="toggleLayer()"
                class="flex items-center gap-2 rounded-lg bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-md transition hover:bg-slate-50 dark:bg-zinc-800 dark:text-zinc-200 dark:hover:bg-zinc-700"
            >
                <template x-if="currentLayer === 'street'">
                    <span class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        Satellite
                    </span>
                </template>
                <template x-if="currentLayer === 'satellite'">
                    <span class="flex items-center gap-2">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7" />
                        </svg>
                        Street
                    </span>
                </template>
            </button>
        </div>
    @endif
</div>
