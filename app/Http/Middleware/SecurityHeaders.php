<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Add security headers to responses.
 *
 * @see https://owasp.org/www-project-secure-headers/
 */
class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Only add headers to HTML responses
        if (! $this->isHtmlResponse($response)) {
            return $response;
        }

        // X-Frame-Options: Prevent clickjacking
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        // X-Content-Type-Options: Prevent MIME-sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // X-XSS-Protection: Enable XSS filter (legacy browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');

        // Referrer-Policy: Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // Permissions-Policy: Disable sensitive browser features
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Content-Security-Policy (CSP)
        if (config('app.env') === 'production') {
            $response->headers->set('Content-Security-Policy', $this->productionCsp());
        }

        // Strict-Transport-Security (HSTS) - production only
        if (config('app.env') === 'production' && $request->secure()) {
            // 1 year, include subdomains
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }

    /**
     * Check if response is HTML.
     */
    private function isHtmlResponse(Response $response): bool
    {
        $contentType = $response->headers->get('Content-Type', '');

        return str_contains($contentType, 'text/html') || $contentType === '';
    }

    /**
     * Get production Content-Security-Policy.
     */
    private function productionCsp(): string
    {
        return implode('; ', [
            // Default: only same origin
            "default-src 'self'",

            // Scripts: same origin + inline for Livewire/Alpine
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https://www.googletagmanager.com https://www.google-analytics.com",

            // Styles: same origin + inline for Tailwind
            "style-src 'self' 'unsafe-inline'",

            // Images: same origin + data URIs + external image hosts
            "img-src 'self' data: blob: https://*.ampre.ca https://*.googleapis.com https://www.google-analytics.com",

            // Fonts: same origin
            "font-src 'self'",

            // Connections: same origin + API endpoints
            "connect-src 'self' https://*.ampre.ca https://www.google-analytics.com https://region1.google-analytics.com",

            // Frames: same origin
            "frame-src 'self'",

            // Objects: none (disable plugins)
            "object-src 'none'",

            // Base URI: same origin
            "base-uri 'self'",

            // Form actions: same origin
            "form-action 'self'",

            // Upgrade insecure requests in production
            'upgrade-insecure-requests',
        ]);
    }
}
