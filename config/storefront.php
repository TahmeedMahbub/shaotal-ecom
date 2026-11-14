<?php

/*
| Storefront rules taken from the prepared design (design/checkout.html, design/assets/js/data.js).
| Anything the design does not define is deliberately left unset.
*/
return [
    'brand' => 'Shaotal',
    'tagline' => 'Artisan Crafted. Curated for Australia.',
    'currency_symbol' => 'A$',
    'country_code' => 'AU',

    // Business rules and wording managed from the admin app live in the config_data table, read ONLY through
    // App\Support\StoreConfig (keys, shapes and required rows: StoreConfig::KEYS; docs/config-data.md). Do not re-add
    // shipping fees, the free-shipping threshold, pickup, payment methods, online discount/deposit, gift-wrap fees,
    // cancellation/returns rules, approved policy pages, contact details or delivery wording here.

    // Load balancer / reverse proxy addresses whose X-Forwarded-* headers are trusted (comma-separated IPs/CIDRs, or *
    // when the app is only reachable through the proxy). Needed behind TLS-terminating proxies so Laravel sees https
    // (secure cookies, HSTS, https URLs). Empty = trust none (direct Apache/Nginx on the same machine).
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Time zone for dates shown to customers (order history, order details, tracking timeline, requests). Owner
    // decision (Part 6): Australia/Sydney. Storage stays UTC; App\Support\DisplayTime converts once for display.
    'display_timezone' => env('STOREFRONT_DISPLAY_TIMEZONE', 'Australia/Sydney'),

    // site_settings keys (JSON string values holding Cloudinary URLs) for the static home page images.
    'home_images' => [
        'home_living_image' => 'living',
        'home_jute_image' => 'jute',
        'home_story_image' => 'story',
    ],

    // Queue connection for the verification / password-reset emails (App\Support\AuthMail). "deferred" needs no
    // worker (sent right after the response); "database" (jobs table, sql/proposed_changes.sql) or "redis" need a running
    // `php artisan queue:work`; "failover" tries the real queue, then "deferred". The browser never waits for SMTP.
    'auth_mail_queue' => env('AUTH_MAIL_QUEUE_CONNECTION', 'deferred'),

    // Queue connection for the order confirmation email (App\Jobs\SendOrderConfirmationEmail, rules in App\Support\OrderEmails).
    // Same options as above; defaults to the auth-mail connection. sql/ecom.sql has no jobs table, so "database" needs it added first.
    'order_mail_queue' => env('ORDER_MAIL_QUEUE_CONNECTION', env('AUTH_MAIL_QUEUE_CONNECTION', 'deferred')),
];
