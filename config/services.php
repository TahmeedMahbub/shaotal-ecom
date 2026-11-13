<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Stripe Checkout (hosted payment page) for card payments. Keys live only in .env. STRIPE_MODE must match the keys
    // (test = pk_test_/sk_test_, live = pk_live_/sk_live_): on a mismatch the card option stays off, so a live key can
    // never be switched on by accident. See docs/stripe-deployment.md.
    'stripe' => [
        'enabled' => (bool) env('STRIPE_ENABLED', false),
        'mode' => env('STRIPE_MODE', 'test'),
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
        'currency' => strtolower((string) env('STRIPE_CURRENCY', 'aud')),
        // Pinned so a Dashboard API upgrade can't change the payloads this code reads.
        'api_version' => env('STRIPE_API_VERSION', '2024-06-20'),
        // How long a Stripe Checkout page stays payable (Stripe allows 30 min to 24 h).
        'session_minutes' => (int) env('STRIPE_SESSION_MINUTES', 60),
        // Webhook signature timestamp tolerance (replay window), seconds.
        'webhook_tolerance' => (int) env('STRIPE_WEBHOOK_TOLERANCE', 300),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI', rtrim((string) env('APP_URL', 'http://localhost'), '/').'/auth/google/callback'),
    ],

];
