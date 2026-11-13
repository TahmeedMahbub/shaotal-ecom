<?php

namespace App\Mail;

/** "Forgot password" reset code (6 digits). */
class PasswordResetCodeMail extends BrandedCodeMail
{
    protected function copy(string $brand): array
    {
        return [
            'subject' => "Your {$brand} password reset code",
            'heading' => 'Reset your password',
            'intro' => "Use this code to choose a new password for your {$brand} account:",
            'ignore' => "If you didn't ask to reset your password, you can ignore this email and your password stays the same. Never share this code with anyone.",
            'textIntro' => "Your {$brand} password reset code is: {$this->code}\n\nEnter it with your new password on the reset page.",
        ];
    }
}
