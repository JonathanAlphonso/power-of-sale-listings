<section id="cta" class="border-t border-slate-200 bg-gradient-to-r from-[#fff5f5] via-white to-[#f3f7ff] px-6 py-24 lg:px-8 scroll-mt-32">
    <div class="mx-auto max-w-4xl rounded-3xl border border-red-100 bg-white p-12 text-center shadow-[0_25px_70px_rgba(217,4,41,0.12)]">
        <span class="inline-flex items-center gap-2 rounded-full border border-red-100 bg-white px-3 py-1 text-xs font-semibold uppercase tracking-[0.35em] text-red-500">Partner Program</span>
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
    </div>
</section>
