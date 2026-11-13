<?php

namespace App\Events;

/**
 * A password reset code was generated for an existing, active account (its hash is already stored). Same handling as
 * VerificationCodeIssued: emailed by a queued, encrypted listener; never logged.
 */
class PasswordResetCodeIssued
{
    public function __construct(
        public int $customerId,
        public string $email,
        public string $firstName,
        public string $code,
        public int $minutes,
    ) {}
}
