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
    'mailgun' => [
    'domain' => env('MAILGUN_DOMAIN'),
    'secret' => env('MAILGUN_SECRET'),
    'endpoint' => env('MAILGUN_ENDPOINT', 'api.mailgun.net'),
],
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

    'idp' => [
        // Base URL includes the Swagger basePath (/api/v1).
        'base_url' => env('IDP_BASE_URL', 'https://identity-provider.isaxbsit2027.com/api/v1'),
        'client_id' => env('IDP_CLIENT_ID', ''),
        // Server-side only. Never rendered, logged, or exposed to the browser.
        'client_secret' => env('IDP_CLIENT_SECRET', ''),
        // Canonical redirect URI. Both IDP_REDIRECT_URI and the previously-used
        // IDP_REDIRECT_URL are accepted so a mismatched .env cannot silently
        // break the flow; IDP_REDIRECT_URI takes precedence.
        'redirect_uri' => env('IDP_REDIRECT_URI', env('IDP_REDIRECT_URL')),
        'timeout' => (int) env('IDP_TIMEOUT', 15),
        // Comma-separated role aliases that are allowed to sign in as a student.
        // Matched exactly (case-insensitive, whole-token), never substring.
        'student_roles' => env('IDP_STUDENT_ROLES', 'student'),
        // Optional: IDP logout endpoint relative to base_url (e.g. /auth/logout).
        'logout_path' => env('IDP_LOGOUT_PATH', '/auth/logout'),
    ],

];
