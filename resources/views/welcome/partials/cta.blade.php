<section id="cta" class="scroll-mt-28 lg:scroll-mt-32 border-t border-slate-200 bg-white px-6 py-24 lg:px-8 dark:border-zinc-800 dark:bg-zinc-900">
    @php
        $hasLogin = Route::has('login');
        $hasRegister = Route::has('register');
    @endphp

    <div class="mx-auto max-w-4xl text-center">
        <div class="relative isolate overflow-hidden bg-slate-900 px-6 py-24 shadow-2xl sm:rounded-3xl sm:px-16 dark:bg-zinc-800">
            <h2 class="mx-auto max-w-2xl text-3xl font-bold tracking-tight text-white sm:text-4xl">Ready to shape the future of Ontario power of sale intelligence?</h2>
            <p class="mx-auto mt-6 max-w-xl text-lg leading-8 text-slate-300">Become an early design partner to influence workflows, data coverage, and automated diligence before launch.</p>
            <div class="mt-10 flex items-center justify-center gap-x-6">
                @if ($hasRegister)
                    <a href="{{ route('register') }}" class="rounded-md bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-sm hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Join the waitlist</a>
                @endif
                @if ($hasLogin)
                    <a href="{{ route('login') }}" class="text-sm font-semibold leading-6 text-white">Partner login <span aria-hidden="true">→</span></a>
                @endif
                @if (! $hasRegister && ! $hasLogin)
                    <a href="mailto:hello@powerofsale.ca" class="rounded-md bg-white px-3.5 py-2.5 text-sm font-semibold text-slate-900 shadow-sm hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white">Contact us</a>
                @endif
            </div>
            <svg viewBox="0 0 1024 1024" class="absolute left-1/2 top-1/2 -z-10 h-[64rem] w-[64rem] -translate-x-1/2 [mask-image:radial-gradient(closest-side,white,transparent)]" aria-hidden="true">
                <circle cx="512" cy="512" r="512" fill="url(#827591b1-ce8c-4110-b064-7cb85a0b1217)" fill-opacity="0.7" />
                <defs>
                    <radialGradient id="827591b1-ce8c-4110-b064-7cb85a0b1217">
                        <stop stop-color="#7775D6" />
                        <stop offset="1" stop-color="#E935C1" />
                    </radialGradient>
                </defs>
            </svg>
        </div>
    </div>
</section>
