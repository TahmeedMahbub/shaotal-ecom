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

    // Design rule: free delivery over the threshold, flat fee otherwise. THE source of truth: checkout charges exactly
    // this (App\Models\Checkout::totals: free when the subtotal is MORE than free_shipping_over), and every delivery
    // message that mentions a threshold or fee is built from these two values.
    'shipping_flat' => 10.00,
    'free_shipping_over' => 100.00,

    // Delivery/returns wording shown on product, checkout and account pages. These are the design's original words and
    // have NOT been confirmed by the owner (no courier integration, express option, dispatch location or returns flow
    // exists in the code). Confirm or change each line before launch; set a line to null to hide it where supported.
    // See STOREFRONT_PRODUCTION_READINESS_PLAN.md §16 (delivery decisions).
    'delivery_copy' => [
        'carrier' => 'Australia Post',
        'checkout_badge' => 'Australia Post Express',
        'estimate' => '3 - 5 Business Days via Australia Post',
        'dispatch_status' => 'In Stock — Ready to dispatch from Sydney',
        'dispatch_point' => 'West Croydon Dispatch',
        'timeframe' => 'Dispatched from our Sydney hub within 24 hours. Express shipping takes 1–2 business days. Standard shipping takes 3–5 business days across AU & NZ.',
        'returns_short' => '30-Day Returns',
        'returns_title' => '30-Day Returns & Exchanges',
        'returns_text' => 'Not quite right? We offer 30-day hassle-free returns or size exchanges across Australia. Drop off at any Australia Post location.',
        'checkout_promise' => 'Fast Australian Dispatch & Free Returns',
        'announcement' => '✈️ Express Australian Delivery · Seamless Door-to-Door Shipping',
    ],

    // Load balancer / reverse proxy addresses whose X-Forwarded-* headers are trusted (comma-separated IPs/CIDRs, or *
    // when the app is only reachable through the proxy). Needed behind TLS-terminating proxies so Laravel sees https
    // (secure cookies, HSTS, https URLs). Empty = trust none (direct Apache/Nginx on the same machine).
    'trusted_proxies' => env('TRUSTED_PROXIES'),

    // Time zone for dates shown to customers (order pages). Storage stays UTC. Not assumed: set it to the business's
    // zone (e.g. Australia/Sydney) once confirmed; until then dates show in UTC, as before.
    'display_timezone' => env('STOREFRONT_DISPLAY_TIMEZONE', 'UTC'),

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
