<?php

namespace App\Payments;

/**
 * Card payment on Stripe's hosted Checkout page. The order is created "pending" first; the customer is then redirected
 * to Stripe. Only a verified webhook (App\Models\CardPayments::applyEvent) ever marks it paid: never the browser.
 */
class StripeCard implements PaymentMethod
{
    public function code(): string
    {
        return 'card';
    }

    public function label(): string
    {
        return 'Credit or Debit Card';
    }

    public function provider(): ?string
    {
        return 'stripe';
    }

    public function initialPaymentStatus(): string
    {
        return 'pending';
    }
}
