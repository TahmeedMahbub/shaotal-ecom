<?php

namespace Tests\Feature;

use App\Events\PasswordResetCodeIssued;
use App\Events\VerificationCodeIssued;
use App\Listeners\SendPasswordResetCodeEmail;
use App\Listeners\SendVerificationCodeEmail;
use App\Mail\VerificationCodeMail;
use App\Support\CustomerSession;
use App\Support\EmailVerification;
use Illuminate\Events\CallQueuedListener;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\Mime\Email;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/** Registration email verification: App\Support\EmailVerification + EmailVerificationController (+ the session gate). */
class EmailVerificationTest extends TestCase
{
    use DatabaseTransactions, StorefrontFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
        config(['mail.default' => 'array', 'mail.from.address' => 'shop@example.test', 'mail.from.name' => 'Shaotal']);
    }

    /** @return list<Email> */
    private function sent(): array
    {
        return array_map(fn ($m) => $m->getOriginalMessage(), iterator_to_array(app('mailer')->getSymfonyTransport()->messages()));
    }

    private function lastCode(): string
    {
        $mails = $this->sent();
        $this->assertNotEmpty($mails);
        $this->assertSame(1, preg_match('/verification code is: (\d{4})/', end($mails)->getTextBody(), $m));

        return $m[1];
    }

    private function wrong(string $code): string
    {
        return $code === '0000' ? '1111' : '0000';
    }

    private function register(array $extra = [], string $email = 'new@example.test')
    {
        return $this->postJson('/auth/register', ['name' => 'Nora New', 'email' => $email, 'password' => 'Correct123'] + $extra);
    }

    private function id(string $email = 'new@example.test'): int
    {
        return (int) DB::table('customers')->where('email', $email)->value('id');
    }

    /** Lets the next resend through (the 60-second cooldown) without waiting. */
    private function skipCooldown(string $email = 'new@example.test'): void
    {
        RateLimiter::clear('verify:resend:'.$this->id($email));
    }

    /* ── Registration + email ── */

    public function test_registration_does_not_sign_in_and_emails_a_branded_4_digit_code(): void
    {
        $res = $this->register()->assertOk();
        $res->assertJson(['verificationRequired' => true, 'verificationSent' => true, 'redirect' => '/verify-email']);
        $res->assertJsonMissingPath('user');                                             // no session payload yet
        $this->assertNull(session(CustomerSession::ID));
        $this->assertSame($this->id(), session(EmailVerification::PENDING));
        $this->assertNull(DB::table('customers')->where('id', $this->id())->value('email_verified_at'));

        $mails = $this->sent();
        $this->assertCount(1, $mails);
        $mail = $mails[0];
        $this->assertSame('new@example.test', $mail->getTo()[0]->getAddress());
        $this->assertSame('shop@example.test', $mail->getFrom()[0]->getAddress());        // sender from config only
        $this->assertSame('Shaotal', $mail->getFrom()[0]->getName());
        $this->assertSame('Your Shaotal verification code', $mail->getSubject());
        $code = $this->lastCode();
        $this->assertMatchesRegularExpression('/^\d{4}$/', $code);
        $this->assertStringContainsString($code, $mail->getHtmlBody());                 // HTML + plain-text parts
        $this->assertStringContainsString('expires in <strong style="color:#231E1A;">10 minutes', $mail->getHtmlBody());
        $this->assertStringContainsString('assets/logos/logo.png', $mail->getHtmlBody());
        $this->assertStringContainsString('ignore this email', $mail->getTextBody());

        $row = DB::table('customer_verification_codes')->where('customer_id', $this->id())->where('purpose', 'email_verification')->first();
        $this->assertNotSame($code, $row->code_hash);                                    // only a hash is stored
        $this->assertSame(EmailVerification::hash($this->id(), $code), $row->code_hash);      // keyed HMAC, bound to the customer
        $this->assertNotSame(EmailVerification::hash($this->id() + 1, $code), $row->code_hash);
        $this->assertEqualsWithDelta(now()->addMinutes(10)->timestamp, strtotime($row->expires_at), 5);
        $this->assertStringNotContainsString($code, json_encode($res->json()));         // never sent to the browser
    }

    public function test_verification_page_shows_the_address_and_noindex(): void
    {
        $this->register()->assertOk();
        $res = $this->get('/verify-email')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $html = $res->getContent();
        $this->assertStringContainsString('Verify your email address', $html);
        $this->assertStringNotContainsString('verify-banner', $html);                                 // no banner, straight to the code
        $this->assertStringContainsString("We've sent a 4-digit verification code to <strong id=\"verify-email-address\">ne***@example.test</strong>.", $html);
        $this->assertStringNotContainsString('new@example.test', $html);                              // masked
        $this->assertSame(4, substr_count($html, 'class="otp-input"'));
        $this->assertStringContainsString('inputmode="numeric"', $html);
        $this->assertStringContainsString('data-cooldown="', $html);
    }

    public function test_page_without_a_pending_verification_shows_the_expired_state(): void
    {
        $html = $this->get('/verify-email')->assertOk()->getContent();
        $this->assertStringContainsString('Your verification session has expired', $html);
        $this->assertStringNotContainsString('otp-input', $html);
    }

    /* ── Correct / incorrect / expired / single use ── */

    public function test_correct_code_signs_in_and_redirects_to_the_account_by_default(): void
    {
        $this->register()->assertOk();
        $this->postJson('/verify-email', ['code' => $this->wrong($this->lastCode())])->assertStatus(422)->assertJson(['message' => 'That code is incorrect. 4 attempts left.']);

        $this->postJson('/verify-email', ['code' => $this->lastCode()])->assertOk()
            ->assertJson(['verified' => true, 'redirect' => '/account']);
        $this->assertSame($this->id(), session(CustomerSession::ID));
        $this->assertNull(session(EmailVerification::PENDING));
        $this->assertNotNull(DB::table('customers')->where('id', $this->id())->value('email_verified_at'));
        $this->assertSame(1, DB::table('customer_security_logs')->where('customer_id', $this->id())->where('event_type', 'email_verified')->count());
        $this->getJson('/account/addresses')->assertOk();                                  // protected features open now
    }

    public function test_code_is_single_use_and_a_repeated_submit_is_harmless(): void
    {
        $this->register()->assertOk();
        $code = $this->lastCode();
        $this->postJson('/verify-email', ['code' => $code])->assertOk();
        // Double click / retry in the same browser: same success, nothing logged twice.
        $this->postJson('/verify-email', ['code' => $code])->assertOk()->assertJson(['verified' => true]);
        $this->assertSame(1, DB::table('customer_security_logs')->where('customer_id', $this->id())->where('event_type', 'email_verified')->count());
        $this->assertNotNull(DB::table('customer_verification_codes')->where('customer_id', $this->id())->value('verified_at'));

        // A different browser (no pending verification) cannot reuse the code.
        $this->flushSession();
        $this->postJson('/verify-email', ['code' => $code])->assertStatus(401)->assertJson(['expired' => true]);
    }

    public function test_expired_code_is_rejected(): void
    {
        $this->register()->assertOk();
        $code = $this->lastCode();
        DB::table('customer_verification_codes')->where('customer_id', $this->id())->update(['expires_at' => now()->subSecond()]);
        $this->postJson('/verify-email', ['code' => $code])->assertStatus(422)
            ->assertJson(['message' => 'This code has expired. Please request a new one.', 'codeExpired' => true]);
        $this->assertNull(session(CustomerSession::ID));
    }

    public function test_code_dies_after_five_wrong_attempts(): void
    {
        $this->register()->assertOk();
        $code = $this->lastCode();
        for ($i = 4; $i >= 1; $i--) {
            $this->postJson('/verify-email', ['code' => $this->wrong($code)])->assertStatus(422)->assertJsonPath('message', "That code is incorrect. {$i} attempt".($i > 1 ? 's' : '').' left.');
        }
        $this->postJson('/verify-email', ['code' => $this->wrong($code)])->assertStatus(422)->assertJson(['codeExpired' => true]);
        $this->postJson('/verify-email', ['code' => $code])->assertStatus(422)->assertJson(['codeExpired' => true]);   // even the right one
    }

    public function test_guesses_are_capped_per_customer_across_codes(): void
    {
        $this->register()->assertOk();
        for ($i = 0; $i < EmailVerification::MAX_GUESSES; $i++) {
            if ($i > 0 && $i % EmailVerification::MAX_ATTEMPTS === 0) {
                $this->skipCooldown();
                $this->postJson('/verify-email/resend')->assertOk();
            }
            $this->postJson('/verify-email', ['code' => $this->wrong($this->lastCode())])->assertStatus(422);
        }
        $this->skipCooldown();
        $this->postJson('/verify-email/resend')->assertOk();
        $this->postJson('/verify-email', ['code' => $this->lastCode()])->assertStatus(429);
    }

    public function test_code_format_is_validated(): void
    {
        $this->register()->assertOk();
        foreach (['123', '12345', 'abcd', ''] as $bad) {
            $this->postJson('/verify-email', ['code' => $bad])->assertStatus(422);
        }
    }

    /* ── Resend ── */

    public function test_resend_has_a_60_second_cooldown_and_replaces_the_code(): void
    {
        $this->register()->assertOk();
        $first = $this->lastCode();
        $res = $this->postJson('/verify-email/resend')->assertStatus(429);                 // right after sign-up
        $this->assertGreaterThan(50, $res->json('retryAfter'));

        $this->skipCooldown();
        $this->postJson('/verify-email/resend')->assertOk()->assertJson(['retryAfter' => 60]);
        $this->assertCount(2, $this->sent());
        $this->assertSame(1, DB::table('customer_verification_codes')->where('customer_id', $this->id())->whereNull('verified_at')->count());
        $latest = $this->lastCode();
        if ($latest !== $first) {
            $this->postJson('/verify-email', ['code' => $first])->assertStatus(422);    // the old code is gone
        }
        $this->postJson('/verify-email', ['code' => $latest])->assertOk();
    }

    public function test_resend_is_capped_per_hour(): void
    {
        $this->register()->assertOk();                                                     // send 1
        for ($i = 2; $i <= EmailVerification::MAX_SENDS_PER_HOUR; $i++) {
            $this->skipCooldown();
            $this->postJson('/verify-email/resend')->assertOk();
        }
        $this->skipCooldown();
        $this->postJson('/verify-email/resend')->assertStatus(429)->assertJsonFragment(['message' => 'Too many codes requested. Please try again in 60 minutes.']);
        $this->assertCount(EmailVerification::MAX_SENDS_PER_HOUR, $this->sent());
    }

    /* ── Change email ── */

    public function test_change_email_moves_the_code_to_the_new_address(): void
    {
        $this->makeCustomer('taken@example.test');
        $this->register()->assertOk();
        $this->skipCooldown();
        $this->postJson('/verify-email/email', ['email' => 'taken@example.test'])->assertStatus(422);
        $this->postJson('/verify-email/email', ['email' => 'new@example.test'])->assertStatus(422);

        $this->postJson('/verify-email/email', ['email' => 'Fixed@Example.test'])->assertOk()->assertJson(['email' => 'fi***@example.test']);
        $this->assertSame('fixed@example.test', DB::table('customers')->where('id', session(EmailVerification::PENDING))->value('email'));
        $this->assertSame('fixed@example.test', $this->sent()[count($this->sent()) - 1]->getTo()[0]->getAddress());
        $this->postJson('/verify-email', ['code' => $this->lastCode()])->assertOk();
    }

    /* ── Redirects ── */

    public function test_intended_destination_is_kept_and_validated(): void
    {
        $this->register(['return' => '/checkout'])->assertOk();
        $this->postJson('/verify-email', ['code' => $this->lastCode()])->assertOk()->assertJson(['redirect' => '/checkout']);

        foreach (['https://evil.example/x', '//evil.example', '/\\evil.example', "/\t/evil.example", '/verify-email', 'javascript:alert(1)'] as $i => $bad) {
            $this->flushSession();
            $this->register(['return' => $bad], "r{$i}@example.test")->assertOk();
            $this->postJson('/verify-email', ['code' => $this->lastCode()])->assertOk()->assertJson(['redirect' => '/account']);
        }
    }

    /* ── Access restrictions ── */

    public function test_unverified_customers_cannot_reach_protected_features(): void
    {
        $this->register()->assertOk();
        $this->getJson('/account/addresses')->assertStatus(403)->assertJson(['verificationRequired' => true, 'redirect' => '/verify-email']);
        $this->putJson('/account/profile', ['name' => 'X'])->assertStatus(403);
        $this->get('/checkout')->assertRedirect('/verify-email');
        $this->get('/account')->assertRedirect('/verify-email');
        $this->get('/account/orders/INV-1')->assertRedirect('/verify-email');
    }

    public function test_password_sign_in_of_an_unverified_account_requires_verification(): void
    {
        $this->makeCustomer('late@example.test', 'Correct123', verified: false);
        $res = $this->postJson('/auth/login', ['email' => 'late@example.test', 'password' => 'Correct123', 'return' => '/checkout'])->assertOk();
        $res->assertJson(['verificationRequired' => true, 'redirect' => '/verify-email'])->assertJsonMissingPath('user');
        $this->assertNull(session(CustomerSession::ID));
        $this->assertCount(1, $this->sent());
        $this->postJson('/verify-email', ['code' => $this->lastCode()])->assertOk()->assertJson(['redirect' => '/checkout']);

        // Wrong password never reaches verification (and sends nothing).
        $this->flushSession();
        $this->makeCustomer('late2@example.test', 'Correct123', verified: false);
        $this->postJson('/auth/login', ['email' => 'late2@example.test', 'password' => 'Wrong12345'])->assertStatus(422);
        $this->assertCount(1, $this->sent());
    }

    public function test_existing_session_of_an_unverified_account_is_ended(): void
    {
        $id = $this->makeCustomer('old@example.test', verified: false);
        $this->withSession($this->signedInSession($id))->getJson('/account/addresses')->assertStatus(401);
    }

    public function test_verified_customers_sign_in_as_before(): void
    {
        $this->makeCustomer('ok@example.test', 'Correct123');
        $this->postJson('/auth/login', ['email' => 'ok@example.test', 'password' => 'Correct123'])->assertOk()->assertJsonPath('user.email', 'ok@example.test');
        $this->assertCount(0, $this->sent());
    }

    public function test_password_reset_proves_ownership_and_verifies_the_email(): void
    {
        $this->makeCustomer('reset@example.test', 'Correct123', verified: false);
        $this->postJson('/auth/forgot', ['email' => 'reset@example.test'])->assertOk();
        preg_match('/password reset code is: (\d{6})/', $this->sent()[count($this->sent()) - 1]->getTextBody(), $m);
        $this->postJson('/auth/reset', ['email' => 'reset@example.test', 'code' => $m[1], 'password' => 'NewPass123'])->assertOk();
        $this->assertNotNull(DB::table('customers')->where('email', 'reset@example.test')->value('email_verified_at'));
        $this->postJson('/auth/login', ['email' => 'reset@example.test', 'password' => 'NewPass123'])->assertOk()->assertJsonPath('user.email', 'reset@example.test');
    }

    public function test_guest_bag_is_merged_after_verification(): void
    {
        $this->register(['cart' => [['productId' => 2, 'quantity' => 2]], 'wishlist' => [2]])->assertOk();
        $this->postJson('/verify-email', ['code' => $this->lastCode()])->assertOk();
        $this->assertSame(1, DB::table('wishlist_items')->where('customer_id', $this->id())->where('product_id', 2)->count());
    }

    public function test_sign_in_to_an_unverified_account_opens_the_same_code_page(): void
    {
        $this->makeCustomer('later@example.test', 'Correct123', verified: false);
        $this->postJson('/auth/login', ['email' => 'later@example.test', 'password' => 'Correct123'])->assertOk();
        $html = $this->get('/verify-email')->getContent();
        $this->assertStringContainsString('la***@example.test', $html);
        $this->assertSame(4, substr_count($html, 'class="otp-input"'));
        $this->assertStringNotContainsString('verify-banner', $html);
    }

    public function test_email_masking(): void
    {
        $this->assertSame('ne***@example.test', EmailVerification::mask('new@example.test'));
        $this->assertSame('a***@x.io', EmailVerification::mask('ab@x.io'));
        $this->assertSame('a***@x.io', EmailVerification::mask('a@x.io'));
    }

    public function test_sign_up_answer_does_not_wait_for_the_email(): void
    {
        $res = $this->register()->assertOk();
        $this->assertSame((string) strlen($res->getContent()), $res->headers->get('Content-Length'));   // browser can finish reading early
        $this->assertCount(1, $this->sent());                                                     // delivered after the response
    }

    public function test_email_logo_is_an_absolute_app_url_whatever_host_the_request_used(): void
    {
        config(['app.url' => 'https://shop.example.com/']);
        // Registration arrives on a different (local) host: the email must still point at the public APP_URL.
        $this->postJson('http://localhost:8000/auth/register', ['name' => 'Nora New', 'email' => 'new@example.test', 'password' => 'Correct123'])->assertOk();
        $html = $this->sent()[0]->getHtmlBody();
        $this->assertStringContainsString('<img src="https://shop.example.com/assets/logos/logo.png" width="160" height="29" alt="Shaotal logo"', $html);
        $this->assertStringNotContainsString('localhost', $html);
        $this->assertFileExists(public_path('assets/logos/logo.png'));

        // Production never emits an http logo URL, even if APP_URL was entered without https.
        $this->app['env'] = 'production';
        config(['app.url' => 'http://shop.example.com']);
        $this->assertSame('https://shop.example.com/assets/logos/logo.png', VerificationCodeMail::logoUrl());
        $this->app['env'] = 'testing';
    }

    /* ── Event-driven delivery (App\Support\AuthMail → queued listeners) ── */

    public function test_registration_dispatches_the_event_with_the_stored_code(): void
    {
        Event::fake([VerificationCodeIssued::class]);
        $this->register()->assertOk();
        Event::assertDispatched(VerificationCodeIssued::class, function (VerificationCodeIssued $e) {
            $row = DB::table('customer_verification_codes')->where('customer_id', $e->customerId)->first();

            return $e->customerId === $this->id() && $e->email === 'new@example.test' && $e->minutes === 10
                && EmailVerification::matches($e->customerId, $e->code, $row->code_hash);
        });
        $this->assertCount(0, $this->sent());                                                        // the listener sends, not the request
    }

    public function test_listeners_are_queued_encrypted_on_the_configured_connection(): void
    {
        Queue::fake();
        config(['storefront.auth_mail_queue' => 'database']);
        $this->register()->assertOk();
        $this->postJson('/auth/forgot', ['email' => 'new@example.test'])->assertOk();
        foreach ([SendVerificationCodeEmail::class, SendPasswordResetCodeEmail::class] as $listener) {
            Queue::assertPushed(CallQueuedListener::class, fn (CallQueuedListener $job) => $job->class === $listener && $job->shouldBeEncrypted === true);
            $this->assertSame('database', (new $listener)->viaConnection());
            $this->assertSame(3, (new $listener)->tries);
            $this->assertSame([10, 60], (new $listener)->backoff());
        }
        $this->assertCount(0, $this->sent());
    }

    public function test_queue_dispatch_failure_still_delivers_the_email_after_the_response(): void
    {
        // The test database has no `jobs` table, so pushing to the "database" queue throws: the code must not be lost.
        config(['storefront.auth_mail_queue' => 'database']);
        $this->register()->assertOk()->assertJson(['verificationRequired' => true]);
        $this->assertCount(1, $this->sent());
        $this->postJson('/verify-email', ['code' => $this->lastCode()])->assertOk();
    }

    public function test_final_delivery_failure_lifts_the_resend_cooldown_and_is_logged_without_the_code(): void
    {
        $this->register()->assertOk();
        $code = $this->lastCode();
        $this->postJson('/verify-email/resend')->assertStatus(429);                                   // cooldown after sign-up
        Log::spy();
        (new SendVerificationCodeEmail)->failed(new VerificationCodeIssued($this->id(), 'new@example.test', 'Nora', $code, 10), new \RuntimeException('smtp down'));
        Log::shouldHaveReceived('warning')->withArgs(fn ($m, $ctx) => $m === 'Verification email could not be delivered'
            && $ctx === ['customer_id' => $this->id(), 'error' => \RuntimeException::class])->once();
        $this->postJson('/verify-email/resend')->assertOk();                                         // no 60-second wait
    }

    public function test_smtp_outage_keeps_the_account_pending_and_resend_stays_available(): void
    {
        config(['mail.default' => 'smtp', 'mail.mailers.smtp.host' => '127.0.0.1', 'mail.mailers.smtp.port' => 1, 'mail.mailers.smtp.timeout' => 2]);
        // Sign-up answers without waiting for the mail server; the delivery failure happens after the response.
        $this->register()->assertOk()->assertJson(['verificationRequired' => true, 'verificationSent' => true]);
        $this->assertNull(session(CustomerSession::ID));
        // The failed delivery lifted the cooldown: the customer can ask again straight away.
        $this->postJson('/verify-email/resend')->assertOk();
    }

    /* ── Forgot password (same event-driven delivery) ── */

    public function test_forgot_password_emails_a_branded_code_off_the_request_and_resets(): void
    {
        $this->makeCustomer('fp@example.test', 'OldPass123');
        $res = $this->postJson('/auth/forgot', ['email' => 'fp@example.test'])->assertOk();
        $this->assertSame((string) strlen($res->getContent()), $res->headers->get('Content-Length'));   // answer is not held up
        $mail = $this->sent()[0];
        $this->assertSame('Your Shaotal password reset code', $mail->getSubject());
        $html = $mail->getHtmlBody();
        foreach (['Reset your password', 'alt="Shaotal logo"', 'assets/logos/logo.png', 'expires in <strong style="color:#231E1A;">15 minutes', 'border:2px solid #BE6D37'] as $part) {
            $this->assertStringContainsString($part, $html);                                        // same design as verification
        }
        preg_match('/password reset code is: (\d{6})/', $mail->getTextBody(), $m);
        $wrong = $m[1] === '000000' ? '111111' : '000000';
        $this->postJson('/auth/reset', ['email' => 'fp@example.test', 'code' => $wrong, 'password' => 'NewPass123'])->assertStatus(422);
        $this->postJson('/auth/reset', ['email' => 'fp@example.test', 'code' => $m[1], 'password' => 'NewPass123'])->assertOk();
        $this->postJson('/auth/reset', ['email' => 'fp@example.test', 'code' => $m[1], 'password' => 'Another123'])->assertStatus(422);   // single use
        $this->postJson('/auth/login', ['email' => 'fp@example.test', 'password' => 'NewPass123'])->assertOk();
    }

    public function test_forgot_password_answer_is_identical_for_unknown_addresses_and_sends_nothing(): void
    {
        Event::fake([PasswordResetCodeIssued::class]);
        $this->makeCustomer('known@example.test');
        $known = $this->postJson('/auth/forgot', ['email' => 'known@example.test'])->assertOk()->json();
        $unknown = $this->postJson('/auth/forgot', ['email' => 'nobody@example.test'])->assertOk()->json();
        $this->assertSame($known, $unknown);
        Event::assertDispatchedTimes(PasswordResetCodeIssued::class, 1);
    }

    public function test_new_reset_code_replaces_the_previous_one(): void
    {
        $this->makeCustomer('again@example.test', 'OldPass123');
        $this->postJson('/auth/forgot', ['email' => 'again@example.test'])->assertOk();
        preg_match('/password reset code is: (\d{6})/', $this->sent()[0]->getTextBody(), $first);
        $this->postJson('/auth/forgot', ['email' => 'again@example.test'])->assertOk();
        preg_match('/password reset code is: (\d{6})/', $this->sent()[1]->getTextBody(), $second);
        $this->assertSame(1, DB::table('customer_verification_codes')->where('destination', 'again@example.test')->where('purpose', 'password_reset')->whereNull('verified_at')->count());
        if ($first[1] !== $second[1]) {
            $this->postJson('/auth/reset', ['email' => 'again@example.test', 'code' => $first[1], 'password' => 'NewPass123'])->assertStatus(422);
        }
        $this->postJson('/auth/reset', ['email' => 'again@example.test', 'code' => $second[1], 'password' => 'NewPass123'])->assertOk();
    }
}
