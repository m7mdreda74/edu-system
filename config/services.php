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

    'skipcash' => [
        'client_id'  => env('SKIPCASH_CLIENT_ID'),
        'key_id'     => env('SKIPCASH_KEY_ID'),
        'secret_key' => env('SKIPCASH_SECRET_KEY'),
        'live'       => env('SKIPCASH_LIVE', false),
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
        'gateway' => env('PAYMENT_GATEWAY', 'skipcash'), // skipcash | fatora | stripe | tap
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

    'video_streaming' => [
        'provider' => env('VIDEO_STREAMING_PROVIDER', 'cloudflare_stream'),
        'playback_ttl' => (int) env('VIDEO_PLAYBACK_TTL', 900),
    ],

    'cloudflare_stream' => [
        'account_id' => env('CLOUDFLARE_STREAM_ACCOUNT_ID'),
        'api_token' => env('CLOUDFLARE_STREAM_API_TOKEN'),
        'api_base_url' => env('CLOUDFLARE_STREAM_API_BASE_URL', 'https://api.cloudflare.com/client/v4/accounts/'.env('CLOUDFLARE_STREAM_ACCOUNT_ID')),
        'playback_host' => env('CLOUDFLARE_STREAM_PLAYBACK_HOST'),
        'webhook_secret' => env('CLOUDFLARE_STREAM_WEBHOOK_SECRET'),
        'webhook_tolerance' => (int) env('CLOUDFLARE_STREAM_WEBHOOK_TOLERANCE', 300),
        'upload_ttl' => (int) env('CLOUDFLARE_STREAM_UPLOAD_TTL', 3600),
        'max_duration_seconds' => (int) env('VIDEO_MAX_DURATION_SECONDS', 14400),
        'timeout' => (int) env('CLOUDFLARE_STREAM_TIMEOUT', 20),
    ],

    'vercel' => [
        'cron_secret' => env('CRON_SECRET'),
    ],

    'turnstile' => [
        'enabled' => filter_var(env('TURNSTILE_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
        'siteverify_url' => env(
            'TURNSTILE_SITEVERIFY_URL',
            'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        ),
        'timeout' => (int) env('TURNSTILE_TIMEOUT', 5),
    ],

    'jitsi' => [
        // Use a self-hosted Jitsi domain in production. `meet.jit.si` is a
        // convenient fallback for local development.
        'domain' => env('JITSI_DOMAIN', 'meet.jit.si'),
        // Leave these blank for an anonymous deployment. Configure both when
        // the Jitsi server uses JWT/token authentication.
        'app_id' => env('JITSI_APP_ID'),
        'app_secret' => env('JITSI_APP_SECRET'),
        // Keep this false for a public/anonymous Jitsi deployment. Set it
        // true together with JITSI_APP_ID and JITSI_APP_SECRET for a private
        // token-authenticated deployment.
        'require_auth' => filter_var(env('JITSI_REQUIRE_AUTH', false), FILTER_VALIDATE_BOOLEAN),
        'token_ttl' => (int) env('JITSI_TOKEN_TTL', 21600),
        'whiteboard' => [
            'enabled' => filter_var(env('JITSI_WHITEBOARD_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            'collab_server_base_url' => env('JITSI_WHITEBOARD_COLLAB_SERVER'),
            'user_limit' => (int) env('JITSI_WHITEBOARD_USER_LIMIT', 30),
        ],
        'recording' => [
            // This is Jitsi's server-side file recording, not browser-local recording.
            'enabled' => filter_var(env('JITSI_FILE_RECORDINGS_ENABLED', true), FILTER_VALIDATE_BOOLEAN),
            // Start recording as soon as the teacher joins a live room. The
            // Jitsi file-recording service must be configured separately.
            'auto_start' => filter_var(env('JITSI_FILE_RECORDINGS_AUTO_START', true), FILTER_VALIDATE_BOOLEAN),
            'allowed_hosts' => array_values(array_filter(array_map(
                'trim',
                explode(',', (string) env('JITSI_RECORDING_ALLOWED_HOSTS', '')),
            ))),
        ],
    ],

];
