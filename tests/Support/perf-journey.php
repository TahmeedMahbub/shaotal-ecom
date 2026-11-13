<?php

/*
| LOCAL MEASUREMENT ONLY. Server-side cost (wall time inside the HTTP kernel, DB query count/time, slowest queries) of the signed-in
| customer journey: checkout page, quote, order placement (COD, with and without gift wrapping), order history, order details.
| Each request boots a fresh application (like a PHP-FPM worker without OPcache would), so numbers are conservative and
| comparable between runs, not production values. Stripe latency is NOT included (card placement is measured in
| tests/browser/checkout-flow.mjs, and external latency is reported separately).
|
|   APP_ROOT=<repo root, default this repo> DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=shaotal_perf_test DB_USERNAME=root \
|   DB_PASSWORD= CACHE_STORE=file SESSION_DRIVER=file MAIL_MAILER=array php tests/Support/perf-journey.php [runs=15] > result.json
|
| Refuses anything but a local *_test database. Creates (and leaves) COD orders for perf@example.test in that scratch database.
*/

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

$root = realpath(getenv('APP_ROOT') ?: __DIR__.'/../..');
require $root.'/vendor/autoload.php';
$runs = (int) ($argv[1] ?? 15);

$boot = function () use ($root) {
    $app = require $root.'/bootstrap/app.php';
    $app->make(Kernel::class)->bootstrap();
    $c = config('database.connections.'.config('database.default'));
    if (! in_array($c['host'] ?? '', ['127.0.0.1', 'localhost'], true) || ! str_ends_with((string) ($c['database'] ?? ''), '_test')) {
        fwrite(STDERR, "Refusing: not a local *_test database\n");
        exit(1);
    }

    return $app;
};

$app = $boot();
$cid = DB::table('customers')->where('email', 'perf@example.test')->value('id');
if (! $cid) {
    fwrite(STDERR, "Run tests/Support/perf-server.php once first (creates perf@example.test).\n");
    exit(1);
}
$addressId = DB::table('customer_addresses')->where('customer_id', $cid)->value('id');
$session = $app['session']->driver('file');
$session->start();
$session->put('customer_id', (int) $cid);
$session->put('customer_pw', hash_hmac('sha256', 'customer-session|'.DB::table('customers')->where('id', $cid)->value('password_hash'), (string) config('app.key')));
$session->put('_token', 'perf-token');
$session->save();
$cookieName = config('session.cookie');
$cookie = $app['encrypter']->encrypt(CookieValuePrefix::create($cookieName, $app['encrypter']->getKey()).$session->getId(), false);

$items = fn (int $n) => array_slice([['productId' => 2, 'quantity' => 2], ['productId' => 3, 'quantity' => 1], ['productId' => 4, 'quantity' => 1]], 0, $n);
$measure = function (string $method, string $path, ?array $json = null) use ($boot, $cookieName, $cookie) {
    $app = $boot();
    $queries = [];
    DB::listen(function ($e) use (&$queries) {
        $queries[] = [$e->time, $e->sql];
    });
    $server = ['HTTP_ACCEPT' => 'application/json', 'HTTP_X_CSRF_TOKEN' => 'perf-token', 'CONTENT_TYPE' => 'application/json'];
    $req = Request::create($path, $method, [], [$cookieName => $cookie], [], $server, $json === null ? null : json_encode($json));
    $kernel = $app->make(Kernel::class);
    $t = hrtime(true);
    $res = $kernel->handle($req);
    $ms = (hrtime(true) - $t) / 1e6;
    $qHandle = count($queries);   // queries issued before the response was ready (the customer waits for these)
    $t2 = hrtime(true);
    $kernel->terminate($req, $res);   // work deferred until after the response (e.g. the confirmation email) runs here
    $afterMs = (hrtime(true) - $t2) / 1e6;
    if (getenv('PRINT_SQL') && str_contains($path, getenv('PRINT_SQL'))) {
        foreach ($queries as $i => $q) {
            fwrite(STDERR, sprintf('  %2d%s %5.2fms  %s
', $i + 1, $i < $qHandle ? ' ' : '*', $q[0], preg_replace('/\s+/', ' ', mb_substr($q[1], 0, 150))));
        }
        exit;
    }
    usort($queries, fn ($a, $b) => $b[0] <=> $a[0]);
    $app['db']->purge();   // each request boots a new app: close its connection so MySQL's connection limit is not hit

    return ['status' => $res->getStatusCode(), 'ms' => $ms, 'afterMs' => $afterMs, 'q' => count($queries), 'qHandle' => $qHandle, 'qms' => array_sum(array_column($queries, 0)),
        'slow' => array_map(fn ($q) => [round($q[0], 2), mb_substr($q[1], 0, 110)], array_slice($queries, 0, 3))];
};

$pct = function (array $xs, float $p) {
    sort($xs);

    return $xs[(int) min(count($xs) - 1, floor($p * count($xs)))];
};
$order = DB::table('orders')->where('customer_id', $cid)->orderByDesc('id')->value('order_number');
$n = 0;
$body = function (bool $gift) use ($items, $addressId, &$n) {
    $n++;

    $lines = $items(3);
    $lines[0]['quantity'] = 1 + ($n % 90);   // a different cart every time: an identical repeat is (correctly) answered from the replay cache

    return ['items' => $lines, 'paymentMethod' => 'cod', 'addressId' => $addressId,
        'gift' => $gift ? ['enabled' => true, 'message' => 'perf '.$n.' '.microtime(true), 'items' => [['productId' => 2, 'quantity' => 1], ['productId' => 3, 'quantity' => 1]]] : null,
        'coupon' => null, 'nonce' => $n.microtime(true)];
};
$scenarios = [
    'checkout page' => fn () => ['GET', '/checkout', null],
    'quote (3 lines)' => fn () => ['POST', '/checkout/quote', ['items' => $items(3), 'gift' => null]],
    'place COD' => fn () => ['POST', '/checkout/place', $body(false)],
    'place COD + gift' => fn () => ['POST', '/checkout/place', $body(true)],
    'order history (JSON)' => fn () => ['GET', '/account/orders', null],
    'order details' => fn () => ['GET', '/account/orders/'.$order, null],
];

$out = [];
foreach ($scenarios as $name => $make) {
    Cache::store('file')->flush();
    $rows = [];
    // POST /checkout/place is throttled at 20 per minute (as it must be): stay under it, or the samples would be 429s.
    $n_runs = str_starts_with($name, 'place') ? min($runs, 17) : $runs;
    for ($i = 0; $i < $n_runs + 1; $i++) {   // run 0 = cold catalog cache, kept out of the percentiles but reported
        [$m, $p, $j] = $make();
        $rows[] = $measure($m, $p, $j);
        if ($rows[count($rows) - 1]['status'] >= 400) {
            fwrite(STDERR, "  ! $name returned HTTP ".$rows[count($rows) - 1]['status'].'
');
        }
    }
    $cold = array_shift($rows);
    $ms = array_column($rows, 'ms');
    $out[$name] = [
        'status' => $rows[0]['status'], 'runs' => count($rows), 'coldMs' => round($cold['ms'], 1),
        'avgMs' => round(array_sum($ms) / count($ms), 1), 'p50Ms' => round($pct($ms, 0.5), 1), 'p95Ms' => round($pct($ms, 0.95), 1), 'maxMs' => round(max($ms), 1),
        'queries' => (int) $pct(array_column($rows, 'q'), 0.5), 'queriesBeforeResponse' => (int) $pct(array_column($rows, 'qHandle'), 0.5), 'queryMs' => round($pct(array_column($rows, 'qms'), 0.5), 1),
        'afterResponseMs' => round($pct(array_column($rows, 'afterMs'), 0.5), 1), 'slowest' => $rows[0]['slow'],
    ];
    fwrite(STDERR, sprintf("%-22s %3d  avg %6.1f  p50 %6.1f  p95 %6.1f  cold %6.1f | q=%2d/%2d (%5.1fms) | after-response %6.1fms\n", $name, $out[$name]['status'], $out[$name]['avgMs'], $out[$name]['p50Ms'], $out[$name]['p95Ms'], $out[$name]['coldMs'], $out[$name]['queriesBeforeResponse'], $out[$name]['queries'], $out[$name]['queryMs'], $out[$name]['afterResponseMs']));
}
echo json_encode($out, JSON_PRETTY_PRINT), "\n";
