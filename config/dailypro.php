<?php

return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
        'quota_fallback_enabled' => env('DAILYPRO_OPENAI_QUOTA_FALLBACK', false),
    ],

    'trello' => [
        'key' => env('TRELLO_KEY'),
        'token' => env('TRELLO_TOKEN'),
        'webhook_secret' => env('TRELLO_WEBHOOK_SECRET'),
    ],

    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri' => env('GOOGLE_REDIRECT_URI'),
        'access_token' => env('GOOGLE_ACCESS_TOKEN'),
    ],

    'scheduler' => [
        'default_max_minutes_per_day' => (int) env('DAILYPRO_DEFAULT_MAX_MINUTES_PER_DAY', 60),
    ],

    'reminder_email' => env('DAILYPRO_REMINDER_EMAIL'),
];
