<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response (registered globally so error pages get them too).
 *
 * CSP exceptions (see STOREFRONT_PRODUCTION_READINESS_PLAN.md → A4):
 *  - script-src 'unsafe-inline': the design's scripts build markup with inline handlers (onclick=, onerror=) and the
 *    layout has one inline bootstrap script. Nonces cannot cover inline handlers, and adding a nonce would make
 *    browsers ignore 'unsafe-inline' and break them. External script hosts are still restricted.
 *  - code.jquery.com / cdn.jsdelivr.net: jQuery + Select2 on /bulk-order.
 *  - style-src 'unsafe-inline': inline style="" attributes throughout the design.
 *  - img-src https: product/category/hero images are admin-entered URLs (Cloudinary, Unsplash, others).
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('Content-Security-Policy', implode('; ', [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://code.jquery.com https://cdn.jsdelivr.net",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net",
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "connect-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
        ]), false);
        $h->set('X-Frame-Options', 'SAMEORIGIN', false);
        $h->set('X-Content-Type-Options', 'nosniff', false);
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin', false);
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), usb=()', false);
        $h->set('Cross-Origin-Opener-Policy', 'same-origin-allow-popups', false);

        // HSTS only over real HTTPS in production (never on local HTTP development).
        if ($request->isSecure() && app()->environment('production')) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains', false);
        }

        return $response;
    }
}
