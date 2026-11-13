<?php

namespace Tests\Concerns;

use App\Support\CustomerSession;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Fixtures for tests that need the real MySQL schema (local *_test database, see phpunit.mysql.xml). */
trait StorefrontFixtures
{
    protected function requireMysql(): void
    {
        if (config('database.default') !== 'mysql') {
            $this->markTestSkipped('Requires the MySQL test database (composer test).');
        }
    }

    /** A normal (email-verified) customer unless $verified is false: unverified accounts cannot be signed in. */
    protected function makeCustomer(string $email, ?string $password = 'Password123', string $status = 'active', bool $verified = true): int
    {
        return DB::table('customers')->insertGetId([
            'first_name' => 'Test', 'email' => $email, 'status' => $status,
            'password_hash' => $password === null ? null : Hash::make($password),
            'email_verified_at' => $verified ? now() : null,
        ]);
    }

    /** Session contents of a browser that signed in as this customer with their current password. */
    protected function signedInSession(int $customerId): array
    {
        return [
            CustomerSession::ID => $customerId,
            CustomerSession::FINGERPRINT => CustomerSession::fingerprint(DB::table('customers')->where('id', $customerId)->value('password_hash')),
        ];
    }

    protected function makeAddress(int $customerId, array $overrides = []): int
    {
        return DB::table('customer_addresses')->insertGetId($overrides + [
            'customer_id' => $customerId, 'label' => 'Home', 'recipient_name' => 'Test Buyer', 'phone' => '0400 000 000',
            'street_address' => '1 Test St', 'suburb' => 'Carlton', 'state' => 'VIC', 'postcode' => '3053', 'is_default' => 1,
        ]);
    }

    protected function makeCoupon(string $code, array $overrides = []): int
    {
        return DB::table('coupons')->insertGetId($overrides + [
            'code' => $code, 'discount_type' => 'percentage', 'discount_value' => 10, 'status' => 'active',
        ]);
    }
}
