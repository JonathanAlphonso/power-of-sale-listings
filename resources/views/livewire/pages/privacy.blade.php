<?php

use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('components.layouts.site', ['title' => 'Privacy Policy'])] class extends Component {
    //
}; ?>

<section class="mx-auto max-w-4xl px-6 py-12 lg:px-8">
    <div class="space-y-2">
        <h1 class="text-3xl font-semibold tracking-tight text-slate-900 dark:text-zinc-100">
            {{ __('Privacy Policy') }}
        </h1>
        <p class="text-sm text-slate-600 dark:text-zinc-400">
            {{ __('Last updated: :date', ['date' => now()->format('F j, Y')]) }}
        </p>
    </div>

    <div class="mt-8 prose prose-slate max-w-none dark:prose-invert prose-headings:font-semibold prose-headings:tracking-tight prose-p:text-slate-600 dark:prose-p:text-zinc-300 prose-li:text-slate-600 dark:prose-li:text-zinc-300">
        <h2>{{ __('Introduction') }}</h2>
        <p>
            {{ __('This Privacy Policy explains how Power of Sales Ontario ("we", "us", or "our") collects, uses, discloses, and protects your personal information when you use our website and services. We are committed to protecting your privacy and ensuring that your personal information is handled responsibly.') }}
        </p>

        <h2>{{ __('Information We Collect') }}</h2>
        <h3>{{ __('Information You Provide') }}</h3>
        <p>{{ __('We collect information you voluntarily provide when you:') }}</p>
        <ul>
            <li>{{ __('Create an account (name, email address, password)') }}</li>
            <li>{{ __('Set up saved searches and notification preferences') }}</li>
            <li>{{ __('Contact us through our contact form') }}</li>
            <li>{{ __('Communicate with us via email') }}</li>
        </ul>

        <h3>{{ __('Information Collected Automatically') }}</h3>
        <p>{{ __('When you visit our website, we may automatically collect:') }}</p>
        <ul>
            <li>{{ __('Device information (browser type, operating system)') }}</li>
            <li>{{ __('IP address and general location data') }}</li>
            <li>{{ __('Pages visited and time spent on our website') }}</li>
            <li>{{ __('Referring website addresses') }}</li>
        </ul>

        <h2>{{ __('How We Use Your Information') }}</h2>
        <p>{{ __('We use the collected information to:') }}</p>
        <ul>
            <li>{{ __('Provide and maintain our services') }}</li>
            <li>{{ __('Send you notifications about new listings matching your saved searches') }}</li>
            <li>{{ __('Respond to your inquiries and provide customer support') }}</li>
            <li>{{ __('Improve our website and services') }}</li>
            <li>{{ __('Detect and prevent fraud or abuse') }}</li>
            <li>{{ __('Comply with legal obligations') }}</li>
        </ul>

        <h2>{{ __('Cookies and Tracking Technologies') }}</h2>
        <p>
            {{ __('We use cookies and similar tracking technologies to enhance your experience on our website. Cookies are small text files stored on your device that help us remember your preferences, understand how you use our site, and improve our services.') }}
        </p>
        <p>{{ __('You can control cookie settings through your browser. However, disabling certain cookies may limit your ability to use some features of our website.') }}</p>

        <h2>{{ __('Third-Party Services') }}</h2>
        <p>{{ __('We may use third-party services for:') }}</p>
        <ul>
            <li>{{ __('Analytics (Google Analytics) to understand website usage') }}</li>
            <li>{{ __('Email delivery services to send notifications') }}</li>
            <li>{{ __('Hosting and infrastructure providers') }}</li>
        </ul>
        <p>{{ __('These third parties have their own privacy policies governing how they use your information.') }}</p>

        <h2>{{ __('Data Sharing and Disclosure') }}</h2>
        <p>{{ __('We do not sell your personal information. We may share your information:') }}</p>
        <ul>
            <li>{{ __('With service providers who assist in operating our website') }}</li>
            <li>{{ __('When required by law or to protect our legal rights') }}</li>
            <li>{{ __('In connection with a business transfer, merger, or acquisition') }}</li>
            <li>{{ __('With your consent') }}</li>
        </ul>

        <h2>{{ __('Data Retention') }}</h2>
        <p>
            {{ __('We retain your personal information for as long as your account is active or as needed to provide you services. We may retain certain information as required by law or for legitimate business purposes.') }}
        </p>
        <p>{{ __('You may request deletion of your account and associated data at any time by contacting us.') }}</p>

        <h2>{{ __('Data Security') }}</h2>
        <p>
            {{ __('We implement appropriate technical and organizational measures to protect your personal information against unauthorized access, alteration, disclosure, or destruction. However, no method of transmission over the internet is 100% secure.') }}
        </p>

        <h2>{{ __('Your Rights') }}</h2>
        <p>{{ __('Depending on your jurisdiction, you may have the right to:') }}</p>
        <ul>
            <li>{{ __('Access the personal information we hold about you') }}</li>
            <li>{{ __('Request correction of inaccurate information') }}</li>
            <li>{{ __('Request deletion of your personal information') }}</li>
            <li>{{ __('Object to or restrict certain processing') }}</li>
            <li>{{ __('Data portability') }}</li>
            <li>{{ __('Withdraw consent where processing is based on consent') }}</li>
        </ul>
        <p>{{ __('To exercise these rights, please contact us using the information provided below.') }}</p>

        <h2>{{ __('Children\'s Privacy') }}</h2>
        <p>
            {{ __('Our services are not intended for individuals under 18 years of age. We do not knowingly collect personal information from children. If we become aware that we have collected personal information from a child, we will take steps to delete it.') }}
        </p>

        <h2>{{ __('Changes to This Policy') }}</h2>
        <p>
            {{ __('We may update this Privacy Policy from time to time. We will notify you of any material changes by posting the new policy on this page and updating the "Last updated" date. We encourage you to review this policy periodically.') }}
        </p>

        <h2>{{ __('Contact Us') }}</h2>
        <p>{{ __('If you have questions about this Privacy Policy or our privacy practices, please contact us through our contact page.') }}</p>
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
