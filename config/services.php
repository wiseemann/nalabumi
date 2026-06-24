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

    'opencode' => [
        'api_url' => env('OPENCODE_API_URL'),
        'api_key' => env('OPENCODE_API_KEY'),
        'model' => env('OPENCODE_MODEL', 'deepseek-v4-flash'),
    ],

    'embedding' => [
        'api_url' => env('EMBEDDING_API_URL'),
        'model' => env('EMBEDDING_MODEL', 'bge-m3'),
    ],

    'qdrant' => [
        'host' => env('QDRANT_HOST', 'http://127.0.0.1:6333'),
        'collection' => env('QDRANT_COLLECTION', 'articles'),
    ],

];
