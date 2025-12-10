<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site', ['title' => 'Frequently Asked Questions'])] class extends Component {
    //
}; ?>

<section class="mx-auto max-w-4xl px-6 py-12 lg:px-8">
    <div class="space-y-2">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">
            {{ __('Frequently Asked Questions') }}
        </h1>
        <p class="text-sm text-slate-600 dark:text-zinc-400">
            {{ __('Common questions about power of sale properties and how our platform works.') }}
        </p>
    </div>

    <div class="mt-8 space-y-6">
        <!-- What is Power of Sale -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
            <flux:heading size="sm" class="text-slate-900 dark:text-white">
                {{ __('What is a Power of Sale property?') }}
            </flux:heading>
            <flux:text class="mt-3 text-slate-600 dark:text-zinc-300">
                {{ __('A Power of Sale is a legal process that allows a lender to sell a property when the borrower has defaulted on their mortgage. Unlike foreclosure, Power of Sale in Ontario allows lenders to sell the property without going through the court system, making the process faster. The property is sold to recover the outstanding mortgage balance, and any surplus funds are returned to the borrower.') }}
            </flux:text>
        </div>

        <!-- How is this different from foreclosure -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
            <flux:heading size="sm" class="text-slate-900 dark:text-white">
                {{ __('How is Power of Sale different from foreclosure?') }}
            </flux:heading>
            <flux:text class="mt-3 text-slate-600 dark:text-zinc-300">
                {{ __('In a foreclosure, the lender takes ownership of the property through the courts and can keep any profit from the sale. In a Power of Sale, the lender sells the property on behalf of the borrower—the lender does not take ownership, and must return any surplus funds to the borrower after the mortgage debt is satisfied. Power of Sale is generally faster and less costly than foreclosure.') }}
            </flux:text>
        </div>

        <!-- Are these properties cheaper -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
            <flux:heading size="sm" class="text-slate-900 dark:text-white">
                {{ __('Are Power of Sale properties cheaper than market value?') }}
            </flux:heading>
            <flux:text class="mt-3 text-slate-600 dark:text-zinc-300">
                {{ __('Not necessarily. While some Power of Sale properties may be priced competitively to facilitate a quick sale, lenders are legally required to sell at fair market value. Properties may appear to be deals due to the circumstances of the sale, but buyers should conduct proper due diligence and work with qualified professionals before making an offer.') }}
            </flux:text>
        </div>

        <!-- Can anyone buy -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
            <flux:heading size="sm" class="text-slate-900 dark:text-white">
                {{ __('Can anyone buy a Power of Sale property?') }}
            </flux:heading>
            <flux:text class="mt-3 text-slate-600 dark:text-zinc-300">
                {{ __('Yes, anyone can purchase a Power of Sale property. The buying process is similar to purchasing any other property—you make an offer, negotiate terms, and complete the transaction. However, it\'s important to note that Power of Sale properties are typically sold "as is," meaning the lender may not make repairs or provide the same warranties as a traditional seller.') }}
            </flux:text>
        </div>

        <!-- Risks -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
            <flux:heading size="sm" class="text-slate-900 dark:text-white">
                {{ __('What are the risks of buying a Power of Sale property?') }}
            </flux:heading>
            <flux:text class="mt-3 text-slate-600 dark:text-zinc-300">
                {{ __('Key risks include: the property is sold "as is" with limited seller disclosures, there may be unknown defects or deferred maintenance, outstanding liens or property tax arrears may exist, and the previous owner may still be occupying the property. We strongly recommend getting a home inspection, title search, and consulting with a real estate lawyer before purchasing.') }}
            </flux:text>
        </div>

        <!-- How our platform works -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
            <flux:heading size="sm" class="text-slate-900 dark:text-white">
                {{ __('How does this platform work?') }}
            </flux:heading>
            <flux:text class="mt-3 text-slate-600 dark:text-zinc-300">
                {{ __('Our platform aggregates Power of Sale listings from multiple MLS data sources across Ontario. We automatically identify properties that indicate they are being sold under power of sale based on listing remarks and other indicators. You can browse listings, set up saved searches, and receive notifications when new properties matching your criteria become available.') }}
            </flux:text>
        </div>

        <!-- Saved searches -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
            <flux:heading size="sm" class="text-slate-900 dark:text-white">
                {{ __('How do saved searches work?') }}
            </flux:heading>
            <flux:text class="mt-3 text-slate-600 dark:text-zinc-300">
                {{ __('Once you create an account, you can save search criteria including location, price range, property type, and other filters. When new listings match your criteria, we\'ll notify you via email based on your notification preferences—instantly, daily, or weekly. You can manage your saved searches and notification settings from your account dashboard.') }}
            </flux:text>
        </div>

        <!-- Data accuracy -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
            <flux:heading size="sm" class="text-slate-900 dark:text-white">
                {{ __('How accurate is the listing data?') }}
            </flux:heading>
            <flux:text class="mt-3 text-slate-600 dark:text-zinc-300">
                {{ __('Our data comes directly from MLS feeds and is updated regularly throughout the day. While we strive for accuracy, listing information is provided by real estate professionals and may change without notice. Always verify current listing status and details with the listing agent or your own real estate professional before making any decisions.') }}
            </flux:text>
        </div>

        <!-- Contact agent -->
        <div class="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-900/70">
            <flux:heading size="sm" class="text-slate-900 dark:text-white">
                {{ __('How do I make an offer on a property?') }}
            </flux:heading>
            <flux:text class="mt-3 text-slate-600 dark:text-zinc-300">
                {{ __('To make an offer, you\'ll need to work with a licensed real estate agent. You can contact the listing agent directly or work with your own buyer\'s agent. We recommend working with an agent who has experience with Power of Sale transactions, as they can help you navigate the unique aspects of these purchases.') }}
            </flux:text>
        </div>
    </div>

    <div class="mt-12 rounded-2xl border border-blue-200 bg-blue-50 p-6 dark:border-blue-900 dark:bg-blue-950/50">
        <flux:heading size="sm" class="text-blue-900 dark:text-blue-100">
            {{ __('Have more questions?') }}
        </flux:heading>
        <flux:text class="mt-2 text-blue-800 dark:text-blue-200">
            {{ __('If you couldn\'t find the answer you were looking for, please reach out to us through our contact page.') }}
        </flux:text>
        <flux:button
            variant="primary"
            :href="route('pages.contact')"
            wire:navigate
            class="mt-4"
        >
            {{ __('Contact us') }}
        </flux:button>
    </div>
</section>
