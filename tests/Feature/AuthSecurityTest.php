<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/** Task 3 (password policy) and Task 9 (login / forgot-password rate limiting). */
class AuthSecurityTest extends TestCase
{
    use DatabaseTransactions, StorefrontFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
    }

    private function login(string $email, string $password, string $ip = '10.0.0.1')
    {
        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson('/auth/login', ['email' => $email, 'password' => $password]);
    }

    /* ── Password policy ── */

    public function test_registration_rejects_passwords_shorter_than_8(): void
    {
        $this->postJson('/auth/register', ['name' => 'Short Pw', 'email' => 'short@example.test', 'password' => '1234567'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->assertFalse(DB::table('customers')->where('email', 'short@example.test')->exists());
    }

    public function test_registration_accepts_8_characters_without_composition_rules(): void
    {
        $this->postJson('/auth/register', ['name' => 'Ok Pw', 'email' => 'ok@example.test', 'password' => 'abcdefgh'])->assertOk();
        $this->assertTrue(DB::table('customers')->where('email', 'ok@example.test')->exists());
    }

    public function test_reset_rejects_short_password_and_keeps_the_old_one(): void
    {
        $id = $this->makeCustomer('r@example.test', 'OldPassword1');
        DB::table('customer_verification_codes')->insert([
            'customer_id' => $id, 'destination' => 'r@example.test', 'channel' => 'email', 'purpose' => 'password_reset',
            'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(15),
        ]);

        $this->postJson('/auth/reset', ['email' => 'r@example.test', 'code' => '123456', 'password' => 'short'])
            ->assertStatus(422)->assertJsonValidationErrors('password');
        $this->assertTrue(Hash::check('OldPassword1', DB::table('customers')->where('id', $id)->value('password_hash')));
    }

    public function test_existing_customer_with_a_6_character_password_can_still_sign_in(): void
    {
        $this->makeCustomer('legacy6@example.test', 'abc123');

        $this->login('legacy6@example.test', 'abc123')->assertOk();
    }

    /* ── Login throttling ── */

    public function test_five_failures_from_one_ip_block_that_ip_for_that_email_even_with_the_right_password(): void
    {
        $this->makeCustomer('t1@example.test', 'Correct123');
        for ($i = 0; $i < 5; $i++) {
            $this->login('t1@example.test', 'wrong-'.$i)->assertStatus(422);
        }

        $this->login('t1@example.test', 'Correct123')->assertStatus(429)->assertHeader('Retry-After');
        // The real owner on another network is not locked out by that one source.
        $this->login('t1@example.test', 'Correct123', '10.0.0.2')->assertOk();
    }

    public function test_changing_ip_does_not_bypass_the_per_account_limit(): void
    {
        $this->makeCustomer('t2@example.test', 'Correct123');
        for ($i = 0; $i < 20; $i++) {
            $this->login('t2@example.test', 'wrong', '10.1.0.'.($i + 1))->assertStatus(422);
        }

        $this->login('t2@example.test', 'Correct123', '10.1.1.1')->assertStatus(429);
    }

    public function test_one_ip_cannot_spray_many_accounts(): void
    {
        for ($i = 0; $i < 30; $i++) {
            // Different emails each time, spaced to stay under the route's 10-per-minute throttle but inside the 15-minute window.
            $this->travel(7)->seconds();
            $this->login("spray{$i}@example.test", 'wrong', '10.2.0.1')->assertStatus(422);
        }
        $this->travel(7)->seconds();

        $this->login('fresh@example.test', 'whatever', '10.2.0.1')->assertStatus(429);
    }

    public function test_throttle_reply_is_identical_for_existing_and_unknown_emails(): void
    {
        $this->makeCustomer('exists@example.test', 'Correct123');
        foreach (['exists@example.test', 'nobody@example.test'] as $email) {
            for ($i = 0; $i < 5; $i++) {
                $this->login($email, 'wrong', '10.3.0.1');
            }
            $this->travel(61)->seconds();
        }

        $a = $this->login('exists@example.test', 'x', '10.3.0.1')->assertStatus(429)->json('message');
        $b = $this->login('nobody@example.test', 'x', '10.3.0.1')->assertStatus(429)->json('message');
        // Same text apart from the countdown (the two lock-outs started at different times in this test).
        $this->assertSame(preg_replace('/\d+/', 'N', $a), preg_replace('/\d+/', 'N', $b));
    }

    public function test_successful_sign_in_clears_the_failure_count(): void
    {
        $this->makeCustomer('t3@example.test', 'Correct123');
        for ($i = 0; $i < 4; $i++) {
            $this->login('t3@example.test', 'wrong');
        }
        $this->login('t3@example.test', 'Correct123')->assertOk();
        $this->flushSession();

        for ($i = 0; $i < 4; $i++) {
            $this->travel(10)->seconds();
            $this->login('t3@example.test', 'wrong')->assertStatus(422);
        }
    }

    public function test_lockout_expires(): void
    {
        $this->makeCustomer('t4@example.test', 'Correct123');
        for ($i = 0; $i < 5; $i++) {
            $this->login('t4@example.test', 'wrong');
        }
        $this->login('t4@example.test', 'Correct123')->assertStatus(429);

        $this->travel(16)->minutes();

        $this->login('t4@example.test', 'Correct123')->assertOk();
    }

    public function test_forgot_password_emails_are_capped_per_address_with_the_same_reply(): void
    {
        Mail::fake();
        $this->makeCustomer('inbox@example.test', 'Correct123');

        $replies = [];
        for ($i = 0; $i < 4; $i++) {
            $replies[] = $this->postJson('/auth/forgot', ['email' => 'inbox@example.test'])->assertOk()->json('message');
        }

        $this->assertCount(1, array_unique($replies));
        $this->assertSame(3, DB::table('customer_security_logs')->where('event_type', 'password_reset_requested')
            ->whereIn('customer_id', DB::table('customers')->where('email', 'inbox@example.test')->pluck('id'))->count());
    }
}
