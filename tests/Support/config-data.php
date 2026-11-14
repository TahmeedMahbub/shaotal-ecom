<?php

/*
| TESTS ONLY: config_data values served by App\Support\StoreConfig::fake() (tests/TestCase.php, place-order-child.php),
| because the test databases have no config_data table. They mirror the current owner-approved values (the same examples
| as docs/config-data.md). The application itself never falls back to these: production reads config_data only.
*/
return [
    'shipping_fee_adelaide' => 5.00,
    'shipping_fee_australia' => 12.00,
    'free_shipping_threshold' => 299.00,
    'pickup_enabled' => true,
    'pickup_location' => ['label' => 'Free Pickup – West Croydon', 'suburb' => 'West Croydon', 'state' => 'SA', 'postcode' => '5008', 'details' => null],
    'online_payment_discount_percent' => 2,
    'online_payment_deposit_percent' => 30,
    'online_payment_balance_terms' => 'The remaining :balance is payable later and is never charged automatically.',
    'gift_wrap_base_fee' => 1.99,
    'gift_wrap_base_items' => 2,
    'gift_wrap_extra_fee' => 1.00,
    'payment_methods' => ['cod'],
    'customer_cancellable_statuses' => ['pending'],
    'returns_enabled' => false,
    'returns_window_days' => null,
    'return_reasons' => ['Damaged or faulty', 'Wrong item received', 'Item not as described', 'Other'],
    'approved_info_pages' => [],
    'contact_details' => ['email' => null, 'phone' => null, 'hours' => null],
    'delivery_copy' => [
        'announcement' => 'Delivery across Australia · Free shipping on orders of :free_over or more',
        'checkout_subtitle' => 'Secure checkout with delivery within Australia.',
        'checkout_promise' => 'Secure checkout · Delivery within Australia',
        'estimate' => null,
        'dispatch_status' => 'In Stock',
        'timeframe_title' => 'Delivery Within Australia',
        'timeframe' => 'We currently deliver to addresses within Australia only. You can follow your order’s progress in My Orders.',
        'rates_title' => 'Shipping Rates',
        'rates' => 'Adelaide delivery :adelaide, rest of Australia :flat, free pickup from West Croydon. Free delivery on orders of :free_over or more.',
        'returns_title' => 'Returns',
        'returns_text' => 'Our returns policy is being finalised. If there is a problem with your order, please contact us.',
    ],
];
