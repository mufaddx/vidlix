<?php

return [
    'currency' => env('VIDLIX_CURRENCY', 'INR'),
    'public_form_honeypot' => 'company_website',

    'providers' => [
        'email' => env('EMAIL_PROVIDER', 'unconfigured'),
        'payment' => env('PAYMENT_PROVIDER', 'unconfigured'),
        'payout' => env('PAYOUT_PROVIDER', env('PAYMENT_PROVIDER', 'unconfigured')),
        'instagram' => env('INSTAGRAM_PROVIDER', 'unconfigured'),
        'storage' => env('FILESYSTEM_DISK', 'local'),
        'push' => env('PUSH_PROVIDER', 'unconfigured'),
        'broadcast' => env('BROADCAST_CONNECTION', 'log'),
    ],

    /*
     | Webhook secrets and the signature scheme each provider actually uses.
     | Schemes are implemented in App\Services\Webhooks\SignatureVerifier.
     |   hmac_hex        - hex HMAC-SHA256 of the raw body (Razorpay, generic)
     |   hub_signature   - "sha256=<hex hmac>" in X-Hub-Signature-256 (Meta)
     |   sendgrid_ecdsa  - ECDSA over timestamp+body (SendGrid Event Webhook)
     |   svix            - Svix v1 signature (Resend)
     |   basic           - HTTP Basic credentials (Postmark inbound)
     */
    'webhooks' => [
        'email_secret' => env('EMAIL_WEBHOOK_SECRET'),
        'payment_secret' => env('PAYMENT_WEBHOOK_SECRET'),
        'payout_secret' => env('PAYOUT_WEBHOOK_SECRET'),
        'meta_secret' => env('META_WEBHOOK_VERIFY_TOKEN'),
        'meta_app_secret' => env('META_APP_SECRET'),
        'schemes' => [
            'payment' => env('PAYMENT_WEBHOOK_SCHEME', 'hmac_hex'),
            'payout' => env('PAYOUT_WEBHOOK_SCHEME', 'hmac_hex'),
            'email' => env('EMAIL_WEBHOOK_SCHEME', 'hmac_hex'),
            'meta' => env('META_WEBHOOK_SCHEME', 'hub_signature'),
        ],
        'headers' => [
            // Extra header names checked for hmac_hex, in addition to X-Webhook-Signature.
            'payment' => ['X-Razorpay-Signature'],
            'payout' => ['X-Razorpay-Signature'],
            'email' => ['X-Postmark-Signature', 'X-Mailer-Signature'],
        ],
    ],

    'payment' => [
        'driver' => env('PAYMENT_PROVIDER', 'unconfigured'),
        'key_id' => env('PAYMENT_KEY_ID'),
        'key_secret' => env('PAYMENT_KEY_SECRET'),
        'api_base' => env('PAYMENT_API_BASE', 'https://api.razorpay.com/v1'),
        'callback_url' => env('PAYMENT_CALLBACK_URL'),
        'timeout' => (int) env('PAYMENT_HTTP_TIMEOUT', 20),
    ],

    'payout' => [
        'driver' => env('PAYOUT_PROVIDER', env('PAYMENT_PROVIDER', 'unconfigured')),
        'key_id' => env('PAYOUT_KEY_ID', env('PAYMENT_KEY_ID')),
        'key_secret' => env('PAYOUT_KEY_SECRET', env('PAYMENT_KEY_SECRET')),
        'api_base' => env('PAYOUT_API_BASE', 'https://api.razorpay.com/v1'),
        // RazorpayX source account. Payouts stay unconfigured without it.
        'account_number' => env('PAYOUT_ACCOUNT_NUMBER'),
        'mode' => env('PAYOUT_MODE', 'IMPS'),
        'purpose' => env('PAYOUT_PURPOSE', 'payout'),
        'timeout' => (int) env('PAYOUT_HTTP_TIMEOUT', 20),
    ],

    'email' => [
        'driver' => env('EMAIL_PROVIDER', 'unconfigured'),
        'api_key' => env('EMAIL_API_KEY'),
        'api_base' => env('EMAIL_API_BASE'),
        'from_address' => env('MAIL_FROM_ADDRESS'),
        'from_name' => env('MAIL_FROM_NAME', env('APP_NAME', 'Vidlix')),
        // Reply-To routing: reply+<routing_token>@<inbound_domain>
        'inbound_domain' => env('EMAIL_INBOUND_DOMAIN'),
        'reply_prefix' => env('EMAIL_REPLY_PREFIX', 'reply'),
        'webhook_username' => env('EMAIL_WEBHOOK_USERNAME'),
        'webhook_password' => env('EMAIL_WEBHOOK_PASSWORD'),
        'verification_key' => env('EMAIL_WEBHOOK_PUBLIC_KEY'),
        'timeout' => (int) env('EMAIL_HTTP_TIMEOUT', 20),
    ],

    'instagram' => [
        'driver' => env('INSTAGRAM_PROVIDER', 'unconfigured'),
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'redirect_uri' => env('META_REDIRECT_URI'),
        'graph_base' => env('META_GRAPH_BASE', 'https://graph.facebook.com'),
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),
        'scopes' => array_values(array_filter(explode(',', (string) env(
            'META_SCOPES',
            'instagram_basic,instagram_manage_insights,pages_show_list,pages_read_engagement,business_management',
        )))),
        'timeout' => (int) env('META_HTTP_TIMEOUT', 20),
    ],

    'push' => [
        'driver' => env('PUSH_PROVIDER', 'unconfigured'),
        // Path to a Firebase service-account JSON file (never commit it).
        'fcm_credentials' => env('FCM_CREDENTIALS_PATH'),
        'fcm_project_id' => env('FCM_PROJECT_ID'),
        'timeout' => (int) env('PUSH_HTTP_TIMEOUT', 15),
    ],

    'media' => [
        // Disk used for project files / portfolio media. Never MySQL.
        'disk' => env('MEDIA_DISK') ?: env('FILESYSTEM_DISK', 'local'),
        'max_bytes' => (int) env('MEDIA_MAX_BYTES', 512 * 1024 * 1024),
        'signed_url_minutes' => (int) env('MEDIA_SIGNED_URL_MINUTES', 15),
    ],

    'social' => [
        'instagram' => env('VIDLIX_SOCIAL_INSTAGRAM', 'https://www.instagram.com/vidlix'),
        'youtube' => env('VIDLIX_SOCIAL_YOUTUBE', 'https://www.youtube.com/@vidlix'),
        'x' => env('VIDLIX_SOCIAL_X', 'https://x.com/vidlix'),
        'linkedin' => env('VIDLIX_SOCIAL_LINKEDIN', 'https://www.linkedin.com/company/vidlix'),
    ],
];
