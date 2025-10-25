<x-layouts.site :title="config('app.name', 'Power of Sale Ontario')">
    @php
        $hasLogin = Route::has('login');
        $hasRegister = Route::has('register');
    @endphp

    <div class="relative overflow-hidden bg-[#f6f8fb] text-slate-800">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute -left-40 top-[-18rem] h-[28rem] w-[28rem] rounded-full bg-[#ffeadb]/60 blur-3xl"></div>
            <div class="absolute bottom-[-16rem] right-[-10rem] h-[30rem] w-[30rem] rounded-full bg-[#d5e6ff]/60 blur-3xl"></div>
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(220,38,38,0.08),_transparent_60%)]"></div>
        </div>

        <div class="relative z-10">
            @include('welcome.partials.hero')
            @include('welcome.partials.product')
            @include('welcome.partials.roadmap')
            @include('welcome.partials.pipeline')
            @include('welcome.partials.database')
            @include('welcome.partials.listings-demo')
            @include('welcome.partials.tech')
            @include('welcome.partials.cta')
        </div>
    </div>
</x-layouts.site>
