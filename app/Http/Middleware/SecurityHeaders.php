<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers on every response (registered globally so error pages get them too).
 *
 * CSP (STOREFRONT_PRODUCTION_READINESS_PLAN.md → A4/A12):
 *  - script-src 'self' only: no inline scripts or inline event handlers anywhere (page data is JSON in
 *    <script type="application/json">, handlers are addEventListener / data-action delegation in data.js).
 *  - /bulk-order alone may load jQuery 3.7.1 + Select2 4.1.0-rc.0, pinned to the exact files (SRI in the view).
 *  - style-src 'unsafe-inline': kept on purpose. The design uses inline style="" attributes throughout (hundreds,
 *    including ones the scripts generate); style injection cannot run script under this policy.
 *  - img-src https: product/category/hero images are admin-entered URLs (Cloudinary, Unsplash, others).
 */
class SecurityHeaders
{
    public const JQUERY = 'https://code.jquery.com/jquery-3.7.1.min.js';

    public const SELECT2_JS = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';

    public const SELECT2_CSS = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;
        $bulk = $request->is('bulk-order');

        $h->set('Content-Security-Policy', implode('; ', array_filter([
            "default-src 'self'",
            "script-src 'self'".($bulk ? ' '.self::JQUERY.' '.self::SELECT2_JS : ''),
            "script-src-attr 'none'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com".($bulk ? ' '.self::SELECT2_CSS : ''),
            "font-src 'self' data: https://fonts.gstatic.com",
            "img-src 'self' data: blob: https:",
            "connect-src 'self'",
            "frame-src 'none'",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'self'",
            app()->environment('production') ? 'upgrade-insecure-requests' : null,
        ])), false);
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
