<?php

namespace App\Listeners;

use App\Events\PasswordResetCodeIssued;
use App\Mail\PasswordResetCodeMail;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

/** Emails the "forgot password" reset code off the request (same queue, encryption and retries as verification). */
class SendPasswordResetCodeEmail implements ShouldBeEncrypted, ShouldQueue
{
    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    public function viaConnection(): string
    {
        return (string) config('storefront.auth_mail_queue');
    }

    public function handle(PasswordResetCodeIssued $event): void
    {
        Mail::to($event->email)->send(new PasswordResetCodeMail($event->code, $event->firstName, $event->minutes));
    }

    public function failed(PasswordResetCodeIssued $event, ?Throwable $e = null): void
    {
        // The reset page offers "Resend code" (still capped per address); log without the address or code.
        Log::warning('Password reset email could not be delivered', ['customer_id' => $event->customerId, 'error' => $e ? $e::class : null]);
    }
}
