<x-layouts.site :title="config('app.name', 'Power of Sale Ontario')">
    @php
        $hasLogin = Route::has('login');
        $hasRegister = Route::has('register');
    @endphp

    <main class="bg-[#f6f8fb] text-slate-800">
        @include('welcome.partials.hero')
        @include('welcome.partials.product')
        @include('welcome.partials.roadmap')
        @include('welcome.partials.pipeline')
        @include('welcome.partials.database')
        @include('welcome.partials.listings-demo')
        @include('welcome.partials.tech')
        @include('welcome.partials.cta')
    </main>
</x-layouts.site>
