<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * One-time-code email in the Shaotal design (emails/code + plain-text part). Subclasses only supply the wording.
 * Sender (address + name) comes from config/mail.php, i.e. .env — never set here. These mailables are sent by the
 * queued listeners (App\Listeners\*), whose job payload is encrypted, so the code is never queued in plain text.
 */
abstract class BrandedCodeMail extends Mailable
{
    public function __construct(public string $code, public string $firstName, public int $minutes) {}

    /** @return array{subject: string, heading: string, intro: string, ignore: string, textIntro: string} */
    abstract protected function copy(string $brand): array;

    /**
     * Absolute logo URL built from APP_URL, never from the current request: asset() would use whatever host the
     * request came in on (localhost, shaotal.test, a proxy) and the email is read elsewhere, by Gmail's image proxy.
     * Outside local development the scheme is always https (storefront:deploy-check also requires an https APP_URL).
     */
    public static function logoUrl(): string
    {
        $base = rtrim((string) config('app.url'), '/');
        if (! app()->environment('local', 'testing')) {
            $base = preg_replace('#^http://#i', 'https://', $base);
        }

        return $base.'/assets/logos/logo.png';
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->copy((string) config('storefront.brand'))['subject']);
    }

    public function content(): Content
    {
        $brand = (string) config('storefront.brand');

        return new Content(
            view: 'emails.code',
            text: 'emails.code-text',
            with: ['brand' => $brand, 'logoUrl' => self::logoUrl()] + $this->copy($brand),
        );
    }
}
