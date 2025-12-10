<?php

use function Pest\Laravel\get;

test('faq page is accessible', function (): void {
    get(route('pages.faq'))
        ->assertOk()
        ->assertSee('Frequently Asked Questions')
        ->assertSee('What is a Power of Sale property?')
        ->assertSee('How is Power of Sale different from foreclosure?');
});

test('privacy page is accessible', function (): void {
    get(route('pages.privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee('Information We Collect')
        ->assertSee('How We Use Your Information');
});

test('terms page is accessible', function (): void {
    get(route('pages.terms'))
        ->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee('Acceptance of Terms')
        ->assertSee('Listing Information Disclaimer');
});

test('contact page is accessible', function (): void {
    get(route('pages.contact'))
        ->assertOk()
        ->assertSee('Contact Us')
        ->assertSee('Your name')
        ->assertSee('Email address')
        ->assertSee('Subject')
        ->assertSee('Message');
});
