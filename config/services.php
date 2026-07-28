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
        'key'            => env('STRIPE_KEY'),
        'secret'         => env('STRIPE_SECRET'),
        'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
    ],

    'tap' => [
        'secret_key'      => env('TAP_SECRET_KEY'),
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
        'serverless' => filter_var(env('VERCEL', false), FILTER_VALIDATE_BOOLEAN),
    ],

];
