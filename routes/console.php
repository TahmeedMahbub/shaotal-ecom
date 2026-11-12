<?php

use App\Http\Controllers\PageController;
use App\Models\Storefront;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;

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
    // Canonicals, Open Graph, JSON-LD, the sitemap and robots.txt are built from APP_URL: a local/test host there would
    // publish unreachable URLs to search engines.
    $host = strtolower((string) parse_url($url, PHP_URL_HOST));
    $localHost = $host === '' || in_array(trim($host, '[]'), ['localhost', '0.0.0.0', '::1'], true) || str_starts_with($host, '127.')
        || (bool) preg_match('/\.(test|local|localhost|invalid)$/', $host);
    $add($localHost ? 'FAIL' : 'OK', 'APP_URL host', $localHost ? 'local/test host ('.($host ?: 'none').'): set the public domain (canonical URLs and the sitemap use it)' : $host);
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
    foreach (['.env', 'composer.json'] as $f) {
        $add(file_exists(public_path($f)) ? 'FAIL' : 'OK', 'public/'.$f, file_exists(public_path($f)) ? 'must not be inside the web root' : 'not in web root');
    }
    // public/storage must be the `php artisan storage:link` symlink to storage/app/public (sourcing-request photos are
    // served from there) — never a copy of, or a link to, the whole storage directory.
    $link = public_path('storage');
    $target = is_link($link) ? realpath($link) : false;
    $publicDisk = realpath(storage_path('app/public'));
    $linkOk = $target && $publicDisk && $target === $publicDisk;
    $add($linkOk ? 'OK' : 'FAIL', 'public/storage', $linkOk ? 'symlink to storage/app/public' : (file_exists($link) || is_link($link)
        ? 'must be only the storage:link symlink to storage/app/public (remove it and run php artisan storage:link)'
        : 'missing: run php artisan storage:link (staff cannot view sourcing-request photos without it)'));
    foreach ([storage_path('framework/cache'), storage_path('framework/sessions'), storage_path('framework/views'), storage_path('logs'), storage_path('app/public'), base_path('bootstrap/cache')] as $dir) {
        $add(is_writable($dir) ? 'OK' : 'FAIL', str_replace(base_path().'/', '', $dir), is_writable($dir) ? 'writable' : 'must be writable by the web server user');
    }
    // Uploaded photos are decoded and re-encoded with GD (App\Support\ImageUpload); without it every upload is refused.
    $gd = extension_loaded('gd') && function_exists('imagecreatefromstring') && function_exists('imagejpeg') && function_exists('imagepng') && function_exists('imagewebp');
    $add($gd ? 'OK' : 'FAIL', 'PHP gd extension', $gd ? 'loaded (JPEG, PNG, WebP)' : 'required with JPEG, PNG and WebP support: photo uploads are re-encoded for safety');

    $tz = (string) config('storefront.display_timezone');
    $tzValid = in_array($tz, timezone_identifiers_list(), true);
    $add(! $tzValid ? 'FAIL' : ($tz === 'Australia/Sydney' ? 'OK' : 'WARN'), 'STOREFRONT_DISPLAY_TIMEZONE', ! $tzValid ? "not a valid time zone: {$tz}" : ($tz === 'Australia/Sydney' ? $tz : "{$tz} (owner decision is Australia/Sydney; unset it to use the default)"));
    $add(config('storefront.trusted_proxies') ? 'OK' : 'WARN', 'TRUSTED_PROXIES', config('storefront.trusted_proxies') ? 'set' : 'empty: set it if a load balancer/CDN terminates TLS');

    // Policy pages stay "being finalised" (noindex, not in the sitemap) until the owner approves their wording.
    $pages = array_keys(PageController::INFO_PAGES);
    $listed = (array) config('storefront.approved_pages');
    $unknown = array_diff($listed, $pages);
    $pending = array_diff($pages, $listed);
    $add($unknown ? 'FAIL' : ($pending ? 'WARN' : 'OK'), 'STOREFRONT_APPROVED_PAGES', $unknown
        ? 'unknown page(s): '.implode(', ', $unknown).' (allowed: '.implode(', ', $pages).')'
        : ($pending ? 'not approved yet (shown as drafts, noindex): '.implode(', ', $pending) : 'all policy pages approved'));
    $contact = array_filter((array) config('storefront.contact'));
    $add($contact ? 'OK' : 'WARN', 'STOREFRONT_CONTACT_*', $contact ? 'set: '.implode(', ', array_keys($contact)) : 'none set: /contact shows only the form (owner to provide email/phone/hours)');

    // Return requests: off by default. Turning them on needs an approved policy, the tables in sql/proposed_changes.sql §4
    // and the admin review screens. The database is only queried when the switch is on.
    if (config('storefront.returns.enabled')) {
        try {
            $tables = Schema::hasTable('order_return_requests') && Schema::hasTable('order_return_request_events');
        } catch (Throwable) {
            $tables = false;
        }
        $add($tables ? 'WARN' : 'FAIL', 'STOREFRONT_RETURNS_ENABLED', $tables
            ? 'ON: confirm the owner approved the returns policy and the admin app can review requests'
            : 'ON but the return-request tables are missing (sql/proposed_changes.sql §4): the form stays hidden');
    } else {
        $add('OK', 'STOREFRONT_RETURNS_ENABLED', 'off (default; returns policy, tables and admin review still pending)');
    }
    $window = config('storefront.returns.window_days');
    $add($window === null || $window > 0 ? 'OK' : 'FAIL', 'STOREFRONT_RETURNS_WINDOW_DAYS', $window === null ? 'not set (no limit; owner decision pending)' : ($window > 0 ? "{$window} days after delivery" : 'must be a positive number of days, or unset'));

    $this->table(['', 'Setting', 'Result'], $rows);
    $fails = count(array_filter($rows, fn ($r) => $r[0] === 'FAIL'));
    $fails ? $this->error("{$fails} blocking problem(s).") : $this->info('No blocking problems.');

    return $fails ? 1 : 0;
})->purpose('Check production configuration (read-only; prints no secrets)');
