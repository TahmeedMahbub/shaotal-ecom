<?php

namespace App\Support;

use App\Events\PasswordResetCodeIssued;
use App\Events\VerificationCodeIssued;
use App\Listeners\SendPasswordResetCodeEmail;
use App\Listeners\SendVerificationCodeEmail;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Dispatches the one-time-code events. Their listeners are queued on storefront.auth_mail_queue
 * (AUTH_MAIL_QUEUE_CONNECTION):
 *   - "deferred" (default): no worker needed; the email is sent right after the response, so the browser never waits.
 *   - "database" / "redis": a real queue; needs its table/server and a running `php artisan queue:work`.
 *   - "failover": tries the real queue, then "deferred" (config/queue.php).
 * If pushing to the queue itself throws (e.g. no jobs table), the email is still sent after the response and the
 * error is reported: a code is never silently lost.
 */
class AuthMail
{
    private const LISTENERS = [
        VerificationCodeIssued::class => SendVerificationCodeEmail::class,
        PasswordResetCodeIssued::class => SendPasswordResetCodeEmail::class,
    ];

    public static function dispatch(VerificationCodeIssued|PasswordResetCodeIssued $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            report($e);
            defer(fn () => self::deliverNow($event));
        }
    }

    /** Fallback: run the listener in this process (after the response), with its failure handling. */
    public static function deliverNow(VerificationCodeIssued|PasswordResetCodeIssued $event): void
    {
        $listener = app(self::LISTENERS[$event::class]);
        try {
            $listener->handle($event);
        } catch (Throwable $e) {
            report($e);
            $listener->failed($event, $e);
        }
    }
}
