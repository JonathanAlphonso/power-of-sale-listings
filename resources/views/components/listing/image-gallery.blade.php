@props([
    'images' => collect(),
    'alt' => 'Property photo',
])

@php
    $imageList = $images->map(fn($img) => [
        'url' => $img->public_url,
        'label' => $img->label ?? $alt,
        'is_primary' => $img->is_primary ?? false,
    ])->values()->toArray();

    $primaryIndex = collect($imageList)->search(fn($img) => $img['is_primary']) ?: 0;
@endphp

@if(count($imageList) > 0)
<div
    x-data="imageGallery({{ json_encode($imageList) }}, {{ $primaryIndex }})"
    x-on:keydown.escape.window="closeLightbox()"
    x-on:keydown.arrow-left.window="if (lightboxOpen) prevImage()"
    x-on:keydown.arrow-right.window="if (lightboxOpen) nextImage()"
    class="relative"
>
    {{-- Main Image --}}
    <button
        type="button"
        @click="openLightbox(currentIndex)"
        class="group relative aspect-[4/3] w-full overflow-hidden rounded-xl bg-slate-100 dark:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
    >
        <img
            :src="images[currentIndex]?.url"
            :alt="images[currentIndex]?.label"
            class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
        />
        <div class="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/10"></div>
        <div class="absolute bottom-3 right-3 flex items-center gap-2 rounded-lg bg-black/60 px-3 py-1.5 text-sm text-white opacity-0 transition-opacity group-hover:opacity-100">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" />
            </svg>
            <span x-text="`${currentIndex + 1} / ${images.length}`"></span>
        </div>
    </button>

    {{-- Thumbnail Strip (show when more than 1 image) --}}
    <template x-if="images.length > 1">
        <div class="mt-2 grid grid-cols-6 gap-2">
            <template x-for="(image, index) in images.slice(0, 6)" :key="index">
                <button
                    type="button"
                    @click="openLightbox(index)"
                    class="group relative aspect-[4/3] overflow-hidden rounded-lg bg-slate-100 dark:bg-zinc-800 focus:outline-none focus:ring-2 focus:ring-emerald-500"
                    :class="{ 'ring-2 ring-emerald-500': index === currentIndex }"
                >
                    <img
                        :src="image.url"
                        :alt="image.label"
                        class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                        loading="lazy"
                    />
                    <div class="absolute inset-0 bg-black/0 transition-colors group-hover:bg-black/10"></div>

                    {{-- Show "+X more" on last thumbnail if more images exist --}}
                    <template x-if="index === 5 && images.length > 6">
                        <div class="absolute inset-0 flex items-center justify-center bg-black/50 text-white">
                            <span class="text-lg font-semibold" x-text="`+${images.length - 6}`"></span>
                        </div>
                    </template>
                </button>
            </template>
        </div>
    </template>

    {{-- Lightbox Modal --}}
    <template x-teleport="body">
        <div
            x-show="lightboxOpen"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="fixed inset-0 z-[200] flex items-center justify-center bg-black/95 p-4"
            @click.self="closeLightbox()"
            style="display: none;"
        >
            {{-- Close Button --}}
            <button
                type="button"
                @click="closeLightbox()"
                class="absolute right-4 top-4 z-10 rounded-full bg-white/10 p-2 text-white transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>

            {{-- Image Counter --}}
            <div class="absolute left-4 top-4 rounded-lg bg-white/10 px-3 py-1.5 text-sm text-white">
                <span x-text="`${lightboxIndex + 1} / ${images.length}`"></span>
            </div>

            {{-- Previous Button --}}
            <template x-if="images.length > 1">
                <button
                    type="button"
                    @click="prevImage()"
                    class="absolute left-4 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
                    </svg>
                </button>
            </template>

            {{-- Main Image --}}
            <div class="relative max-h-[80vh] max-w-[90vw]">
                <img
                    :src="images[lightboxIndex]?.url"
                    :alt="images[lightboxIndex]?.label"
                    class="max-h-[80vh] max-w-full rounded-lg object-contain"
                />
            </div>

            {{-- Next Button --}}
            <template x-if="images.length > 1">
                <button
                    type="button"
                    @click="nextImage()"
                    class="absolute right-4 top-1/2 z-10 -translate-y-1/2 rounded-full bg-white/10 p-3 text-white transition-colors hover:bg-white/20 focus:outline-none focus:ring-2 focus:ring-white"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
            </template>

            {{-- Thumbnail Strip --}}
            <template x-if="images.length > 1">
                <div class="absolute bottom-4 left-1/2 z-10 flex max-w-[90vw] -translate-x-1/2 gap-2 overflow-x-auto rounded-lg bg-black/50 p-2">
                    <template x-for="(image, index) in images" :key="index">
                        <button
                            type="button"
                            @click="goToImage(index)"
                            class="h-16 w-20 flex-shrink-0 overflow-hidden rounded transition-all focus:outline-none"
                            :class="index === lightboxIndex ? 'ring-2 ring-white opacity-100' : 'opacity-50 hover:opacity-75'"
                        >
                            <img
                                :src="image.url"
                                :alt="image.label"
                                class="h-full w-full object-cover"
                                loading="lazy"
                            />
                        </button>
                    </template>
                </div>
            </template>
        </div>
    </template>
</div>
@else
{{-- No Images Placeholder --}}
<x-listing.no-photo-placeholder class="aspect-[4/3] rounded-xl" />
@endif

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('imageGallery', (images, primaryIndex = 0) => ({
        images: images,
        currentIndex: primaryIndex,
        lightboxOpen: false,
        lightboxIndex: 0,

        openLightbox(index) {
            this.lightboxIndex = index;
            this.lightboxOpen = true;
            document.body.style.overflow = 'hidden';
        },

        closeLightbox() {
            this.lightboxOpen = false;
            document.body.style.overflow = '';
            this.currentIndex = this.lightboxIndex;
        },

        nextImage() {
            this.lightboxIndex = (this.lightboxIndex + 1) % this.images.length;
        },

        prevImage() {
            this.lightboxIndex = (this.lightboxIndex - 1 + this.images.length) % this.images.length;
        },

        goToImage(index) {
            this.lightboxIndex = index;
        }
    }));
});
</script>
