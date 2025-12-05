<x-layouts.site :title="config('app.name', 'Power of Sale Listings')">
    <main>
        @include('welcome.partials.hero')
        @include('welcome.partials.product')
        @include('welcome.partials.roadmap')
        @include('welcome.partials.pipeline')
        @include('welcome.partials.database')
        @include('welcome.partials.listings-demo')
        @include('welcome.partials.idx-feed')
        @include('welcome.partials.tech')
        @include('welcome.partials.cta')
    </main>
</x-layouts.site>
