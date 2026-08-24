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

    'talksasa' => [
        'api_token' => env('TALKSASA_API_TOKEN'),
        'sender_id' => env('TALKSASA_SENDER_ID', 'EldoGas'),
        'api_url'   => env('TALKSASA_API_URL', 'https://bulksms.talksasa.com/api/v3/sms/send'),
    ],

    'firebase' => [
        // FCM HTTP v1. The legacy server-key endpoint was decommissioned by
        // Google in July 2024, so delivery now needs the project id plus a
        // service-account credential — either an absolute path to the JSON
        // file or the JSON itself pasted into the env var.
        'project_id'  => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    // Store-review (Google Play / App Store) bypass. When BOTH values are
    // set, the reserved reviewer phone skips real SMS delivery and signs in
    // with the fixed OTP below. Leave either blank to disable completely
    // (e.g. after the app is live).
    'customer_review' => [
        'phone' => env('CUSTOMER_REVIEW_PHONE'),
        'otp' => env('CUSTOMER_REVIEW_OTP'),
    ],

];
