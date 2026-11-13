<?php

namespace App\Listeners;

use App\Events\VerificationCodeIssued;
use App\Mail\VerificationCodeMail;
use App\Support\EmailVerification;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Emails the registration verification code off the request: queued on storefront.auth_mail_queue (see
 * App\Support\AuthMail). Encrypted payload, 3 attempts with back-off; when it finally fails the resend cooldown is
 * lifted so "Resend code" works at once.
 */
class SendVerificationCodeEmail implements ShouldBeEncrypted, ShouldQueue
{
    public int $tries = 3;

    /** @return list<int> seconds before attempt 2 and 3 (worker queues; the deferred fallback runs once) */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function viaConnection(): string
    {
        return (string) config('storefront.auth_mail_queue');
    }

    public function handle(VerificationCodeIssued $event): void
    {
        Mail::to($event->email)->send(new VerificationCodeMail($event->code, $event->firstName, $event->minutes));
    }

    public function failed(VerificationCodeIssued $event, ?Throwable $e = null): void
    {
        EmailVerification::deliveryFailed($event->customerId);
        // Customer id and error class only: never the address, the code or the transport credentials.
        Log::warning('Verification email could not be delivered', ['customer_id' => $event->customerId, 'error' => $e ? $e::class : null]);
    }
}
