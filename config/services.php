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

    'meta' => [
        // Versión de la Graph API de Meta.
        'graph_version' => env('META_GRAPH_VERSION', 'v21.0'),

        // Token que tú defines y registras en el panel de Meta para
        // verificar el webhook (challenge GET). Es global, no por tenant.
        'webhook_verify_token' => env('META_WEBHOOK_VERIFY_TOKEN'),

        // App Secret de tu app de Meta. Se usa para validar la firma
        // (X-Hub-Signature-256) de cada webhook entrante. Global, no por tenant.
        'app_secret' => env('META_APP_SECRET'),
    ],

];
