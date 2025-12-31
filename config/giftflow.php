<?php

return [
    /*
    |--------------------------------------------------------------------------
    | File storage paths
    |--------------------------------------------------------------------------
    */
    'codes_file' => env('GIFTFLOW_CODES_FILE', storage_path('app/giftcodes.json')),
    'events_file' => env('GIFTFLOW_EVENTS_FILE', storage_path('app/webhook_events.json')),

    /*
    |--------------------------------------------------------------------------
    | Webhook configuration
    |--------------------------------------------------------------------------
    */
    'webhook_secret' => env('GIFTFLOW_WEBHOOK_SECRET', 'giftflow_local_secret'),
    'issuer_url' => env(
        'GIFTFLOW_ISSUER_URL',
        rtrim(env('APP_URL', 'http://localhost'), '/').'/api/webhook/issuer-platform'
    ),

    /*
    |--------------------------------------------------------------------------
    | Defaults and seeds
    |--------------------------------------------------------------------------
    */
    'event_prefix' => 'evt_',
    'seed_codes' => [
        [
            'code' => 'GFLOW-TEST-0001',
            'status' => 'available',
            'product_id' => 'product_abc',
            'creator_id' => 'creator_123',
        ],
        [
            'code' => 'GFLOW-TEST-0002',
            'status' => 'available',
            'product_id' => 'product_abc',
            'creator_id' => 'creator_123',
        ],
        [
            'code' => 'GFLOW-USED-0003',
            'status' => 'redeemed',
            'product_id' => 'product_def',
            'creator_id' => 'creator_legacy',
            'redeemed_by' => 'used@example.com',
        ],
    ],
];
