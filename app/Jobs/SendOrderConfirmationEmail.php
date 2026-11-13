<?php

namespace App\Jobs;

use App\Support\OrderEmails;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Throwable;

/**
 * Sends the order confirmation email off the request (see App\Support\OrderEmails for the rules). Carries only the order
 * number: the content is read from the database when it runs, so it always reflects what is stored, and no personal data
 * sits in the queue payload.
 */
class SendOrderConfirmationEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(public string $orderNumber)
    {
        $this->onConnection((string) config('storefront.order_mail_queue'));
        $this->afterCommit();
    }

    /** @return list<int> seconds before attempts 2 and 3 (worker queues; the "deferred" connection runs once) */
    public function backoff(): array
    {
        return [30, 180];
    }

    public function handle(): void
    {
        OrderEmails::deliver($this->orderNumber);
    }

    public function failed(?Throwable $e): void
    {
        OrderEmails::giveUp($this->orderNumber, $e);
    }
}
