<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
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

    'fatora' => [
        'api_key' => env('FATORA_API_KEY'),
    ],

    'stripe' => [
        'key' => env('STRIPE_KEY'),
        'secret' => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'tap' => [
        'secret_key' => env('TAP_SECRET_KEY'),
        'publishable_key' => env('TAP_PUBLISHABLE_KEY'),
    ],

    'payment' => [
        'gateway' => env('PAYMENT_GATEWAY', 'fatora'), // fatora | stripe | tap
    ],

    'vercel_blob' => [
        'enabled' => filter_var(env('VERCEL', false), FILTER_VALIDATE_BOOLEAN)
            && (
                trim((string) env('BLOB_STORE_ID', '')) !== ''
                || trim((string) env('BLOB_READ_WRITE_TOKEN', '')) !== ''
            ),
        'store_id' => env('BLOB_STORE_ID'),
        'token' => env('BLOB_READ_WRITE_TOKEN'),
        'handle_url' => env('BLOB_UPLOAD_HANDLE_URL', '/api/blob-upload'),
        'download_handle_url' => env('BLOB_DOWNLOAD_HANDLE_URL', '/api/blob-download'),
        'serverless' => filter_var(env('VERCEL', false), FILTER_VALIDATE_BOOLEAN),
    ],

    'vercel' => [
        'cron_secret' => env('CRON_SECRET'),
    ],

    'jitsi' => [
        // Use a self-hosted Jitsi domain in production. `meet.jit.si` is a
        // convenient fallback for local development.
        'domain' => env('JITSI_DOMAIN', 'meet.jit.si'),
        // Leave these blank for an anonymous deployment. Configure both when
        // the Jitsi server uses JWT/token authentication.
        'app_id' => env('JITSI_APP_ID'),
        'app_secret' => env('JITSI_APP_SECRET'),
        'require_auth' => filter_var(env('JITSI_REQUIRE_AUTH', true), FILTER_VALIDATE_BOOLEAN),
        'token_ttl' => (int) env('JITSI_TOKEN_TTL', 21600),
        'whiteboard' => [
            'enabled' => filter_var(env('JITSI_WHITEBOARD_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'collab_server_base_url' => env('JITSI_WHITEBOARD_COLLAB_SERVER'),
            'user_limit' => (int) env('JITSI_WHITEBOARD_USER_LIMIT', 30),
        ],
    ],

];
