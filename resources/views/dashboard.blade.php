<x-layouts.site :title="__('Dashboard')">
    <section class="mx-auto max-w-6xl px-6 py-12 lg:px-8">
        <div class="flex flex-col gap-6">
            <div>
                <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">{{ __('Dashboard') }}</h1>
                <p class="mt-2 text-sm text-slate-600 dark:text-zinc-400">
                    {{ __('This space will surface listings intelligence, ingestion status, and review queues as features come online.') }}
                </p>
            </div>

            <div class="grid auto-rows-min gap-4 md:grid-cols-3">
                <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900/60">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900/60">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                </div>
                <div class="relative aspect-video overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900/60">
                    <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
                </div>
            </div>

            <div class="relative h-full min-h-[320px] overflow-hidden rounded-xl border border-neutral-200 bg-white dark:border-neutral-700 dark:bg-zinc-900/60">
                <x-placeholder-pattern class="absolute inset-0 size-full stroke-gray-900/20 dark:stroke-neutral-100/20" />
            </div>
        </div>
    </section>
</x-layouts.site>
