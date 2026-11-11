<?php

namespace Tests\Feature;

use App\Support\CustomerSession;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/** Task 1 (server side of 401/419 handling) and Task 2 (sessions end after a password reset). */
class SessionRevocationTest extends TestCase
{
    use DatabaseTransactions, StorefrontFixtures;

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
    }

    private function resetPassword(string $email, int $customerId, string $newPassword)
    {
        DB::table('customer_verification_codes')->insert([
            'customer_id' => $customerId, 'destination' => $email, 'channel' => 'email', 'purpose' => 'password_reset',
            'code_hash' => Hash::make('123456'), 'expires_at' => now()->addMinutes(15),
        ]);

        return $this->postJson('/auth/reset', ['email' => $email, 'code' => '123456', 'password' => $newPassword]);
    }

    public function test_sessions_signed_in_before_a_password_reset_lose_access(): void
    {
        $id = $this->makeCustomer('victim@example.test', 'OldPassword1');
        $otherDevice = $this->signedInSession($id);

        $this->withSession($otherDevice)->getJson('/account/orders')->assertOk();

        $this->flushSession();
        $this->resetPassword('victim@example.test', $id, 'NewPassword1')->assertOk();

        $this->flushSession();
        $this->withSession($otherDevice)->getJson('/account/orders')->assertStatus(401)->assertSessionMissing(CustomerSession::ID);
        $this->postJson('/checkout/place', [])->assertStatus(401);
        $this->get('/checkout')->assertRedirect('/?signin=/checkout');
    }

    public function test_revoked_session_renders_pages_as_a_guest(): void
    {
        $id = $this->makeCustomer('guestview@example.test', 'OldPassword1');
        $old = $this->signedInSession($id);
        DB::table('customers')->where('id', $id)->update(['password_hash' => Hash::make('Changed123')]);

        $this->withSession($old)->get('/')->assertOk()->assertDontSee('guestview@example.test');
    }

    public function test_reset_does_not_sign_out_other_customers(): void
    {
        $victim = $this->makeCustomer('v2@example.test', 'OldPassword1');
        $bystander = $this->makeCustomer('bystander@example.test', 'Bystander1');
        $bystanderSession = $this->signedInSession($bystander);

        $this->resetPassword('v2@example.test', $victim, 'NewPassword1')->assertOk();

        $this->flushSession();
        $this->withSession($bystanderSession)->getJson('/account/orders')->assertOk();
    }

    public function test_session_from_before_this_release_is_kept_then_revoked_by_a_reset(): void
    {
        $id = $this->makeCustomer('legacy@example.test', 'OldPassword1');
        // A session created before fingerprints existed only holds customer_id.
        $this->withSession([CustomerSession::ID => $id])->getJson('/account/orders')->assertOk()->assertSessionHas(CustomerSession::FINGERPRINT);
        $adopted = session()->all();

        $this->flushSession();
        $this->resetPassword('legacy@example.test', $id, 'NewPassword1')->assertOk();

        $this->flushSession();
        $this->withSession($adopted)->getJson('/account/orders')->assertStatus(401);
    }

    public function test_resetting_while_signed_in_ends_this_session_and_new_password_signs_in(): void
    {
        $id = $this->makeCustomer('self@example.test', 'OldPassword1');
        $this->withSession($this->signedInSession($id));

        $this->resetPassword('self@example.test', $id, 'NewPassword1')->assertOk()->assertSessionMissing(CustomerSession::ID);

        $this->postJson('/auth/login', ['email' => 'self@example.test', 'password' => 'OldPassword1'])->assertStatus(422);
        $this->postJson('/auth/login', ['email' => 'self@example.test', 'password' => 'NewPassword1'])->assertOk();
        $this->getJson('/account/orders')->assertOk();
    }

    public function test_login_stores_a_fingerprint_that_matches_the_account(): void
    {
        $id = $this->makeCustomer('fp@example.test', 'Password123');

        $this->postJson('/auth/login', ['email' => 'fp@example.test', 'password' => 'Password123'])->assertOk();

        $this->assertSame($id, session(CustomerSession::ID));
        $this->assertSame(CustomerSession::fingerprint(DB::table('customers')->where('id', $id)->value('password_hash')), session(CustomerSession::FINGERPRINT));
    }

    public function test_protected_endpoint_401_has_a_safe_message_and_csrf_endpoint_returns_a_token(): void
    {
        $this->getJson('/account/orders')->assertStatus(401)->assertExactJson(['message' => 'Your session has ended. Please sign in again.']);

        $res = $this->getJson('/auth/csrf')->assertOk();
        $this->assertSame(session()->token(), $res->json('csrf'));
        $this->assertStringContainsString('no-store', $res->headers->get('Cache-Control'));
    }
}
