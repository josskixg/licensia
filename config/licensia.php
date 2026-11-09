<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Certificate Version Prefix
    |--------------------------------------------------------------------------
    | Prefix prepended to all issued offline-verifiable certificates.
    | Format: LIC-v1.<base64url_payload>.<base64url_ed25519_signature>
    */
    'prefix' => env('LICENSIA_PREFIX', 'LIC-v1'),

    /*
    |--------------------------------------------------------------------------
    | Asymmetric Ed25519 Keypair (Hex-Encoded)
    |--------------------------------------------------------------------------
    | The 64-byte secret key signs certificates on your licensing server.
    | The 32-byte public key is embedded inside buyer applications for 100%
    | offline verification via sodium_crypto_sign_verify_detached().
    */
    'secret_key_hex' => env('LICENSIA_SECRET_KEY_HEX', ''),
    'public_key_hex' => env('LICENSIA_PUBLIC_KEY_HEX', ''),

    /*
    |--------------------------------------------------------------------------
    | Offline Heartbeat & Grace Period
    |--------------------------------------------------------------------------
    */
    'grace_period_hours' => (int) env('LICENSIA_GRACE_PERIOD_HOURS', 72),

    /*
    |--------------------------------------------------------------------------
    | Product Tiers & Default Seat Quotas
    |--------------------------------------------------------------------------
    */
    'tiers' => [
        'starter' => [
            'name' => 'Starter Single-Domain',
            'max_seats' => 1,
            'offline_allowed' => true,
            'updates_days' => 365,
        ],
        'pro' => [
            'name' => 'Pro Multi-Node',
            'max_seats' => 5,
            'offline_allowed' => true,
            'updates_days' => 365,
        ],
        'agency' => [
            'name' => 'Agency Unlimited Fleet',
            'max_seats' => 25,
            'offline_allowed' => true,
            'updates_days' => 730,
        ],
    ],
];
