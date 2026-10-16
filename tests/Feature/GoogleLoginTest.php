<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialUser;
use Mockery;
use Tests\TestCase;

/**
 * Needs the MySQL schema (sql/ecom.sql + sql/google_login.sql) in a scratch database, e.g.:
 * DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=shaotal_local DB_USERNAME=root DB_PASSWORD= CACHE_STORE=file php artisan test --filter=GoogleLoginTest
 * Google itself is faked; this does not prove real OAuth credentials work.
 */
class GoogleLoginTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires the MySQL scratch database.');
        }
        config(['services.google.client_id' => 'test-id', 'services.google.client_secret' => 'test-secret']);
    }

    private function fakeGoogle(array $overrides = []): void
    {
        $user = (new SocialUser)->setRaw($overrides['raw'] ?? ['email_verified' => true])->map([
            'id' => $overrides['id'] ?? 'g-123', 'name' => 'Gina Google', 'email' => $overrides['email'] ?? 'gina@example.com',
        ]);
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('user')->andReturn($user);
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);
    }

    public function test_creates_new_customer_and_signs_in(): void
    {
        $this->fakeGoogle();
        $this->get('/auth/google/callback?code=x&state=y')->assertRedirect('/');

        $c = DB::table('customers')->where('email', 'gina@example.com')->first();
        $this->assertNotNull($c);
        $this->assertNull($c->password_hash);
        $this->assertNotNull($c->email_verified_at);
        $this->assertSame('Gina', $c->first_name);
        $this->assertTrue(DB::table('customer_social_accounts')->where('customer_id', $c->id)->where('provider_user_id', 'g-123')->exists());
        $this->assertSame($c->id, session('customer_id'));
    }

    public function test_second_login_reuses_linked_account(): void
    {
        $this->fakeGoogle();
        $this->get('/auth/google/callback?code=x');
        $this->flushSession();
        $this->get('/auth/google/callback?code=x');

        $this->assertSame(1, DB::table('customers')->where('email', 'gina@example.com')->count());
        $this->assertSame(1, DB::table('customer_social_accounts')->count());
    }

    public function test_links_verified_existing_account_and_keeps_password(): void
    {
        $id = DB::table('customers')->insertGetId(['first_name' => 'G', 'email' => 'gina@example.com', 'password_hash' => 'hash', 'email_verified_at' => now()]);
        $this->fakeGoogle();
        $this->get('/auth/google/callback?code=x');

        $this->assertSame($id, session('customer_id'));
        $this->assertSame('hash', DB::table('customers')->where('id', $id)->value('password_hash'));
    }

    public function test_unverified_local_account_has_password_cleared_on_link(): void
    {
        $id = DB::table('customers')->insertGetId(['first_name' => 'G', 'email' => 'gina@example.com', 'password_hash' => 'squatter']);
        $this->fakeGoogle();
        $this->get('/auth/google/callback?code=x');

        $row = DB::table('customers')->where('id', $id)->first();
        $this->assertNull($row->password_hash);
        $this->assertNotNull($row->email_verified_at);
    }

    public function test_rejects_unverified_google_email(): void
    {
        $this->fakeGoogle(['raw' => ['email_verified' => false]]);
        $this->get('/auth/google/callback?code=x')->assertRedirect('/')->assertSessionHas('auth_error');

        $this->assertNull(session('customer_id'));
        $this->assertFalse(DB::table('customers')->where('email', 'gina@example.com')->exists());
    }

    public function test_blocked_account_cannot_sign_in(): void
    {
        DB::table('customers')->insert(['first_name' => 'G', 'email' => 'gina@example.com', 'status' => 'blocked']);
        $this->fakeGoogle();
        $this->get('/auth/google/callback?code=x')->assertSessionHas('auth_error');

        $this->assertNull(session('customer_id'));
    }

    public function test_cancellation_shows_message(): void
    {
        $this->get('/auth/google/callback?error=access_denied')->assertRedirect('/')->assertSessionHas('auth_error', 'Google sign-in was cancelled.');
    }

    public function test_disabled_without_credentials(): void
    {
        config(['services.google.client_id' => null]);
        $this->get('/auth/google')->assertRedirect('/')->assertSessionHas('auth_error');
    }

    public function test_return_path_must_be_relative(): void
    {
        $provider = Mockery::mock('Laravel\Socialite\Contracts\Provider');
        $provider->shouldReceive('redirect')->andReturn(redirect('https://accounts.google.com'));
        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $this->get('/auth/google?return=//evil.com')->assertSessionHas('google_return', '/');
    }
}
