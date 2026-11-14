<?php

/*
 * Child process for CouponConcurrencyTest: places ONE order through App\Models\Checkout (the same pricing → totals →
 * place path the controller uses) in its own PHP process and DB connection, then prints a JSON result.
 * Usage: php place-order-child.php <customerId> <productId> <couponCode>
 * Refuses to run unless the DB is a local *_test database (same rule as tests/TestCase.php).
 */

use App\Models\Checkout;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

// Use exactly the parent test's connection (passed as JSON), never .env's DB settings: on Windows an empty env var
// (e.g. DB_PASSWORD=) cannot be passed to a child process, so .env values would otherwise fill the gap.
$parent = json_decode((string) base64_decode((string) getenv('TEST_DB_CONNECTION')), true);
if (! is_array($parent)) {
    fwrite(STDERR, 'Refusing: TEST_DB_CONNECTION not provided.');
    exit(2);
}
config(['database.default' => 'mysql', 'database.connections.mysql' => $parent]);
DB::purge('mysql');

$conn = config('database.connections.mysql');
if (($conn['driver'] ?? '') !== 'mysql' || ! in_array($conn['host'] ?? '', ['127.0.0.1', 'localhost', '::1'], true) || ! str_ends_with((string) ($conn['database'] ?? ''), '_test')) {
    fwrite(STDERR, 'Refusing: not a local *_test MySQL database.');
    exit(2);
}

[, $customerId, $productId, $code] = $argv;
$address = ['fullName' => 'Concurrency', 'phone' => '0400000000', 'street' => '1 Race St', 'suburb' => 'Carlton', 'state' => 'VIC', 'postcode' => '3053'];

// Same config_data values as the parent test (the test database has no config_data table).
\App\Support\StoreConfig::fake(require __DIR__.'/config-data.php');

$priced = Checkout::priceLines([['productId' => (int) $productId, 'quantity' => 1]]);
$totals = Checkout::totals($priced['lines'], $code, (int) $customerId);
if ($totals['couponError']) {
    echo json_encode(['status' => 'precheck_rejected', 'message' => $totals['couponError']]);
    exit(0);
}

try {
    $order = Checkout::place($priced['lines'], $totals, $address, 'cod', (int) $customerId, null);
    echo json_encode(['status' => 'placed', 'order' => $order['orderNumber']]);
} catch (HttpException $e) {
    echo json_encode(['status' => 'rejected', 'code' => $e->getStatusCode(), 'message' => $e->getMessage()]);
}
