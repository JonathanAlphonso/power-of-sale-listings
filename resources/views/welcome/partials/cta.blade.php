<section id="cta" class="scroll-mt-28 lg:scroll-mt-32 border-t border-slate-200 bg-gradient-to-r from-[#fff5f5] via-white to-[#f3f7ff] px-6 py-24 lg:px-8">
    @php
        $hasLogin = Route::has('login');
        $hasRegister = Route::has('register');
    @endphp

    <x-ui.card variant="red" padding="p-12" class="mx-auto max-w-4xl text-center shadow-[0_25px_70px_rgba(217,4,41,0.12)]">
        <x-ui.section-badge variant="red">Partner Program</x-ui.section-badge>
        <h2 class="mt-4 text-3xl font-semibold text-slate-900 sm:text-4xl">Ready to shape the future of Ontario power of sale intelligence?</h2>
        <p class="mt-4 text-lg text-slate-600">Become an early design partner to influence workflows, data coverage, and automated diligence before launch.</p>
        <div class="mt-10 flex flex-wrap justify-center gap-4">
            @if ($hasRegister)
                <a href="{{ route('register') }}" class="rounded-full bg-[#d90429] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#b90322]">Join the waitlist</a>
            @endif
            @if ($hasLogin)
                <a href="{{ route('login') }}" class="rounded-full border border-slate-300 px-6 py-3 text-sm font-semibold text-slate-600 transition hover:border-[#d90429] hover:text-[#d90429]">Partner login</a>
            @endif
            @if (! $hasRegister && ! $hasLogin)
                <a href="mailto:hello@powerofsale.ca" class="rounded-full bg-[#d90429] px-6 py-3 text-sm font-semibold text-white transition hover:bg-[#b90322]">Contact us</a>
            @endif
        </div>
    </x-ui.card>
</section>
