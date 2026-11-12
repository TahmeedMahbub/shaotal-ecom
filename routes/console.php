<?php

use App\Models\Storefront;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('storefront:refresh-catalog', function () {
    Storefront::refresh();
    $this->info('Catalog cache invalidated: the next page request rebuilds it from the database.');
})->purpose('Show admin catalog changes immediately (otherwise they appear within ~1–6 minutes)');

/*
| Read-only check of the running configuration before/after a deploy (STOREFRONT_DEPLOYMENT_CHECKLIST.md). Prints only
| setting names and verdicts, never secret values. Exit code 1 when any FAIL is found.
*/
Artisan::command('storefront:deploy-check', function () {
    $rows = [];
    $add = function (string $level, string $item, string $note) use (&$rows) {
        $rows[] = [$level, $item, $note];
    };
    $url = (string) config('app.url');

    $add(app()->isProduction() ? 'OK' : 'FAIL', 'APP_ENV', app()->isProduction() ? 'production' : 'must be production (now: '.app()->environment().')');
    $add(config('app.debug') ? 'FAIL' : 'OK', 'APP_DEBUG', config('app.debug') ? 'must be false: errors would show code and settings' : 'false');
    $add(config('app.key') ? 'OK' : 'FAIL', 'APP_KEY', config('app.key') ? 'set' : 'missing: run php artisan key:generate once (never rotate casually: it signs sessions)');
    $add(str_starts_with($url, 'https://') ? 'OK' : 'FAIL', 'APP_URL', str_starts_with($url, 'https://') ? 'https' : 'must be the public https origin (canonical URLs, sitemap, robots.txt, cookies)');
    $add(config('app.timezone') === 'UTC' ? 'OK' : 'WARN', 'APP_TIMEZONE', config('app.timezone') === 'UTC' ? 'UTC (storage standard)' : 'expected UTC; use STOREFRONT_DISPLAY_TIMEZONE for display');
    $add(config('session.secure') ? 'OK' : 'FAIL', 'SESSION_SECURE_COOKIE', config('session.secure') ? 'true' : 'must be true over HTTPS');
    $add(in_array(config('session.driver'), ['file', 'redis', 'database', 'memcached'], true) ? 'OK' : 'FAIL', 'SESSION_DRIVER', (string) config('session.driver').(config('session.driver') === 'database' ? ' (needs a sessions table: not in sql/ecom.sql)' : ''));
    if (config('session.driver') === 'database') {
        $rows[array_key_last($rows)][0] = 'FAIL';
    }
    $cache = (string) config('cache.default');
    $add(in_array($cache, ['file', 'redis'], true) ? 'OK' : 'FAIL', 'CACHE_STORE', $cache.(in_array($cache, ['file', 'redis'], true) ? '' : ': use file (one server) or redis (several); database has no cache table in sql/ecom.sql'));
    $add(in_array(config('queue.default'), ['sync', 'redis'], true) ? 'OK' : 'WARN', 'QUEUE_CONNECTION', config('queue.default').' (the storefront queues nothing; database needs a jobs table)');
    $level = (string) config('logging.channels.'.config('logging.default').'.level', config('logging.channels.single.level'));
    $add($level === 'debug' ? 'WARN' : 'OK', 'LOG_LEVEL', $level.($level === 'debug' ? ': use warning or error in production' : ''));
    $add(in_array(config('mail.default'), ['log', 'array'], true) ? 'WARN' : 'OK', 'MAIL_MAILER', config('mail.default').(in_array(config('mail.default'), ['log', 'array'], true) ? ': password-reset emails will not be delivered' : ''));
    $add(config('database.connections.mysql.timezone') === '+00:00' ? 'OK' : 'WARN', 'DB_TIMEZONE', (string) config('database.connections.mysql.timezone'));
    $add(file_exists(public_path('robots.txt')) ? 'FAIL' : 'OK', 'public/robots.txt', file_exists(public_path('robots.txt')) ? 'remove it: it hides the dynamic /robots.txt' : 'absent (dynamic route serves robots.txt)');
    foreach (['.env', 'composer.json', 'storage'] as $f) {
        $add(file_exists(public_path($f)) ? 'FAIL' : 'OK', 'public/'.$f, file_exists(public_path($f)) ? 'must not be inside the web root' : 'not in web root');
    }
    foreach ([storage_path('framework/cache'), storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs'), base_path('bootstrap/cache')] as $dir) {
        $add(is_writable($dir) ? 'OK' : 'FAIL', str_replace(base_path().'/', '', $dir), is_writable($dir) ? 'writable' : 'must be writable by the web server user');
    }
    $add(config('storefront.display_timezone') !== 'UTC' ? 'OK' : 'WARN', 'STOREFRONT_DISPLAY_TIMEZONE', config('storefront.display_timezone') !== 'UTC' ? (string) config('storefront.display_timezone') : 'not set: customers see UTC times (owner decision)');
    $add(config('storefront.trusted_proxies') ? 'OK' : 'WARN', 'TRUSTED_PROXIES', config('storefront.trusted_proxies') ? 'set' : 'empty: set it if a load balancer/CDN terminates TLS');

    $this->table(['', 'Setting', 'Result'], $rows);
    $fails = count(array_filter($rows, fn ($r) => $r[0] === 'FAIL'));
    $fails ? $this->error("{$fails} blocking problem(s).") : $this->info('No blocking problems.');

    return $fails ? 1 : 0;
})->purpose('Check production configuration (read-only; prints no secrets)');
