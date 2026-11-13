<?php

namespace App\Payments;

use RuntimeException;

/** A Stripe API or webhook verification failure. Messages never contain keys or customer data. */
class StripeException extends RuntimeException
{
    public function __construct(string $message, int $status = 0, public readonly ?string $stripeCode = null)
    {
        parent::__construct($message, $status);
    }
}
