<?php

/*
| LOCAL / CI TEST DATABASES ONLY. Creates the customer the browser smoke test signs in with (perf@example.test /
| Password123) plus one saved address. Idempotent. Refuses anything but a local *_test database.
|   DB_CONNECTION=mysql DB_HOST=127.0.0.1 DB_DATABASE=shaotal_storefront_test php tests/Support/browser-fixtures.php
*/

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$c = config('database.connections.'.config('database.default'));
if (! in_array($c['host'] ?? '', ['127.0.0.1', 'localhost'], true) || ! str_ends_with((string) ($c['database'] ?? ''), '_test')) {
    fwrite(STDERR, "Refusing: not a local *_test database\n");
    exit(1);
}

$id = DB::table('customers')->where('email', 'perf@example.test')->value('id') ?: DB::table('customers')->insertGetId([
    'first_name' => 'Perf', 'email' => 'perf@example.test', 'status' => 'active', 'password_hash' => password_hash('Password123', PASSWORD_BCRYPT),
]);
if (! DB::table('customer_addresses')->where('customer_id', $id)->exists()) {
    DB::table('customer_addresses')->insert(['customer_id' => $id, 'label' => 'Home', 'recipient_name' => 'Perf', 'phone' => '0400000000', 'street_address' => '1 Test St', 'suburb' => 'Carlton', 'state' => 'VIC', 'postcode' => '3053', 'is_default' => 1]);
}
echo "browser fixtures ready (customer {$id})\n";
