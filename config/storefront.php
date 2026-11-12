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

    // Shipping (owner decision, Part 6): flat fee below the threshold, free AT or above it. THE source of truth:
    // checkout charges exactly this (App\Models\Checkout::totals) and every delivery message is built from it.
    'shipping_flat' => 10.00,
    'free_shipping_over' => 100.00,

    // Delivery/returns wording (owner decisions, Part 6): Australia only; no express service; no carrier or live-tracking
    // claims; no returns promise until a policy is approved. :free_over and :flat are replaced with the amounts above.
    // A null line is hidden. Do not add dispatch locations, delivery times or refund terms without owner approval.
    'delivery_copy' => [
        'area' => 'Australia',
        'checkout_badge' => 'Delivery within Australia',
        'estimate' => null,                       // no confirmed delivery time: the success dialog row is hidden
        'dispatch_status' => 'In Stock',
        'dispatch_point' => 'Delivery within Australia',
        'free_shipping_short' => 'Free shipping on orders of :free_over or more',
        'timeframe_title' => 'Delivery Within Australia',
        'timeframe' => 'We currently deliver to addresses within Australia only. You can follow your order’s progress in My Orders.',
        'rates_title' => 'Shipping Rates',
        'rates' => 'Free shipping on orders of :free_over or more. A flat :flat shipping fee applies to orders under :free_over.',
        'returns_short' => null,                  // was "30-Day Returns": no approved returns policy yet
        'returns_title' => 'Returns',
        'returns_text' => 'Our returns policy is being finalised. If there is a problem with your order, please contact us.',
        'checkout_subtitle' => 'Secure checkout with delivery within Australia.',
        'checkout_promise' => 'Secure checkout · Delivery within Australia',
        'announcement' => 'Delivery across Australia · Free shipping on orders of :free_over or more',
    ],

    // Return requests (Part 6). OFF until (1) the owner approves a returns policy and (2) the tables in
    // sql/proposed_changes.sql §4 exist. When on, a customer can ask for a return on their own DELIVERED order, once;
    // staff review it in the admin app. Nothing is refunded or restocked automatically.
    'returns' => [
        'enabled' => (bool) env('STOREFRONT_RETURNS_ENABLED', false),
        // Days after delivery during which a request can be sent. null = no limit set (owner decision pending); a
        // limit needs a recorded delivery time (order_timeline row entering "delivered").
        'window_days' => env('STOREFRONT_RETURNS_WINDOW_DAYS') !== null ? (int) env('STOREFRONT_RETURNS_WINDOW_DAYS') : null,
        'reasons' => ['Damaged or faulty', 'Wrong item received', 'Item not as described', 'Other'],
    ],

    // Information pages (/contact, /shipping, /returns, /terms, /privacy). Until a page's wording is approved by the owner
    // (and, for terms/privacy, a legal adviser) it shows a "being finalised" notice, is noindex and is left out of the
    // sitemap. Approve pages with STOREFRONT_APPROVED_PAGES=shipping,returns,terms,privacy,contact.
    'approved_pages' => array_values(array_filter(array_map('trim', explode(',', (string) env('STOREFRONT_APPROVED_PAGES', ''))))),

    // Public contact details shown on /contact. Not invented: empty until the owner provides them (the contact form
    // works without them and stores messages in contact_messages for the admin app).
    'contact' => [
        'email' => env('STOREFRONT_CONTACT_EMAIL'),
        'phone' => env('STOREFRONT_CONTACT_PHONE'),
        'hours' => env('STOREFRONT_CONTACT_HOURS'),
    ],

    // Load balancer / reverse proxy addresses whose X-Forwarded-* headers are trusted (comma-separated IPs/CIDRs, or *
    // when the app is only reachable through the proxy). Needed behind TLS-terminating proxies so Laravel sees https
    // (secure cookies, HSTS, https URLs). Empty = trust none (direct Apache/Nginx on the same machine).
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Time zone for dates shown to customers (order history, order details, tracking timeline, requests). Owner
    // decision (Part 6): Australia/Sydney. Storage stays UTC; App\Support\DisplayTime converts once for display.
    'display_timezone' => env('STOREFRONT_DISPLAY_TIMEZONE', 'Australia/Sydney'),

    // Payment methods offered at checkout. Card and PayPal need gateway credentials
    // that are not available yet, so only cash on delivery is enabled.
    'payment_methods' => ['cod'],

    // Order statuses in which a customer may cancel their own order from the order page. Only "pending" (placed, not yet
    // confirmed by staff) is supported; set to [] to turn customer cancellation off. Returns need an admin-side flow and
    // a schema change, so they are not offered online (see STOREFRONT_PRODUCTION_READINESS_PLAN.md, Part 3).
    'customer_cancellable_statuses' => ['pending'],

    // site_settings keys (JSON string values holding Cloudinary URLs) for the static home page images.
    'home_images' => [
        'home_living_image' => 'living',
        'home_jute_image' => 'jute',
        'home_story_image' => 'story',
    ],
];
