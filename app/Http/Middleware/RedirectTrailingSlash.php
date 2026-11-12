<?php

namespace App\Http\Middleware;

use App\Support\Seo;
use Closure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One URL per page: GET/HEAD requests for "/path/" get a 301 to "/path" (query string kept), whatever web server is in
 * front. public/.htaccess does the same on Apache; this covers Nginx and others. The target is always a same-site path:
 * leading slashes/backslashes are collapsed, so "//evil.example/" can never become a protocol-relative redirect.
 * Other methods are left alone (a redirect would drop a POST body).
 */
class RedirectTrailingSlash
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        if ($path === '/' || ! str_ends_with($path, '/') || ! in_array($request->getMethod(), ['GET', 'HEAD'], true)) {
            return $next($request);
        }

        $clean = '/'.trim(str_replace('\\', '/', $path), '/');
        $query = $request->getQueryString();

        // Absolute from APP_URL (like canonical URLs), never from the client-supplied Host header.
        return new RedirectResponse(Seo::abs($clean.($query !== null && $query !== '' ? '?'.$query : '')), 301);
    }
}
