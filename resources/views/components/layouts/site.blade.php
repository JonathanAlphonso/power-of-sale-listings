<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    @include('partials.head')
</head>

<body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-zinc-950 dark:text-zinc-100">
    <div class="flex min-h-screen flex-col">
        <flux:header container
            class="sticky top-0 z-[100] border-b border-slate-200/80 bg-white/85 backdrop-blur supports-[backdrop-filter]:bg-white/70 dark:border-zinc-800/80 dark:bg-zinc-900/80">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-3-center-left" inset="left" />

            <flux:brand :href="route('home')" :name="config('app.name')" class="hidden items-center gap-2 lg:flex">
                <x-slot name="logo">
                    <x-app-logo-icon class="h-8 w-8 text-emerald-500 dark:text-emerald-400" />
                </x-slot>
            </flux:brand>

            <a href="{{ route('home') }}" class="flex items-center gap-2 lg:hidden" wire:navigate>
                <x-app-logo-icon class="h-8 w-8 text-emerald-500 dark:text-emerald-400" />
                <span class="text-lg font-semibold tracking-tight">{{ config('app.name') }}</span>
            </a>

            @php
                $homeUrl = route('home');
            @endphp

            <flux:navbar class="-mb-px hidden gap-2 lg:flex">
                <flux:navbar.item :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                    {{ __('Home') }}</flux:navbar.item>
                <flux:navbar.item :href="route('listings.index')" :current="request()->routeIs('listings.index')"
                    wire:navigate>{{ __('Listings') }}</flux:navbar.item>
                <flux:navbar.item href="{{ $homeUrl }}#product" :current="false">{{ __('Platform') }}</flux:navbar.item>
                <flux:navbar.item href="{{ $homeUrl }}#pipeline" :current="false">{{ __('Pipeline') }}
                </flux:navbar.item>
                <flux:navbar.item href="{{ $homeUrl }}#roadmap" :current="false">{{ __('Roadmap') }}</flux:navbar.item>
                <flux:navbar.item href="{{ $homeUrl }}#cta" :current="false">{{ __('Contact') }}</flux:navbar.item>
            </flux:navbar>

            <flux:spacer />

            @auth
                @if (auth()->user()->isAdmin())
                    <flux:navbar class="hidden items-center gap-2 lg:flex">
                        <flux:navbar.item :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:navbar.item>
                    </flux:navbar>
                @endif

                <flux:dropdown position="top" align="end">
                    <flux:profile class="cursor-pointer" :name="auth()->user()->name" :initials="auth()->user()->initials()"
                        icon:trailing="chevrons-up-down" />

                    <flux:menu class="w-[220px]">
                        <div class="px-3 py-2">
                            <p class="text-sm font-semibold">{{ auth()->user()->name }}</p>
                            <p class="text-xs text-slate-500 dark:text-zinc-400">{{ auth()->user()->email }}</p>
                        </div>

                        <flux:menu.separator />

                        <flux:menu.item :href="route('favorites.index')" icon="heart" wire:navigate>{{ __('My Favorites') }}
                        </flux:menu.item>
                        <flux:menu.item :href="route('recently-viewed.index')" icon="clock" wire:navigate>{{ __('Recently Viewed') }}
                        </flux:menu.item>
                        <flux:menu.item :href="route('saved-searches.index')" icon="bell" wire:navigate>{{ __('Saved Searches') }}
                        </flux:menu.item>

                        <flux:menu.separator />

                        <flux:menu.item :href="route('profile.edit')" icon="cog-6-tooth" wire:navigate>{{ __('Settings') }}
                        </flux:menu.item>

                        <flux:menu.separator />

                        <form method="POST" action="{{ route('logout') }}" class="w-full">
                            @csrf
                            <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">
                                {{ __('Log Out') }}
                            </flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @else
                <div class="hidden items-center gap-2 lg:flex">
                    <flux:button variant="ghost" :href="route('login')" wire:navigate>{{ __('Sign in') }}</flux:button>
                    @if (Route::has('register'))
                        <flux:button variant="primary" :href="route('register')" wire:navigate>{{ __('Create account') }}
                        </flux:button>
                    @endif
                </div>
            @endauth
        </flux:header>

        <flux:sidebar stashable sticky
            class="lg:hidden border-r border-slate-200 bg-white dark:border-zinc-800 dark:bg-zinc-900">
            <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

            <flux:sidebar.brand :href="route('home')" :name="config('app.name')" wire:navigate>
                <x-slot name="logo">
                    <x-app-logo-icon class="h-8 w-8 text-emerald-500 dark:text-emerald-400" />
                </x-slot>
            </flux:sidebar.brand>

            <flux:sidebar.nav>
                <flux:sidebar.item :href="route('home')" :current="request()->routeIs('home')" wire:navigate>
                    {{ __('Home') }}
                </flux:sidebar.item>
                <flux:sidebar.item :href="route('listings.index')" :current="request()->routeIs('listings.index')"
                    wire:navigate>
                    {{ __('Listings') }}
                </flux:sidebar.item>
                <flux:sidebar.item href="{{ $homeUrl }}#product" :current="false">
                    {{ __('Platform') }}
                </flux:sidebar.item>
                <flux:sidebar.item href="{{ $homeUrl }}#pipeline" :current="false">
                    {{ __('Pipeline') }}
                </flux:sidebar.item>
                <flux:sidebar.item href="{{ $homeUrl }}#roadmap" :current="false">
                    {{ __('Roadmap') }}
                </flux:sidebar.item>
                <flux:sidebar.item href="{{ $homeUrl }}#cta" :current="false">
                    {{ __('Contact') }}
                </flux:sidebar.item>
            </flux:sidebar.nav>

            <flux:sidebar.spacer />

            <flux:sidebar.nav>
                @auth
                    @if (auth()->user()->isAdmin())
                        <flux:sidebar.item :href="route('dashboard')" :current="request()->routeIs('dashboard')" wire:navigate>
                            {{ __('Dashboard') }}
                        </flux:sidebar.item>
                    @endif
                    <flux:sidebar.item :href="route('favorites.index')" :current="request()->routeIs('favorites.index')" wire:navigate>{{ __('My Favorites') }}</flux:sidebar.item>
                    <flux:sidebar.item :href="route('recently-viewed.index')" :current="request()->routeIs('recently-viewed.index')" wire:navigate>{{ __('Recently Viewed') }}</flux:sidebar.item>
                    <flux:sidebar.item :href="route('saved-searches.index')" :current="request()->routeIs('saved-searches.*')" wire:navigate>{{ __('Saved Searches') }}</flux:sidebar.item>
                    <flux:sidebar.item :href="route('profile.edit')" wire:navigate>{{ __('Settings') }}</flux:sidebar.item>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <flux:sidebar.item as="button" type="submit">
                            {{ __('Log Out') }}
                        </flux:sidebar.item>
                    </form>
                @else
                    <flux:sidebar.item :href="route('login')" wire:navigate>
                        {{ __('Sign in') }}
                    </flux:sidebar.item>
                    @if (Route::has('register'))
                        <flux:sidebar.item :href="route('register')" wire:navigate>
                            {{ __('Create account') }}
                        </flux:sidebar.item>
                    @endif
                @endauth
            </flux:sidebar.nav>
        </flux:sidebar>

        <main class="flex-1">
            {{ $slot }}
        </main>

        <footer class="border-t border-slate-200 bg-white/90 dark:border-zinc-800 dark:bg-zinc-900/90">
            <div
                class="mx-auto flex max-w-6xl flex-col gap-6 px-6 py-10 sm:flex-row sm:items-center sm:justify-between lg:px-8">
                <div class="flex items-center gap-3">
                    <x-app-logo-icon class="h-8 w-8 text-emerald-500 dark:text-emerald-400" />
                    <div>
                        <p class="text-sm font-semibold">{{ config('app.name') }}</p>
                        <p class="text-xs text-slate-500 dark:text-zinc-400">
                            {{ __('Ontario foreclosure intelligence, powered by Laravel & Livewire.') }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 text-sm text-slate-500 dark:text-zinc-400">
                    <a href="{{ route('home') . '#roadmap' }}"
                        class="hover:text-slate-900 dark:hover:text-zinc-200">{{ __('Roadmap') }}</a>
                    <a href="{{ route('home') . '#cta' }}"
                        class="hover:text-slate-900 dark:hover:text-zinc-200">{{ __('Contact') }}</a>
                    <a href="https://github.com/JonathanAlphonso/power-of-sale-listings" target="_blank"
                        class="hover:text-slate-900 dark:hover:text-zinc-200">
                        {{ __('Repository') }}
                    </a>
                </div>
                <p class="text-xs text-slate-400 dark:text-zinc-500">
                    &copy; {{ now()->year }} {{ config('app.name') }}. {{ __('All rights reserved.') }}
                </p>
            </div>
        </footer>
    </div>

    @fluxScripts
</body>

</html>