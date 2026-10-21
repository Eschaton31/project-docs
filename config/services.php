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

    // server-side PDF export (headless Chrome via Browsershot)
    'pdf' => [
        // where headless Chrome opens the print view; must reach this app from the queue worker's machine
        'base_url' => env('PDF_BASE_URL', env('APP_URL', 'http://localhost')),
        'timeout' => (int) env('PDF_TIMEOUT', 180),
        // leave empty to use the Chrome that `npm install` downloaded for Puppeteer
        'chrome_path' => env('PDF_CHROME_PATH'),
        'node_binary' => env('PDF_NODE_BINARY'),
    ],

];
