<?php

use App\Models\Integration;
use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

Route::get('/', function () {
    return view('welcome');
});


Route::get('/auth/google', function () {
    return Socialite::driver('google')
        ->scopes([
            'openid',
            'email',
            'profile',
            'https://www.googleapis.com/auth/calendar.events',
        ])
        ->with(['access_type' => 'offline', 'prompt' => 'consent'])
        ->redirect();
});

Route::get('/auth/google/callback', function () {
    $googleUser = Socialite::driver('google')->stateless()->user();

    $accessToken = $googleUser->token;
    $refreshToken = $googleUser->refreshToken; // puede venir null
    $expiresIn = (int) ($googleUser->expiresIn ?? 0);

    $integration = Integration::updateOrCreate(
        ['provider' => 'google'],
        [
            'access_token' => $accessToken,
            // si viene null, NO pises el refresh_token existente
            'refresh_token' => $refreshToken ?: Integration::where('provider', 'google')->value('refresh_token'),
            'expires_at' => $expiresIn ? now()->addSeconds($expiresIn) : null,
        ]
    );

    return 'OK, tokens guardados en DB.';
});