<?php

namespace Tests;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Tests\Concerns\StorefrontFixtures;

abstract class TestCase extends BaseTestCase
{
    /**
     * Runs inside parent::setUp() BEFORE traits such as DatabaseTransactions open a database connection, so the
     * database guard below is checked before anything can connect.
     */
    protected function setUpTraits()
    {
        // .env points at the shared remote database. Tests write rows (inside transactions, or committed and cleaned
        // up in CouponConcurrencyTest), so they may only ever run against in-memory SQLite or a LOCAL MySQL database
        // whose name ends in "_test".
        $name = config('database.default');
        $conn = config("database.connections.{$name}");
        if (($conn['driver'] ?? null) !== 'sqlite') {
            $host = (string) ($conn['host'] ?? '');
            $db = (string) ($conn['database'] ?? '');
            if (! in_array($host, ['127.0.0.1', 'localhost', '::1'], true) || ! str_ends_with($db, '_test')) {
                throw new RuntimeException("Refusing to run tests against database [{$db}] on host [{$host}]. Use a local *_test database.");
            }
        }

        // Tests that need the real (MySQL) schema are skipped in the SQLite run before any connection is attempted.
        $uses = class_uses_recursive(static::class);
        if ($name !== 'mysql' && (isset($uses[DatabaseTransactions::class]) || isset($uses[StorefrontFixtures::class]))) {
            $this->markTestSkipped('Requires the MySQL test database (composer test).');
        }

        return parent::setUpTraits();
    }

    protected function setUp(): void
    {
        parent::setUp();

        // The storefront caches the catalog on the "file" store explicitly; keep tests off the real cache directory.
        config(['cache.stores.file.path' => storage_path('framework/testing/cache')]);
        Cache::forgetDriver('file');
        Cache::store('file')->flush();
    }
}
