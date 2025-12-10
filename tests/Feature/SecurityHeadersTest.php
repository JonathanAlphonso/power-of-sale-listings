<?php

declare(strict_types=1);

it('adds security headers to html responses', function (): void {
    $response = $this->get('/');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('X-XSS-Protection', '1; mode=block');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    $response->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
});

it('does not add hsts in non-production environment', function (): void {
    $response = $this->get('/');

    // HSTS should not be present in testing environment
    expect($response->headers->has('Strict-Transport-Security'))->toBeFalse();
});

it('does not add csp in non-production environment', function (): void {
    $response = $this->get('/');

    // CSP is only added in production
    expect($response->headers->has('Content-Security-Policy'))->toBeFalse();
});
