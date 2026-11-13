<?php

namespace App\Http\Middleware;

use App\Support\CustomerSession;
use App\Support\EmailVerification;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for customer-only endpoints (alias "customer"). ValidateCustomerSession (global, runs first) has already
 * removed customer_id from the session when the account is gone, inactive, or its password changed; this only checks
 * that a customer is still signed in. Controllers still read the id via Controller::customerId() as a second layer.
 */
class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if ((int) $request->session()->get(CustomerSession::ID) > 0) {
            return $next($request);
        }
        // Registered/signed in but email not verified yet: the only way forward is the verification page.
        if (EmailVerification::pendingId($request) > 0) {
            if ($request->expectsJson() || ! $request->isMethod('GET')) {
                return response()->json(['message' => 'Please verify your email address first.', 'verificationRequired' => true, 'redirect' => '/verify-email'], 403);
            }

            return redirect('/verify-email');
        }

        if ($request->expectsJson() || ! $request->isMethod('GET')) {
            return response()->json(['message' => 'Your session has ended. Please sign in again.'], 401);
        }

        // Same sign-in prompt + return path the checkout page has always used (e.g. /?signin=/checkout).
        return redirect('/?signin=/'.ltrim($request->path(), '/'));
    }
}
