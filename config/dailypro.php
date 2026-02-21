<?php

return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
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

    'reminder_email' => env('DAILYPRO_REMINDER_EMAIL'),
];
