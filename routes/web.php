<?php

use Illuminate\Support\Facades\Route;
use Laravel\Socialite\Facades\Socialite;

Route::get('/', function () {
    return view('welcome');
});



Route::get('/auth/google', function () {
    return Socialite::driver('google')
        ->scopes(['https://www.googleapis.com/auth/calendar.events'])
        ->with(['access_type' => 'offline', 'prompt' => 'consent'])
        ->redirect();
});

Route::get('/auth/google/callback', function () {
    $googleUser = Socialite::driver('google')->stateless()->user();

    // OJO: Socialite no siempre te da refresh_token si ya autorizaste antes.
    // Por eso arriba forzamos prompt=consent + access_type=offline.
    $accessToken = $googleUser->token;
    $refreshToken = $googleUser->refreshToken; // puede venir null si no fuerza consentimiento
    $expiresIn = $googleUser->expiresIn;

    // Guardalo donde quieras (DB / .env no recomendado):
    // ejemplo rápido: log
    logger()->info('Google tokens', [
        'access_token' => substr($accessToken, 0, 10).'...',
        'refresh_token_present' => !empty($refreshToken),
        'expires_in' => $expiresIn,
    ]);

    return 'OK, tokens recibidos. Mirá logs.';
});