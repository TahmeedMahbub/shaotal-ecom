<?php

/*
| Storefront rules taken from the prepared design (design/checkout.html, design/assets/js/data.js).
| Anything the design does not define is deliberately left unset.
*/
return [
    'brand' => 'Bongo Curated',
    'tagline' => 'Artisan Crafted. Curated for Australia.',
    'currency_symbol' => 'A$',
    'country_code' => 'AU',

    // Design rule: free delivery over the threshold, flat fee otherwise.
    'shipping_flat' => 10.00,
    'free_shipping_over' => 100.00,

    // Payment methods offered at checkout. Card and PayPal need gateway credentials
    // that are not available yet, so only cash on delivery is enabled.
    'payment_methods' => ['cod'],

    // site_settings keys (JSON string values holding Cloudinary URLs) for the static home page images.
    'home_images' => [
        'home_living_image' => 'living',
        'home_jute_image' => 'jute',
        'home_story_image' => 'story',
    ],
];
