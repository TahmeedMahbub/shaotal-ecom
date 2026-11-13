<?php

namespace App\Events;

/**
 * A registration verification code was generated (its hash is already stored). Carries the plain code only so the
 * listener can email it: the queued listener's payload is encrypted (ShouldBeEncrypted), and nothing logs this event.
 */
class VerificationCodeIssued
{
    public function __construct(
        public int $customerId,
        public string $email,
        public string $firstName,
        public string $code,
        public int $minutes,
    ) {}
}
