<?php

namespace App\Payments;

/**
 * A way to pay for an order. Cash on delivery is the only method today. A card gateway (Stripe) will implement this and
 * add a confirmation step (redirect / client secret + webhook); the order's payment_status must then only ever be set to
 * "paid" from the gateway's verified server-side confirmation, never from anything the browser sends.
 */
interface PaymentMethod
{
    /** Code stored in orders.payment_method / payments.payment_method (and listed in config_data payment_methods). */
    public function code(): string;

    /** Customer-facing name. */
    public function label(): string;

    /** payments.provider value (null when no external provider is involved). */
    public function provider(): ?string;

    /** Status of the payments row created with the order; orders.payment_status starts as "pending" for every method. */
    public function initialPaymentStatus(): string;
}
