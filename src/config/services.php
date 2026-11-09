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

    'github' => [
        'api_url' => env('GITHUB_API_URL', 'https://api.github.com'),
        'token' => env('GITHUB_API_TOKEN'),
        'repository' => env('GITHUB_REPOSITORY', 'bmartinezr1/linktree-a-lo-weillo'),
        'branch' => env('GITHUB_PUBLICATION_BRANCH', 'main'),
    ],

    'cloudflare' => [
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
        'pages_project' => env('CLOUDFLARE_PAGES_PROJECT'),
        'token' => env('CLOUDFLARE_API_TOKEN'),
        'public_url' => env('CLOUDFLARE_PUBLIC_URL', 'https://clicktap.app'),
        'dashboard_url' => env('CLOUDFLARE_DASHBOARD_URL'),
    ],

    'openrouter' => [
        'api_url' => env('OPENROUTER_API_URL', 'https://openrouter.ai/api/v1'),
        'key' => env('OPENROUTER_API_KEY'),
        'model' => env('OPENROUTER_MODEL', 'openrouter/free'),
        'timeout' => (int) env('OPENROUTER_TIMEOUT', 60),
        'app_url' => env('OPENROUTER_APP_URL', 'https://clicktap.app'),
        'app_title' => env('OPENROUTER_APP_TITLE', 'ClickTap'),
    ],

    'brave' => [
        'api_url' => env('BRAVE_SEARCH_API_URL', 'https://api.search.brave.com/res/v1/web/search'),
        'key' => env('BRAVE_SEARCH_API_KEY'),
        'timeout' => (int) env('BRAVE_SEARCH_TIMEOUT', 15),
        'delay_ms' => (int) env('BRAVE_SEARCH_DELAY_MS', 1100),
    ],

    'supabase' => [
        'dashboard_url' => env('SUPABASE_DASHBOARD_URL'),
    ],

];
