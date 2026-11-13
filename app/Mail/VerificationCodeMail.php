<?php

namespace App\Mail;

/** Registration email verification code (4 digits). */
class VerificationCodeMail extends BrandedCodeMail
{
    protected function copy(string $brand): array
    {
        return [
            'subject' => "Your {$brand} verification code",
            'heading' => 'Verify your email address',
            'intro' => "Use this code to finish creating your {$brand} account:",
            'ignore' => "If you didn't create a {$brand} account, you can safely ignore this email. Never share this code with anyone.",
            'textIntro' => "Your {$brand} verification code is: {$this->code}\n\nEnter it on the verification page to finish creating your account.",
        ];
    }
}
