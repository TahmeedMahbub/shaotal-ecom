<?php

namespace App\Payments;

/** Paid in cash to the courier: nothing is collected online, so the payment stays "pending" until the admin records it. */
class CashOnDelivery implements PaymentMethod
{
    public function code(): string
    {
        return 'cod';
    }

    public function label(): string
    {
        return 'Cash on Delivery';
    }

    public function provider(): ?string
    {
        return null;
    }

    public function initialPaymentStatus(): string
    {
        return 'pending';
    }
}
