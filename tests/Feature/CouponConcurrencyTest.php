<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Concerns\StorefrontFixtures;
use Tests\TestCase;

/**
 * Task 5: coupon usage limits under REAL concurrency (separate PHP processes, separate MySQL connections).
 * Cannot use DatabaseTransactions (child processes must see the rows), so it commits rows to the local *_test
 * database and deletes exactly the rows it created in tearDown.
 */
class CouponConcurrencyTest extends TestCase
{
    use StorefrontFixtures;

    private string $code;

    private array $customers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->requireMysql();
        $this->code = 'CONC-'.Str::upper(Str::random(8));
        $this->makeCoupon($this->code, ['usage_limit' => 1]);
        foreach (['x', 'y', 'z'] as $who) {
            $this->customers[$who] = $this->makeCustomer("conc-{$who}-".Str::lower(Str::random(6)).'@example.test');
        }
    }

    /** @var array<int, resource> */
    private array $procs = [];

    protected function tearDown(): void
    {
        // A failed assertion can leave a child running or the lock-holding connection open; release both first.
        foreach ($this->procs as $p) {
            if (is_resource($p)) {
                if (proc_get_status($p)['running']) {
                    proc_terminate($p);
                }
                proc_close($p);
            }
        }
        if (array_key_exists('mysql_other', config('database.connections'))) {
            while (DB::connection('mysql_other')->transactionLevel() > 0) {
                DB::connection('mysql_other')->rollBack();
            }
            DB::purge('mysql_other');
        }
        if ($this->customers) {
            $orderIds = DB::table('orders')->whereIn('customer_id', $this->customers)->pluck('id');
            DB::table('payments')->whereIn('order_id', $orderIds)->delete();
            DB::table('orders')->whereIn('id', $orderIds)->delete();   // items + timeline cascade
            DB::table('carts')->whereIn('customer_id', $this->customers)->delete();
            DB::table('customers')->whereIn('id', $this->customers)->delete();
            DB::table('coupons')->where('code', $this->code)->delete();
        }
        parent::tearDown();
    }

    /** @return array{0: resource, 1: array} */
    private function startChild(int $customerId)
    {
        $c = config('database.connections.mysql');
        $env = [
            'DB_CONNECTION' => 'mysql', 'DB_HOST' => $c['host'], 'DB_PORT' => (string) $c['port'], 'DB_DATABASE' => $c['database'],
            'TEST_DB_CONNECTION' => base64_encode(json_encode($c)), 'APP_ENV' => 'testing',
            'CACHE_STORE' => 'array', 'SESSION_DRIVER' => 'array', 'MAIL_MAILER' => 'array', 'LOG_CHANNEL' => 'null',
            'APP_KEY' => (string) config('app.key'),
            'SystemRoot' => getenv('SystemRoot') ?: '', 'PATH' => getenv('PATH') ?: '', 'TEMP' => sys_get_temp_dir(), 'TMP' => sys_get_temp_dir(),
        ];
        $proc = proc_open(
            [PHP_BINARY, base_path('tests/Support/place-order-child.php'), (string) $customerId, '2', $this->code],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, base_path(), $env,
        );
        $this->assertIsResource($proc);
        $this->procs[] = $proc;

        return [$proc, $pipes];
    }

    private function finish($proc, array $pipes, int $timeout = 60): array
    {
        $start = microtime(true);
        while (proc_get_status($proc)['running'] && microtime(true) - $start < $timeout) {
            usleep(50_000);
        }
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        $json = json_decode((string) $out, true);
        $this->assertIsArray($json, "Child output: {$out} {$err}");

        return $json;
    }

    public function test_second_checkout_waits_for_the_coupon_lock_and_is_rejected_once_the_last_use_commits(): void
    {
        config(['database.connections.mysql_other' => config('database.connections.mysql')]);
        $other = DB::connection('mysql_other');

        // Customer X is mid-checkout: holds the coupon row lock and has inserted (not yet committed) the last use.
        $other->beginTransaction();
        $other->table('coupons')->where('code', $this->code)->lockForUpdate()->first();
        $other->table('orders')->insert([
            'order_number' => 'INV-C'.random_int(10000, 99999), 'customer_id' => $this->customers['x'], 'coupon_code' => $this->code,
            'shipping_recipient_name' => 'X', 'shipping_phone' => '0400000000', 'shipping_street_address' => 'S', 'grand_total' => 1,
        ]);

        // Customer Y checks out with the same coupon in another process: its unlocked pre-check still sees 0 uses.
        [$proc, $pipes] = $this->startChild($this->customers['y']);
        usleep(2_500_000);
        $this->assertTrue(proc_get_status($proc)['running'], 'Y should be blocked on the coupon row lock while X is mid-transaction.');

        $other->commit();
        $result = $this->finish($proc, $pipes);

        $this->assertSame('rejected', $result['status'], json_encode($result));
        $this->assertStringContainsString('usage limit', $result['message']);
        $this->assertSame(1, DB::table('orders')->where('coupon_code', $this->code)->count());
    }

    public function test_two_simultaneous_checkouts_for_the_last_use_place_exactly_one_order(): void
    {
        $a = $this->startChild($this->customers['y']);
        $b = $this->startChild($this->customers['z']);
        $results = [$this->finish(...$a), $this->finish(...$b)];

        $statuses = array_column($results, 'status');
        sort($statuses);
        $this->assertSame('placed', $statuses[0] === 'placed' ? $statuses[0] : $statuses[1], json_encode($results));
        $this->assertSame(1, count(array_filter($statuses, fn ($s) => $s === 'placed')), json_encode($results));
        $this->assertSame(1, DB::table('orders')->where('coupon_code', $this->code)->count());
    }
}
