<?php

use App\Http\Controllers\DailyRunController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\PublishController;
use App\Http\Controllers\RescheduleController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

// Plans CRUD
Route::post('/plans', [PlanController::class, 'store']);
Route::get('/plans/{plan}', [PlanController::class, 'show']);

// Plan actions
Route::post('/plans/{plan}/publish', PublishController::class);
Route::post('/plans/{plan}/reschedule', RescheduleController::class);
Route::post('/plans/{plan}/daily-run', DailyRunController::class);

// Webhooks
Route::match(['head', 'post'], '/webhooks/trello', [WebhookController::class, 'trello']);
