<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site', ['title' => 'Terms of Service'])] class extends Component {
    //
}; ?>

<section class="mx-auto max-w-4xl px-6 py-12 lg:px-8">
    <div class="space-y-2">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">
            {{ __('Terms of Service') }}
        </h1>
        <p class="text-sm text-slate-600 dark:text-zinc-400">
            {{ __('Last updated: :date', ['date' => now()->format('F j, Y')]) }}
        </p>
    </div>

    <div class="mt-8 prose prose-slate max-w-none dark:prose-invert prose-headings:font-semibold prose-headings:tracking-tight prose-p:text-slate-600 dark:prose-p:text-zinc-300 prose-li:text-slate-600 dark:prose-li:text-zinc-300">
        <h2>{{ __('Acceptance of Terms') }}</h2>
        <p>
            {{ __('By accessing or using Power of Sales Ontario ("the Service"), you agree to be bound by these Terms of Service ("Terms"). If you do not agree to these Terms, please do not use the Service.') }}
        </p>

        <h2>{{ __('Description of Service') }}</h2>
        <p>
            {{ __('Power of Sales Ontario is an informational platform that aggregates real estate listings identified as power of sale properties from MLS data sources across Ontario. The Service allows users to browse listings, create saved searches, and receive notifications about new listings matching their criteria.') }}
        </p>

        <h2>{{ __('User Accounts') }}</h2>
        <p>{{ __('To access certain features of the Service, you must create an account. You agree to:') }}</p>
        <ul>
            <li>{{ __('Provide accurate and complete registration information') }}</li>
            <li>{{ __('Maintain the security of your account credentials') }}</li>
            <li>{{ __('Promptly update your account information if it changes') }}</li>
            <li>{{ __('Accept responsibility for all activities under your account') }}</li>
            <li>{{ __('Notify us immediately of any unauthorized use of your account') }}</li>
        </ul>

        <h2>{{ __('Acceptable Use') }}</h2>
        <p>{{ __('You agree not to:') }}</p>
        <ul>
            <li>{{ __('Use the Service for any unlawful purpose') }}</li>
            <li>{{ __('Attempt to gain unauthorized access to the Service or related systems') }}</li>
            <li>{{ __('Interfere with or disrupt the Service or servers') }}</li>
            <li>{{ __('Scrape, harvest, or collect data from the Service without permission') }}</li>
            <li>{{ __('Use automated systems to access the Service in a manner that exceeds reasonable use') }}</li>
            <li>{{ __('Impersonate another person or entity') }}</li>
            <li>{{ __('Transmit viruses, malware, or other malicious code') }}</li>
            <li>{{ __('Violate any applicable laws or regulations') }}</li>
        </ul>

        <h2>{{ __('Listing Information Disclaimer') }}</h2>
        <p>
            {{ __('The listing information displayed on the Service is provided by third-party MLS data sources. We do not guarantee the accuracy, completeness, or timeliness of any listing information. Listings may be subject to prior sale, price changes, or withdrawal without notice.') }}
        </p>
        <p>
            {{ __('Our identification of properties as "power of sale" is based on automated analysis of listing remarks and other indicators. We cannot guarantee that all listed properties are actually power of sale properties, or that all power of sale properties in Ontario are included in our listings.') }}
        </p>
        <p>
            {{ __('You should independently verify all listing information before making any real estate decisions. We strongly recommend consulting with licensed real estate professionals and legal counsel.') }}
        </p>

        <h2>{{ __('No Real Estate Advice') }}</h2>
        <p>
            {{ __('The Service is provided for informational purposes only and does not constitute real estate, legal, financial, or investment advice. We are not a licensed real estate brokerage and do not represent buyers or sellers in real estate transactions.') }}
        </p>
        <p>{{ __('Any decisions you make based on information found on the Service are at your own risk.') }}</p>

        <h2>{{ __('Intellectual Property') }}</h2>
        <p>
            {{ __('The Service and its original content (excluding listing data from MLS sources), features, and functionality are owned by Power of Sales Ontario and are protected by copyright, trademark, and other intellectual property laws.') }}
        </p>
        <p>{{ __('MLS listing data is provided under license and remains the property of the respective MLS organizations and listing brokerages.') }}</p>

        <h2>{{ __('Limitation of Liability') }}</h2>
        <p>
            {{ __('To the maximum extent permitted by law, Power of Sales Ontario and its affiliates, officers, employees, agents, partners, and licensors shall not be liable for any indirect, incidental, special, consequential, or punitive damages, including without limitation, loss of profits, data, use, goodwill, or other intangible losses, resulting from:') }}
        </p>
        <ul>
            <li>{{ __('Your access to or use of (or inability to access or use) the Service') }}</li>
            <li>{{ __('Any conduct or content of any third party on the Service') }}</li>
            <li>{{ __('Any content obtained from the Service') }}</li>
            <li>{{ __('Unauthorized access, use, or alteration of your transmissions or content') }}</li>
        </ul>

        <h2>{{ __('Disclaimer of Warranties') }}</h2>
        <p>
            {{ __('The Service is provided on an "as is" and "as available" basis without warranties of any kind, whether express or implied, including but not limited to implied warranties of merchantability, fitness for a particular purpose, non-infringement, or course of performance.') }}
        </p>

        <h2>{{ __('Indemnification') }}</h2>
        <p>
            {{ __('You agree to indemnify, defend, and hold harmless Power of Sales Ontario and its affiliates, officers, directors, employees, agents, and licensors from any claims, damages, losses, or expenses (including reasonable attorney fees) arising out of or relating to your use of the Service or violation of these Terms.') }}
        </p>

        <h2>{{ __('Termination') }}</h2>
        <p>
            {{ __('We may terminate or suspend your account and access to the Service immediately, without prior notice or liability, for any reason, including if you breach these Terms. Upon termination, your right to use the Service will immediately cease.') }}
        </p>

        <h2>{{ __('Governing Law') }}</h2>
        <p>
            {{ __('These Terms shall be governed by and construed in accordance with the laws of the Province of Ontario and the federal laws of Canada applicable therein, without regard to conflict of law provisions.') }}
        </p>

        <h2>{{ __('Changes to Terms') }}</h2>
        <p>
            {{ __('We reserve the right to modify these Terms at any time. We will notify users of material changes by posting the updated Terms on the Service and updating the "Last updated" date. Your continued use of the Service after changes constitutes acceptance of the new Terms.') }}
        </p>

        <h2>{{ __('Severability') }}</h2>
        <p>
            {{ __('If any provision of these Terms is found to be unenforceable or invalid, that provision will be limited or eliminated to the minimum extent necessary, and the remaining provisions will remain in full force and effect.') }}
        </p>

        <h2>{{ __('Contact Information') }}</h2>
        <p>{{ __('If you have questions about these Terms, please contact us through our contact page.') }}</p>
    </div>

    <div class="mt-8">
        <flux:button
            variant="outline"
            :href="route('pages.contact')"
            wire:navigate
        >
            {{ __('Contact us with questions') }}
        </flux:button>
    </div>
</section>
