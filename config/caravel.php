<?php

/*
 * Caravel Pay. Get credentials once with `php artisan caravel:connect 0xYourPolygonWallet`
 * (or POST /v1/connect) and put them in .env. Never commit the secret.
 */
return [
    // Your merchant id (mch_…) and signing secret.
    'merchant_id' => env('CARAVEL_MERCHANT_ID'),
    'secret' => env('CARAVEL_SECRET'),

    // Credentials you replaced (new wallet or rotated secret), kept until their open checkouts have
    // finished: their webhooks stay signed with the old secret. "mch_A:secretA,mch_B:secretB".
    'previous_credentials' => env('CARAVEL_PREVIOUS_CREDENTIALS', ''),

    // Who pays the fees for checkouts that don't set fee_mode themselves: "seller" (you absorb the card
    // provider's fee and the 2% Caravel fee), "split" (the card provider's fee is added to the buyer's total)
    // or "buyer" (the 2% Caravel fee is added too). Card surcharges are restricted in some countries.
    'fee_mode' => env('CARAVEL_FEE_MODE', 'seller'),

    // Only for checkouts that aren't priced in US dollars (send your own `amount` and `currency`: EUR, GBP,
    // SEK and so on). They are converted to US dollars when the checkout is created; buyers are charged in
    // US dollars and you are paid in USDC. A buffer adds a margin to the converted amount, to cover
    // exchange-rate movement until you convert your USDC. A percentage, 0 to 5; 0 charges the plain amount.
    'fx_buffer' => env('CARAVEL_FX_BUFFER', 0),

    // API origin. Leave empty for production; staging only: an https origin or http://127.0.0.1:port.
    'api_base' => env('CARAVEL_API_BASE', ''),

    // HTTP timeout for API calls, seconds. Webhooks must be answered within 10 seconds and the
    // webhook route re-fetches the checkout first, so keep this under about 8.
    'timeout' => (int) env('CARAVEL_TIMEOUT', 8),

    'webhook' => [
        // Register POST <path> (named "caravel.webhook"), outside the "web" group, so no CSRF token
        // applies: requests are authenticated by their HMAC signature instead.
        'enabled' => (bool) env('CARAVEL_WEBHOOK_ENABLED', true),
        'path' => env('CARAVEL_WEBHOOK_PATH', 'caravel/webhook'),
        // Extra callback URLs you send as callback_url (only the path + query matters for the signature).
        'urls' => array_filter(explode(',', (string) env('CARAVEL_WEBHOOK_URLS', ''))),
        // Cache store for processed event ids (null = the default store) and how long to remember them.
        'cache_store' => env('CARAVEL_WEBHOOK_CACHE_STORE'),
        'remember_seconds' => 172800,
    ],
];
