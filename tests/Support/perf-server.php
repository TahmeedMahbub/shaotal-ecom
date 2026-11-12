<?php

/*
| LOCAL MEASUREMENT ONLY. Server-side cost of storefront pages: wall time inside the HTTP kernel, query count, query
| time, HTML size and the size of the embedded boot JSON. Each request uses a fresh application instance.
|
|   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=shaotal_perf_test DB_USERNAME=root DB_PASSWORD= \
|   CACHE_STORE=file SESSION_DRIVER=file APP_DEBUG=false php tests/Support/perf-server.php [runs=5] > result.json
|
| Refuses anything but a local *_test database. Signed-in pages use customer perf@example.test (created if missing, with
| one order) in that database. Numbers are local (PHP CLI, no OPcache, no network): compare runs, not production.
*/

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';

$runs = (int) ($argv[1] ?? 5);
$root = realpath(__DIR__.'/../..');

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

// Signed-in customer + one order, and a file session holding the sign-in (same keys as App\Support\CustomerSession).
$app = $boot();
$cid = DB::table('customers')->where('email', 'perf@example.test')->value('id') ?: DB::table('customers')->insertGetId([
    'first_name' => 'Perf', 'email' => 'perf@example.test', 'status' => 'active', 'password_hash' => password_hash('Password123', PASSWORD_BCRYPT),
]);
if (! DB::table('customer_addresses')->where('customer_id', $cid)->exists()) {
    DB::table('customer_addresses')->insert(['customer_id' => $cid, 'label' => 'Home', 'recipient_name' => 'Perf', 'phone' => '0400000000', 'street_address' => '1 Test St', 'suburb' => 'Carlton', 'state' => 'VIC', 'postcode' => '3053', 'is_default' => 1]);
}
$order = DB::table('orders')->where('customer_id', $cid)->value('order_number');
if (! $order) {
    $order = 'INV-PERF01';
    $oid = DB::table('orders')->insertGetId(['order_number' => $order, 'customer_id' => $cid, 'shipping_recipient_name' => 'Perf', 'shipping_phone' => '0400000000', 'shipping_street_address' => '1 Test St', 'shipping_suburb' => 'Carlton', 'shipping_state' => 'VIC', 'shipping_postcode' => '3053', 'subtotal' => 55, 'grand_total' => 65, 'shipping_total' => 10, 'payment_method' => 'cod', 'placed_at' => now()]);
    DB::table('order_items')->insert(['order_id' => $oid, 'product_id' => 2, 'product_name' => 'Nakshi Kantha Cushion', 'unit_price' => 55, 'unit_selling_price' => 55, 'quantity' => 1, 'line_total' => 55]);
    DB::table('order_timeline')->insert(['order_id' => $oid, 'new_status' => 'pending', 'note' => 'Order placed']);
}
$session = $app['session']->driver('file');
$session->start();
$session->put('customer_id', (int) $cid);
$session->put('customer_pw', hash_hmac('sha256', 'customer-session|'.DB::table('customers')->where('id', $cid)->value('password_hash'), (string) config('app.key')));
$session->save();
$cookieName = config('session.cookie');
$cookie = $app['encrypter']->encrypt(CookieValuePrefix::create($cookieName, $app['encrypter']->getKey()).$session->getId(), false);

$pages = [
    'home' => ['/', false], 'listing' => ['/products', false], 'category' => ['/products?category=fashion', false],
    'subcategory' => ['/products?category=fashion&subcategory=t-shirts', false], 'search' => ['/products?search=jute', false],
    'product' => ['/products/essential-heavyweight-tee', false], 'bulk-order' => ['/bulk-order', false], 'request' => ['/request', false],
    'account' => ['/account', true], 'checkout' => ['/checkout', true], 'order' => ['/account/orders/'.$order, true],
];

$measure = function (string $path, bool $auth) use ($boot, $cookieName, $cookie) {
    $app = $boot();
    $q = 0;
    $qt = 0.0;
    DB::listen(function ($e) use (&$q, &$qt) {
        $q++;
        $qt += $e->time;
    });
    $req = Request::create($path, 'GET', [], $auth ? [$cookieName => $cookie] : []);
    $kernel = $app->make(Kernel::class);
    $t = hrtime(true);
    $res = $kernel->handle($req);
    $ms = (hrtime(true) - $t) / 1e6;
    $kernel->terminate($req, $res);
    $html = (string) $res->getContent();
    $bootBytes = preg_match('#<script type="application/json" id="boot-data">(.*?)</script>#s', $html, $m) ? strlen($m[1]) : 0;

    return ['status' => $res->getStatusCode(), 'ms' => round($ms, 1), 'queries' => $q, 'queryMs' => round($qt, 1), 'htmlBytes' => strlen($html), 'htmlGzip' => strlen(gzencode($html, 6)), 'bootBytes' => $bootBytes];
};

$median = function (array $xs) {
    sort($xs);

    return $xs[intdiv(count($xs), 2)];
};

$out = [];
foreach ($pages as $name => [$path, $auth]) {
    $boot();
    Cache::store('file')->flush();
    $cold = $measure($path, $auth);   // catalog cache empty
    $warm = [];
    for ($i = 0; $i < $runs; $i++) {
        $warm[] = $measure($path, $auth);
    }
    $out[$name] = [
        'path' => $path, 'status' => $warm[0]['status'],
        'coldMs' => $cold['ms'], 'coldQueries' => $cold['queries'], 'coldQueryMs' => $cold['queryMs'],
        'warmMs' => $median(array_column($warm, 'ms')), 'warmQueries' => $median(array_column($warm, 'queries')), 'warmQueryMs' => $median(array_column($warm, 'queryMs')),
        'htmlBytes' => $warm[0]['htmlBytes'], 'htmlGzip' => $warm[0]['htmlGzip'], 'bootBytes' => $warm[0]['bootBytes'],
    ];
    fwrite(STDERR, sprintf("%-12s %3d warm %6.1fms q=%2d (%5.1fms) | cold %6.1fms q=%2d | html %7d (gz %6d) boot %7d\n", $name, $out[$name]['status'], $out[$name]['warmMs'], $out[$name]['warmQueries'], $out[$name]['warmQueryMs'], $out[$name]['coldMs'], $out[$name]['coldQueries'], $out[$name]['htmlBytes'], $out[$name]['htmlGzip'], $out[$name]['bootBytes']));
}
echo json_encode($out, JSON_PRETTY_PRINT), "\n";
